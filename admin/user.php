
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectUser($pesan) {
    header('Location: user.php?pesan=' . urlencode($pesan));
    exit;
}

if (empty($_SESSION['csrf_user'])) {
    $_SESSION['csrf_user'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_user'];
$pesan = '';
$jenisPesan = 'success';

/* =========================================
   PROSES TAMBAH, EDIT, DAN HAPUS USER
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_user'], $_POST['csrf_token'])
        ) {
            throw new Exception('Permintaan tidak valid. Muat ulang halaman.');
        }

        $aksi = $_POST['aksi'] ?? '';

        /* TAMBAH USER */
        if ($aksi === 'tambah') {
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $noHp = trim($_POST['no_hp'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'pemilih';

            if ($nama === '' || $email === '' || $noHp === '' || $password === '') {
                throw new Exception('Semua kolom wajib diisi.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            if (strlen($password) < 8) {
                throw new Exception('Password minimal 8 karakter.');
            }

            if (!in_array($role, ['admin', 'pemilih'], true)) {
                throw new Exception('Role tidak valid.');
            }

            $stmt = mysqli_prepare($conn, "
                SELECT id FROM users WHERE email = ? LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $emailAda = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($emailAda) {
                throw new Exception('Email sudah digunakan akun lain.');
            }

            // Password otomatis di-hash.
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "
                INSERT INTO users (nama, email, no_hp, password, role)
                VALUES (?, ?, ?, ?, ?)
            ");

            mysqli_stmt_bind_param(
                $stmt,
                'sssss',
                $nama,
                $email,
                $noHp,
                $passwordHash,
                $role
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal menambahkan akun.');
            }

            mysqli_stmt_close($stmt);

            $_SESSION['csrf_user'] = bin2hex(random_bytes(32));
            redirectUser('tambah');
        }

        /* EDIT USER */
        elseif ($aksi === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $noHp = trim($_POST['no_hp'] ?? '');
            $role = $_POST['role'] ?? '';
            $passwordBaru = $_POST['password'] ?? '';

            if ($id <= 0 || $nama === '' || $email === '' || $noHp === '') {
                throw new Exception('Data akun belum lengkap.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            if (!in_array($role, ['admin', 'pemilih'], true)) {
                throw new Exception('Role tidak valid.');
            }

            if ($passwordBaru !== '' && strlen($passwordBaru) < 8) {
                throw new Exception('Password baru minimal 8 karakter.');
            }

            $stmt = mysqli_prepare($conn, "
                SELECT id FROM users
                WHERE email = ? AND id <> ?
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 'si', $email, $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $emailAda = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($emailAda) {
                throw new Exception('Email sudah digunakan akun lain.');
            }

            if ($passwordBaru !== '') {
                $passwordHash = password_hash($passwordBaru, PASSWORD_DEFAULT);

                $stmt = mysqli_prepare($conn, "
                    UPDATE users
                    SET nama = ?, email = ?, no_hp = ?, role = ?, password = ?
                    WHERE id = ?
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    'sssssi',
                    $nama,
                    $email,
                    $noHp,
                    $role,
                    $passwordHash,
                    $id
                );
            } else {
                $stmt = mysqli_prepare($conn, "
                    UPDATE users
                    SET nama = ?, email = ?, no_hp = ?, role = ?
                    WHERE id = ?
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssi',
                    $nama,
                    $email,
                    $noHp,
                    $role,
                    $id
                );
            }

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal memperbarui akun.');
            }

            mysqli_stmt_close($stmt);

            // Perbarui nama pada sesi jika admin mengedit akunnya sendiri.
            if ($id === (int)$_SESSION['user_id']) {
                $_SESSION['nama'] = $nama;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
            }

            $_SESSION['csrf_user'] = bin2hex(random_bytes(32));
            redirectUser('edit');
        }

        /* HAPUS USER */
        elseif ($aksi === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Akun tidak valid.');
            }

            if ($id === (int)$_SESSION['user_id']) {
                throw new Exception('Kamu tidak dapat menghapus akun yang sedang digunakan.');
            }

            $stmt = mysqli_prepare($conn, "
                SELECT id FROM users WHERE id = ? LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $userAda = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$userAda) {
                throw new Exception('Akun tidak ditemukan.');
            }

            // Lindungi akun yang sudah memiliki pesanan atau riwayat voting.
            $stmt = mysqli_prepare($conn, "
                SELECT
                    (SELECT COUNT(*) FROM voting WHERE user_id = ?) AS total_voting,
                    (SELECT COUNT(*) FROM pesanan_vote WHERE user_id = ?) AS total_pesanan
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $id, $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $riwayat = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (
                (int)$riwayat['total_voting'] > 0 ||
                (int)$riwayat['total_pesanan'] > 0
            ) {
                throw new Exception(
                    'Akun tidak dapat dihapus karena memiliki riwayat voting atau pesanan.'
                );
            }

            $stmt = mysqli_prepare($conn, "
                DELETE FROM users WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal menghapus akun.');
            }

            mysqli_stmt_close($stmt);

            $_SESSION['csrf_user'] = bin2hex(random_bytes(32));
            redirectUser('hapus');
        } else {
            throw new Exception('Aksi tidak dikenali.');
        }

    } catch (Throwable $error) {
        $pesan = $error->getMessage();
        $jenisPesan = 'danger';
    }
}

/* =========================================
   NOTIFIKASI
========================================= */

if (isset($_GET['pesan'])) {
    $daftarPesan = [
        'tambah' => 'Akun berhasil ditambahkan.',
        'edit' => 'Data akun berhasil diperbarui.',
        'hapus' => 'Akun berhasil dihapus.'
    ];

    if (isset($daftarPesan[$_GET['pesan']])) {
        $pesan = $daftarPesan[$_GET['pesan']];
        $jenisPesan = 'success';
    }
}

/* =========================================
   STATISTIK USER
========================================= */

$statistikQuery = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_user,
        SUM(role = 'admin') AS total_admin,
        SUM(role = 'pemilih') AS total_pemilih
    FROM users
");

$statistik = mysqli_fetch_assoc($statistikQuery);

/* =========================================
   AMBIL DATA USER
========================================= */

$keyword = trim($_GET['cari'] ?? '');

if ($keyword !== '') {
    $cari = '%' . $keyword . '%';

    $stmt = mysqli_prepare($conn, "
        SELECT id, nama, email, no_hp, role, created_at
        FROM users
        WHERE nama LIKE ? OR email LIKE ?
        ORDER BY id DESC
    ");

    mysqli_stmt_bind_param($stmt, 'ss', $cari, $cari);
    mysqli_stmt_execute($stmt);
    $resultUser = mysqli_stmt_get_result($stmt);
} else {
    $stmt = mysqli_prepare($conn, "
        SELECT id, nama, email, no_hp, role, created_at
        FROM users
        ORDER BY id DESC
    ");

    mysqli_stmt_execute($stmt);
    $resultUser = mysqli_stmt_get_result($stmt);
}

$daftarUser = [];

while ($row = mysqli_fetch_assoc($resultUser)) {
    $daftarUser[] = $row;
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen User | Kakang Senduk</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin.css">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f8f5f6;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        /*
         * Layout konten.
         * Sidebar admin diasumsikan memiliki lebar 280px.
         */
        .user-page {
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            min-width: 0;
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .user-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
        }

        .user-page-header h2 {
            color: #641b32;
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 5px;
        }

        .user-page-header p {
            color: #8b7b82;
            font-size: 13px;
            margin: 0;
        }

        .btn-maroon {
            background: #641b32;
            border: 1px solid #641b32;
            color: #fff;
            border-radius: 9px;
            padding: 11px 17px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-maroon:hover {
            background: #451020;
            border-color: #451020;
            color: white;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #eee4e8;
            border-radius: 15px;
            padding: 22px;
            height: 100%;
            box-shadow: 0 5px 20px rgba(0,0,0,.035);
        }

        .stat-card small {
            color: #897b81;
            font-size: 12px;
        }

        .stat-card h3 {
            color: #641b32;
            font-size: 29px;
            font-weight: 800;
            margin: 9px 0 0;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #f8edf1;
            color: #641b32;
        }

        .user-table-card {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            background: #fff;
            border: 1px solid #eee4e8;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,.035);
            overflow: hidden;
        }

        .table-card-header {
            padding: 22px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-card-header h5 {
            font-size: 17px;
            font-weight: 700;
            color: #30262a;
            margin: 0;
        }

        .search-form {
            display: flex;
            gap: 8px;
            width: min(100%, 360px);
        }

        .search-form input {
            min-width: 0;
            font-size: 12px;
            border-radius: 8px;
        }

        .table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            padding: 0 18px 18px;
        }

        .user-table {
            width: 100%;
            min-width: 850px;
            margin: 0;
            border-collapse: collapse;
        }

        .user-table thead th {
            color: #641b32;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            padding: 15px 12px;
            background: #fffafc;
            border-bottom: 1px solid #eee4e8;
        }

        .user-table tbody td {
            color: #4c4146;
            font-size: 12px;
            padding: 15px 12px;
            border-bottom: 1px solid #f1e9ec;
            vertical-align: middle;
        }

        .user-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .user-table tbody tr:hover {
            background: #fffafd;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
        }

        .role-admin {
            background: #f8e5eb;
            color: #641b32;
        }

        .role-pemilih {
            background: #e5f5ed;
            color: #16804b;
        }

        .action-button {
            width: 34px;
            height: 34px;
            display: inline-grid;
            place-items: center;
            border-radius: 8px;
            border: 1px solid #eadde2;
            background: #fff;
            color: #641b32;
        }

        .action-button:hover {
            background: #f8edf1;
        }

        .action-delete {
            color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px !important;
            color: #897b81 !important;
        }

        .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            background: #641b32;
            color: #fff;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
        }

        .form-control,
        .form-select {
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
        }

        @media (max-width: 991px) {
            .user-page {
                width: 100%;
                max-width: 100%;
                margin-left: 0;
                padding: 22px 16px;
            }
        }

        @media (max-width: 576px) {
            .user-page {
                padding: 18px 12px;
            }

            .user-page-header h2 {
                font-size: 23px;
            }

            .user-page-header .btn {
                width: 100%;
            }

            .table-card-header {
                padding: 18px;
            }

            .search-form {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="user-page">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-<?= e($jenisPesan) ?> alert-dismissible fade show">
            <?= e($pesan) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="user-page-header">
        <div>
            <h2>Manajemen User</h2>
            <p>Buat dan kelola akun administrator maupun pemilih.</p>
        </div>

        <button
            type="button"
            class="btn btn-maroon"
            data-bs-toggle="modal"
            data-bs-target="#modalTambah"
        >
            <i data-lucide="user-plus" style="width:16px;vertical-align:middle;"></i>
            Tambah User
        </button>
    </div>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>Total User</small>
                        <h3><?= (int)$statistik['total_user'] ?></h3>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>Administrator</small>
                        <h3><?= (int)$statistik['total_admin'] ?></h3>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="shield-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>Pemilih</small>
                        <h3><?= (int)$statistik['total_pemilih'] ?></h3>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="user-round"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL USER -->
    <div class="user-table-card">

        <div class="table-card-header">
            <h5>Daftar Akun</h5>

            <form method="GET" class="search-form">
                <input
                    type="text"
                    name="cari"
                    class="form-control"
                    placeholder="Cari nama atau email..."
                    value="<?= e($keyword) ?>"
                >

                <button type="submit" class="btn btn-maroon">
                    <i data-lucide="search" style="width:15px;"></i>
                </button>

                <?php if ($keyword !== ''): ?>
                    <a href="user.php" class="btn btn-outline-secondary">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrap">
            <table class="table user-table align-middle">
                <thead>
                    <tr>
                        <th style="width:55px;">No.</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>No. HP</th>
                        <th>Role</th>
                        <th>Tanggal Dibuat</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($daftarUser) > 0): ?>
                        <?php foreach ($daftarUser as $index => $user): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <strong><?= e($user['nama']) ?></strong>
                                    <?php if ((int)$user['id'] === (int)$_SESSION['user_id']): ?>
                                        <small class="text-secondary d-block">Akun kamu</small>
                                    <?php endif; ?>
                                </td>

                                <td><?= e($user['email']) ?></td>

                                <td><?= e($user['no_hp']) ?></td>

                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="role-badge role-admin">Administrator</span>
                                    <?php else: ?>
                                        <span class="role-badge role-pemilih">Pemilih</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= $user['created_at']
                                        ? date('d-m-Y H:i', strtotime($user['created_at']))
                                        : '-' ?>
                                </td>

                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">

                                        <button
                                            type="button"
                                            class="action-button"
                                            title="Edit akun"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEdit"
                                            data-id="<?= (int)$user['id'] ?>"
                                            data-nama="<?= e($user['nama']) ?>"
                                            data-email="<?= e($user['email']) ?>"
                                            data-nohp="<?= e($user['no_hp']) ?>"
                                            data-role="<?= e($user['role']) ?>"
                                        >
                                            <i data-lucide="pencil" style="width:15px;"></i>
                                        </button>

                                        <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus akun ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="aksi" value="hapus">
                                                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

                                                <button
                                                    type="submit"
                                                    class="action-button action-delete"
                                                    title="Hapus akun"
                                                >
                                                    <i data-lucide="trash-2" style="width:15px;"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i data-lucide="users" style="width:35px;height:35px;"></i>
                                <p class="mt-2 mb-0">
                                    <?= $keyword !== ''
                                        ? 'Akun yang dicari tidak ditemukan.'
                                        : 'Belum ada data akun.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-secondary small mt-3">
        Menampilkan <?= count($daftarUser) ?> akun.
        Akun yang memiliki riwayat voting atau pesanan tidak dapat dihapus.
    </p>

</div>

<!-- MODAL TAMBAH USER -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tambah Akun Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <div class="modal-body p-4">

                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="aksi" value="tambah">

                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor HP</label>
                        <input type="text" name="no_hp" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            required
                        >
                        <small class="text-secondary">Minimal 8 karakter. Password otomatis diamankan.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role Akun</label>
                        <select name="role" class="form-select" required>
                            <option value="pemilih">Pemilih</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-maroon">
                        Simpan Akun
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- MODAL EDIT USER -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <div class="modal-body p-4">

                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="aksi" value="edit">
                    <input type="hidden" name="id" id="editId">

                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" id="editNama" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor HP</label>
                        <input type="text" name="no_hp" id="editNoHp" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role Akun</label>
                        <select name="role" id="editRole" class="form-select" required>
                            <option value="pemilih">Pemilih</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            placeholder="Kosongkan jika tidak ingin mengganti"
                        >
                        <small class="text-secondary">
                            Isi hanya jika ingin mengganti password.
                        </small>
                    </div>

                </div>

                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-maroon">
                        Simpan Perubahan
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    lucide.createIcons();

    const modalEdit = document.getElementById('modalEdit');

    modalEdit.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;

        document.getElementById('editId').value = button.dataset.id;
        document.getElementById('editNama').value = button.dataset.nama;
        document.getElementById('editEmail').value = button.dataset.email;
        document.getElementById('editNoHp').value = button.dataset.nohp;
        document.getElementById('editRole').value = button.dataset.role;
    });
</script>

</body>
</html>
