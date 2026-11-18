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

$pesan = "";
$jenis_pesan = "danger";

// ==========================================
// CEK SESSION LOGIN
// ==========================================
if (isset($_SESSION["user_id"])) {

    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../index.php");
    }

    exit;
}

// ==========================================
// NOTIFIKASI REGISTRASI
// ==========================================
if (isset($_GET["daftar"]) && $_GET["daftar"] === "berhasil") {
    $pesan = "Registrasi berhasil! Silakan login menggunakan akun Anda.";
    $jenis_pesan = "success";
}

// ==========================================
// PROSES LOGIN
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $pesan = "Email dan password wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $pesan = "Format email tidak valid.";

    } else {

        try {

            $query = mysqli_prepare(
                $conn,
                "SELECT id, nama, email, password, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            if (!$query) {
                throw new Exception("Query login gagal disiapkan.");
            }

            mysqli_stmt_bind_param($query, "s", $email);
            mysqli_stmt_execute($query);

            $hasil = mysqli_stmt_get_result($query);
            $user = mysqli_fetch_assoc($hasil);

            mysqli_stmt_close($query);

            if ($user && password_verify($password, $user["password"])) {

                // Regenerasi session untuk keamanan
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["nama"] = $user["nama"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];

                // Redirect berdasarkan role
                if ($user["role"] === "admin") {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../index.php");
                }

                exit;

            } else {

                $pesan = "Email atau password yang Anda masukkan salah.";
            }

        } catch (Throwable $e) {

            // Detail error dicatat di server, bukan ditampilkan ke pengguna.
            error_log("Login error: " . $e->getMessage());

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

    <title>Login | Voting Kakang Senduk Ponorogo</title>

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
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 20px;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 10% 10%, rgba(222, 120, 151, .15), transparent 30%),
                radial-gradient(circle at 90% 90%, rgba(123, 24, 53, .12), transparent 35%),
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

        /* LOGIN CONTAINER */
        .login-container {
            width: 100%;
            max-width: 1030px;
            min-height: 590px;
            background: white;
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(86, 26, 46, .12);
            position: relative;
            z-index: 2;
        }

        /* LEFT SIDE */
        .login-banner {
            background: linear-gradient(145deg, #8e2142, #65132e 70%, #4d1026);
            color: white;
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .login-banner::before {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50%;
            top: -150px;
            right: -120px;
        }

        .login-banner::after {
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
            margin-bottom: 55px;
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
            font-size: 11px;
            letter-spacing: 1px;
            margin-bottom: 22px;
        }

        .banner-label i {
            width: 14px;
            height: 14px;
        }

        .banner-title {
            font-family: 'Playfair Display', serif;
            font-size: 37px;
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
            max-width: 340px;
            height: 110px;
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
            margin-top: 40px;
        }

        /* RIGHT SIDE */
        .login-form-section {
            padding: 65px 55px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-form-content {
            width: 100%;
            max-width: 365px;
        }

        .welcome-icon {
            width: 53px;
            height: 53px;
            background: #fceaf0;
            color: #861d3c;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .welcome-icon i {
            width: 25px;
            height: 25px;
        }

        .form-heading {
            font-size: 28px;
            font-weight: 800;
            color: #30212a;
            margin-bottom: 8px;
        }

        .form-description {
            color: #999;
            font-size: 13px;
            margin-bottom: 30px;
        }

        .form-label {
            color: #40333a;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 17px;
            height: 17px;
            color: #a18b94;
            z-index: 2;
        }

        .custom-input {
            height: 49px;
            border: 1px solid #e9dfe3;
            border-radius: 10px;
            padding: 10px 15px 10px 43px; /* Disesuaikan agar pas tanpa tombol toggle */
            font-size: 13px;
            color: #33232b;
            background: #fff;
            transition: .25s;
        }

        .custom-input:focus {
            border-color: #8e2142;
            box-shadow: 0 0 0 4px rgba(142,33,66,.09);
        }

        .custom-input::placeholder {
            color: #b7aab0;
        }

        .form-group {
            margin-bottom: 19px;
        }

        /* BUTTON */
        .btn-login {
            height: 49px;
            width: 100%;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(110deg, #8e2142, #70152f);
            color: white;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .3px;
            margin-top: 8px;
            transition: .25s;
            box-shadow: 0 7px 18px rgba(123,24,53,.2);
        }

        .btn-login:hover {
            background: linear-gradient(110deg, #70152f, #551025);
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(123,24,53,.27);
            color: white;
        }

        .btn-login i {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-left: 6px;
        }

        .register-text {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 25px;
        }

        .register-text a {
            color: #861d3c;
            font-weight: 700;
            text-decoration: none;
        }

        .register-text a:hover {
            text-decoration: underline;
        }

        .security-note {
            margin-top: 20px;
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

        /* DEMO CREDENTIALS BOX */
        .demo-credentials {
            background: #fdf6f8;
            border: 1px dashed #dca7b7;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #65132e;
        }

        .demo-credentials p {
            margin-bottom: 4px;
            font-weight: 700;
        }

        .demo-credentials ul {
            margin: 0;
            padding-left: 16px;
            color: #40333a;
        }

        .alert {
            border: 0;
            border-radius: 10px;
            font-size: 12px;
            padding: 12px 14px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #eaf8ef;
            color: #267344;
        }

        .alert-danger {
            background: #fff0f1;
            color: #b42336;
        }

        /* RESPONSIVE */
        @media(max-width: 850px) {

            .login-container {
                max-width: 480px;
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .login-banner {
                padding: 35px;
            }

            .brand {
                margin-bottom: 30px;
            }

            .banner-title {
                font-size: 30px;
            }

            .banner-decoration {
                margin-top: 25px;
            }

            .banner-footer {
                margin-top: 25px;
            }

            .login-form-section {
                padding: 45px 35px;
            }
        }

        @media(max-width: 480px) {

            .login-wrapper {
                padding: 15px;
            }

            .login-container {
                border-radius: 19px;
            }

            .login-banner {
                padding: 27px 25px;
            }

            .banner-title {
                font-size: 27px;
            }

            .banner-description {
                font-size: 12px;
            }

            .login-form-section {
                padding: 35px 25px;
            }

            .form-heading {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <div class="bg-circle circle-one"></div>
    <div class="bg-circle circle-two"></div>

    <div class="login-container">

        <!-- ==================================
             BAGIAN KIRI / BANNER
        =================================== -->
        <section class="login-banner">

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
                    PEMILIHAN GENERASI BERPRESTASI
                </div>

                <h1 class="banner-title">
                    Suarakan Pilihanmu,
                    <span>Tentukan Masa Depan.</span>
                </h1>

                <p class="banner-description">
                    Selamat datang di platform voting Kakang Senduk Ponorogo.
                    Gunakan hak pilih Anda untuk mendukung kandidat terbaik
                    yang membawa inspirasi dan perubahan.
                </p>

                <div class="banner-decoration">

                    <div class="decoration-item">
                        <i data-lucide="users"></i>
                        <p>Para Kandidat</p>
                    </div>

                    <div class="decoration-divider"></div>

                    <div class="decoration-item">
                        <i data-lucide="vote"></i>
                        <p>Hak Suara</p>
                    </div>

                    <div class="decoration-divider"></div>

                    <div class="decoration-item">
                        <i data-lucide="award"></i>
                        <p>Generasi Unggul</p>
                    </div>

                </div>

            </div>

            <div class="banner-footer">
                © <?= date('Y') ?> Kakang Senduk Ponorogo. All Rights Reserved.
            </div>

        </section>

        <!-- ==================================
             BAGIAN KANAN / FORM LOGIN
        =================================== -->
        <section class="login-form-section">

            <div class="login-form-content">

                <div class="welcome-icon">
                    <i data-lucide="user-round"></i>
                </div>

                <h2 class="form-heading">
                    Selamat Datang!
                </h2>

                <p class="form-description">
                    Silakan masuk ke akun Anda untuk melanjutkan.
                </p>

                <!-- NOTIFIKASI -->
                <?php if ($pesan !== ""): ?>

                    <div class="alert alert-<?= htmlspecialchars($jenis_pesan) ?>">

                        <i
                            data-lucide="<?= $jenis_pesan === 'success' ? 'check-circle' : 'alert-circle' ?>"
                            style="width:15px; height:15px; vertical-align:middle; margin-right:5px;">
                        </i>

                        <?= htmlspecialchars($pesan) ?>

                    </div>

                <?php endif; ?>

                <!-- INFO AKUN DEMO / ADMIN -->
                <div class="demo-credentials">
                    <p><i data-lucide="info" style="width:14px; height:14px; vertical-align:middle; margin-right:3px;"></i> Info Akun Admin:</p>
                    <ul>
                        <li>Email: <strong>admin@voting.com</strong></li>
                        <li>Password: <strong>admin12345</strong></li>
                    </ul>
                </div>

                <!-- FORM LOGIN -->
                <form method="POST" action="">

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
                            Password
                        </label>

                        <div class="input-wrapper">

                            <i data-lucide="lock-keyhole"></i>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control custom-input"
                                placeholder="Masukkan password Anda"
                                autocomplete="current-password"
                                required>

                        </div>

                    </div>

                    <button type="submit" class="btn btn-login">

                        Masuk Sekarang

                        <i data-lucide="arrow-right"></i>

                    </button>

                </form>

                <div class="register-text">

                    Belum memiliki akun?

                    <a href="register.php">
                        Daftar sekarang
                    </a>

                </div>

                <div class="security-note">

                    <i data-lucide="shield-check"></i>

                    Akun Anda dilindungi dengan sistem keamanan.

                </div>

            </div>

        </section>

    </div>

</div>

<script>
    // Inisialisasi ikon Lucide
    lucide.createIcons();
</script>

</body>
</html>