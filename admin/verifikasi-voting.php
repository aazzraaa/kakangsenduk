
<?php
session_start();

require_once __DIR__ . "/../config/koneksi.php";

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

if (empty($_SESSION['csrf_verifikasi'])) {
    $_SESSION['csrf_verifikasi'] = bin2hex(random_bytes(32));
}

$pesan = '';
$jenisPesan = '';

/* ======================================
   PROSES AKSI ADMIN
====================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $aksi = $_POST['aksi'] ?? '';
    $pesananId = (int)($_POST['pesanan_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_verifikasi'], $token)) {

        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";
        $jenisPesan = "danger";

    } elseif ($pesananId <= 0) {

        $pesan = "ID pesanan tidak valid.";
        $jenisPesan = "danger";

    } else {

        $transaksiAktif = false;

        try {

            mysqli_begin_transaction($conn);
            $transaksiAktif = true;

            // Kunci pesanan agar tidak diproses bersamaan
            $stmt = mysqli_prepare($conn, "
                SELECT
                    p.*,
                    u.nama AS nama_pemilih,
                    u.email,
                    k.nama AS nama_kontestan
                FROM pesanan_vote p
                JOIN users u ON u.id = p.user_id
                JOIN kontestan k ON k.id = p.kontestan_id
                WHERE p.id = ?
                LIMIT 1
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($stmt, "i", $pesananId);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $pesanan = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$pesanan) {
                throw new Exception("Pesanan tidak ditemukan.");
            }

            /* ==================================
               HAPUS PESANAN
            ================================== */

            if ($aksi === 'hapus') {

                // Pesanan yang sudah disetujui tidak boleh dihapus
                if ($pesanan['status'] === 'Disetujui') {
                    throw new Exception(
                        "Pesanan yang sudah disetujui tidak boleh dihapus karena suaranya sudah dihitung."
                    );
                }

                $buktiLama = $pesanan['bukti_pembayaran'] ?? '';

                $stmt = mysqli_prepare($conn, "
                    DELETE FROM pesanan_vote
                    WHERE id = ?
                ");

                mysqli_stmt_bind_param($stmt, "i", $pesananId);

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal menghapus pesanan.");
                }

                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $transaksiAktif = false;

                // Hapus file bukti pembayaran setelah data berhasil dihapus
                if ($buktiLama !== '') {

                    $folderBukti = realpath(
                        __DIR__ . "/../uploads/bukti-pembayaran"
                    );

                    $lokasiBukti = realpath(
                        __DIR__ . "/../" . $buktiLama
                    );

                    if (
                        $folderBukti &&
                        $lokasiBukti &&
                        strpos(
                            $lokasiBukti,
                            $folderBukti . DIRECTORY_SEPARATOR
                        ) === 0 &&
                        is_file($lokasiBukti)
                    ) {
                        unlink($lokasiBukti);
                    }
                }

                $pesan = "Pesanan berhasil dihapus.";
                $jenisPesan = "success";

            }

            /* ==================================
               SETUJUI PEMBAYARAN
            ================================== */

            elseif ($aksi === 'setujui') {

                if ($pesanan['status'] !== 'Menunggu Verifikasi') {
                    throw new Exception(
                        "Pesanan belum mengirim bukti atau sudah diproses."
                    );
                }

                if (empty($pesanan['bukti_pembayaran'])) {
                    throw new Exception("Bukti pembayaran belum tersedia.");
                }

                $lokasiBukti = __DIR__ . "/../" .
                    $pesanan['bukti_pembayaran'];

                if (!is_file($lokasiBukti)) {
                    throw new Exception(
                        "File bukti pembayaran tidak ditemukan."
                    );
                }

                // Pastikan belum pernah masuk ke tabel voting
                $stmt = mysqli_prepare($conn, "
                    SELECT id
                    FROM voting
                    WHERE pesanan_id = ?
                    LIMIT 1
                ");

                mysqli_stmt_bind_param($stmt, "i", $pesananId);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $votingLama = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if ($votingLama) {
                    throw new Exception(
                        "Pesanan ini sudah tercatat sebagai suara."
                    );
                }

                // Masukkan jumlah suara sesuai pembelian
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO voting
                    (
                        user_id,
                        kontestan_id,
                        kategori,
                        jumlah_vote,
                        pesanan_id,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "iisii",
                    $pesanan['user_id'],
                    $pesanan['kontestan_id'],
                    $pesanan['kategori'],
                    $pesanan['jumlah_vote'],
                    $pesananId
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(
                        "Gagal memasukkan suara ke database."
                    );
                }

                mysqli_stmt_close($stmt);

                // Perbarui status pesanan
                $stmt = mysqli_prepare($conn, "
                    UPDATE pesanan_vote
                    SET status = 'Disetujui',
                        catatan_admin = NULL,
                        verified_at = NOW()
                    WHERE id = ?
                ");

                mysqli_stmt_bind_param($stmt, "i", $pesananId);

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(
                        "Gagal memperbarui status pesanan."
                    );
                }

                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $transaksiAktif = false;

                $pesan = "Pembayaran berhasil disetujui. " .
                    number_format((int)$pesanan['jumlah_vote']) .
                    " suara untuk " .
                    $pesanan['nama_kontestan'] .
                    " berhasil dimasukkan.";

                $jenisPesan = "success";

            }

            /* ==================================
               TOLAK PEMBAYARAN
            ================================== */

            elseif ($aksi === 'tolak') {

                if ($pesanan['status'] !== 'Menunggu Verifikasi') {
                    throw new Exception(
                        "Pesanan belum mengirim bukti atau sudah diproses."
                    );
                }

                if (empty($pesanan['bukti_pembayaran'])) {
                    throw new Exception("Bukti pembayaran belum tersedia.");
                }

                $catatan = trim($_POST['catatan_admin'] ?? '');

                if ($catatan === '') {
                    throw new Exception(
                        "Alasan penolakan wajib diisi."
                    );
                }

                if (mb_strlen($catatan) > 1000) {
                    throw new Exception(
                        "Alasan penolakan maksimal 1000 karakter."
                    );
                }

                $stmt = mysqli_prepare($conn, "
                    UPDATE pesanan_vote
                    SET status = 'Ditolak',
                        catatan_admin = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "si",
                    $catatan,
                    $pesananId
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(
                        "Gagal menolak pembayaran."
                    );
                }

                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $transaksiAktif = false;

                $pesan = "Pembayaran berhasil ditolak. Pemilih dapat memperbaiki pesanannya.";
                $jenisPesan = "success";

            }

            else {
                throw new Exception("Aksi tidak dikenali.");
            }

        } catch (Throwable $error) {

            if ($transaksiAktif) {
                mysqli_rollback($conn);
            }

            $pesan = $error->getMessage();
            $jenisPesan = "danger";
        }
    }

    $_SESSION['csrf_verifikasi'] = bin2hex(random_bytes(32));
}

