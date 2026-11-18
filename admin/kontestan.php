
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . "/../config/koneksi.php";

// ==========================================
// KEAMANAN ADMIN
// ==========================================
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$judul_halaman = "Data Kontestan";

$folder_upload = __DIR__ . "/../uploads/kontestan/";
$path_upload = "../uploads/kontestan/";

if (!is_dir($folder_upload)) {
    mkdir($folder_upload, 0775, true);
}

// ==========================================
// CSRF TOKEN
// ==========================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = "";
$jenis_pesan = "success";

// ==========================================
// PROSES TAMBAH DAN EDIT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Permintaan tidak valid.");
    }

    $id = (int)($_POST['id'] ?? 0);
    $nomor = trim($_POST['nomor_peserta'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $kategori = $_POST['kategori'] ?? '';
    $instansi = trim($_POST['asal_instansi'] ?? '');
    $visi = trim($_POST['visi'] ?? '');
    $misi = trim($_POST['misi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (
        $nomor === '' ||
        $nama === '' ||
        !in_array($kategori, ['Kakang', 'Senduk'], true)
    ) {
        $pesan = "Nomor peserta, nama, dan kategori wajib diisi dengan benar.";
        $jenis_pesan = "danger";
    } else {

        $foto_lama = "";

        // Ambil foto lama jika edit
        if ($id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT foto, kategori FROM kontestan WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $data_lama = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmt)
            );

            mysqli_stmt_close($stmt);

            if (!$data_lama) {
                die("Data kontestan tidak ditemukan.");
            }

            $foto_lama = $data_lama['foto'] ?? '';
            $kategori_lama = $data_lama['kategori'];
        }

        $foto = $foto_lama;
        $foto_baru = "";

        // ==========================================
        // UPLOAD FOTO
        // ==========================================
        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {

                $pesan = "Upload foto gagal. Silakan coba kembali.";
                $jenis_pesan = "danger";

            } elseif ($_FILES['foto']['size'] > 10 * 1024 * 1024) {

                $pesan = "Ukuran foto maksimal 10 MB.";
                $jenis_pesan = "danger";

            } else {

                $tmp = $_FILES['foto']['tmp_name'];
                $info = getimagesize($tmp);

                if (!$info) {

                    $pesan = "File harus berupa gambar.";
                    $jenis_pesan = "danger";

                } else {

                    $mime = $info['mime'];

                    $ekstensi = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($ekstensi[$mime])) {

                        $pesan = "Format foto harus JPG, PNG, atau WEBP.";
                        $jenis_pesan = "danger";

                    } else {

                        $nama_file = bin2hex(random_bytes(16))
                            . '.' . $ekstensi[$mime];

                        $tujuan = $folder_upload . $nama_file;

                        if (move_uploaded_file($tmp, $tujuan)) {
                            $foto_baru = $nama_file;
                            $foto = $nama_file;
                        } else {
                            $pesan = "Foto gagal disimpan ke folder upload.";
                            $jenis_pesan = "danger";
                        }
                    }
                }
            }
        }

        // ==========================================
        // VALIDASI NOMOR PESERTA
        // ==========================================
        if ($pesan === "") {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id FROM kontestan
                 WHERE nomor_peserta = ? AND id != ?"
            );

            mysqli_stmt_bind_param($stmt, "si", $nomor, $id);
            mysqli_stmt_execute($stmt);

            $cek = mysqli_stmt_get_result($stmt);
            $nomor_duplikat = mysqli_num_rows($cek) > 0;

            mysqli_stmt_close($stmt);

            if ($nomor_duplikat) {

                $pesan = "Nomor peserta sudah digunakan.";
                $jenis_pesan = "danger";

            } else {

                // ==========================================
                // CEK SUARA SEBELUM MENGUBAH KATEGORI
                // ==========================================
                if ($id > 0) {

                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT COUNT(*) AS total
                         FROM voting
                         WHERE kontestan_id = ?"
                    );

                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);

                    $jumlah_vote = (int)mysqli_fetch_assoc(
                        mysqli_stmt_get_result($stmt)
                    )['total'];

                    mysqli_stmt_close($stmt);

                    if (
                        $jumlah_vote > 0 &&
                        $kategori !== $kategori_lama
                    ) {
                        $pesan = "Kategori tidak dapat diubah karena kontestan sudah menerima suara.";
                        $jenis_pesan = "danger";
                    }
                }

                // ==========================================
                // SIMPAN DATA
                // ==========================================
                if ($pesan === "") {

                    if ($id > 0) {

                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE kontestan SET
                                nomor_peserta = ?,
                                nama = ?,
                                kategori = ?,
                                foto = ?,
                                asal_instansi = ?,
                                visi = ?,
                                misi = ?,
                                deskripsi = ?
                             WHERE id = ?"
                        );

                        mysqli_stmt_bind_param(
                            $stmt,
                            "ssssssssi",
                            $nomor,
                            $nama,
                            $kategori,
                            $foto,
                            $instansi,
                            $visi,
                            $misi,
                            $deskripsi,
                            $id
                        );

                        $berhasil = mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);

                        if ($berhasil) {

                            if ($foto_baru !== "" && $foto_lama !== "") {
                                $file_lama = $folder_upload . basename($foto_lama);

                                if (is_file($file_lama)) {
                                    unlink($file_lama);
                                }
                            }

                            header("Location: kontestan.php?status=updated");
                            exit;

                        } else {

                            $pesan = "Gagal memperbarui data kontestan.";
                            $jenis_pesan = "danger";
                        }

                    } else {

                        $stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO kontestan
                            (
                                nomor_peserta,
                                nama,
                                kategori,
                                foto,
                                asal_instansi,
                                visi,
                                misi,
                                deskripsi
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                        );

                        mysqli_stmt_bind_param(
                            $stmt,
                            "ssssssss",
                            $nomor,
                            $nama,
                            $kategori,
                            $foto,
                            $instansi,
                            $visi,
                            $misi,
                            $deskripsi
                        );

                        $berhasil = mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);

                        if ($berhasil) {

                            header("Location: kontestan.php?status=added");
                            exit;

                        } else {

                            $pesan = "Gagal menambahkan data kontestan.";
                            $jenis_pesan = "danger";
                        }
                    }
                }
            }
        }

        // Hapus foto baru jika penyimpanan gagal
        if ($pesan !== "" && $foto_baru !== "") {

            $file_baru = $folder_upload . basename($foto_baru);

            if (is_file($file_baru)) {
                unlink($file_baru);
            }
        }
    }
}

