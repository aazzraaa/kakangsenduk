
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . "/../config/koneksi.php";

// Proteksi halaman admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$judul_halaman = "Periode Voting";
$pesan = "";
$jenis_pesan = "success";

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Proses tambah dan edit periode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Permintaan tidak valid.");
    }

    $id = (int)($_POST['id'] ?? 0);
    $nama_periode = trim($_POST['nama_periode'] ?? '');
    $tanggal_mulai = trim($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');

    // Validasi input
    if ($nama_periode === '' || $tanggal_mulai === '' || $tanggal_selesai === '') {
        $pesan = "Semua kolom wajib diisi.";
        $jenis_pesan = "danger";
    } else {
        $mulai = DateTime::createFromFormat('Y-m-d\TH:i', $tanggal_mulai);
        $selesai = DateTime::createFromFormat('Y-m-d\TH:i', $tanggal_selesai);

        if (!$mulai || !$selesai) {
            $pesan = "Format tanggal atau waktu tidak valid.";
            $jenis_pesan = "danger";
        } elseif ($selesai <= $mulai) {
            $pesan = "Tanggal selesai harus setelah tanggal mulai.";
            $jenis_pesan = "danger";
        } else {
            $tanggal_mulai_db = $mulai->format('Y-m-d H:i:s');
            $tanggal_selesai_db = $selesai->format('Y-m-d H:i:s');

            if ($id > 0) {
                // Pastikan periode tersedia
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id FROM periode_voting WHERE id = ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $periode_ditemukan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                if (!$periode_ditemukan) {
                    $pesan = "Periode voting tidak ditemukan.";
                    $jenis_pesan = "danger";
                } else {
                    // Cegah jadwal diubah jika sudah ada suara
                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT COUNT(*) AS total FROM voting"
                    );

                    mysqli_stmt_execute($stmt);
                    $total_vote = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
                    mysqli_stmt_close($stmt);

                    if ((int)$total_vote > 0) {
                        $pesan = "Jadwal tidak dapat diedit setelah terdapat suara voting. Buat periode baru untuk pemilihan berikutnya.";
                        $jenis_pesan = "danger";
                    } else {
                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE periode_voting
                             SET nama_periode = ?, tanggal_mulai = ?, tanggal_selesai = ?
                             WHERE id = ?"
                        );

                        mysqli_stmt_bind_param(
                            $stmt,
                            "sssi",
                            $nama_periode,
                            $tanggal_mulai_db,
                            $tanggal_selesai_db,
                            $id
                        );

                        $berhasil = mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);

                        if ($berhasil) {
                            header("Location: periode.php?status=updated");
                            exit;
                        } else {
                            $pesan = "Gagal memperbarui periode voting.";
                            $jenis_pesan = "danger";
                        }
                    }
                }
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO periode_voting
                     (nama_periode, tanggal_mulai, tanggal_selesai, status)
                     VALUES (?, ?, ?, 'Nonaktif')"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sss",
                    $nama_periode,
                    $tanggal_mulai_db,
                    $tanggal_selesai_db
                );

                $berhasil = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if ($berhasil) {
                    header("Location: periode.php?status=added");
                    exit;
                } else {
                    $pesan = "Gagal menambahkan periode voting.";
                    $jenis_pesan = "danger";
                }
            }
        }
    }
}

