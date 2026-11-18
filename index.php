<?php
session_start();
require_once __DIR__ . "/config/koneksi.php";

date_default_timezone_set('Asia/Jakarta');

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Ambil periode voting yang sedang aktif
$periodeQuery = mysqli_query($conn, "
    SELECT *
    FROM periode_voting
    WHERE status = 'Aktif'
    ORDER BY id DESC
    LIMIT 1
");

$periode = $periodeQuery ? mysqli_fetch_assoc($periodeQuery) : null;

$waktuSekarang = date('Y-m-d H:i:s');
$votingDibuka = false;

if ($periode) {
    $votingDibuka = (
        $waktuSekarang >= $periode['tanggal_mulai'] &&
        $waktuSekarang <= $periode['tanggal_selesai']
    );
}

// Ambil jumlah pemilih
$resultPemilih = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'pemilih'
");
$totalPemilih = $resultPemilih ? (int)mysqli_fetch_assoc($resultPemilih)['total'] : 0;

// Ambil jumlah kontestan
$resultKontestan = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM kontestan
");
$totalKontestan = $resultKontestan ? (int)mysqli_fetch_assoc($resultKontestan)['total'] : 0;

// Ambil total suara
$resultSuara = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM voting
");
$totalSuara = $resultSuara ? (int)mysqli_fetch_assoc($resultSuara)['total'] : 0;

// Ambil daftar kontestan
$queryKontestan = mysqli_query($conn, "
    SELECT
        k.*,
        COUNT(v.id) AS jumlah_suara
    FROM kontestan k
    LEFT JOIN voting v ON v.kontestan_id = k.id
    GROUP BY k.id
    ORDER BY
        CASE WHEN k.kategori = 'Kakang' THEN 1 ELSE 2 END,
        k.nomor_peserta ASC
");

$kontestan = [];

if ($queryKontestan) {
    while ($row = mysqli_fetch_assoc($queryKontestan)) {
        $kontestan[] = $row;
    }
}

$namaPengguna = $_SESSION['nama'] ?? '';
$rolePengguna = $_SESSION['role'] ?? '';
$sudahLogin = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kakang Senduk Ponorogo | Pemilihan</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --maroon: #641b32;
            --maroon-dark: #3d1020;
            --gold: #d8b56d;
            --cream: #fffaf2;
            --text: #29232a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: var(--text);
            background: #fff;
        }

        html {
            scroll-behavior: smooth;
        }

        .navbar {
            background: rgba(255, 250, 242, .97);
            box-shadow: 0 3px 20px rgba(0,0,0,.06);
            padding: 15px 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: var(--maroon);
            font-weight: 800;
            letter-spacing: .3px;
            text-decoration: none;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            color: var(--gold);
            background: var(--maroon);
        }

        .nav-link {
            color: #51454a;
            font-size: 13px;
            font-weight: 600;
            margin: 0 8px;
        }

        .nav-link:hover {
            color: var(--maroon);
        }

        .btn-maroon {
            background: var(--maroon);
            color: white;
            border: 1px solid var(--maroon);
            border-radius: 9px;
            padding: 11px 21px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-maroon:hover {
            background: var(--maroon-dark);
            color: white;
        }

        .btn-outline-maroon {
            border: 1px solid var(--maroon);
            color: var(--maroon);
            border-radius: 9px;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-outline-maroon:hover {
            background: var(--maroon);
            color: white;
        }

        .hero {
            position: relative;
            overflow: hidden;
            min-height: 570px;
            display: flex;
            align-items: center;
            color: white;
            background:
                radial-gradient(circle at 85% 20%, rgba(216,181,109,.25), transparent 28%),
                linear-gradient(115deg, #35101e 0%, #641b32 55%, #87384b 100%);
        }

        .hero::before {
            content: "";
            position: absolute;
            width: 430px;
            height: 430px;
            border: 1px solid rgba(216,181,109,.25);
            border-radius: 50%;
            right: -100px;
            top: 60px;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 330px;
            height: 330px;
            border: 1px solid rgba(216,181,109,.18);
            border-radius: 50%;
            right: -50px;
            top: 110px;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding-top: 65px;
            padding-bottom: 65px;
        }

        .hero-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(216,181,109,.5);
            color: var(--gold);
            border-radius: 30px;
            padding: 8px 15px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.2px;
            margin-bottom: 22px;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(38px, 5vw, 62px);
            line-height: 1.15;
            font-weight: 800;
            max-width: 700px;
            margin-bottom: 20px;
        }

        .hero h1 span {
            color: var(--gold);
        }

        .hero p {
            color: rgba(255,255,255,.78);
            font-size: 14px;
            line-height: 1.9;
            max-width: 570px;
        }

        .hero-decoration {
            position: relative;
            z-index: 2;
            min-height: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .emblem {
            width: 270px;
            height: 270px;
            border: 2px solid rgba(216,181,109,.75);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--gold);
            background: rgba(255,255,255,.04);
            box-shadow: 0 0 0 17px rgba(216,181,109,.06),
                        0 0 0 35px rgba(216,181,109,.035);
        }

        .emblem-inner {
            border: 1px solid rgba(216,181,109,.5);
            border-radius: 50%;
            width: 220px;
            height: 220px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .emblem strong {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            line-height: 1.2;
        }

        .emblem small {
            font-size: 10px;
            letter-spacing: 2px;
            margin-top: 10px;
        }

        .stats-section {
            position: relative;
            z-index: 3;
            margin-top: -45px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 23px 15px;
            box-shadow: 0 10px 35px rgba(40,20,25,.09);
            text-align: center;
            height: 100%;
        }

        .stats-card h3 {
            color: var(--maroon);
            font-size: 27px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stats-card p {
            color: #887d81;
            font-size: 11px;
            margin: 0;
        }

        .section {
            padding: 85px 0;
        }

        .section-soft {
            background: var(--cream);
        }

        .section-label {
            color: #a17b3c;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            color: var(--maroon-dark);
            font-size: clamp(29px, 4vw, 39px);
            font-weight: 700;
            margin: 10px 0;
        }

        .section-description {
            color: #81767a;
            font-size: 13px;
            line-height: 1.8;
        }

        .period-card {
            background: white;
            border: 1px solid #f0e6e8;
            border-radius: 15px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 8px 25px rgba(50,20,30,.04);
        }

        .period-icon {
            width: 48px;
            height: 48px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            color: var(--maroon);
            background: #f8eaf0;
            margin-bottom: 17px;
        }

        .period-card h5 {
            color: var(--maroon-dark);
            font-size: 16px;
            font-weight: 700;
        }

        .period-card p {
            color: #81767a;
            font-size: 12px;
            margin-bottom: 7px;
        }

        .status-open {
            color: #147a45;
            background: #e7f7ed;
        }

        .status-closed {
            color: #9b3446;
            background: #fbecef;
        }

        .status-badge {
            display: inline-block;
            border-radius: 30px;
            padding: 6px 11px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .contestant-card {
            height: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #eee6e8;
            transition: .25s ease;
        }

        .contestant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(50,20,30,.10);
        }

        .contestant-photo {
            height: 245px;
            background: linear-gradient(135deg, #f4e5e9, #ead4dc);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .contestant-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            color: #9a6475;
            text-align: center;
        }

        .photo-placeholder i {
            width: 45px;
            height: 45px;
        }

        .contestant-info {
            padding: 19px;
        }

        .category-tag {
            display: inline-block;
            color: var(--maroon);
            background: #f8eaf0;
            border-radius: 30px;
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 700;
        }

        .contestant-info h5 {
            color: var(--maroon-dark);
            font-size: 16px;
            font-weight: 700;
            margin: 12px 0 5px;
        }

        .contestant-info p {
            color: #8b8084;
            font-size: 11px;
            margin-bottom: 12px;
        }

        .vote-count {
            border-top: 1px solid #f0e9eb;
            padding-top: 12px;
            color: #75696e;
            font-size: 11px;
        }

        .empty-state {
            background: white;
            border: 1px dashed #d9c5cb;
            border-radius: 15px;
            padding: 45px 20px;
            text-align: center;
            color: #82757a;
        }

        .steps-card {
            text-align: center;
            padding: 25px 15px;
        }

        .step-number {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: var(--maroon);
            color: var(--gold);
            font-weight: 800;
        }

        .steps-card h6 {
            color: var(--maroon-dark);
            font-weight: 700;
            font-size: 14px;
        }

        .steps-card p {
            color: #81767a;
            font-size: 11px;
            line-height: 1.8;
        }

        .cta-section {
            background: linear-gradient(110deg, #3d1020, #70253d);
            color: white;
            padding: 55px 0;
        }

        .cta-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
        }

        .cta-section p {
            color: rgba(255,255,255,.72);
            font-size: 13px;
        }

        footer {
            background: #2c101a;
            color: rgba(255,255,255,.65);
            padding: 25px 0;
            font-size: 11px;
        }

        footer strong {
            color: var(--gold);
        }

        @media (max-width: 767px) {
            .hero {
                min-height: auto;
            }

            .hero-content {
                padding-top: 75px;
                padding-bottom: 45px;
            }

            .hero-decoration {
                min-height: 230px;
                padding-bottom: 40px;
            }

            .emblem {
                width: 210px;
                height: 210px;
            }

            .emblem-inner {
                width: 170px;
                height: 170px;
            }

            .emblem strong {
                font-size: 22px;
            }

            .section {
                padding: 60px 0;
            }

            .stats-section {
                margin-top: 20px;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a href="index.php" class="brand">
            <div class="brand-icon">
                <i data-lucide="crown"></i>
            </div>
            <div>
                <div style="font-size:14px;">KAKANG SENDUK</div>
                <div style="font-size:10px; font-weight:500; color:#92717c;">PONOROGO</div>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
            <i data-lucide="menu"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="#beranda">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="#periode">Periode Voting</a></li>
                <li class="nav-item"><a class="nav-link" href="#kontestan">Kontestan</a></li>
                <li class="nav-item"><a class="nav-link" href="#cara-voting">Cara Voting</a></li>

                <?php if ($sudahLogin): ?>
                    <?php if ($rolePengguna === 'admin'): ?>
                        <li class="nav-item ms-lg-2">
                            <a href="admin/dashboard.php" class="btn btn-maroon">Dashboard Admin</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2">
                            <a href="voting.php" class="btn btn-maroon">Mulai Voting</a>
                        </li>
                        <li class="nav-item">
                            <a href="auth/logout.php" class="nav-link">Keluar</a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a href="auth/login.php" class="btn btn-outline-maroon">Masuk</a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a href="auth/register.php" class="btn btn-maroon">Daftar Pemilih</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero" id="beranda">
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="hero-label">
                    <i data-lucide="award" style="width:16px;"></i>
                    PEMILIHAN KAKANG SENDUK PONOROGO
                </div>

                <h1>
                    Pilih Generasi Muda
                    <span>Inspiratif</span>
                    Ponorogo
                </h1>

                <p>
                    Berikan dukunganmu kepada putra-putri terbaik Ponorogo.
                    Kenali para kontestan, tentukan pilihanmu, dan ikut menjadi
                    bagian dari proses pemilihan Kakang Senduk Ponorogo.
                </p>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="#kontestan" class="btn btn-maroon" style="background:var(--gold);border-color:var(--gold);color:#35101e;">
                        Lihat Kontestan <i data-lucide="arrow-right" style="width:15px; margin-left:5px;"></i>
                    </a>

                    <?php if ($sudahLogin && $rolePengguna === 'pemilih'): ?>
                        <a href="voting.php" class="btn btn-outline-light">Berikan Suara</a>
                    <?php elseif (!$sudahLogin): ?>
                        <a href="auth/register.php" class="btn btn-outline-light">Daftar Sekarang</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hero-decoration">
                    <div class="emblem">
                        <div class="emblem-inner">
                            <i data-lucide="crown" style="width:48px;height:48px;margin-bottom:12px;"></i>
                            <strong>KAKANG<br>SENDUK</strong>
                            <small>PONOROGO</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STATISTIK -->
<section class="stats-section">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-lg-4">
                <div class="stats-card">
                    <h3><?= number_format($totalKontestan) ?></h3>
                    <p>Total Kontestan</p>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="stats-card">
                    <h3><?= number_format($totalPemilih) ?></h3>
                    <p>Pemilih Terdaftar</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="stats-card">
                    <h3><?= number_format($totalSuara) ?></h3>
                    <p>Total Suara Masuk</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PERIODE VOTING -->
<section class="section" id="periode">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">Informasi Pemilihan</div>
            <h2 class="section-title">Periode Voting</h2>
            <p class="section-description">
                Perhatikan jadwal pemilihan sebelum memberikan suara.
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="period-card">
                    <div class="period-icon">
                        <i data-lucide="calendar-days"></i>
                    </div>

                    <?php if ($periode): ?>
                        <span class="status-badge <?= $votingDibuka ? 'status-open' : 'status-closed' ?>">
                            <?= $votingDibuka ? 'VOTING SEDANG DIBUKA' : 'VOTING BELUM / SUDAH DITUTUP' ?>
                        </span>

                        <h5><?= e($periode['nama_periode']) ?></h5>

                        <p>
                            <i data-lucide="calendar" style="width:14px;vertical-align:middle;"></i>
                            Mulai:
                            <strong><?= date('d F Y, H:i', strtotime($periode['tanggal_mulai'])) ?> WIB</strong>
                        </p>

                        <p>
                            <i data-lucide="calendar-check" style="width:14px;vertical-align:middle;"></i>
                            Selesai:
                            <strong><?= date('d F Y, H:i', strtotime($periode['tanggal_selesai'])) ?> WIB</strong>
                        </p>

                        <?php if ($votingDibuka): ?>
                            <?php if ($sudahLogin && $rolePengguna === 'pemilih'): ?>
                                <a href="voting.php" class="btn btn-maroon mt-3">Masuk ke Halaman Voting</a>
                            <?php elseif (!$sudahLogin): ?>
                                <a href="auth/login.php" class="btn btn-maroon mt-3">Login untuk Voting</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mt-3 mb-0">
                                Saat ini pemungutan suara belum dapat dilakukan.
                            </p>
                        <?php endif; ?>

                    <?php else: ?>
                        <span class="status-badge status-closed">BELUM ADA PERIODE AKTIF</span>
                        <h5>Periode voting belum tersedia</h5>
                        <p>Informasi jadwal pemilihan akan ditampilkan setelah admin mengatur periode voting.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- DAFTAR KONTESTAN -->
<section class="section section-soft" id="kontestan">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">Putra-Putri Terbaik</div>
            <h2 class="section-title">Kenali Para Kontestan</h2>
            <p class="section-description">
                Berikut daftar peserta pemilihan Kakang dan Senduk Ponorogo.
            </p>
        </div>

        <?php if (count($kontestan) > 0): ?>
            <div class="row g-4">
                <?php foreach ($kontestan as $peserta): ?>
                    <div class="col-sm-6 col-lg-4">
                        <div class="contestant-card">
                            <div class="contestant-photo">
                                <?php
                                $foto = trim($peserta['foto'] ?? '');
                                $lokasiFoto = "uploads/kontestan/" . basename($foto);
                                $fileFotoAda = ($foto !== '' && file_exists(__DIR__ . "/" . $lokasiFoto));
                                ?>

                                <?php if ($fileFotoAda): ?>
                                    <img src="<?= e($lokasiFoto) ?>" alt="Foto <?= e($peserta['nama']) ?>">
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <i data-lucide="user-round"></i>
                                        <div style="font-size:11px;margin-top:8px;">Foto belum tersedia</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="contestant-info">
                                <span class="category-tag"><?= e($peserta['kategori']) ?></span>
                                <h5><?= e($peserta['nama']) ?></h5>
                                <p>
                                    Nomor peserta: <strong><?= e($peserta['nomor_peserta']) ?></strong>
                                    <?php if (!empty($peserta['asal_instansi'])): ?>
                                        <br><?= e($peserta['asal_instansi']) ?>
                                    <?php endif; ?>
                                </p>

                                <div class="vote-count d-flex justify-content-between">
                                    <span><i data-lucide="vote" style="width:14px;vertical-align:middle;"></i> Suara masuk</span>
                                    <strong><?= number_format((int)$peserta['jumlah_suara']) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i data-lucide="users" style="width:40px;height:40px;margin-bottom:12px;"></i>
                <h5>Belum ada kontestan</h5>
                <p class="mb-0">Daftar kontestan akan ditampilkan setelah admin menambahkan data peserta.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- CARA VOTING -->
<section class="section" id="cara-voting">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">Panduan Pemilih</div>
            <h2 class="section-title">Cara Mengikuti Voting</h2>
            <p class="section-description">
                Ikuti langkah berikut untuk memberikan suara.
            </p>
        </div>

        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="steps-card">
                    <div class="step-number">01</div>
                    <h6>Daftar Akun</h6>
                    <p>Buat akun pemilih menggunakan nama, email, nomor HP, dan password.</p>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="steps-card">
                    <div class="step-number">02</div>
                    <h6>Login</h6>
                    <p>Masuk menggunakan email dan password akun yang sudah didaftarkan.</p>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="steps-card">
                    <div class="step-number">03</div>
                    <h6>Pilih Kontestan</h6>
                    <p>Kenali peserta dan tentukan pilihan sesuai kategori pemilihan.</p>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="steps-card">
                    <div class="step-number">04</div>
                    <h6>Konfirmasi Suara</h6>
                    <p>Periksa pilihan sebelum mengirimkan suara. Pilihan yang sudah dikirim tidak dapat diubah.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2>Suaramu Berarti untuk Ponorogo</h2>
                <p class="mb-lg-0">
                    Jadilah bagian dari pemilihan generasi muda yang inspiratif dan berkontribusi untuk daerah.
                </p>
            </div>

            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <?php if (!$sudahLogin): ?>
                    <a href="auth/register.php" class="btn btn-maroon" style="background:var(--gold);color:#35101e;border-color:var(--gold);">
                        Daftar sebagai Pemilih
                    </a>
                <?php elseif ($rolePengguna === 'pemilih'): ?>
                    <a href="voting.php" class="btn btn-maroon" style="background:var(--gold);color:#35101e;border-color:var(--gold);">
                        Buka Halaman Voting
                    </a>
                <?php else: ?>
                    <a href="admin/dashboard.php" class="btn btn-maroon" style="background:var(--gold);color:#35101e;border-color:var(--gold);">
                        Dashboard Admin
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
        <div><strong>KAKANG SENDUK PONOROGO</strong> — Sistem Informasi Pemilihan</div>
        <div>© <?= date('Y') ?> Pemilihan Kakang Senduk Ponorogo. All rights reserved.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();
</script>

</body>
</html>