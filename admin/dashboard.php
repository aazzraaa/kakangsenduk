
<?php
session_start();

require_once __DIR__ . "/../config/koneksi.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

// Judul halaman untuk navbar
$judul_halaman = "Dashboard";

// Fungsi menghitung data
function hitungData($conn, $sql) {
    $hasil = mysqli_query($conn, $sql);

    if (!$hasil) {
        return 0;
    }

    $data = mysqli_fetch_row($hasil);
    return (int) ($data[0] ?? 0);
}

// Statistik
$total_pemilih = hitungData(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'pemilih'"
);

$total_kontestan = hitungData(
    $conn,
    "SELECT COUNT(*) FROM kontestan"
);

$total_voting = hitungData(
    $conn,
    "SELECT COUNT(*) FROM voting"
);

$total_kakang = hitungData(
    $conn,
    "SELECT COUNT(*) FROM kontestan WHERE kategori = 'Kakang'"
);

$total_senduk = hitungData(
    $conn,
    "SELECT COUNT(*) FROM kontestan WHERE kategori = 'Senduk'"
);

// Periode aktif
$query_periode = mysqli_query(
    $conn,
    "SELECT * FROM periode_voting
     WHERE status = 'Aktif'
     ORDER BY id DESC LIMIT 1"
);

$periode = $query_periode
    ? mysqli_fetch_assoc($query_periode)
    : null;

// Grafik 7 hari terakhir
$labels = [];
$data_grafik = [];

for ($i = 6; $i >= 0; $i--) {
    $tanggal = date("Y-m-d", strtotime("-$i days"));
    $labels[] = date("d M", strtotime($tanggal));
    $data_grafik[$tanggal] = 0;
}

$query_grafik = mysqli_query(
    $conn,
    "SELECT DATE(created_at) AS tanggal, COUNT(*) AS jumlah
     FROM voting
     WHERE created_at >= CURDATE() - INTERVAL 6 DAY
       AND created_at < CURDATE() + INTERVAL 1 DAY
     GROUP BY DATE(created_at)"
);

if ($query_grafik) {
    while ($row = mysqli_fetch_assoc($query_grafik)) {
        if (isset($data_grafik[$row["tanggal"]])) {
            $data_grafik[$row["tanggal"]] = (int) $row["jumlah"];
        }
    }
}

$nilai_grafik = array_values($data_grafik);

// Grafik dan tabel kontestan
$nama_kontestan = [];
$suara_kontestan = [];
$daftar_kontestan = [];

$query_kontestan = mysqli_query(
    $conn,
    "SELECT
        k.id,
        k.nama,
        k.nomor_peserta,
        k.kategori,
        COUNT(v.id) AS total_suara
     FROM kontestan k
     LEFT JOIN voting v ON k.id = v.kontestan_id
     GROUP BY k.id, k.nama, k.nomor_peserta, k.kategori
     ORDER BY total_suara DESC
     LIMIT 5"
);