// Proses aktivasi dan nonaktivasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_status'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Permintaan tidak valid.");
    }

    $id_status = (int)($_POST['id_status'] ?? 0);
    $status_baru = $_POST['status_baru'] ?? '';

    if (!in_array($status_baru, ['Aktif', 'Nonaktif'], true)) {
        $pesan = "Status tidak valid.";
        $jenis_pesan = "danger";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM periode_voting WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_status);
        mysqli_stmt_execute($stmt);
        $periode = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$periode) {
            $pesan = "Periode voting tidak ditemukan.";
            $jenis_pesan = "danger";
        } elseif ($status_baru === 'Aktif') {

            $sekarang = date('Y-m-d H:i:s');

            if ($sekarang < $periode['tanggal_mulai']) {
                $pesan = "Periode belum dapat diaktifkan karena tanggal mulai belum tiba.";
                $jenis_pesan = "danger";
            } elseif ($sekarang > $periode['tanggal_selesai']) {
                $pesan = "Periode sudah melewati tanggal selesai. Buat periode baru.";
                $jenis_pesan = "danger";
            } else {
                mysqli_begin_transaction($conn);

                try {
                    // Nonaktifkan periode lainnya
                    if (!mysqli_query(
                        $conn,
                        "UPDATE periode_voting SET status = 'Nonaktif'"
                    )) {
                        throw new Exception(mysqli_error($conn));
                    }

                    // Aktifkan periode pilihan
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE periode_voting SET status = 'Aktif' WHERE id = ?"
                    );

                    mysqli_stmt_bind_param($stmt, "i", $id_status);

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }

                    mysqli_stmt_close($stmt);
                    mysqli_commit($conn);

                    header("Location: periode.php?status=activated");
                    exit;

                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    $pesan = "Gagal mengaktifkan periode voting.";
                    $jenis_pesan = "danger";
                }
            }

        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE periode_voting SET status = 'Nonaktif' WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_status);
            $berhasil = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($berhasil) {
                header("Location: periode.php?status=deactivated");
                exit;
            } else {
                $pesan = "Gagal menonaktifkan periode voting.";
                $jenis_pesan = "danger";
            }
        }
    }
}

// Proses hapus periode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Permintaan tidak valid.");
    }

    $id_hapus = (int)($_POST['id_hapus'] ?? 0);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM periode_voting WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_hapus);
    mysqli_stmt_execute($stmt);
    $periode = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$periode) {
        $pesan = "Periode tidak ditemukan.";
        $jenis_pesan = "danger";
    } elseif ($periode['status'] === 'Aktif') {
        $pesan = "Periode aktif tidak dapat dihapus. Nonaktifkan terlebih dahulu.";
        $jenis_pesan = "danger";
    } else {
        // Jangan hapus periode jika sudah ada riwayat voting
        $stmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total FROM voting"
        );

        mysqli_stmt_execute($stmt);
        $total_vote = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
        mysqli_stmt_close($stmt);

        if ((int)$total_vote > 0) {
            $pesan = "Periode tidak dapat dihapus karena sistem sudah memiliki riwayat voting.";
            $jenis_pesan = "danger";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM periode_voting WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_hapus);
            $berhasil = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($berhasil) {
                header("Location: periode.php?status=deleted");
                exit;
            } else {
                $pesan = "Gagal menghapus periode.";
                $jenis_pesan = "danger";
            }
        }
    }
}

// Notifikasi
$notifikasi = [
    'added' => 'Periode voting berhasil ditambahkan.',
    'updated' => 'Periode voting berhasil diperbarui.',
    'activated' => 'Periode voting berhasil diaktifkan.',
    'deactivated' => 'Periode voting berhasil dinonaktifkan.',
    'deleted' => 'Periode voting berhasil dihapus.'
];

if (isset($_GET['status']) && isset($notifikasi[$_GET['status']])) {
    $pesan = $notifikasi[$_GET['status']];
    $jenis_pesan = "success";
}

// Ambil data periode
$result = mysqli_query(
    $conn,
    "SELECT * FROM periode_voting ORDER BY id DESC"
);

$data_periode = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data_periode[] = $row;
}

// Data edit
$edit = null;

if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM periode_voting WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_edit);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// Periode aktif
$query_aktif = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM periode_voting WHERE status = 'Aktif'"
);

$jumlah_aktif = (int)mysqli_fetch_assoc($query_aktif)['total'];