/* ======================================
   FILTER STATUS
====================================== */

$filter = $_GET['status'] ?? 'Menunggu Verifikasi';

$statusDiizinkan = [
    'Semua',
    'Menunggu Pembayaran',
    'Menunggu Verifikasi',
    'Disetujui',
    'Ditolak'
];

if (!in_array($filter, $statusDiizinkan, true)) {
    $filter = 'Menunggu Verifikasi';
}

/* ======================================
   STATISTIK PESANAN
====================================== */

$statistik = [
    'Menunggu Pembayaran' => 0,
    'Menunggu Verifikasi' => 0,
    'Disetujui' => 0,
    'Ditolak' => 0
];

$queryStatistik = mysqli_query($conn, "
    SELECT status, COUNT(*) AS total
    FROM pesanan_vote
    GROUP BY status
");

if ($queryStatistik) {
    while ($row = mysqli_fetch_assoc($queryStatistik)) {
        $statistik[$row['status']] = (int)$row['total'];
    }
}

/* ======================================
   AMBIL DAFTAR PESANAN
====================================== */

if ($filter === 'Semua') {

    $query = mysqli_query($conn, "
        SELECT
            p.*,
            u.nama AS nama_pemilih,
            u.email,
            u.no_hp,
            k.nama AS nama_kontestan,
            k.nomor_peserta,
            k.foto
        FROM pesanan_vote p
        JOIN users u ON u.id = p.user_id
        JOIN kontestan k ON k.id = p.kontestan_id
        ORDER BY
            CASE
                WHEN p.status = 'Menunggu Verifikasi' THEN 1
                WHEN p.status = 'Menunggu Pembayaran' THEN 2
                WHEN p.status = 'Ditolak' THEN 3
                ELSE 4
            END,
            p.created_at DESC
    ");

} else {

    $stmt = mysqli_prepare($conn, "
        SELECT
            p.*,
            u.nama AS nama_pemilih,
            u.email,
            u.no_hp,
            k.nama AS nama_kontestan,
            k.nomor_peserta,
            k.foto
        FROM pesanan_vote p
        JOIN users u ON u.id = p.user_id
        JOIN kontestan k ON k.id = p.kontestan_id
        WHERE p.status = ?
        ORDER BY p.created_at DESC
    ");

    mysqli_stmt_bind_param($stmt, "s", $filter);
    mysqli_stmt_execute($stmt);

    $query = mysqli_stmt_get_result($stmt);
}

$daftarPesanan = [];

if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $daftarPesanan[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verifikasi Voting | Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        .page-title {
            color: #3d1020;
            font-weight: 800;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #eee5e8;
            border-radius: 14px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
        }

        .stat-card small {
            color: #82777b;
            font-size: 12px;
        }

        .stat-card h3 {
            margin: 8px 0 0;
            font-weight: 800;
            color: #641b32;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: #f8edf1;
            color: #641b32;
        }

        .content-card {
            background: white;
            border: 1px solid #eee5e8;
            border-radius: 15px;
            padding: 22px;
            box-shadow: 0 5px 20px rgba(0,0,0,.035);
        }

        .filter-link {
            display: inline-block;
            padding: 8px 13px;
            border: 1px solid #eadde1;
            border-radius: 8px;
            text-decoration: none;
            color: #5d4a51;
            font-size: 12px;
            font-weight: 600;
            margin: 3px;
        }

        .filter-link.active {
            background: #641b32;
            color: white;
            border-color: #641b32;
        }

        .proof-image {
            width: 100%;
            max-width: 260px;
            max-height: 260px;
            object-fit: contain;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 5px;
            background: #fafafa;
        }

        .order-item {
            border: 1px solid #eee5e8;
            border-radius: 13px;
            padding: 20px;
            margin-bottom: 18px;
        }

        .order-code {
            color: #641b32;
            font-weight: 800;
        }

        .detail-label {
            color: #82777b;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .btn-maroon {
            background: #641b32;
            color: white;
            border: none;
        }

        .btn-maroon:hover {
            background: #3d1020;
            color: white;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
            color: #82777b;
        }

        .empty-state i {
            width: 50px;
            height: 50px;
            color: #c9b2bb;
        }

        .proof-link {
            display: inline-block;
        }

        @media(max-width: 768px) {
            .content-card {
                padding: 15px;
            }

            .order-item {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="main-content">

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <div class="mb-4">
            <h3 class="page-title mb-1">Verifikasi Pembayaran Voting</h3>
            <p class="text-secondary small mb-0">
                Periksa bukti pembayaran pemilih dan tentukan persetujuan suara.
            </p>
        </div>

        <?php if ($pesan): ?>
            <div class="alert alert-<?= e($jenisPesan) ?> alert-dismissible fade show">
                <?= e($pesan) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- STATISTIK -->

        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Menunggu Pembayaran</small>
                            <h3><?= number_format($statistik['Menunggu Pembayaran']) ?></h3>
                        </div>
                        <div class="stat-icon">
                            <i data-lucide="wallet"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Menunggu Verifikasi</small>
                            <h3><?= number_format($statistik['Menunggu Verifikasi']) ?></h3>
                        </div>
                        <div class="stat-icon">
                            <i data-lucide="clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Pembayaran Disetujui</small>
                            <h3><?= number_format($statistik['Disetujui']) ?></h3>
                        </div>
                        <div class="stat-icon">
                            <i data-lucide="check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Pembayaran Ditolak</small>
                            <h3><?= number_format($statistik['Ditolak']) ?></h3>
                        </div>
                        <div class="stat-icon">
                            <i data-lucide="x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- DAFTAR PESANAN -->

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Daftar Pesanan Pemilih</h5>
                    <p class="text-secondary small mb-0">
                        Periksa detail transaksi dan bukti pembayaran.
                    </p>
                </div>

                <span class="badge text-bg-dark">
                    <?= count($daftarPesanan) ?> Pesanan
                </span>
            </div>

            <!-- FILTER -->

            <div class="mb-4">
                <?php foreach ($statusDiizinkan as $status): ?>
                    <a
                        href="?status=<?= urlencode($status) ?>"
                        class="filter-link <?= $filter === $status ? 'active' : '' ?>"
                    >
                        <?= e($status) ?>

                        <?php if (isset($statistik[$status])): ?>
                            (<?= $statistik[$status] ?>)
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (count($daftarPesanan) > 0): ?>

                <?php foreach ($daftarPesanan as $pesanan): ?>

                    <?php
                    $statusClass = [
                        'Menunggu Pembayaran' => 'warning',
                        'Menunggu Verifikasi' => 'info',
                        'Disetujui' => 'success',
                        'Ditolak' => 'danger'
                    ][$pesanan['status']] ?? 'secondary';

                    $pathBukti = __DIR__ . "/../" .
                        ($pesanan['bukti_pembayaran'] ?? '');

                    $buktiAda = !empty($pesanan['bukti_pembayaran']) &&
                        is_file($pathBukti);
                    ?>

                    <div class="order-item">

                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">

                            <div>
                                <div class="detail-label">Kode Pesanan</div>
                                <div class="order-code">
                                    <?= e($pesanan['kode_pesanan']) ?>
                                </div>
                                <small class="text-secondary">
                                    <?= date('d M Y, H:i', strtotime($pesanan['created_at'])) ?> WIB
                                </small>
                            </div>

                            <span class="badge text-bg-<?= $statusClass ?>">
                                <?= e($pesanan['status']) ?>
                            </span>

                        </div>

                        <hr>

                        <div class="row g-4">

                            <!-- INFORMASI PEMILIH DAN PESANAN -->

                            <div class="col-lg-7">

                                <h6 class="fw-bold mb-3">Informasi Pemilih</h6>

                                <div class="row">

                                    <div class="col-sm-6">
                                        <div class="detail-label">Nama Pemilih</div>
                                        <div class="detail-value">
                                            <?= e($pesanan['nama_pemilih']) ?>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="detail-label">Email</div>
                                        <div class="detail-value">
                                            <?= e($pesanan['email']) ?>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="detail-label">Nomor HP</div>
                                        <div class="detail-value">
                                            <?= e($pesanan['no_hp']) ?>
                                        </div>
                                    </div>

                                </div>

                                <h6 class="fw-bold mt-3 mb-3">Detail Pembelian Suara</h6>

                                <div class="row">

                                    <div class="col-sm-6">
                                        <div class="detail-label">Kontestan</div>
                                        <div class="detail-value">
                                            <?= e($pesanan['nama_kontestan']) ?>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="detail-label">Nomor Peserta</div>
                                        <div class="detail-value">
                                            <?= e($pesanan['nomor_peserta']) ?>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="detail-label">Kategori</div>
                                        <div class="detail-value">
                                            <?= e($pesanan['kategori']) ?>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="detail-label">Jumlah Suara</div>
                                        <div class="detail-value">
                                            <?= number_format((int)$pesanan['jumlah_vote']) ?> suara
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="detail-label">Harga per Suara</div>
                                        <div class="detail-value">
                                            <?= rupiah($pesanan['harga_per_vote']) ?>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="detail-label">Total Pembayaran</div>
                                        <div class="detail-value text-danger fs-6">
                                            <?= rupiah($pesanan['total_bayar']) ?>
                                        </div>
                                    </div>

                                </div>

                                <?php if ($pesanan['catatan_admin']): ?>
                                    <div class="alert alert-danger small mt-2">
                                        <strong>Catatan penolakan:</strong><br>
                                        <?= nl2br(e($pesanan['catatan_admin'])) ?>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <!-- BUKTI PEMBAYARAN DAN AKSI -->

                            <div class="col-lg-5">

                                <h6 class="fw-bold mb-3">Bukti Pembayaran</h6>

                                <?php if ($buktiAda): ?>

                                    <a
                                        href="../<?= e($pesanan['bukti_pembayaran']) ?>"
                                        target="_blank"
                                        class="proof-link"
                                    >
                                        <img
                                            src="../<?= e($pesanan['bukti_pembayaran']) ?>"
                                            class="proof-image"
                                            alt="Bukti pembayaran"
                                        >
                                    </a>

                                    <div class="small text-secondary mt-2">
                                        Klik gambar untuk melihat ukuran penuh.
                                    </div>

                                <?php else: ?>

                                    <div class="alert alert-secondary small">
                                        Belum ada bukti pembayaran yang dikirim.
                                    </div>

                                <?php endif; ?>

                                <!-- SETUJUI DAN TOLAK -->

                                <?php if ($pesanan['status'] === 'Menunggu Verifikasi' && $buktiAda): ?>

                                    <div class="d-grid gap-2 mt-3">

                                        <form method="POST"
                                            onsubmit="return confirm('Yakin menyetujui pembayaran ini? Jumlah suara akan langsung ditambahkan ke hasil voting.');">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_verifikasi']) ?>"
                                            >

                                            <input type="hidden" name="aksi" value="setujui">

                                            <input
                                                type="hidden"
                                                name="pesanan_id"
                                                value="<?= (int)$pesanan['id'] ?>"
                                            >

                                            <button type="submit" class="btn btn-success w-100">
                                                <i data-lucide="check-circle" style="width:16px;vertical-align:middle;"></i>
                                                Setujui Pembayaran
                                            </button>

                                        </form>

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#tolak<?= (int)$pesanan['id'] ?>"
                                        >
                                            <i data-lucide="x-circle" style="width:16px;vertical-align:middle;"></i>
                                            Tolak Pembayaran
                                        </button>

                                        <div class="collapse" id="tolak<?= (int)$pesanan['id'] ?>">

                                            <div class="border rounded p-3 mt-2">

                                                <form method="POST"
                                                    onsubmit="return confirm('Yakin menolak pembayaran ini?');">

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_verifikasi']) ?>"
                                                    >

                                                    <input type="hidden" name="aksi" value="tolak">

                                                    <input
                                                        type="hidden"
                                                        name="pesanan_id"
                                                        value="<?= (int)$pesanan['id'] ?>"
                                                    >

                                                    <label class="form-label small fw-semibold">
                                                        Alasan Penolakan
                                                    </label>

                                                    <textarea
                                                        name="catatan_admin"
                                                        class="form-control form-control-sm mb-2"
                                                        rows="3"
                                                        maxlength="1000"
                                                        placeholder="Contoh: Bukti pembayaran tidak sesuai dengan total tagihan."
                                                        required
                                                    ></textarea>

                                                    <button type="submit" class="btn btn-danger btn-sm w-100">
                                                        Kirim Penolakan
                                                    </button>

                                                </form>

                                            </div>

                                        </div>

                                    </div>

                                <?php elseif ($pesanan['status'] === 'Disetujui'): ?>

                                    <div class="alert alert-success small mt-3 mb-0">
                                        <i data-lucide="check-circle" style="width:15px;vertical-align:middle;"></i>
                                        Pembayaran telah disetujui dan suara telah dihitung.
                                        <br>
                                        <strong>
                                            <?= number_format((int)$pesanan['jumlah_vote']) ?> suara masuk.
                                        </strong>
                                    </div>

                                <?php elseif ($pesanan['status'] === 'Ditolak'): ?>

                                    <div class="alert alert-danger small mt-3 mb-0">
                                        Pembayaran ditolak. Pemilih dapat mengirim ulang bukti setelah memperbaiki pesanannya.
                                    </div>

                                <?php endif; ?>

                                <!-- HAPUS PESANAN -->

                                <?php if ($pesanan['status'] !== 'Disetujui'): ?>

                                    <form
                                        method="POST"
                                        class="mt-3"
                                        onsubmit="return confirm('Yakin ingin menghapus pesanan ini? Data pesanan dan file bukti pembayarannya akan dihapus permanen.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_verifikasi']) ?>"
                                        >

                                        <input type="hidden" name="aksi" value="hapus">

                                        <input
                                            type="hidden"
                                            name="pesanan_id"
                                            value="<?= (int)$pesanan['id'] ?>"
                                        >

                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i data-lucide="trash-2" style="width:16px;vertical-align:middle;"></i>
                                            Hapus Pesanan
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">
                    <i data-lucide="inbox"></i>
                    <h6 class="fw-bold mt-3">Tidak Ada Pesanan</h6>
                    <p class="small mb-0">
                        Belum ada pesanan dengan status <?= e($filter) ?>.
                    </p>
                </div>

            <?php endif; ?>

        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    lucide.createIcons();
</script>

</body>
</html>