// ==========================================
// PROSES HAPUS KONTESAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Permintaan tidak valid.");
    }

    $id_hapus = (int)($_POST['id_hapus'] ?? 0);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT foto FROM kontestan WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_hapus);
    mysqli_stmt_execute($stmt);

    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    mysqli_stmt_close($stmt);

    if ($data) {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                (SELECT COUNT(*) FROM voting WHERE kontestan_id = ?) +
                (SELECT COUNT(*) FROM pesanan_vote WHERE kontestan_id = ?) AS total"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $id_hapus,
            $id_hapus
        );

        mysqli_stmt_execute($stmt);

        $total_terkait = (int)mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        )['total'];

        mysqli_stmt_close($stmt);

        if ($total_terkait > 0) {

            $pesan = "Kontestan tidak bisa dihapus karena sudah memiliki riwayat voting atau pesanan.";
            $jenis_pesan = "danger";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM kontestan WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_hapus);

            $berhasil = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($berhasil) {

                if (!empty($data['foto'])) {

                    $file = $folder_upload . basename($data['foto']);

                    if (is_file($file)) {
                        unlink($file);
                    }
                }

                header("Location: kontestan.php?status=deleted");
                exit;

            } else {

                $pesan = "Gagal menghapus kontestan.";
                $jenis_pesan = "danger";
            }
        }

    } else {

        $pesan = "Data kontestan tidak ditemukan.";
        $jenis_pesan = "danger";
    }
}

// ==========================================
// NOTIFIKASI
// ==========================================
if (isset($_GET['status'])) {

    $notifikasi = [
        'added' => 'Kontestan berhasil ditambahkan.',
        'updated' => 'Data kontestan berhasil diperbarui.',
        'deleted' => 'Kontestan berhasil dihapus.'
    ];

    if (isset($notifikasi[$_GET['status']])) {
        $pesan = $notifikasi[$_GET['status']];
        $jenis_pesan = "success";
    }
}

// ==========================================
// PENCARIAN DAN FILTER
// ==========================================
$cari = trim($_GET['cari'] ?? '');
$filter = trim($_GET['kategori'] ?? '');

if (!in_array($filter, ['', 'Kakang', 'Senduk'], true)) {
    $filter = '';
}

$per_halaman = 6;

$halaman = max(1, (int)($_GET['halaman'] ?? 1));

$where = " WHERE 1=1 ";
$types = "";
$params = [];

if ($cari !== "") {

    $where .= " AND (
        k.nama LIKE ?
        OR k.nomor_peserta LIKE ?
        OR k.asal_instansi LIKE ?
    )";

    $keyword = "%{$cari}%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= "sss";
}