if ($query_kontestan) {
    while ($row = mysqli_fetch_assoc($query_kontestan)) {
        $nama_kontestan[] = $row["nama"];
        $suara_kontestan[] = (int) $row["total_suara"];
        $daftar_kontestan[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin | Voting Kakang Senduk</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/admin.css">

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<main class="main-content">

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <section class="dashboard-container">

        <div class="welcome-section">
            <div>
                <h4>Selamat Datang, <?= htmlspecialchars($_SESSION["nama"]) ?>!</h4>
                <p>
                    Berikut ringkasan aktivitas sistem voting Kakang Senduk Ponorogo.
                </p>
            </div>

            <div class="date-badge">
                <i data-lucide="calendar" style="width:14px;vertical-align:middle"></i>
                <?= date("d F Y") ?>
            </div>
        </div>

        <!-- STATISTIK -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-title">Total Pemilih</span>
                        <div class="stat-icon icon-purple">
                            <i data-lucide="users"></i>
                        </div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($total_pemilih) ?>
                    </div>

                    <div class="stat-description">
                        Akun pemilih terdaftar
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-title">Total Kontestan</span>
                        <div class="stat-icon icon-pink">
                            <i data-lucide="crown"></i>
                        </div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($total_kontestan) ?>
                    </div>

                    <div class="stat-description">
                        <?= $total_kakang ?> Kakang dan <?= $total_senduk ?> Senduk
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-title">Total Suara</span>
                        <div class="stat-icon icon-blue">
                            <i data-lucide="vote"></i>
                        </div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($total_voting) ?>
                    </div>

                    <div class="stat-description">
                        Total suara masuk
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-title">Status Voting</span>
                        <div class="stat-icon icon-green">
                            <i data-lucide="activity"></i>
                        </div>
                    </div>

                    <div class="stat-value" style="font-size:22px">
                        <?php if ($periode): ?>
                            <span class="text-success">Aktif</span>
                        <?php else: ?>
                            <span class="text-danger">Nonaktif</span>
                        <?php endif; ?>
                    </div>

                    <div class="stat-description">
                        Status periode pemilihan
                    </div>
                </div>
            </div>

        </div>

        <!-- GRAFIK PERKEMBANGAN -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-xl-8">
                <div class="content-card">

                    <div class="card-heading">
                        <div>
                            <h6>Grafik Perkembangan Voting</h6>
                            <p>Jumlah suara masuk selama 7 hari terakhir</p>
                        </div>

                        <span class="category-badge">7 Hari Terakhir</span>
                    </div>

                    <div class="chart-container">
                        <canvas id="votingChart"></canvas>
                    </div>

                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="period-card">

                    <span class="period-status">
                        <?= $periode ? "PERIODE AKTIF" : "BELUM AKTIF" ?>
                    </span>

                    <h6>Periode Pemilihan</h6>

                    <?php if ($periode): ?>

                        <h5 class="fw-bold mt-3">
                            <?= htmlspecialchars($periode["nama_periode"]) ?>
                        </h5>

                        <p class="mt-3 mb-2">Waktu Mulai</p>

                        <h6>
                            <?= date("d M Y, H:i", strtotime($periode["tanggal_mulai"])) ?>
                        </h6>

                        <p class="mt-3 mb-2">Waktu Selesai</p>

                        <h6>
                            <?= date("d M Y, H:i", strtotime($periode["tanggal_selesai"])) ?>
                        </h6>

                    <?php else: ?>

                        <h5 class="fw-bold mt-3">Belum Ada Periode</h5>

                        <p class="mt-3">
                            Belum ada periode voting yang diaktifkan.
                        </p>

                    <?php endif; ?>

                </div>
            </div>

        </div>

        <!-- GRAFIK KONTESAN -->
        <div class="row g-3 mb-4">

            <div class="col-12">
                <div class="content-card">

                    <div class="card-heading">
                        <div>
                            <h6>Perolehan Suara Kontestan</h6>
                            <p>Perbandingan suara lima kontestan teratas</p>
                        </div>

                        <i data-lucide="chart-column" style="color:#641b36"></i>
                    </div>

                    <div class="chart-container-small">
                        <canvas id="candidateChart"></canvas>
                    </div>

                </div>
            </div>

        </div>

        <!-- TABEL TOP KONTESAN -->
        <div class="content-card">

            <div class="card-heading">
                <div>
                    <h6>Kontestan dengan Suara Terbanyak</h6>
                    <p>Lima kontestan dengan perolehan suara tertinggi</p>
                </div>

                <i data-lucide="trophy" style="color:#c28b32"></i>
            </div>

            <div class="table-responsive">

                <table class="table">

                    <thead>
                        <tr>
                            <th>RANK</th>
                            <th>NOMOR</th>
                            <th>NAMA KONTESTAN</th>
                            <th>KATEGORI</th>
                            <th>JUMLAH SUARA</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($daftar_kontestan) > 0): ?>

                            <?php foreach ($daftar_kontestan as $index => $k): ?>

                                <tr>
                                    <td>
                                        <span class="rank-number">
                                            <?= $index + 1 ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($k["nomor_peserta"]) ?>
                                    </td>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($k["nama"]) ?>
                                    </td>

                                    <td>
                                        <span class="category-badge">
                                            <?= htmlspecialchars($k["kategori"]) ?>
                                        </span>
                                    </td>

                                    <td class="fw-bold">
                                        <?= number_format($k["total_suara"]) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty-data">
                                    Belum ada data kontestan.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <div class="footer">
            © <?= date("Y") ?> Voting Kakang Senduk Ponorogo. All Rights Reserved.
        </div>

    </section>

</main>

<script>
lucide.createIcons();

// Sidebar mobile
const sidebar = document.getElementById("sidebar");
const toggleSidebar = document.getElementById("toggleSidebar");

toggleSidebar.addEventListener("click", function () {
    sidebar.classList.toggle("show");
});

// Data grafik dari database
const labelsVoting = <?= json_encode($labels) ?>;
const dataVoting = <?= json_encode($nilai_grafik) ?>;

const namaKontestan = <?= json_encode(
    $nama_kontestan,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const suaraKontestan = <?= json_encode($suara_kontestan) ?>;

// Grafik garis
new Chart(document.getElementById("votingChart"), {
    type: "line",

    data: {
        labels: labelsVoting,

        datasets: [{
            label: "Suara Masuk",
            data: dataVoting,
            borderColor: "#8a3454",
            backgroundColor: "rgba(138,52,84,0.10)",
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: "#641b36",
            pointBorderColor: "#fff",
            pointBorderWidth: 2
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: { display: false }
        },

        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
                grid: { color: "#f1f1f4" }
            },

            x: {
                grid: { display: false }
            }
        }
    }
});

// Grafik batang
new Chart(document.getElementById("candidateChart"), {
    type: "bar",

    data: {
        labels: namaKontestan,

        datasets: [{
            label: "Jumlah Suara",
            data: suaraKontestan,
            backgroundColor: [
                "#641b36",
                "#87405a",
                "#aa6680",
                "#cb8da5",
                "#e5b6c7"
            ],
            borderRadius: 7,
            barThickness: 35
        }]
    },

    options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: { display: false }
        },

        scales: {
            x: {
                beginAtZero: true,
                ticks: { precision: 0 },
                grid: { color: "#f1f1f4" }
            },

            y: {
                grid: { display: false }
            }
        }
    }
});
</script>

</body>
</html>
