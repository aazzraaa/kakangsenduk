
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . "/../config/koneksi.php";

// Proteksi halaman admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$judul_halaman = "Data Pemilih";
$pesan = "";
$jenis_pesan = "success";

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Proses hapus akun pemilih
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Permintaan tidak valid.");
    }

    $id_hapus = (int) ($_POST['id_hapus'] ?? 0);

    // Pastikan akun benar-benar pemilih
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, nama FROM users WHERE id = ? AND role = 'pemilih'"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_hapus);
    mysqli_stmt_execute($stmt);
    $pemilih = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$pemilih) {
        $pesan = "Data pemilih tidak ditemukan.";
        $jenis_pesan = "danger";
    } else {

        // Periksa apakah pemilih sudah pernah memberikan suara
        $stmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total FROM voting WHERE user_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_hapus);
        mysqli_stmt_execute($stmt);
        $hasil_vote = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)$hasil_vote['total'] > 0) {
            $pesan = "Akun tidak dapat dihapus karena sudah memiliki riwayat voting.";
            $jenis_pesan = "danger";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM users WHERE id = ? AND role = 'pemilih'"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_hapus);
            $berhasil = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($berhasil) {
                header("Location: pemilih.php?status=deleted");
                exit;
            } else {
                $pesan = "Gagal menghapus akun pemilih.";
                $jenis_pesan = "danger";
            }
        }
    }
}

// Notifikasi
if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
    $pesan = "Akun pemilih berhasil dihapus.";
    $jenis_pesan = "success";
}

// Pencarian
$cari = trim($_GET['cari'] ?? '');

$sql = "
    SELECT
        u.id,
        u.nama,
        u.email,
        u.no_hp,
        u.created_at,
        (SELECT COUNT(*) FROM voting v
         WHERE v.user_id = u.id AND v.kategori = 'Kakang') AS vote_kakang,
        (SELECT COUNT(*) FROM voting v
         WHERE v.user_id = u.id AND v.kategori = 'Senduk') AS vote_senduk,
        (SELECT COUNT(*) FROM voting v
         WHERE v.user_id = u.id) AS total_vote
    FROM users u
    WHERE u.role = 'pemilih'
";

$types = "";
$params = [];

if ($cari !== "") {
    $sql .= " AND (u.nama LIKE ? OR u.email LIKE ? OR u.no_hp LIKE ?)";
    $keyword = "%{$cari}%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types = "sss";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data_pemilih = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data_pemilih[] = $row;
}

mysqli_stmt_close($stmt);

// Statistik
$query_statistik = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_pemilih,
        SUM(CASE WHEN EXISTS (
            SELECT 1 FROM voting v WHERE v.user_id = users.id
        ) THEN 1 ELSE 0 END) AS sudah_memilih
     FROM users
     WHERE role = 'pemilih'"
);

$statistik = mysqli_fetch_assoc($query_statistik);