if ($filter !== "") {

    $where .= " AND k.kategori = ?";

    $params[] = $filter;
    $types .= "s";
}

// ==========================================
// HITUNG TOTAL DATA
// ==========================================
$sql_total = "
    SELECT COUNT(*) AS total
    FROM kontestan k
    {$where}
";

$stmt = mysqli_prepare($conn, $sql_total);

if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);

$total_data = (int)mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
)['total'];

mysqli_stmt_close($stmt);

$total_halaman = max(
    1,
    (int)ceil($total_data / $per_halaman)
);

if ($halaman > $total_halaman) {
    $halaman = $total_halaman;
}

$offset = ($halaman - 1) * $per_halaman;

// ==========================================
// AMBIL DATA KONTESAN
// ==========================================
$sql = "
    SELECT
        k.*,
        COALESCE((
            SELECT SUM(v.jumlah_vote)
            FROM voting v
            WHERE v.kontestan_id = k.id
        ), 0) AS total_vote
    FROM kontestan k
    {$where}
    ORDER BY k.kategori ASC, k.nomor_peserta ASC
    LIMIT ? OFFSET ?
";

$params_data = $params;
$types_data = $types . "ii";

$params_data[] = $per_halaman;
$params_data[] = $offset;

$stmt = mysqli_prepare($conn, $sql);

// Bind parameter dinamis
$bind_params = [];
$bind_params[] = &$types_data;

foreach ($params_data as $key => $value) {
    $bind_params[] = &$params_data[$key];
}

call_user_func_array(
    'mysqli_stmt_bind_param',
    array_merge([$stmt], $bind_params)
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data_kontestan = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data_kontestan[] = $row;
}

mysqli_stmt_close($stmt);

// ==========================================
// DATA EDIT
// ==========================================
$edit = null;

