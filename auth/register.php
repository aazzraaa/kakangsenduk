
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . "/../config/koneksi.php";

// ==========================================
// CEK KONEKSI DATABASE
// ==========================================
if (!isset($conn) || !$conn) {
    die("Koneksi database tidak ditemukan. Periksa config/koneksi.php.");
}

// ==========================================
// CEK SESSION
// ==========================================
if (isset($_SESSION["user_id"])) {

    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../index.php");
    }

    exit;
}

$pesan = "";
$jenis_pesan = "danger";

// ==========================================
// PROSES REGISTRASI
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim($_POST["nama"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $password = $_POST["password"] ?? "";
    $konfirmasi = $_POST["konfirmasi_password"] ?? "";

    // VALIDASI INPUT
    if (
        $nama === "" ||
        $email === "" ||
        $no_hp === "" ||
        $password === "" ||
        $konfirmasi === ""
    ) {

        $pesan = "Semua kolom wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $pesan = "Format email tidak valid.";

    } elseif (strlen($password) < 8) {

        $pesan = "Password minimal 8 karakter.";

    } elseif ($password !== $konfirmasi) {

        $pesan = "Konfirmasi password tidak cocok.";

    } else {

        try {

            // CEK EMAIL DUPLIKAT
            $cek = mysqli_prepare(
                $conn,
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            if (!$cek) {
                throw new Exception("Gagal menyiapkan pemeriksaan email.");
            }

            mysqli_stmt_bind_param($cek, "s", $email);
            mysqli_stmt_execute($cek);

            $hasil = mysqli_stmt_get_result($cek);

            $email_terdaftar = mysqli_num_rows($hasil) > 0;

            mysqli_stmt_close($cek);

            if ($email_terdaftar) {

                $pesan = "Email sudah terdaftar. Gunakan email lain.";

            } else {

                // HASH PASSWORD
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // SIMPAN AKUN PEMILIH
                $query = mysqli_prepare(
                    $conn,
                    "INSERT INTO users
                    (nama, email, no_hp, password, role)
                    VALUES (?, ?, ?, ?, 'pemilih')"
                );

                if (!$query) {
                    throw new Exception("Gagal menyiapkan penyimpanan akun.");
                }

                mysqli_stmt_bind_param(
                    $query,
                    "ssss",
                    $nama,
                    $email,
                    $no_hp,
                    $password_hash
                );

                $berhasil = mysqli_stmt_execute($query);

                mysqli_stmt_close($query);

                if ($berhasil) {

                    header("Location: login.php?daftar=berhasil");
                    exit;

                } else {

                    $pesan = "Registrasi gagal. Silakan coba kembali.";
                }
            }

        } catch (Throwable $e) {

            error_log("Register error: " . $e->getMessage());

            $pesan = "Terjadi kesalahan sistem. Silakan coba kembali.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registrasi | Voting Kakang Senduk Ponorogo</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap"
        rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            background: #f8f3f5;
            overflow-x: hidden;
        }

        /* BACKGROUND */
        .register-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 20px;
            position: relative;
            overflow: hidden;

            background:
                radial-gradient(circle at 10% 10%, rgba(222,120,151,.15), transparent 30%),
                radial-gradient(circle at 90% 90%, rgba(123,24,53,.12), transparent 35%),
                #f8f3f5;
        }

        .bg-circle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .circle-one {
            width: 420px;
            height: 420px;
            background: linear-gradient(135deg, #f7dce5, #fcecf1);
            top: -210px;
            right: -130px;
            opacity: .7;
        }

        .circle-two {
            width: 350px;
            height: 350px;
            background: linear-gradient(135deg, #ead0da, #f7e7ed);
            bottom: -200px;
            left: -130px;
            opacity: .7;
        }

        /* MAIN CARD */
        .register-container {
            width: 100%;
            max-width: 1050px;
            min-height: 680px;
            background: white;
            display: grid;
            grid-template-columns: .95fr 1.05fr;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(86,26,46,.12);
            position: relative;
            z-index: 2;
        }

        /* LEFT BANNER */
        .register-banner {
            background: linear-gradient(145deg, #8e2142, #65132e 70%, #4d1026);
            color: white;
            padding: 50px 43px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .register-banner::before {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50%;
            top: -150px;
            right: -120px;
        }

        .register-banner::after {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 50%;
            bottom: -140px;
            left: -120px;
        }

        .banner-content,
        .banner-footer {
            position: relative;
            z-index: 2;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 65px;
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-icon i {
            width: 23px;
            height: 23px;
        }

        .brand-name {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .5px;
        }

        .brand-subtitle {
            font-size: 10px;
            opacity: .75;
            letter-spacing: 1.5px;
            margin-top: 2px;
        }

        .banner-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 13px;
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.1);
            border-radius: 30px;
            font-size: 10px;
            letter-spacing: 1px;
            margin-bottom: 22px;
        }

        .banner-label i {
            width: 14px;
            height: 14px;
        }

        .banner-title {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 17px;
        }

        .banner-title span {
            color: #f4bdcf;
        }

        .banner-description {
            color: rgba(255,255,255,.76);
            font-size: 13px;
            line-height: 1.9;
            max-width: 340px;
        }

        .banner-decoration {
            margin-top: 38px;
            width: 100%;
            height: 115px;
            border-radius: 17px;
            border: 1px solid rgba(255,255,255,.18);
            background: rgba(255,255,255,.08);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
        }

        .decoration-item {
            text-align: center;
        }

        .decoration-item i {
            width: 27px;
            height: 27px;
            color: #f4bdcf;
            margin-bottom: 8px;
        }

        .decoration-item p {
            font-size: 10px;
            color: rgba(255,255,255,.8);
            margin: 0;
        }

        .decoration-divider {
            height: 42px;
            width: 1px;
            background: rgba(255,255,255,.2);
        }

        .banner-footer {
            font-size: 11px;
            color: rgba(255,255,255,.55);
            margin-top: 35px;
        }

        /* RIGHT FORM */
        .register-form-section {
            padding: 45px 55px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-form-content {
            width: 100%;
            max-width: 390px;
        }

        .welcome-icon {
            width: 49px;
            height: 49px;
            background: #fceaf0;
            color: #861d3c;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 17px;
        }

        .welcome-icon i {
            width: 23px;
            height: 23px;
        }

        .form-heading {
            font-size: 27px;
            font-weight: 800;
            color: #30212a;
            margin-bottom: 7px;
        }

        .form-description {
            color: #999;
            font-size: 12px;
            margin-bottom: 24px;
        }

        .form-label {
            color: #40333a;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: #a18b94;
            z-index: 2;
        }

        .custom-input {
            height: 46px;
            border: 1px solid #e9dfe3;
            border-radius: 10px;
            padding: 10px 42px;
            font-size: 12px;
            color: #33232b;
            background: white;
            transition: .25s;
        }

        .custom-input:focus {
            border-color: #8e2142;
            box-shadow: 0 0 0 4px rgba(142,33,66,.09);
        }

        .custom-input::placeholder {
            color: #b7aab0;
        }

        .password-toggle {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: 0;
            color: #a18b94;
            cursor: pointer;
            z-index: 3;
            display: flex;
        }

        .password-toggle i {
            width: 16px;
            height: 16px;
        }

        .password-hint {
            font-size: 10px;
            color: #aaa;
            margin-top: 6px;
        }

        /* BUTTON */
        .btn-register {
            height: 47px;
            width: 100%;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(110deg, #8e2142, #70152f);
            color: white;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .3px;
            margin-top: 7px;
            transition: .25s;
            box-shadow: 0 7px 18px rgba(123,24,53,.2);
        }

        .btn-register:hover {
            background: linear-gradient(110deg, #70152f, #551025);
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(123,24,53,.27);
            color: white;
        }

        .btn-register i {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-left: 6px;
        }

        .login-text {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 20px;
        }

        .login-text a {
            color: #861d3c;
            font-weight: 700;
            text-decoration: none;
        }

        .login-text a:hover {
            text-decoration: underline;
        }

        .security-note {
            margin-top: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            color: #aaa;
            font-size: 10px;
        }

        .security-note i {
            width: 13px;
            height: 13px;
            color: #8e2142;
        }

        .alert {
            border: 0;
            border-radius: 10px;
            font-size: 12px;
            padding: 11px 13px;
            margin-bottom: 17px;
        }

        .alert-danger {
            background: #fff0f1;
            color: #b42336;
        }

        /* RESPONSIVE */
        @media(max-width: 850px) {

            .register-container {
                max-width: 480px;
                grid-template-columns: 1fr;
            }

            .register-banner {
                padding: 30px;
            }

            .brand {
                margin-bottom: 30px;
            }

            .banner-title {
                font-size: 29px;
            }

            .banner-decoration {
                margin-top: 25px;
            }

            .banner-footer {
                margin-top: 25px;
            }

            .register-form-section {
                padding: 40px 35px;
            }
        }

        @media(max-width: 480px) {

            .register-wrapper {
                padding: 15px;
            }

            .register-container {
                border-radius: 19px;
            }

            .register-banner {
                padding: 25px;
            }

            .banner-title {
                font-size: 26px;
            }

            .register-form-section {
                padding: 32px 24px;
            }

            .form-heading {
                font-size: 24px;
            }
        }

    </style>

</head>

<body>

<div class="register-wrapper">

    <div class="bg-circle circle-one"></div>
    <div class="bg-circle circle-two"></div>

    <div class="register-container">

        <!-- BAGIAN KIRI -->
        <section class="register-banner">

            <div class="banner-content">

                <div class="brand">

                    <div class="brand-icon">
                        <i data-lucide="crown"></i>
                    </div>

                    <div>
                        <div class="brand-name">
                            KAKANG SENDUK
                        </div>

                        <div class="brand-subtitle">
                            PONOROGO VOTING SYSTEM
                        </div>
                    </div>

                </div>

                <div class="banner-label">
                    <i data-lucide="sparkles"></i>
                    BERGABUNG BERSAMA KAMI
                </div>

                <h1 class="banner-title">
                    Jadilah Bagian dari
                    <span>Perubahan.</span>
                </h1>

                <p class="banner-description">
                    Buat akun Anda dan ikut berpartisipasi dalam
                    pemilihan Kakang Senduk Ponorogo. Setiap suara
                    memiliki arti untuk menentukan generasi terbaik.
                </p>

                <div class="banner-decoration">

                    <div class="decoration-item">
                        <i data-lucide="users"></i>
                        <p>Komunitas</p>
                    </div>

                    <div class="decoration-divider"></div>

                    <div class="decoration-item">
                        <i data-lucide="vote"></i>
                        <p>Partisipasi</p>
                    </div>

                    <div class="decoration-divider"></div>

                    <div class="decoration-item">
                        <i data-lucide="award"></i>
                        <p>Prestasi</p>
                    </div>

                </div>

            </div>

            <div class="banner-footer">
                © <?= date('Y') ?> Kakang Senduk Ponorogo. All Rights Reserved.
            </div>

        </section>

        <!-- BAGIAN KANAN -->
        <section class="register-form-section">

            <div class="register-form-content">

                <div class="welcome-icon">
                    <i data-lucide="user-round-plus"></i>
                </div>

                <h2 class="form-heading">
                    Buat Akun Baru
                </h2>

                <p class="form-description">
                    Lengkapi informasi berikut untuk membuat akun pemilih.
                </p>

                <!-- NOTIFIKASI -->
                <?php if ($pesan !== ""): ?>

                    <div class="alert alert-<?= htmlspecialchars($jenis_pesan) ?>">

                        <i
                            data-lucide="alert-circle"
                            style="width:15px;height:15px;vertical-align:middle;margin-right:5px;">
                        </i>

                        <?= htmlspecialchars($pesan) ?>

                    </div>

                <?php endif; ?>

                <!-- FORM REGISTRASI -->
                <form method="POST" action="">

                    <div class="form-group">

                        <label class="form-label">
                            Nama Lengkap
                        </label>

                        <div class="input-wrapper">

                            <i data-lucide="user"></i>

                            <input
                                type="text"
                                name="nama"
                                class="form-control custom-input"
                                placeholder="Masukkan nama lengkap"
                                value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                                autocomplete="name"
                                required>

                        </div>

                    </div>

                    <div class="form-group">

                        <label class="form-label">
                            Alamat Email
                        </label>

                        <div class="input-wrapper">

                            <i data-lucide="mail"></i>

                            <input
                                type="email"
                                name="email"
                                class="form-control custom-input"
                                placeholder="nama@email.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                autocomplete="email"
                                required>

                        </div>

                    </div>

                    <div class="form-group">

                        <label class="form-label">
                            Nomor HP
                        </label>

                        <div class="input-wrapper">

                            <i data-lucide="phone"></i>

                            <input
                                type="tel"
                                name="no_hp"
                                class="form-control custom-input"
                                placeholder="Contoh: 081234567890"
                                value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
                                autocomplete="tel"
                                required>

                        </div>

                    </div>

                    <div class="form-group">

                        <label class="form-label">
                            Password
                        </label>

                        <div class="input-wrapper">

                            <i data-lucide="lock-keyhole"></i>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control custom-input"
                                placeholder="Minimal 8 karakter"
                                minlength="8"
                                autocomplete="new-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                id="togglePassword"
                                aria-label="Tampilkan password">

                                <i data-lucide="eye"></i>

                            </button>

                        </div>

                        <div class="password-hint">
                            Password harus terdiri dari minimal 8 karakter.
                        </div>

                    </div>

                    <div class="form-group">

                        <label class="form-label">
                            Konfirmasi Password
                        </label>

                        <div class="input-wrapper">

                            <i data-lucide="shield-check"></i>

                            <input
                                type="password"
                                name="konfirmasi_password"
                                id="konfirmasi_password"
                                class="form-control custom-input"
                                placeholder="Masukkan ulang password"
                                autocomplete="new-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                id="toggleKonfirmasi"
                                aria-label="Tampilkan konfirmasi password">

                                <i data-lucide="eye"></i>

                            </button>

                        </div>

                    </div>

                    <button type="submit" class="btn btn-register">

                        Daftar Sekarang

                        <i data-lucide="arrow-right"></i>

                    </button>

                </form>

                <div class="login-text">

                    Sudah memiliki akun?

                    <a href="login.php">
                        Login di sini
                    </a>

                </div>

                <div class="security-note">

                    <i data-lucide="shield-check"></i>

                    Informasi akun Anda dilindungi dengan aman.

                </div>

            </div>

        </section>

    </div>

</div>

<script>

    lucide.createIcons();

    // Fungsi tampil/sembunyikan password
    function setupPasswordToggle(buttonId, inputId) {

        const button = document.getElementById(buttonId);
        const input = document.getElementById(inputId);

        button.addEventListener('click', function () {

            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';

            button.innerHTML = isPassword
                ? '<i data-lucide="eye-off"></i>'
                : '<i data-lucide="eye"></i>';

            button.setAttribute(
                'aria-label',
                isPassword ? 'Sembunyikan password' : 'Tampilkan password'
            );

            lucide.createIcons();
        });
    }

    setupPasswordToggle('togglePassword', 'password');
    setupPasswordToggle('toggleKonfirmasi', 'konfirmasi_password');

</script>

</body>
</html>
