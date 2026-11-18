
<?php
session_start();
require_once __DIR__ . "/config/koneksi.php";

date_default_timezone_set('Asia/Jakarta');

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function redirectVoting($pesan) {
    header("Location: voting.php?pesan=" . urlencode($pesan));
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'pemilih') {
    header("Location: admin/dashboard.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Pemilih';

if (empty($_SESSION['csrf_voting'])) {
    $_SESSION['csrf_voting'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_voting'];
$pesan = '';
$jenisPesan = '';

/* =========================================
   AMBIL PERIODE VOTING
========================================= */

$periodeQuery = mysqli_query($conn, "
    SELECT *
    FROM periode_voting
    WHERE status = 'Aktif'
    ORDER BY id DESC
    LIMIT 1
");

$periode = $periodeQuery ? mysqli_fetch_assoc($periodeQuery) : null;

$sekarang = date('Y-m-d H:i:s');

$votingDibuka = $periode
    && $sekarang >= $periode['tanggal_mulai']
    && $sekarang <= $periode['tanggal_selesai'];

/* =========================================
   AMBIL PENGATURAN PEMBAYARAN
========================================= */

$pengaturanQuery = mysqli_query($conn, "
    SELECT *
    FROM pengaturan_vote
    ORDER BY id DESC
    LIMIT 1
");

$pengaturan = $pengaturanQuery
    ? mysqli_fetch_assoc($pengaturanQuery)
    : null;

$hargaPerVote = (float)($pengaturan['harga_per_vote'] ?? 0);
$qrisImage = $pengaturan['qris_image'] ?? '';
$namaPenerima = $pengaturan['nama_penerima'] ?? '';

/* Nominal populer dari pengaturan admin */
$nominalPopuler = [1, 10, 50, 100, 500];

if (!empty($pengaturan['nominal_populer'])) {
    $nominalSementara = array_filter(
        array_map('intval', explode(',', $pengaturan['nominal_populer'])),
        function ($angka) {
            return $angka >= 1 && $angka <= 1000000;
        }
    );

    if (!empty($nominalSementara)) {
        $nominalPopuler = array_values(array_unique($nominalSementara));
    }
}

/* =========================================
   PROSES FORM
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_voting'], $token)) {
        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";
        $jenisPesan = "danger";

    } else {

        $aksi = $_POST['aksi'] ?? '';

        /* =================================
           MEMBUAT / MEMPERBAIKI PESANAN
        ================================= */

        if ($aksi === 'buat_pesanan') {

            $kontestanId = (int)($_POST['kontestan_id'] ?? 0);
            $jumlahVote = (int)($_POST['jumlah_vote'] ?? 0);

            $transaksiAktif = false;

            try {

                if (!$votingDibuka) {
                    throw new Exception("Voting sedang tidak dibuka.");
                }

                if (!$pengaturan || $hargaPerVote <= 0) {
                    throw new Exception("Harga voting belum diatur oleh admin.");
                }

                if (empty($qrisImage) || !is_file(__DIR__ . '/' . $qrisImage)) {
                    throw new Exception("QRIS belum tersedia. Silakan hubungi admin.");
                }

                if ($kontestanId <= 0) {
                    throw new Exception("Kontestan tidak valid.");
                }

                if ($jumlahVote < 1 || $jumlahVote > 1000000) {
                    throw new Exception("Jumlah suara harus antara 1 sampai 1.000.000.");
                }

                $stmt = mysqli_prepare($conn, "
                    SELECT id, nama, kategori
                    FROM kontestan
                    WHERE id = ?
                    LIMIT 1
                ");

                mysqli_stmt_bind_param($stmt, "i", $kontestanId);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $kontestan = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if (!$kontestan) {
                    throw new Exception("Kontestan tidak ditemukan.");
                }

                $kategori = $kontestan['kategori'];
                $totalBayar = $jumlahVote * $hargaPerVote;

                if (!is_finite($totalBayar) || $totalBayar <= 0) {
                    throw new Exception("Total pembayaran tidak valid.");
                }

                mysqli_begin_transaction($conn);
                $transaksiAktif = true;

                // Kunci akun agar tidak ada dua pesanan kategori
                // yang sama dibuat secara bersamaan.
                $stmt = mysqli_prepare($conn, "
                    SELECT id
                    FROM users
                    WHERE id = ?
                    FOR UPDATE
                ");

                mysqli_stmt_bind_param($stmt, "i", $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($conn, "
                    SELECT id, status
                    FROM pesanan_vote
                    WHERE user_id = ?
                    AND kategori = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                mysqli_stmt_bind_param($stmt, "is", $userId, $kategori);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $pesananLama = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if ($pesananLama) {

                    $statusLama = $pesananLama['status'];
                    $pesananId = (int)$pesananLama['id'];

                    if (!in_array($statusLama, ['Ditolak', 'Dibatalkan'], true)) {
                        throw new Exception(
                            "Kamu sudah memiliki pesanan kategori " .
                            $kategori . ". Tidak dapat membuat pesanan kedua."
                        );
                    }

                    // Pesanan ditolak atau dibatalkan dapat digunakan kembali.
                    // ID pesanan tetap sama agar riwayat transaksi tidak hilang.
                    $stmt = mysqli_prepare($conn, "
                        UPDATE pesanan_vote
                        SET kontestan_id = ?,
                            jumlah_vote = ?,
                            harga_per_vote = ?,
                            total_bayar = ?,
                            bukti_pembayaran = NULL,
                            status = 'Menunggu Pembayaran',
                            catatan_admin = NULL,
                            created_at = NOW(),
                            verified_at = NULL
                        WHERE id = ?
                        AND user_id = ?
                    ");

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iiddii",
                        $kontestanId,
                        $jumlahVote,
                        $hargaPerVote,
                        $totalBayar,
                        $pesananId,
                        $userId
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Gagal memperbarui pesanan.");
                    }

                    mysqli_stmt_close($stmt);

                } else {

                    $kodePesanan = 'KS-' . date('YmdHis') . '-' .
                        strtoupper(bin2hex(random_bytes(3)));

                    $stmt = mysqli_prepare($conn, "
                        INSERT INTO pesanan_vote (
                            kode_pesanan,
                            user_id,
                            kontestan_id,
                            kategori,
                            jumlah_vote,
                            harga_per_vote,
                            total_bayar,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran')
                    ");

                    mysqli_stmt_bind_param(
                        $stmt,
                        "siisidd",
                        $kodePesanan,
                        $userId,
                        $kontestanId,
                        $kategori,
                        $jumlahVote,
                        $hargaPerVote,
                        $totalBayar
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Gagal membuat pesanan.");
                    }

                    mysqli_stmt_close($stmt);
                }

                mysqli_commit($conn);
                $transaksiAktif = false;

                $_SESSION['csrf_voting'] = bin2hex(random_bytes(32));

                redirectVoting('pesanan');

            } catch (Throwable $error) {

                if ($transaksiAktif) {
                    mysqli_rollback($conn);
                }

                $pesan = $error->getMessage();
                $jenisPesan = "danger";
            }
        }

        /* =================================
           MEMBATALKAN PESANAN
        ================================= */

        elseif ($aksi === 'batalkan_pesanan') {

            $pesananId = (int)($_POST['pesanan_id'] ?? 0);

            try {

                if ($pesananId <= 0) {
                    throw new Exception("Pesanan tidak valid.");
                }

                // Hanya pesanan yang belum mengirim bukti
                // yang boleh dibatalkan.
                $stmt = mysqli_prepare($conn, "
                    UPDATE pesanan_vote
                    SET status = 'Dibatalkan'
                    WHERE id = ?
                    AND user_id = ?
                    AND status = 'Menunggu Pembayaran'
                    AND bukti_pembayaran IS NULL
                ");

                mysqli_stmt_bind_param($stmt, "ii", $pesananId, $userId);

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal membatalkan pesanan.");
                }

                $jumlahBerubah = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($jumlahBerubah !== 1) {
                    throw new Exception(
                        "Pesanan tidak dapat dibatalkan. Mungkin bukti pembayaran sudah dikirim."
                    );
                }

                $_SESSION['csrf_voting'] = bin2hex(random_bytes(32));

                redirectVoting('dibatalkan');

            } catch (Throwable $error) {
                $pesan = $error->getMessage();
                $jenisPesan = "danger";
            }
        }

        /* =================================
           UPLOAD BUKTI PEMBAYARAN
        ================================= */

        elseif ($aksi === 'upload_bukti') {

            $pesananId = (int)($_POST['pesanan_id'] ?? 0);
            $lokasiFile = null;

            try {

                if ($pesananId <= 0) {
                    throw new Exception("Pesanan tidak valid.");
                }

                if (
                    !isset($_FILES['bukti_pembayaran']) ||
                    $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK
                ) {
                    throw new Exception("Silakan pilih gambar bukti pembayaran.");
                }

                $file = $_FILES['bukti_pembayaran'];

                if ($file['size'] > 3 * 1024 * 1024) {
                    throw new Exception("Ukuran gambar maksimal 3 MB.");
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);

                $formatDiizinkan = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (
                    !isset($formatDiizinkan[$mime]) ||
                    getimagesize($file['tmp_name']) === false
                ) {
                    throw new Exception("Bukti harus berupa gambar JPG, PNG, atau WEBP.");
                }

                $folder = __DIR__ . '/uploads/bukti-pembayaran/';

                if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
                    throw new Exception("Folder bukti pembayaran gagal dibuat.");
                }

                $namaFile = 'bukti-' . $pesananId . '-' .
                    bin2hex(random_bytes(8)) . '.' .
                    $formatDiizinkan[$mime];

                $lokasiFile = $folder . $namaFile;
                $pathDatabase = 'uploads/bukti-pembayaran/' . $namaFile;

                if (!move_uploaded_file($file['tmp_name'], $lokasiFile)) {
                    throw new Exception("Gagal menyimpan gambar bukti pembayaran.");
                }

                // UPDATE bersyarat mencegah upload setelah pesanan dibatalkan
                // atau setelah status berubah.
                $stmt = mysqli_prepare($conn, "
                    UPDATE pesanan_vote
                    SET bukti_pembayaran = ?,
                        status = 'Menunggu Verifikasi'
                    WHERE id = ?
                    AND user_id = ?
                    AND status = 'Menunggu Pembayaran'
                    AND bukti_pembayaran IS NULL
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "sii",
                    $pathDatabase,
                    $pesananId,
                    $userId
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal menyimpan bukti pembayaran ke database.");
                }

                $berhasil = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($berhasil !== 1) {
                    throw new Exception(
                        "Pesanan sudah berubah status atau tidak dapat menerima bukti pembayaran."
                    );
                }

                $_SESSION['csrf_voting'] = bin2hex(random_bytes(32));

                redirectVoting('bukti');

            } catch (Throwable $error) {

                if ($lokasiFile && is_file($lokasiFile)) {
                    unlink($lokasiFile);
                }

                $pesan = $error->getMessage();
                $jenisPesan = "danger";
            }
        }
    }
}

/* =========================================
   PESAN BERHASIL
========================================= */

if (isset($_GET['pesan'])) {
    $daftarPesan = [
        'pesanan' => [
            'Pesanan berhasil dibuat. Silakan lakukan pembayaran melalui QRIS.',
            'success'
        ],
        'bukti' => [
            'Bukti pembayaran berhasil dikirim. Silakan menunggu verifikasi admin.',
            'success'
        ],
        'dibatalkan' => [
            'Pesanan berhasil dibatalkan. Kamu sekarang bisa membuat pesanan baru.',
            'success'
        ]
    ];

    if (isset($daftarPesan[$_GET['pesan']])) {
        $pesan = $daftarPesan[$_GET['pesan']][0];
        $jenisPesan = $daftarPesan[$_GET['pesan']][1];
    }
}

/* =========================================
   AMBIL PESANAN PEMILIH
   Pesanan Dibatalkan tidak ditampilkan sebagai
   pesanan aktif agar pemilih bisa memesan lagi.
========================================= */

$pesananPemilih = [];

$stmt = mysqli_prepare($conn, "
    SELECT
        p.*,
        k.nama AS nama_kontestan,
        k.nomor_peserta,
        k.foto
    FROM pesanan_vote p
    JOIN kontestan k ON k.id = p.kontestan_id
    WHERE p.user_id = ?
    AND p.status <> 'Dibatalkan'
    ORDER BY p.created_at DESC
");

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $pesananPemilih[$row['kategori']] = $row;
}

mysqli_stmt_close($stmt);

/* =========================================
   AMBIL DATA KONTESTAN
========================================= */

$queryKontestan = mysqli_query($conn, "
    SELECT *
    FROM kontestan
    ORDER BY nomor_peserta ASC
");

$daftarKontestan = [
    'Kakang' => [],
    'Senduk' => []
];

if ($queryKontestan) {
    while ($row = mysqli_fetch_assoc($queryKontestan)) {
        if (isset($daftarKontestan[$row['kategori']])) {
            $daftarKontestan[$row['kategori']][] = $row;
        }
    }
}

$totalKategori = count($pesananPemilih);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Voting Pemilih | Kakang Senduk Ponorogo</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --maroon: #641b32;
            --dark: #3d1020;
            --gold: #d8b56d;
            --soft: #f8edf1;
        }

        * { box-sizing: border-box; }

        body {
            background: #f8f5f5;
            font-family: 'Poppins', sans-serif;
            color: #30262a;
        }

        .navbar {
            background: white;
            box-shadow: 0 3px 18px rgba(0,0,0,.05);
            padding: 14px 0;
        }

        .brand {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 800;
            font-size: 14px;
        }

        .brand-icon {
            display: inline-grid;
            place-items: center;
            width: 39px;
            height: 39px;
            border-radius: 11px;
            background: var(--maroon);
            color: var(--gold);
            margin-right: 8px;
            vertical-align: middle;
        }

        .btn-maroon {
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: 9px;
            padding: 10px 18px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-maroon:hover {
            background: var(--dark);
            color: white;
        }

        .page-header {
            background: linear-gradient(115deg, #35101e, #70253d);
            color: white;
            padding: 45px 0 65px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            font-weight: 800;
        }

        .page-header p {
            color: rgba(255,255,255,.75);
            font-size: 13px;
        }

        .main-content {
            margin-top: -30px;
            padding-bottom: 70px;
        }

        .info-card,
        .contestant-card,
        .order-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 7px 25px rgba(0,0,0,.05);
        }

        .info-card {
            padding: 20px;
            height: 100%;
        }

        .info-card small {
            color: #887b80;
            font-size: 11px;
        }

        .info-card h5 {
            color: var(--maroon);
            font-size: 17px;
            font-weight: 700;
            margin-top: 7px;
        }

        .section-heading {
            font-family: 'Playfair Display', serif;
            color: var(--dark);
            font-weight: 700;
            font-size: 27px;
        }

        .category-heading {
            background: white;
            border-radius: 13px;
            padding: 18px 20px;
            border-left: 5px solid var(--gold);
            box-shadow: 0 5px 20px rgba(0,0,0,.04);
            margin: 35px 0 20px;
        }

        .category-heading h4 {
            color: var(--maroon);
            font-size: 17px;
            font-weight: 700;
            margin: 0;
        }

        .contestant-card {
            height: 100%;
            border: 1px solid #eee6e8;
            overflow: hidden;
            transition: .2s;
        }

        .contestant-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,.08);
        }

        .contestant-photo {
            height: 230px;
            background: linear-gradient(135deg, #f3e5e9, #e8d3da);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .contestant-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .contestant-body { padding: 18px; }

        .category-tag {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            background: #f8eaf0;
            color: var(--maroon);
            font-size: 10px;
            font-weight: 700;
        }

        .contestant-body h5 {
            color: var(--dark);
            font-size: 16px;
            font-weight: 700;
            margin: 12px 0 6px;
        }

        .contestant-body p {
            color: #887b80;
            font-size: 11px;
            min-height: 32px;
        }

        .vote-button {
            width: 100%;
            border: none;
            background: var(--maroon);
            color: white;
            border-radius: 8px;
            padding: 11px;
            font-size: 12px;
            font-weight: 600;
        }

        .vote-button:hover {
            background: var(--dark);
            color: white;
        }

        .vote-button:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .guide-box {
            background: #fffaf0;
            border: 1px solid #f0dfb8;
            border-radius: 13px;
            padding: 20px;
            font-size: 12px;
            line-height: 1.9;
        }

        .order-card {
            padding: 22px;
            border: 1px solid #eee6e8;
            margin-bottom: 18px;
        }

        .qris-image {
            width: 100%;
            max-width: 300px;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 8px;
            background: white;
        }

        .payment-total {
            background: #f8edf1;
            color: var(--maroon);
            border-radius: 10px;
            padding: 15px;
            font-weight: 700;
        }

        .empty-box {
            background: white;
            border: 1px dashed #d9c5cb;
            border-radius: 13px;
            text-align: center;
            padding: 35px;
            color: #81767a;
            font-size: 13px;
        }

        .modal-content {
            border: 0;
            border-radius: 17px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--maroon);
            color: white;
            border: 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-contestant {
            width: 90px;
            height: 90px;
            border-radius: 13px;
            object-fit: cover;
            background: #f2e5e9;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .quantity-control button {
            width: 42px;
            height: 42px;
            border: 1px solid #e3cbd3;
            background: #fff;
            color: var(--maroon);
            border-radius: 10px;
            font-size: 22px;
            font-weight: 700;
        }

        .quantity-control input {
            width: 110px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            border: 1px solid #e3cbd3;
            border-radius: 10px;
            padding: 7px;
        }

        .nominal-button {
            border: 1px solid #e4cbd4;
            background: white;
            color: var(--maroon);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .nominal-button.active,
        .nominal-button:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }

        footer {
            background: #2c101a;
            color: rgba(255,255,255,.65);
            padding: 22px;
            text-align: center;
            font-size: 11px;
        }

        @media(max-width: 576px) {
            .page-header h1 { font-size: 30px; }
            .contestant-photo { height: 190px; }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="brand">
            <span class="brand-icon"><i data-lucide="crown"></i></span>
            KAKANG SENDUK PONOROGO
        </a>

        <div class="d-flex align-items-center gap-3">
            <span class="d-none d-md-inline" style="font-size:12px;">
                Halo, <strong><?= e($nama) ?></strong>
            </span>
           <a href="index.php" class="btn btn-outline-secondary btn-sm">Beranda</a>

<a href="daftar-kontestan.php" class="btn btn-maroon">
    <i data-lucide="users" style="width:14px;vertical-align:middle;"></i>
    Semua Kontestan
</a>

<a href="auth/logout.php" class="btn btn-outline-danger btn-sm">Keluar</a>
        </div>
    </div>
</nav>

<header class="page-header">
    <div class="container">
        <div style="font-size:11px;color:var(--gold);letter-spacing:2px;font-weight:700;">
            HALAMAN PEMILIH
        </div>
        <h1 class="mt-2">Berikan Suara Terbaikmu</h1>
        <p>Pilih kontestan, tentukan jumlah suara, lalu lakukan pembayaran melalui QRIS.</p>
    </div>
</header>

<main class="container main-content">

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-<?= e($jenisPesan) ?> alert-dismissible fade show">
            <?= e($pesan) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="info-card">
                <small>Akun Pemilih</small>
                <h5><?= e($nama) ?></h5>
            </div>
        </div>

        <div class="col-md-4">
            <div class="info-card">
                <small>Status Pesanan</small>
                <h5><?= $totalKategori ?>/2 Kategori Dipesan</h5>
            </div>
        </div>

        <div class="col-md-4">
            <div class="info-card">
                <small>Status Voting</small>
                <h5 style="color:<?= $votingDibuka ? '#16804b' : '#b03b4c' ?>;">
                    <?= $votingDibuka ? 'Sedang Dibuka' : 'Tidak Dibuka' ?>
                </h5>
            </div>
        </div>
    </div>

    <?php if ($periode): ?>
        <div class="guide-box mb-4">
            <strong><?= e($periode['nama_periode']) ?></strong><br>
            Jadwal:
            <?= date('d-m-Y H:i', strtotime($periode['tanggal_mulai'])) ?>
            sampai
            <?= date('d-m-Y H:i', strtotime($periode['tanggal_selesai'])) ?> WIB.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            Belum ada periode voting aktif.
        </div>
    <?php endif; ?>

    <div class="guide-box mb-4">
        <strong>
            <i data-lucide="info" style="width:15px;vertical-align:middle;"></i>
            Petunjuk Voting dan Pembayaran
        </strong>
        <ol class="mb-0 mt-2">
            <li>Klik tombol Vote pada kontestan yang kamu pilih.</li>
            <li>Tentukan jumlah suara yang ingin dibeli.</li>
            <li>Periksa total pembayaran sebelum melanjutkan.</li>
            <li>Scan QRIS dan lakukan pembayaran.</li>
            <li>Unggah bukti pembayaran.</li>
            <li>Tunggu admin memverifikasi pembayaran.</li>
            <li>Suara dihitung setelah pembayaran disetujui admin.</li>
            <li>Pesanan dapat dibatalkan selama bukti pembayaran belum dikirim.</li>
        </ol>
    </div>

    <div class="text-center mt-5 mb-4">
        <div style="font-size:11px;color:#a17b3c;font-weight:700;letter-spacing:2px;">
            DAFTAR PESERTA
        </div>
        <h2 class="section-heading mt-2">Pilih Kontestan Favoritmu</h2>
        <p class="text-secondary" style="font-size:12px;">
            Setiap akun hanya dapat membuat satu pesanan untuk setiap kategori.
        </p>

        <?php if ($hargaPerVote > 0): ?>
            <div class="mt-3">
                <span class="badge text-bg-light border">
                    Harga per suara: <?= rupiah($hargaPerVote) ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php foreach (['Kakang', 'Senduk'] as $kategori): ?>

        <?php $pesananAda = $pesananPemilih[$kategori] ?? null; ?>

        <div class="category-heading d-flex justify-content-between align-items-center">
            <h4>
                <i data-lucide="crown" style="width:18px;vertical-align:middle;"></i>
                Kategori <?= e($kategori) ?>
            </h4>

            <?php if ($pesananAda): ?>
                <span class="badge bg-success">Pesanan Dibuat</span>
            <?php else: ?>
                <span class="badge bg-secondary">Belum Memesan</span>
            <?php endif; ?>
        </div>

        <?php if ($pesananAda): ?>

            <div class="order-card">

                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <small class="text-secondary">Kode Pesanan</small>
                        <h6 class="fw-bold"><?= e($pesananAda['kode_pesanan']) ?></h6>
                    </div>

                    <?php
                    $statusClass = [
                        'Menunggu Pembayaran' => 'warning',
                        'Menunggu Verifikasi' => 'info',
                        'Disetujui' => 'success',
                        'Ditolak' => 'danger'
                    ][$pesananAda['status']] ?? 'secondary';
                    ?>

                    <span class="badge text-bg-<?= $statusClass ?> align-self-start">
                        <?= e($pesananAda['status']) ?>
                    </span>
                </div>

                <hr>

                <div class="row g-3">
                    <div class="col-md-7">
                        <p class="mb-1">
                            <strong>Kontestan:</strong>
                            <?= e($pesananAda['nama_kontestan']) ?>
                        </p>
                        <p class="mb-1">
                            <strong>Kategori:</strong>
                            <?= e($pesananAda['kategori']) ?>
                        </p>
                        <p class="mb-1">
                            <strong>Jumlah suara:</strong>
                            <?= number_format($pesananAda['jumlah_vote']) ?>
                        </p>
                        <p class="mb-1">
                            <strong>Harga per suara:</strong>
                            <?= rupiah($pesananAda['harga_per_vote']) ?>
                        </p>

                        <div class="payment-total mt-3">
                            Total pembayaran: <?= rupiah($pesananAda['total_bayar']) ?>
                        </div>
                    </div>

                    <div class="col-md-5">

                        <?php if ($pesananAda['status'] === 'Menunggu Pembayaran'): ?>

                            <?php if ($qrisImage && is_file(__DIR__ . '/' . $qrisImage)): ?>

                                <div class="text-center mb-3">
                                    <strong>Scan QRIS untuk Membayar</strong><br>
                                    <img
                                        src="<?= e($qrisImage) ?>?v=<?= @filemtime(__DIR__ . '/' . $qrisImage) ?>"
                                        class="qris-image mt-2"
                                        alt="QRIS Pembayaran"
                                    >

                                    <?php if ($namaPenerima): ?>
                                        <p class="small text-secondary mt-2">
                                            Penerima: <?= e($namaPenerima) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="aksi" value="upload_bukti">
                                    <input type="hidden" name="pesanan_id" value="<?= (int)$pesananAda['id'] ?>">

                                    <label class="form-label fw-semibold small">
                                        Upload Bukti Pembayaran
                                    </label>

                                    <input
                                        type="file"
                                        name="bukti_pembayaran"
                                        class="form-control form-control-sm mb-2"
                                        accept="image/jpeg,image/png,image/webp"
                                        required
                                    >

                                    <small class="text-secondary d-block mb-3">
                                        JPG, PNG, atau WEBP. Maksimal 3 MB.
                                    </small>

                                    <button type="submit" class="btn btn-maroon w-100 mb-2">
                                        <i data-lucide="upload" style="width:15px;vertical-align:middle;"></i>
                                        Kirim Bukti Pembayaran
                                    </button>
                                </form>

                                <form method="POST"
                                      onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="aksi" value="batalkan_pesanan">
                                    <input type="hidden" name="pesanan_id" value="<?= (int)$pesananAda['id'] ?>">

                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i data-lucide="x-circle" style="width:15px;vertical-align:middle;"></i>
                                        Batalkan Pesanan
                                    </button>
                                </form>

                                <small class="text-secondary d-block mt-2 text-center">
                                    Pembatalan hanya tersedia sebelum bukti pembayaran dikirim.
                                </small>

                            <?php else: ?>

                                <div class="alert alert-danger small">
                                    QRIS belum tersedia atau file QRIS tidak ditemukan.
                                    Hubungi admin.
                                </div>

                                <form method="POST"
                                      onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="aksi" value="batalkan_pesanan">
                                    <input type="hidden" name="pesanan_id" value="<?= (int)$pesananAda['id'] ?>">

                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        Batalkan Pesanan
                                    </button>
                                </form>

                            <?php endif; ?>

                        <?php elseif ($pesananAda['status'] === 'Menunggu Verifikasi'): ?>

                            <div class="alert alert-info small">
                                <i data-lucide="clock" style="width:15px;vertical-align:middle;"></i>
                                Bukti pembayaran sudah dikirim. Tunggu pemeriksaan admin.
                            </div>

                            <?php if ($pesananAda['bukti_pembayaran']): ?>
                                <p class="small fw-semibold">Bukti yang dikirim:</p>
                                <a href="<?= e($pesananAda['bukti_pembayaran']) ?>" target="_blank" rel="noopener">
                                    <img
                                        src="<?= e($pesananAda['bukti_pembayaran']) ?>"
                                        class="img-fluid rounded border"
                                        style="max-height:180px;"
                                        alt="Bukti Pembayaran"
                                    >
                                </a>
                            <?php endif; ?>

                        <?php elseif ($pesananAda['status'] === 'Disetujui'): ?>

                            <div class="alert alert-success small">
                                <i data-lucide="check-circle" style="width:15px;vertical-align:middle;"></i>
                                Pembayaran disetujui. Suara kamu sudah masuk dalam hasil voting.
                            </div>

                        <?php elseif ($pesananAda['status'] === 'Ditolak'): ?>

                            <div class="alert alert-danger small">
                                <strong>Pembayaran ditolak.</strong><br>
                                <?= e($pesananAda['catatan_admin'] ?: 'Silakan periksa kembali pembayaranmu.') ?>
                            </div>

                            <p class="small text-secondary">
                                Kamu dapat memperbaiki pesanan ini. Pesanan baru untuk kategori
                                yang sama tidak perlu dibuat.
                            </p>

                            <?php if ($votingDibuka && $hargaPerVote > 0 && $qrisImage): ?>

                                <form method="POST" class="form-pesanan">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="aksi" value="buat_pesanan">
                                    <input type="hidden" name="kontestan_id" value="<?= (int)$pesananAda['kontestan_id'] ?>">

                                    <label class="form-label small fw-semibold">
                                        Jumlah suara baru
                                    </label>

                                    <input
                                        type="number"
                                        name="jumlah_vote"
                                        class="form-control mb-2"
                                        min="1"
                                        max="1000000"
                                        value="<?= (int)$pesananAda['jumlah_vote'] ?>"
                                        required
                                    >

                                    <button type="submit" class="btn btn-maroon w-100">
                                        Perbaiki dan Buat Ulang Pesanan
                                    </button>
                                </form>

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

        <?php elseif (count($daftarKontestan[$kategori]) > 0): ?>

            <div class="row g-4" id="pilih-<?= e($kategori) ?>">

                <?php foreach ($daftarKontestan[$kategori] as $peserta): ?>

                    <?php
                    $foto = trim($peserta['foto'] ?? '');
                    $lokasiFoto = "uploads/kontestan/" . basename($foto);
                    $fotoAda = $foto !== '' && is_file(__DIR__ . "/" . $lokasiFoto);
                    ?>

                    <div class="col-sm-6 col-lg-4">
                        <div class="contestant-card">

                            <div class="contestant-photo">
                                <?php if ($fotoAda): ?>
                                    <img src="<?= e($lokasiFoto) ?>" alt="<?= e($peserta['nama']) ?>">
                                <?php else: ?>
                                    <div class="text-center text-secondary">
                                        <i data-lucide="user-round" style="width:45px;height:45px;"></i>
                                        <div class="small mt-2">Foto belum tersedia</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="contestant-body">
                                <span class="category-tag">
                                    <?= e($kategori) ?> · <?= e($peserta['nomor_peserta']) ?>
                                </span>

                                <h5><?= e($peserta['nama']) ?></h5>

                                <p>
                                    <?= e($peserta['asal_instansi'] ?: 'Asal instansi belum tersedia') ?>
                                </p>

                                <?php if ($votingDibuka && $hargaPerVote > 0 && $qrisImage && is_file(__DIR__ . '/' . $qrisImage)): ?>

                                    <button
                                        type="button"
                                        class="vote-button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalVote"
                                        data-id="<?= (int)$peserta['id'] ?>"
                                        data-nama="<?= e($peserta['nama']) ?>"
                                        data-kategori="<?= e($kategori) ?>"
                                        data-foto="<?= $fotoAda ? e($lokasiFoto) : '' ?>"
                                    >
                                        <i data-lucide="vote" style="width:15px;vertical-align:middle;"></i>
                                        Vote Kontestan
                                    </button>

                                <?php else: ?>

                                    <button class="vote-button" disabled>
                                        <?= !$votingDibuka ? 'Voting Tidak Dibuka' : 'Pembayaran Belum Tersedia' ?>
                                    </button>

                                <?php endif; ?>

                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-box">
                Belum ada kontestan untuk kategori <?= e($kategori) ?>.
            </div>

        <?php endif; ?>

    <?php endforeach; ?>

    <div class="guide-box mt-5">
        <strong>Catatan penting:</strong>
        Pesanan yang masih menunggu pembayaran atau verifikasi belum dihitung sebagai suara.
        Suara baru dihitung setelah admin menyetujui pembayaran.
        Pesanan yang belum mengirim bukti pembayaran dapat dibatalkan.
    </div>

</main>

<!-- MODAL PEMILIHAN SUARA -->
<div class="modal fade" id="modalVote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">Buat Pesanan Suara</h5>
                    <small class="text-white-50">Pemilihan Kakang Senduk Ponorogo</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" id="formModalVote">
                <div class="modal-body p-4">

                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="aksi" value="buat_pesanan">
                    <input type="hidden" name="kontestan_id" id="modalKontestanId">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img src="" id="modalFoto" class="modal-contestant" alt="Foto Kontestan">

                        <div>
                            <span class="category-tag" id="modalKategori"></span>
                            <h5 class="fw-bold mt-2 mb-0" id="modalNama"></h5>
                            <small class="text-secondary">Kontestan pilihanmu</small>
                        </div>
                    </div>

                    <label class="form-label fw-semibold">
                        Tentukan jumlah suara
                    </label>

                    <div class="quantity-control mb-3">
                        <button type="button" id="kurangiVote">−</button>
                        <input
                            type="number"
                            name="jumlah_vote"
                            id="jumlahVote"
                            min="1"
                            max="1000000"
                            value="1"
                            required
                        >
                        <button type="button" id="tambahVote">+</button>
                    </div>

                    <div class="small text-secondary text-center mb-3">
                        Minimal 1 suara, maksimal 1.000.000 suara.
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                        <?php foreach ($nominalPopuler as $nominal): ?>
                            <button
                                type="button"
                                class="nominal-button"
                                data-nominal="<?= (int)$nominal ?>"
                            >
                                <?= number_format($nominal, 0, ',', '.') ?> Suara
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <label class="form-label fw-semibold small">
                        Harga per suara
                    </label>
                    <div class="form-control bg-light mb-3">
                        <?= rupiah($hargaPerVote) ?>
                    </div>

                    <div class="payment-total d-flex justify-content-between align-items-center">
                        <span>Total pembayaran</span>
                        <strong id="totalModal"><?= rupiah($hargaPerVote) ?></strong>
                    </div>

                    <div class="alert alert-warning small mt-3 mb-0">
                        Periksa jumlah suara dan total pembayaran sebelum melanjutkan.
                        Pesanan hanya dapat dibatalkan sebelum bukti pembayaran dikirim.
                    </div>

                </div>

                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Kembali
                    </button>
                    <button type="submit" class="btn btn-maroon">
                        Lanjutkan Pembayaran
                        <i data-lucide="arrow-right" style="width:15px;vertical-align:middle;"></i>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<footer>
    © <?= date('Y') ?> Kakang Senduk Ponorogo — Sistem Informasi Pemilihan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    lucide.createIcons();

    const hargaPerVote = <?= json_encode($hargaPerVote) ?>;
    const jumlahVoteInput = document.getElementById('jumlahVote');
    const totalModal = document.getElementById('totalModal');

    function formatRupiah(angka) {
        return 'Rp ' + Number(angka).toLocaleString('id-ID');
    }

    function hitungTotalModal() {
        let jumlah = parseInt(jumlahVoteInput.value) || 1;

        if (jumlah < 1) jumlah = 1;
        if (jumlah > 1000000) jumlah = 1000000;

        jumlahVoteInput.value = jumlah;

        totalModal.textContent = formatRupiah(jumlah * hargaPerVote);

        document.querySelectorAll('.nominal-button').forEach(button => {
            button.classList.toggle(
                'active',
                parseInt(button.dataset.nominal) === jumlah
            );
        });
    }

    document.getElementById('kurangiVote').addEventListener('click', function() {
        jumlahVoteInput.value = Math.max(
            1,
            (parseInt(jumlahVoteInput.value) || 1) - 1
        );

        hitungTotalModal();
    });

    document.getElementById('tambahVote').addEventListener('click', function() {
        jumlahVoteInput.value = Math.min(
            1000000,
            (parseInt(jumlahVoteInput.value) || 1) + 1
        );

        hitungTotalModal();
    });

    jumlahVoteInput.addEventListener('input', hitungTotalModal);

    document.querySelectorAll('.nominal-button').forEach(button => {
        button.addEventListener('click', function() {
            jumlahVoteInput.value = this.dataset.nominal;
            hitungTotalModal();
        });
    });

    document.querySelectorAll('[data-bs-target="#modalVote"]').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('modalKontestanId').value = this.dataset.id;
            document.getElementById('modalNama').textContent = this.dataset.nama;
            document.getElementById('modalKategori').textContent = this.dataset.kategori;

            const foto = this.dataset.foto;
            const fotoElement = document.getElementById('modalFoto');

            if (foto) {
                fotoElement.src = foto;
                fotoElement.style.display = 'block';
            } else {
                fotoElement.style.display = 'none';
            }

            jumlahVoteInput.value = 1;
            hitungTotalModal();
        });
    });

    document.getElementById('formModalVote').addEventListener('submit', function(event) {
        const jumlah = parseInt(jumlahVoteInput.value) || 0;

        if (jumlah < 1 || jumlah > 1000000) {
            event.preventDefault();
            alert('Jumlah suara harus antara 1 sampai 1.000.000.');
            return;
        }

        const nama = document.getElementById('modalNama').textContent;
        const kategori = document.getElementById('modalKategori').textContent;
        const total = jumlah * hargaPerVote;

        const yakin = confirm(
            'Konfirmasi pesanan\n\n' +
            'Kontestan: ' + nama + '\n' +
            'Kategori: ' + kategori + '\n' +
            'Jumlah suara: ' + jumlah.toLocaleString('id-ID') + '\n' +
            'Total pembayaran: ' + formatRupiah(total) +
            '\n\nLanjutkan membuat pesanan?'
        );

        if (!yakin) {
            event.preventDefault();
        }
    });

    hitungTotalModal();
</script>

</body>
</html>