if (isset($_GET['edit'])) {

    $id_edit = (int)$_GET['edit'];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM kontestan WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_edit);
    mysqli_stmt_execute($stmt);

    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Kontestan | Admin Voting</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .content-area {
            padding: 28px;
            min-width: 0;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .page-heading h2 {
            font-weight: 750;
            margin: 0;
            color: #29212a;
        }

        .page-heading p {
            color: #888;
            margin: 5px 0 0;
        }

        .panel-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 15px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
            min-width: 0;
        }

        .panel-title {
            font-weight: 700;
            margin-bottom: 20px;
            color: #302632;
        }

        .form-label {
            font-weight: 600;
            font-size: 14px;
        }

        .form-control,
        .form-select {
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

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 10px;
        }

        .table {
            margin-bottom: 0;
            min-width: 850px;
        }

        .table thead th {
            background: #faf6f7;
            color: #6d3447;
            font-size: 13px;
            white-space: nowrap;
            padding: 13px 12px;
        }

        .table td {
            vertical-align: middle;
            font-size: 14px;
            padding: 12px;
        }

        .foto-kontestan {
            width: 52px;
            height: 58px;
            object-fit: cover;
            border-radius: 9px;
            background: #f4edf0;
        }

        .badge-kategori {
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 11px;
            white-space: nowrap;
        }

        .badge-kakang {
            background: #fce7f3;
            color: #9d174d;
        }

        .badge-senduk {
            background: #ede9fe;
            color: #6d28d9;
        }

        .search-box {
            max-width: 260px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 0;
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

        .pagination .page-link {
            color: #7b1835;
            border-color: #eee;
            padding: 8px 13px;
        }

        .pagination .page-item.active .page-link {
            background: #7b1835;
            border-color: #7b1835;
            color: white;
        }

        .pagination .page-link:hover {
            background: #fce7f3;
            color: #7b1835;
        }

        .pagination .page-item.disabled .page-link {
            color: #aaa;
            background: #f8f8f8;
        }

        @media(max-width: 768px) {
            .content-area {
                padding: 18px;
            }

            .panel-card {
                padding: 16px;
            }

            .search-box {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="main-content">

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <main class="content-area">

        <!-- JUDUL HALAMAN -->
        <div class="page-heading">

            <div>
                <h2>Data Kontestan</h2>
                <p>Kelola seluruh peserta pemilihan Kakang Senduk Ponorogo.</p>
            </div>

            <a href="kontestan.php#form-kontestan" class="btn btn-maroon">
                <i data-lucide="plus" class="me-1" style="width:17px;"></i>
                Tambah Kontestan
            </a>

        </div>

        <!-- NOTIFIKASI -->
        <?php if ($pesan !== ""): ?>

            <div class="alert alert-<?= htmlspecialchars($jenis_pesan) ?> alert-dismissible fade show">

                <?= htmlspecialchars($pesan) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>

        <!-- FORM TAMBAH DAN EDIT -->
        <section class="panel-card" id="form-kontestan">

            <h5 class="panel-title">
                <?= $edit ? 'Edit Data Kontestan' : 'Tambah Kontestan Baru' ?>
            </h5>

            <form method="POST" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)($edit['id'] ?? 0) ?>">

                <div class="row g-3">

                    <div class="col-md-4">

                        <label class="form-label">Nomor Peserta *</label>

                        <input
                            type="text"
                            name="nomor_peserta"
                            class="form-control"
                            placeholder="Contoh: K001"
                            value="<?= htmlspecialchars($edit['nomor_peserta'] ?? '') ?>"
                            required>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">Nama Lengkap *</label>

                        <input
                            type="text"
                            name="nama"
                            class="form-control"
                            placeholder="Masukkan nama kontestan"
                            value="<?= htmlspecialchars($edit['nama'] ?? '') ?>"
                            required>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">Kategori *</label>

                        <select name="kategori" class="form-select" required>

                            <option value="">Pilih kategori</option>

                            <option
                                value="Kakang"
                                <?= ($edit['kategori'] ?? '') === 'Kakang' ? 'selected' : '' ?>>
                                Kakang
                            </option>

                            <option
                                value="Senduk"
                                <?= ($edit['kategori'] ?? '') === 'Senduk' ? 'selected' : '' ?>>
                                Senduk
                            </option>

                        </select>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">Asal Instansi</label>

                        <input
                            type="text"
                            name="asal_instansi"
                            class="form-control"
                            placeholder="Sekolah, universitas, atau instansi"
                            value="<?= htmlspecialchars($edit['asal_instansi'] ?? '') ?>">

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">Foto Kontestan</label>

                        <input
                            type="file"
                            name="foto"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp">

                        <small class="text-muted">
                            Format JPG, PNG, WEBP. Maksimal 10 MB.
                        </small>

                        <?php if (!empty($edit['foto'])): ?>

                            <div class="mt-2">

                                <img
                                    src="<?= $path_upload . rawurlencode(basename($edit['foto'])) ?>"
                                    class="foto-kontestan"
                                    alt="Foto kontestan">

                                <small class="text-muted ms-2">
                                    Foto saat ini
                                </small>

                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="col-12">

                        <label class="form-label">Visi</label>

                        <textarea
                            name="visi"
                            class="form-control"
                            rows="2"
                            placeholder="Tuliskan visi kontestan"><?= htmlspecialchars($edit['visi'] ?? '') ?></textarea>

                    </div>

                    <div class="col-12">

                        <label class="form-label">Misi</label>

                        <textarea
                            name="misi"
                            class="form-control"
                            rows="3"
                            placeholder="Tuliskan misi kontestan"><?= htmlspecialchars($edit['misi'] ?? '') ?></textarea>

                    </div>

                    <div class="col-12">

                        <label class="form-label">Deskripsi / Profil Singkat</label>

                        <textarea
                            name="deskripsi"
                            class="form-control"
                            rows="3"
                            placeholder="Tuliskan profil singkat kontestan"><?= htmlspecialchars($edit['deskripsi'] ?? '') ?></textarea>

                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap">

                        <button
                            type="submit"
                            name="simpan"
                            class="btn btn-maroon px-4">

                            <i data-lucide="save" class="me-1" style="width:16px;"></i>

                            <?= $edit ? 'Simpan Perubahan' : 'Simpan Kontestan' ?>

                        </button>

                        <?php if ($edit): ?>

                            <a href="kontestan.php" class="btn btn-light border">
                                Batal
                            </a>

                        <?php else: ?>

                            <button type="reset" class="btn btn-light border">
                                Reset
                            </button>

                        <?php endif; ?>

                    </div>

                </div>

            </form>

        </section>

        <!-- DAFTAR KONTESAN -->
        <section class="panel-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">

                <div>

                    <h5 class="panel-title mb-1">
                        Daftar Kontestan
                    </h5>

                    <small class="text-muted">
                        Menampilkan <?= count($data_kontestan) ?>
                        dari <?= $total_data ?> kontestan.
                    </small>

                </div>

                <!-- FORM PENCARIAN DAN FILTER -->
                <form method="GET" class="d-flex gap-2 flex-wrap">

                    <input
                        type="text"
                        name="cari"
                        class="form-control search-box"
                        placeholder="Cari nama atau nomor..."
                        value="<?= htmlspecialchars($cari) ?>">

                    <select
                        name="kategori"
                        class="form-select"
                        style="width:145px;">

                        <option value="">Semua Kategori</option>

                        <option
                            value="Kakang"
                            <?= $filter === 'Kakang' ? 'selected' : '' ?>>
                            Kakang
                        </option>

                        <option
                            value="Senduk"
                            <?= $filter === 'Senduk' ? 'selected' : '' ?>>
                            Senduk
                        </option>

                    </select>

                    <button class="btn btn-maroon" type="submit">
                        <i data-lucide="search" style="width:16px;"></i>
                    </button>

                    <a href="kontestan.php" class="btn btn-light border">
                        Reset
                    </a>

                </form>

            </div>

            <!-- TABEL -->
            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th>No.</th>
                            <th>Foto</th>
                            <th>Nomor</th>
                            <th>Nama Kontestan</th>
                            <th>Kategori</th>
                            <th>Instansi</th>
                            <th>Total Suara</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($data_kontestan) > 0): ?>

                        <?php $no = $offset + 1; ?>

                        <?php foreach ($data_kontestan as $k): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>

                                    <?php if (!empty($k['foto'])): ?>

                                        <img
                                            src="<?= $path_upload . rawurlencode(basename($k['foto'])) ?>"
                                            class="foto-kontestan"
                                            alt="Foto">

                                    <?php else: ?>

                                        <div class="foto-kontestan d-flex align-items-center justify-content-center">

                                            <i data-lucide="user" style="color:#aaa;"></i>

                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($k['nomor_peserta']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($k['nama']) ?>
                                </td>

                                <td>

                                    <span class="badge-kategori <?= $k['kategori'] === 'Kakang' ? 'badge-kakang' : 'badge-senduk' ?>">

                                        <?= htmlspecialchars($k['kategori']) ?>

                                    </span>

                                </td>

                                <td>
                                    <?= htmlspecialchars($k['asal_instansi'] ?? '-') ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= number_format((int)$k['total_vote'], 0, ',', '.') ?>
                                    </strong>
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        <a
                                            href="kontestan.php?edit=<?= (int)$k['id'] ?>#form-kontestan"
                                            class="action-btn btn btn-warning text-dark"
                                            title="Edit">

                                            <i data-lucide="pencil" style="width:15px;"></i>

                                        </a>

                                        <?php if ((int)$k['total_vote'] === 0): ?>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus kontestan ini?')">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                                <input
                                                    type="hidden"
                                                    name="id_hapus"
                                                    value="<?= (int)$k['id'] ?>">

                                                <button
                                                    type="submit"
                                                    name="hapus"
                                                    class="action-btn btn btn-danger"
                                                    title="Hapus">

                                                    <i data-lucide="trash-2" style="width:15px;"></i>

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <button
                                                class="action-btn btn btn-secondary"
                                                disabled
                                                title="Kontestan sudah memiliki suara">

                                                <i data-lucide="lock" style="width:15px;"></i>

                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="8">

                                <div class="empty-state">

                                    <i data-lucide="users"></i>

                                    <p class="mb-0">
                                        Belum ada data kontestan.
                                    </p>

                                    <small>
                                        Tambahkan kontestan melalui formulir di atas.
                                    </small>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <!-- PAGINATION -->
            <?php if ($total_halaman > 1): ?>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">

                    <small class="text-muted">

                        Halaman <?= $halaman ?>
                        dari <?= $total_halaman ?>

                    </small>

                    <nav aria-label="Navigasi halaman kontestan">

                        <ul class="pagination pagination-sm mb-0">

                            <li class="page-item <?= $halaman <= 1 ? 'disabled' : '' ?>">

                                <a
                                    class="page-link"
                                    href="?halaman=<?= max(1, $halaman - 1) ?>&cari=<?= urlencode($cari) ?>&kategori=<?= urlencode($filter) ?>">

                                    Sebelumnya

                                </a>

                            </li>

                            <?php for ($i = 1; $i <= $total_halaman; $i++): ?>

                                <li class="page-item <?= $halaman === $i ? 'active' : '' ?>">

                                    <a
                                        class="page-link"
                                        href="?halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>&kategori=<?= urlencode($filter) ?>">

                                        <?= $i ?>

                                    </a>

                                </li>

                            <?php endfor; ?>

                            <li class="page-item <?= $halaman >= $total_halaman ? 'disabled' : '' ?>">

                                <a
                                    class="page-link"
                                    href="?halaman=<?= min($total_halaman, $halaman + 1) ?>&cari=<?= urlencode($cari) ?>&kategori=<?= urlencode($filter) ?>">

                                    Berikutnya

                                </a>

                            </li>

                        </ul>

                    </nav>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    lucide.createIcons();
</script>

</body>
</html>