// Menentukan status tampilan berdasarkan tanggal
function statusPeriode($periode) {
    $sekarang = date('Y-m-d H:i:s');

    if ($periode['status'] === 'Aktif'
        && $sekarang >= $periode['tanggal_mulai']
        && $sekarang <= $periode['tanggal_selesai']) {
        return 'Aktif';
    }

    if ($sekarang < $periode['tanggal_mulai']) {
        return 'Akan Datang';
    }

    if ($sekarang > $periode['tanggal_selesai']) {
        return 'Berakhir';
    }

    return 'Nonaktif';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Periode Voting | Admin Voting</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .content-area { padding: 28px; }
        .page-heading { margin-bottom: 25px; }
        .page-heading h2 { font-weight: 750; color: #29212a; margin-bottom: 5px; }
        .page-heading p { color: #888; margin: 0; }

        .panel-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 15px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
        }

        .panel-title { font-weight: 700; color: #302632; margin-bottom: 20px; }
        .form-label { font-weight: 600; font-size: 14px; }
        .form-control { border-radius: 9px; padding: 10px 12px; }

        .btn-maroon { background: #7b1835; color: white; border: 0; }
        .btn-maroon:hover { background: #5f1028; color: white; }

        .stat-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 14px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-bottom: 14px;
        }

        .stat-card h3 { font-size: 27px; font-weight: 750; margin: 0; }
        .stat-card p { color: #888; font-size: 13px; margin: 5px 0 0; }

        .table thead th {
            background: #faf6f7;
            color: #6d3447;
            font-size: 13px;
            white-space: nowrap;
        }

        .table td { vertical-align: middle; font-size: 14px; }

        .badge-status {
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-aktif { background: #dcfce7; color: #166534; }
        .status-nonaktif { background: #f1f5f9; color: #475569; }
        .status-akan { background: #dbeafe; color: #1d4ed8; }
        .status-berakhir { background: #fee2e2; color: #b91c1c; }

        .empty-state { text-align: center; padding: 40px 15px; color: #999; }

        @media(max-width: 768px) {
            .content-area { padding: 18px; }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="main-content">

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <main class="content-area">

        <div class="page-heading">
            <h2>Periode Voting</h2>
            <p>Atur jadwal pelaksanaan dan status pemilihan Kakang Senduk Ponorogo.</p>
        </div>

        <?php if ($pesan !== ""): ?>
            <div class="alert alert-<?= htmlspecialchars($jenis_pesan) ?> alert-dismissible fade show">
                <?= htmlspecialchars($pesan) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistik -->
        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fce7f3;color:#be185d;">
                        <i data-lucide="calendar-days"></i>
                    </div>
                    <h3><?= count($data_periode) ?></h3>
                    <p>Total Periode</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dcfce7;color:#15803d;">
                        <i data-lucide="calendar-check"></i>
                    </div>
                    <h3><?= $jumlah_aktif ?></h3>
                    <p>Periode Berstatus Aktif</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;">
                        <i data-lucide="clock"></i>
                    </div>
                    <h3><?= date('d/m/Y') ?></h3>
                    <p>Tanggal Sekarang</p>
                </div>
            </div>

        </div>

        <!-- Form -->
        <section class="panel-card" id="form-periode">

            <h5 class="panel-title">
                <?= $edit ? 'Edit Periode Voting' : 'Tambah Periode Voting' ?>
            </h5>

            <form method="POST">

                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <input type="hidden" name="id"
                       value="<?= (int)($edit['id'] ?? 0) ?>">

                <div class="row g-3">

                    <div class="col-md-12">
                        <label class="form-label">Nama Periode *</label>
                        <input
                            type="text"
                            name="nama_periode"
                            class="form-control"
                            placeholder="Contoh: Pemilihan Kakang Senduk Ponorogo 2026"
                            value="<?= htmlspecialchars($edit['nama_periode'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal dan Waktu Mulai *</label>
                        <input
                            type="datetime-local"
                            name="tanggal_mulai"
                            class="form-control"
                            value="<?= !empty($edit['tanggal_mulai']) ? date('Y-m-d\TH:i', strtotime($edit['tanggal_mulai'])) : '' ?>"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal dan Waktu Selesai *</label>
                        <input
                            type="datetime-local"
                            name="tanggal_selesai"
                            class="form-control"
                            value="<?= !empty($edit['tanggal_selesai']) ? date('Y-m-d\TH:i', strtotime($edit['tanggal_selesai'])) : '' ?>"
                            required
                        >
                    </div>

                    <div class="col-12 d-flex gap-2">

                        <button type="submit" name="simpan" class="btn btn-maroon px-4">
                            <i data-lucide="save" style="width:16px;" class="me-1"></i>
                            <?= $edit ? 'Simpan Perubahan' : 'Simpan Periode' ?>
                        </button>

                        <?php if ($edit): ?>
                            <a href="periode.php" class="btn btn-light border">Batal</a>
                        <?php else: ?>
                            <button type="reset" class="btn btn-light border">Reset</button>
                        <?php endif; ?>

                    </div>

                </div>

            </form>

        </section>

        <!-- Daftar periode -->
        <section class="panel-card">

            <div class="mb-3">
                <h5 class="panel-title mb-1">Daftar Periode Voting</h5>
                <small class="text-muted">
                    Semua jadwal pemilihan yang pernah dibuat.
                </small>
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Periode</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($data_periode) > 0): ?>

                        <?php $no = 1; ?>

                        <?php foreach ($data_periode as $p): ?>

                            <?php
                                $status_tampilan = statusPeriode($p);

                                $class_status = [
                                    'Aktif' => 'status-aktif',
                                    'Nonaktif' => 'status-nonaktif',
                                    'Akan Datang' => 'status-akan',
                                    'Berakhir' => 'status-berakhir'
                                ][$status_tampilan];
                            ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td class="fw-semibold">
                                    <?= htmlspecialchars($p['nama_periode']) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y H:i', strtotime($p['tanggal_mulai'])) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y H:i', strtotime($p['tanggal_selesai'])) ?>
                                </td>

                                <td>
                                    <span class="badge-status <?= $class_status ?>">
                                        <?= htmlspecialchars($status_tampilan) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex gap-2 flex-wrap">

                                        <?php if ($p['status'] === 'Aktif'): ?>

                                            <form method="POST">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id_status"
                                                       value="<?= (int)$p['id'] ?>">
                                                <input type="hidden" name="status_baru"
                                                       value="Nonaktif">

                                                <button type="submit" name="ubah_status"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Nonaktifkan periode ini?')">
                                                    <i data-lucide="pause" style="width:14px;"></i>
                                                    Nonaktifkan
                                                </button>
                                            </form>

                                        <?php elseif ($status_tampilan !== 'Berakhir'): ?>

                                            <form method="POST">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id_status"
                                                       value="<?= (int)$p['id'] ?>">
                                                <input type="hidden" name="status_baru"
                                                       value="Aktif">

                                                <button type="submit" name="ubah_status"
                                                        class="btn btn-sm btn-success"
                                                        onclick="return confirm('Aktifkan periode ini? Periode aktif lainnya akan dinonaktifkan.')">
                                                    <i data-lucide="play" style="width:14px;"></i>
                                                    Aktifkan
                                                </button>
                                            </form>

                                        <?php endif; ?>

                                        <a href="periode.php?edit=<?= (int)$p['id'] ?>#form-periode"
                                           class="btn btn-sm btn-warning">
                                            <i data-lucide="pencil" style="width:14px;"></i>
                                        </a>

                                        <?php if ($p['status'] !== 'Aktif'): ?>

                                            <form method="POST"
                                                  onsubmit="return confirm('Yakin ingin menghapus periode ini?')">

                                                <input type="hidden" name="csrf_token"
                                                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                                <input type="hidden" name="id_hapus"
                                                       value="<?= (int)$p['id'] ?>">

                                                <button type="submit" name="hapus"
                                                        class="btn btn-sm btn-danger">
                                                    <i data-lucide="trash-2" style="width:14px;"></i>
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty-state">
                                <i data-lucide="calendar-x" style="width:35px;height:35px;"></i>
                                <p class="mt-2 mb-1">Belum ada periode voting.</p>
                                <small>Silakan tambahkan periode melalui formulir di atas.</small>
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    lucide.createIcons();
</script>

</body>
</html>