$total_pemilih = (int)($statistik['total_pemilih'] ?? 0);
$sudah_memilih = (int)($statistik['sudah_memilih'] ?? 0);
$belum_memilih = $total_pemilih - $sudah_memilih;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pemilih | Admin Voting</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .content-area {
            padding: 28px;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h2 {
            font-weight: 750;
            color: #29212a;
            margin-bottom: 5px;
        }

        .page-heading p {
            color: #888;
            margin: 0;
        }

        .stat-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 14px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 27px;
            font-weight: 750;
            margin: 0;
            color: #302632;
        }

        .stat-card p {
            color: #888;
            margin: 5px 0 0;
            font-size: 13px;
        }

        .panel-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 15px;
            padding: 22px;
            margin-top: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
        }

        .panel-title {
            font-weight: 700;
            color: #302632;
        }

        .form-control {
            border-radius: 9px;
            padding: 10px 12px;
        }

        .btn-maroon {
            background: #7b1835;
            color: white;
            border: 0;
        }

        .btn-maroon:hover {
            background: #5f1028;
            color: white;
        }

        .table thead th {
            background: #faf6f7;
            color: #6d3447;
            font-size: 13px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 14px;
        }

        .status-sudah {
            background: #dcfce7;
            color: #166534;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-belum {
            background: #fff7ed;
            color: #c2410c;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
            color: #999;
        }

        .empty-state i {
            width: 38px;
            height: 38px;
            margin-bottom: 10px;
        }

        @media(max-width: 768px) {
            .content-area {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="main-content">

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <main class="content-area">

        <!-- Judul halaman -->
        <div class="page-heading">
            <h2>Data Pemilih</h2>
            <p>Kelola dan pantau akun pengguna yang terdaftar sebagai pemilih.</p>
        </div>

        <!-- Notifikasi -->
        <?php if ($pesan !== ""): ?>
            <div class="alert alert-<?= htmlspecialchars($jenis_pesan) ?> alert-dismissible fade show">
                <?= htmlspecialchars($pesan) ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistik -->
        <div class="row g-3">

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fce7f3;color:#be185d;">
                        <i data-lucide="users"></i>
                    </div>
                    <h3><?= $total_pemilih ?></h3>
                    <p>Total Pemilih Terdaftar</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dcfce7;color:#15803d;">
                        <i data-lucide="user-check"></i>
                    </div>
                    <h3><?= $sudah_memilih ?></h3>
                    <p>Pemilih Sudah Voting</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff7ed;color:#c2410c;">
                        <i data-lucide="user-round"></i>
                    </div>
                    <h3><?= $belum_memilih ?></h3>
                    <p>Pemilih Belum Voting</p>
                </div>
            </div>

        </div>

        <!-- Tabel pemilih -->
        <section class="panel-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>
                    <h5 class="panel-title mb-1">Daftar Pemilih</h5>
                    <small class="text-muted">
                        Menampilkan <?= count($data_pemilih) ?> akun pemilih.
                    </small>
                </div>

                <form method="GET" class="d-flex gap-2">
                    <input
                        type="text"
                        name="cari"
                        class="form-control"
                        placeholder="Cari nama, email, atau HP..."
                        value="<?= htmlspecialchars($cari) ?>"
                        style="min-width:230px;"
                    >

                    <button type="submit" class="btn btn-maroon">
                        <i data-lucide="search" style="width:17px;"></i>
                    </button>

                    <a href="pemilih.php" class="btn btn-light border">
                        Reset
                    </a>
                </form>

            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Pemilih</th>
                            <th>Email</th>
                            <th>Nomor HP</th>
                            <th>Daftar Kakang</th>
                            <th>Daftar Senduk</th>
                            <th>Status Voting</th>
                            <th>Tanggal Daftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($data_pemilih) > 0): ?>

                        <?php $no = 1; ?>

                        <?php foreach ($data_pemilih as $p): ?>

                            <tr>
                                <td><?= $no++ ?></td>

                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($p['nama']) ?>
                                    </div>
                                    <small class="text-muted">
                                        ID: #<?= (int)$p['id'] ?>
                                    </small>
                                </td>

                                <td><?= htmlspecialchars($p['email']) ?></td>

                                <td><?= htmlspecialchars($p['no_hp']) ?></td>

                                <td>
                                    <?= (int)$p['vote_kakang'] === 1
                                        ? '<span class="text-success fw-semibold">Sudah</span>'
                                        : '<span class="text-muted">Belum</span>' ?>
                                </td>

                                <td>
                                    <?= (int)$p['vote_senduk'] === 1
                                        ? '<span class="text-success fw-semibold">Sudah</span>'
                                        : '<span class="text-muted">Belum</span>' ?>
                                </td>

                                <td>
                                    <?php if ((int)$p['total_vote'] > 0): ?>
                                        <span class="status-sudah">Sudah Voting</span>
                                    <?php else: ?>
                                        <span class="status-belum">Belum Voting</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= !empty($p['created_at'])
                                        ? date('d/m/Y H:i', strtotime($p['created_at']))
                                        : '-' ?>
                                </td>

                                <td>
                                    <?php if ((int)$p['total_vote'] === 0): ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Yakin ingin menghapus akun pemilih <?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>?')">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_hapus"
                                                value="<?= (int)$p['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="hapus"
                                                class="btn btn-sm btn-danger"
                                                title="Hapus akun"
                                            >
                                                <i data-lucide="trash-2" style="width:15px;"></i>
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <button
                                            class="btn btn-sm btn-secondary"
                                            disabled
                                            title="Akun memiliki riwayat voting"
                                        >
                                            <i data-lucide="lock" style="width:15px;"></i>
                                        </button>

                                    <?php endif; ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i data-lucide="users"></i>
                                    <p class="mb-1">Belum ada data pemilih.</p>
                                    <small>Data pemilih yang mendaftar akan muncul di sini.</small>
                                </div>
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
