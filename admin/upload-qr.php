
<?php
session_start();

require_once __DIR__ . "/../config/koneksi.php";

date_default_timezone_set('Asia/Jakarta');

// ==========================================
// KEAMANAN ADMIN
// ==========================================

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

// ==========================================
// FUNGSI BANTU
// ==========================================

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function ambilPengaturan($conn) {
    $result = mysqli_query($conn, "
        SELECT *
        FROM pengaturan_vote
        ORDER BY id ASC
        LIMIT 1
    ");

    if (!$result) {
        throw new Exception("Gagal mengambil pengaturan: " . mysqli_error($conn));
    }

    return mysqli_fetch_assoc($result);
}

// ==========================================
// TOKEN CSRF
// ==========================================

if (empty($_SESSION['csrf_qris'])) {
    $_SESSION['csrf_qris'] = bin2hex(random_bytes(32));
}

// ==========================================
// FOLDER UPLOAD
// ==========================================

$folderUpload = __DIR__ . "/../uploads/qris/";

if (!is_dir($folderUpload)) {
    if (!mkdir($folderUpload, 0755, true) && !is_dir($folderUpload)) {
        die("Folder uploads/qris tidak dapat dibuat.");
    }
}

// ==========================================
// PASTIKAN PENGATURAN TERSEDIA
// ==========================================

$cekPengaturan = mysqli_query($conn, "
    SELECT id
    FROM pengaturan_vote
    ORDER BY id ASC
    LIMIT 1
");

if (!$cekPengaturan) {
    die("Tabel pengaturan_vote bermasalah: " . e(mysqli_error($conn)));
}

if (mysqli_num_rows($cekPengaturan) === 0) {

    $buatPengaturan = mysqli_query($conn, "
        INSERT INTO pengaturan_vote
            (harga_per_vote, nominal_populer)
        VALUES
            (3500, '10,50,100,500')
    ");

    if (!$buatPengaturan) {
        die("Gagal membuat pengaturan awal: " . e(mysqli_error($conn)));
    }
}

$pengaturan = ambilPengaturan($conn);

// ==========================================
// PROSES FORM
// ==========================================

$pesan = '';
$jenisPesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $aksi = $_POST['aksi'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_qris'], $token)
    ) {
        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";
        $jenisPesan = "danger";

    } else {

        try {

            // ==================================
            // SIMPAN HARGA DAN NOMINAL POPULER
            // ==================================

            if ($aksi === 'simpan_pengaturan') {

                $hargaInput = $_POST['harga_per_vote'] ?? '';
                $nominalInput = trim($_POST['nominal_populer'] ?? '');

                if (
                    !is_numeric($hargaInput) ||
                    (float)$hargaInput <= 0 ||
                    (float)$hargaInput > 1000000000
                ) {
                    throw new Exception(
                        "Harga per vote harus lebih dari 0 dan tidak melebihi 1 miliar."
                    );
                }

                $harga = (float)$hargaInput;

                // Nominal dipisahkan dengan koma.
                // Contoh: 10,50,100,500

                if ($nominalInput === '') {
                    throw new Exception(
                        "Masukkan minimal satu pilihan jumlah vote."
                    );
                }

                $pecahanNominal = explode(',', $nominalInput);
                $nominalValid = [];

                foreach ($pecahanNominal as $item) {

                    $item = trim($item);

                    if ($item === '' || !ctype_digit($item)) {
                        throw new Exception(
                            "Format nominal tidak valid. Pisahkan angka menggunakan koma."
                        );
                    }

                    $angka = (int)$item;

                    if ($angka < 1 || $angka > 1000000) {
                        throw new Exception(
                            "Setiap pilihan nominal harus antara 1 sampai 1.000.000."
                        );
                    }

                    $nominalValid[] = $angka;
                }

                $nominalValid = array_values(
                    array_unique($nominalValid)
                );

                sort($nominalValid);

                $nominalSimpan = implode(',', $nominalValid);

                $idPengaturan = (int)$pengaturan['id'];

                $stmt = mysqli_prepare($conn, "
                    UPDATE pengaturan_vote
                    SET harga_per_vote = ?,
                        nominal_populer = ?
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception("Gagal menyiapkan penyimpanan pengaturan.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "dsi",
                    $harga,
                    $nominalSimpan,
                    $idPengaturan
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal menyimpan harga dan nominal vote.");
                }

                mysqli_stmt_close($stmt);

                $pesan = "Harga dan pilihan jumlah vote berhasil diperbarui.";
                $jenisPesan = "success";
            }

            // ==================================
            // UPLOAD / GANTI QRIS
            // ==================================

            elseif ($aksi === 'upload') {

                if (
                    !isset($_FILES['qris']) ||
                    $_FILES['qris']['error'] !== UPLOAD_ERR_OK
                ) {
                    throw new Exception(
                        "Silakan pilih gambar QRIS terlebih dahulu."
                    );
                }

                $file = $_FILES['qris'];

                if ($file['size'] > 3 * 1024 * 1024) {
                    throw new Exception(
                        "Ukuran gambar maksimal 3 MB."
                    );
                }

                $tmpFile = $file['tmp_name'];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmpFile);

                $formatDiizinkan = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (
                    !isset($formatDiizinkan[$mime]) ||
                    getimagesize($tmpFile) === false
                ) {
                    throw new Exception(
                        "Format gambar harus JPG, PNG, atau WEBP yang valid."
                    );
                }

                $ekstensi = $formatDiizinkan[$mime];

                $namaFile = 'qris_' .
                    bin2hex(random_bytes(12)) .
                    '.' . $ekstensi;

                $tujuan = $folderUpload . $namaFile;
                $pathDatabase = 'uploads/qris/' . $namaFile;

                if (!move_uploaded_file($tmpFile, $tujuan)) {
                    throw new Exception(
                        "Gagal mengunggah gambar QRIS."
                    );
                }

                $gambarLama = $pengaturan['qris_image'] ?? null;
                $idPengaturan = (int)$pengaturan['id'];

                $stmt = mysqli_prepare($conn, "
                    UPDATE pengaturan_vote
                    SET qris_image = ?
                    WHERE id = ?
                ");

                if (!$stmt) {
                    unlink($tujuan);
                    throw new Exception(
                        "Gagal menyiapkan penyimpanan QRIS."
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "si",
                    $pathDatabase,
                    $idPengaturan
                );

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    unlink($tujuan);

                    throw new Exception(
                        "Gagal menyimpan gambar QRIS ke database."
                    );
                }

                mysqli_stmt_close($stmt);

                // Hapus gambar lama hanya setelah gambar baru berhasil disimpan.
                if ($gambarLama) {

                    $namaLama = basename($gambarLama);

                    $lokasiLama = $folderUpload . $namaLama;

                    if (
                        str_starts_with($gambarLama, 'uploads/qris/') &&
                        is_file($lokasiLama)
                    ) {
                        unlink($lokasiLama);
                    }
                }

                $pesan = "Gambar QRIS berhasil diunggah dan diaktifkan.";
                $jenisPesan = "success";
            }

            // ==================================
            // HAPUS QRIS
            // ==================================

            elseif ($aksi === 'hapus') {

                $gambarLama = $pengaturan['qris_image'] ?? null;
                $idPengaturan = (int)$pengaturan['id'];

                $stmt = mysqli_prepare($conn, "
                    UPDATE pengaturan_vote
                    SET qris_image = NULL
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception("Gagal menyiapkan penghapusan QRIS.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $idPengaturan
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal menghapus QRIS dari database.");
                }

                mysqli_stmt_close($stmt);

                if ($gambarLama) {

                    $namaLama = basename($gambarLama);
                    $lokasiLama = $folderUpload . $namaLama;

                    if (
                        str_starts_with($gambarLama, 'uploads/qris/') &&
                        is_file($lokasiLama)
                    ) {
                        unlink($lokasiLama);
                    }
                }

                $pesan = "Gambar QRIS berhasil dihapus.";
                $jenisPesan = "success";
            }

            else {
                throw new Exception("Aksi tidak dikenali.");
            }

        } catch (Throwable $error) {

            $pesan = $error->getMessage();
            $jenisPesan = "danger";
        }
    }

    // Perbarui token CSRF setelah proses POST.
    $_SESSION['csrf_qris'] = bin2hex(random_bytes(32));

    // Ambil ulang data terbaru.
    $pengaturan = ambilPengaturan($conn);
}

// ==========================================
// DATA TAMPILAN
// ==========================================

$hargaPerVote = (float)($pengaturan['harga_per_vote'] ?? 3500);

$nominalPopuler = array_filter(
    array_map(
        'intval',
        explode(',', $pengaturan['nominal_populer'] ?? '10,50,100,500')
    ),
    function ($angka) {
        return $angka >= 1 && $angka <= 1000000;
    }
);

$nominalPopuler = array_values(array_unique($nominalPopuler));

$qrisImage = $pengaturan['qris_image'] ?? '';

$lokasiQris = $qrisImage
    ? __DIR__ . "/../" . $qrisImage
    : '';

$qrisAda = (
    $qrisImage !== '' &&
    str_starts_with($qrisImage, 'uploads/qris/') &&
    is_file($lokasiQris)
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengaturan QRIS | Admin Kakang Senduk</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        .qris-card {
            background: #fff;
            border: 1px solid #eee5e8;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.04);
            height: 100%;
        }

        .qris-preview {
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #faf7f8;
            border: 1px dashed #d9c5cb;
            border-radius: 12px;
            padding: 20px;
        }

        .qris-preview img {
            max-width: 100%;
            max-height: 350px;
            object-fit: contain;
            border-radius: 8px;
        }

        .upload-area {
            border: 1px dashed #c9b2bb;
            padding: 22px;
            border-radius: 12px;
            background: #fdfafb;
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

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #44343a;
        }

        .form-control {
            border-radius: 9px;
            padding: 10px 12px;
            font-size: 13px;
        }

        .section-description {
            font-size: 12px;
            color: #8b7d82;
        }

        .nominal-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .nominal-preview span {
            display: inline-block;
            padding: 7px 13px;
            border: 1px solid #eadde2;
            border-radius: 30px;
            color: #641b32;
            background: #fbf4f7;
            font-size: 12px;
            font-weight: 600;
        }

        .price-preview {
            background: #fbf4f7;
            border: 1px solid #f0e1e7;
            border-radius: 12px;
            padding: 15px;
        }

        .price-preview strong {
            color: #641b32;
            font-size: 22px;
        }

        @media(max-width: 576px) {
            .qris-card {
                padding: 18px;
            }

            .qris-preview {
                min-height: 220px;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="main-content">

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- HEADER -->

        <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div
                    class="d-flex align-items-center justify-content-center"
                    style="width:43px;height:43px;background:#f8eaf0;color:#641b32;border-radius:12px;"
                >
                    <i data-lucide="qr-code" style="width:22px;"></i>
                </div>

                <div>
                    <h3 class="fw-bold mb-0">Pengaturan QRIS</h3>
                    <p class="text-secondary small mb-0">
                        Atur pembayaran dan harga vote pemilih.
                    </p>
                </div>
            </div>
        </div>

        <!-- PESAN -->

        <?php if ($pesan): ?>

            <div class="alert alert-<?= e($jenisPesan) ?> alert-dismissible fade show">
                <?= e($pesan) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>

        <?php endif; ?>

        <!-- PENGATURAN HARGA DAN NOMINAL -->

        <div class="qris-card mb-4">

            <div class="d-flex align-items-center gap-2 mb-2">
                <i data-lucide="settings" style="color:#641b32;"></i>
                <h5 class="fw-bold mb-0">Pengaturan Harga dan Jumlah Vote</h5>
            </div>

            <p class="section-description mb-4">
                Atur harga satu suara dan pilihan jumlah vote yang akan tampil
                pada popup konfirmasi pemilih.
            </p>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_qris']) ?>"
                >

                <input
                    type="hidden"
                    name="aksi"
                    value="simpan_pengaturan"
                >

                <div class="row g-4">

                    <!-- HARGA -->

                    <div class="col-lg-5">

                        <label class="form-label">
                            Harga per Vote (Rupiah)
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">Rp</span>

                            <input
                                type="number"
                                name="harga_per_vote"
                                id="hargaPerVoteInput"
                                class="form-control"
                                min="1"
                                max="1000000000"
                                step="1"
                                value="<?= e($pengaturan['harga_per_vote'] ?? 3500) ?>"
                                required
                            >
                        </div>

                        <small class="text-secondary">
                            Harga ini berlaku untuk setiap satu suara.
                        </small>

                        <div class="price-preview mt-3">
                            <small class="text-secondary d-block mb-1">
                                Harga saat ini
                            </small>

                            <strong id="previewHarga">
                                <?= rupiah($hargaPerVote) ?>
                            </strong>
                        </div>

                    </div>

                    <!-- NOMINAL POPULER -->

                    <div class="col-lg-7">

                        <label class="form-label">
                            Pilihan Jumlah Vote Populer
                        </label>

                        <input
                            type="text"
                            name="nominal_populer"
                            id="nominalPopulerInput"
                            class="form-control"
                            value="<?= e(implode(',', $nominalPopuler)) ?>"
                            placeholder="Contoh: 10,50,100,500"
                            required
                        >

                        <small class="text-secondary">
                            Pisahkan setiap angka menggunakan koma.
                            Contoh: 10,50,100,500.
                        </small>

                        <div class="mt-3">
                            <label class="form-label">
                                Pratinjau tombol nominal
                            </label>

                            <div
                                class="nominal-preview"
                                id="previewNominal"
                            >
                                <?php foreach ($nominalPopuler as $nominal): ?>
                                    <span>
                                        <?= number_format($nominal, 0, ',', '.') ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0 small">
                            <i data-lucide="info" style="width:15px;vertical-align:middle;"></i>
                            Pemilih tetap dapat mengetik jumlah vote sendiri melalui
                            pilihan Custom. Nominal di atas hanya tombol pilihan cepat.
                        </div>

                    </div>

                </div>

                <hr class="my-4">

                <button type="submit" class="btn btn-maroon px-4 py-2">
                    <i data-lucide="save" style="width:16px;vertical-align:middle;"></i>
                    Simpan Pengaturan Vote
                </button>

            </form>

        </div>

        <!-- PENGATURAN GAMBAR QRIS -->

        <div class="row g-4">

            <!-- PREVIEW QRIS -->

            <div class="col-lg-7">

                <div class="qris-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Gambar QRIS Aktif</h5>
                            <p class="section-description mb-0">
                                QRIS yang akan ditampilkan kepada pemilih.
                            </p>
                        </div>

                        <?php if ($qrisAda): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Belum tersedia</span>
                        <?php endif; ?>
                    </div>

                    <div class="qris-preview">

                        <?php if ($qrisAda): ?>

                            <img
                                src="../<?= e($qrisImage) ?>?v=<?= filemtime($lokasiQris) ?>"
                                alt="QRIS Pembayaran"
                            >

                        <?php else: ?>

                            <div class="text-center text-secondary">
                                <i
                                    data-lucide="qr-code"
                                    style="width:60px;height:60px;"
                                ></i>

                                <p class="mt-3 mb-0">
                                    Belum ada gambar QRIS yang diunggah.
                                </p>
                            </div>

                        <?php endif; ?>

                    </div>

                    <?php if ($qrisAda): ?>

                        <div class="mt-3 d-flex flex-wrap gap-2">

                            <a
                                href="../<?= e($qrisImage) ?>"
                                target="_blank"
                                class="btn btn-outline-secondary btn-sm"
                            >
                                <i data-lucide="external-link" style="width:14px;vertical-align:middle;"></i>
                                Lihat Gambar
                            </a>

                            <form
                                method="POST"
                                onsubmit="return confirm('Yakin ingin menghapus QRIS aktif? Pemilih tidak dapat melakukan pembayaran sampai QRIS baru diunggah.')"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_qris']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="aksi"
                                    value="hapus"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger btn-sm"
                                >
                                    <i data-lucide="trash-2" style="width:14px;vertical-align:middle;"></i>
                                    Hapus QRIS
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- UPLOAD QRIS -->

            <div class="col-lg-5">

                <div class="qris-card">

                    <h5 class="fw-bold mb-2">
                        <?= $qrisAda ? 'Ganti Gambar QRIS' : 'Upload Gambar QRIS' ?>
                    </h5>

                    <p class="section-description mb-4">
                        Unggah gambar QRIS resmi yang akan digunakan
                        untuk pembayaran vote.
                    </p>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_qris']) ?>"
                        >

                        <input
                            type="hidden"
                            name="aksi"
                            value="upload"
                        >

                        <div class="upload-area mb-3">

                            <div class="text-center mb-3">

                                <i
                                    data-lucide="image-plus"
                                    style="width:45px;height:45px;color:#641b32;"
                                ></i>

                                <p class="fw-semibold mt-2 mb-1">
                                    Pilih gambar QRIS
                                </p>

                                <small class="text-secondary">
                                    JPG, PNG, atau WEBP · Maksimal 3 MB
                                </small>

                            </div>

                            <input
                                type="file"
                                name="qris"
                                id="inputQris"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp"
                                required
                            >

                        </div>

                        <div id="previewUpload" class="mb-3" style="display:none;">

                            <label class="form-label">
                                Pratinjau gambar baru
                            </label>

                            <div class="qris-preview" style="min-height:180px;">
                                <img
                                    id="gambarUpload"
                                    src=""
                                    alt="Pratinjau QRIS baru"
                                    style="max-height:220px;"
                                >
                            </div>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-maroon w-100 py-2"
                        >
                            <i data-lucide="upload" style="width:16px;vertical-align:middle;"></i>

                            <?= $qrisAda ? 'Simpan QRIS Baru' : 'Simpan QRIS' ?>
                        </button>

                    </form>

                    <div class="alert alert-warning mt-4 mb-0 small">
                        <strong>Perhatian:</strong>
                        Pastikan gambar QRIS benar dan dapat dipindai.
                        QRIS baru akan menggantikan gambar sebelumnya.
                    </div>

                </div>

            </div>

        </div>

        <!-- RINGKASAN PENGATURAN -->

        <div class="qris-card mt-4">

            <h5 class="fw-bold mb-3">
                <i data-lucide="clipboard-list" style="width:18px;vertical-align:middle;color:#641b32;"></i>
                Ringkasan Pengaturan
            </h5>

            <div class="row g-3">

                <div class="col-md-4">
                    <div class="p-3 rounded-3 bg-light h-100">
                        <small class="text-secondary">Harga satu vote</small>
                        <h5 class="fw-bold mt-2 mb-0" style="color:#641b32;">
                            <?= rupiah($hargaPerVote) ?>
                        </h5>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 rounded-3 bg-light h-100">
                        <small class="text-secondary">Jumlah pilihan populer</small>
                        <h5 class="fw-bold mt-2 mb-0">
                            <?= count($nominalPopuler) ?> pilihan
                        </h5>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 rounded-3 bg-light h-100">
                        <small class="text-secondary">Status QRIS</small>
                        <h5 class="fw-bold mt-2 mb-0">
                            <?= $qrisAda ? 'Sudah tersedia' : 'Belum tersedia' ?>
                        </h5>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    lucide.createIcons();

    // ======================================
    // PREVIEW HARGA
    // ======================================

    const hargaInput = document.getElementById('hargaPerVoteInput');
    const previewHarga = document.getElementById('previewHarga');

    function formatRupiah(angka) {
        return 'Rp ' + Number(angka || 0).toLocaleString('id-ID');
    }

    hargaInput.addEventListener('input', function () {
        previewHarga.textContent = formatRupiah(this.value);
    });

    // ======================================
    // PREVIEW NOMINAL POPULER
    // ======================================

    const nominalInput = document.getElementById('nominalPopulerInput');
    const previewNominal = document.getElementById('previewNominal');

    function tampilkanNominal() {

        previewNominal.innerHTML = '';

        const daftar = nominalInput.value
            .split(',')
            .map(item => parseInt(item.trim()))
            .filter(item => Number.isInteger(item) && item >= 1 && item <= 1000000);

        const unik = [...new Set(daftar)].sort((a, b) => a - b);

        unik.forEach(nominal => {

            const span = document.createElement('span');

            span.textContent = nominal.toLocaleString('id-ID');

            previewNominal.appendChild(span);
        });
    }

    nominalInput.addEventListener('input', tampilkanNominal);

    // ======================================
    // PREVIEW GAMBAR QRIS BARU
    // ======================================

    const inputQris = document.getElementById('inputQris');
    const previewUpload = document.getElementById('previewUpload');
    const gambarUpload = document.getElementById('gambarUpload');

    inputQris.addEventListener('change', function () {

        const file = this.files[0];

        if (!file) {
            previewUpload.style.display = 'none';
            return;
        }

        const tipeDiizinkan = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!tipeDiizinkan.includes(file.type)) {
            alert('Format gambar harus JPG, PNG, atau WEBP.');
            this.value = '';
            previewUpload.style.display = 'none';
            return;
        }

        if (file.size > 3 * 1024 * 1024) {
            alert('Ukuran gambar maksimal 3 MB.');
            this.value = '';
            previewUpload.style.display = 'none';
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            gambarUpload.src = event.target.result;
            previewUpload.style.display = 'block';
        };

        reader.readAsDataURL(file);
    });
</script>

</body>
</html>
