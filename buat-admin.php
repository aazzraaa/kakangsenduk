
<?php
require_once __DIR__ . "/config/koneksi.php";

// Ubah data admin sesuai keinginan
$nama = "Administrator";
$email = "admin@voting.com";
$no_hp = "081234567890";
$password = "Admin12345";

// Periksa apakah akun admin sudah ada
$cek = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE role = 'admin' LIMIT 1"
);

mysqli_stmt_execute($cek);
$hasil = mysqli_stmt_get_result($cek);

if (mysqli_num_rows($hasil) > 0) {
    die("Akun admin sudah tersedia. Tidak perlu membuat akun lagi.");
}

mysqli_stmt_close($cek);

// Enkripsi password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Simpan akun admin
$query = mysqli_prepare(
    $conn,
    "INSERT INTO users (nama, email, no_hp, password, role)
     VALUES (?, ?, ?, ?, 'admin')"
);

mysqli_stmt_bind_param(
    $query,
    "ssss",
    $nama,
    $email,
    $no_hp,
    $password_hash
);

if (mysqli_stmt_execute($query)) {
    echo "Akun admin berhasil dibuat!<br>";
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password: " . htmlspecialchars($password) . "<br>";
    echo "<br>Segera hapus file buat-admin.php setelah selesai.";
} else {
    echo "Gagal membuat akun admin.";
}

mysqli_stmt_close($query);
?>
