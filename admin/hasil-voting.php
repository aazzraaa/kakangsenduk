
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . "/../config/koneksi.php";

// ==========================================
// PROTEKSI ADMIN
// ==========================================
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$judul_halaman = "Hasil Voting";

// ==========================================
// STATISTIK VOTING
// ==========================================
$query_statistik = mysqli_query($conn, "
    SELECT
        COALESCE(SUM(v.jumlah_vote), 0) AS total_suara,

        COALESCE(SUM(
            CASE WHEN k.kategori = 'Kakang'
            THEN v.jumlah_vote ELSE 0 END
        ), 0) AS suara_kakang,

        COALESCE(SUM(
            CASE WHEN k.kategori = 'Senduk'
            THEN v.jumlah_vote ELSE 0 END
        ), 0) AS suara_senduk,

        (SELECT COUNT(*) FROM kontestan) AS total_kontestan

    FROM voting v
    LEFT JOIN kontestan k
        ON k.id = v.kontestan_id
");

if (!$query_statistik) {
    die("Gagal mengambil statistik: " . mysqli_error($conn));
}

$statistik = mysqli_fetch_assoc($query_statistik);

$total_suara = (int)$statistik['total_suara'];
$suara_kakang = (int)$statistik['suara_kakang'];
$suara_senduk = (int)$statistik['suara_senduk'];
$total_kontestan = (int)$statistik['total_kontestan'];

// ==========================================
// FUNGSI RANKING
// ==========================================
function ambilRanking($conn, $kategori)
{
    $stmt = mysqli_prepare($conn, "
        SELECT
            k.id,
            k.nomor_peserta,
            k.nama,
            k.kategori,
            k.foto,
            k.asal_instansi,

            COALESCE(SUM(v.jumlah_vote), 0) AS total_vote

        FROM kontestan k

        LEFT JOIN voting v
            ON v.kontestan_id = k.id

        WHERE k.kategori = ?

        GROUP BY
            k.id,
            k.nomor_peserta,
            k.nama,
            k.kategori,
            k.foto,
            k.asal_instansi

        ORDER BY
            total_vote DESC,
            k.nomor_peserta ASC
    ");

    if (!$stmt) {
        die("Gagal menyiapkan ranking: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "s", $kategori);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $data = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $row['total_vote'] = (int)$row['total_vote'];
        $data[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $data;
}

// ==========================================
// RANKING KAKANG DAN SENDUK
// ==========================================
$ranking_kakang = ambilRanking($conn, 'Kakang');
$ranking_senduk = ambilRanking($conn, 'Senduk');

// ==========================================
// PERSENTASE SUARA
// ==========================================
function hitungPersentase($jumlah, $total)
{
    if ($total <= 0) {
        return 0;
    }

    return round(($jumlah / $total) * 100, 2);
}

foreach ($ranking_kakang as &$k) {
    $k['persentase'] = hitungPersentase(
        $k['total_vote'],
        $suara_kakang
    );
}
unset($k);

foreach ($ranking_senduk as &$s) {
    $s['persentase'] = hitungPersentase(
        $s['total_vote'],
        $suara_senduk
    );
}
unset($s);

// ==========================================
// WAKTU VOTING TERAKHIR
// ==========================================
$query_update = mysqli_query(
    $conn,
    "SELECT MAX(created_at) AS terakhir FROM voting"
);

$data_update = mysqli_fetch_assoc($query_update);

$terakhir_update = !empty($data_update['terakhir'])
    ? date('d/m/Y H:i:s', strtotime($data_update['terakhir']))
    : 'Belum ada aktivitas voting';

// ==========================================
// JUARA SEMENTARA
// ==========================================
$juara_kakang = $ranking_kakang[0] ?? null;
$juara_senduk = $ranking_senduk[0] ?? null;

// ==========================================
// DATA GRAFIK KAKANG
// ==========================================
$label_kakang = [];
$jumlah_kakang = [];

foreach ($ranking_kakang as $k) {
    $label_kakang[] = $k['nomor_peserta'] . ' - ' . $k['nama'];
    $jumlah_kakang[] = $k['total_vote'];
}

// ==========================================
// DATA GRAFIK SENDUK
// ==========================================
$label_senduk = [];
$jumlah_senduk = [];

foreach ($ranking_senduk as $s) {
    $label_senduk[] = $s['nomor_peserta'] . ' - ' . $s['nama'];
    $jumlah_senduk[] = $s['total_vote'];
}

// ==========================================
// PERIODE VOTING AKTIF
// ==========================================
$query_periode = mysqli_query(
    $conn,
    "SELECT *
     FROM periode_voting
     WHERE status = 'Aktif'
     ORDER BY id DESC
     LIMIT 1"
);

$periode_aktif = mysqli_fetch_assoc($query_periode);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hasil Voting | Admin Voting</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .content-area {
            padding: 28px;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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
            border-radius: 15px;
            padding: 21px;
            height: 100%;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 750;
            color: #302632;
            margin: 0;
        }

        .stat-card p {
            color: #888;
            font-size: 13px;
            margin: 5px 0 0;
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
            margin-bottom: 5px;
        }

        .panel-description {
            color: #999;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 320px;
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

        .foto-kontestan {
            width: 48px;
            height: 55px;
            border-radius: 9px;
            object-fit: cover;
            background: #f4edf0;
        }

        .progress {
            height: 8px;
            min-width: 100px;
            background: #f1edf0;
            border-radius: 20px;
        }

        .progress-bar {
            border-radius: 20px;
        }

        .winner-card {
            background: linear-gradient(135deg, #7b1835, #a52c53);
            color: white;
            border-radius: 15px;
            padding: 22px;
            height: 100%;
        }

        .winner-card h5 {
            font-size: 14px;
            opacity: .85;
        }

        .winner-card h3 {
            font-size: 21px;
            font-weight: 750;
            margin: 10px 0 5px;
        }

        .winner-card p {
            font-size: 13px;
            opacity: .85;
            margin: 0;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: #999;
        }

        @media(max-width: 768px) {
            .content-area {
                padding: 18px;
            }

            .chart-container {
                height: 260px;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="main-content">

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <main class="content-area">

        <!-- HEADER -->
        <div class="page-heading">

            <div>
                <h2>Hasil Voting</h2>
                <p>Pantau perolehan suara dan peringkat kontestan secara real-time saat halaman diperbarui.</p>
            </div>

            <button onclick="location.reload()" class="btn btn-outline-secondary">
                <i data-lucide="refresh-cw" style="width:16px;"></i>
                Perbarui Data
            </button>

        </div>

        <!-- PERIODE -->
        <?php if ($periode_aktif): ?>

            <div class="alert alert-success d-flex align-items-center gap-2">
                <i data-lucide="calendar-check"></i>

                <div>
                    <strong><?= htmlspecialchars($periode_aktif['nama_periode']) ?></strong>
                    <br>

                    <small>
                        <?= date('d/m/Y H:i', strtotime($periode_aktif['tanggal_mulai'])) ?>
                        -
                        <?= date('d/m/Y H:i', strtotime($periode_aktif['tanggal_selesai'])) ?>
                    </small>
                </div>
            </div>

        <?php else: ?>

            <div class="alert alert-secondary">
                <i data-lucide="calendar-x" class="me-1"></i>
                Belum ada periode voting yang aktif.
            </div>

        <?php endif; ?>

        <!-- STATISTIK -->
        <div class="row g-3">

            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fce7f3;color:#be185d;">
                        <i data-lucide="vote"></i>
                    </div>
                    <h3><?= number_format($total_suara) ?></h3>
                    <p>Total Seluruh Suara</p>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fce7f3;color:#be185d;">
                        <i data-lucide="user-round"></i>
                    </div>
                    <h3><?= number_format($suara_kakang) ?></h3>
                    <p>Suara Kakang</p>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#ede9fe;color:#6d28d9;">
                        <i data-lucide="users-round"></i>
                    </div>
                    <h3><?= number_format($suara_senduk) ?></h3>
                    <p>Suara Senduk</p>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dcfce7;color:#15803d;">
                        <i data-lucide="award"></i>
                    </div>
                    <h3><?= number_format($total_kontestan) ?></h3>
                    <p>Total Kontestan</p>
                </div>
            </div>

        </div>

        <!-- JUARA SEMENTARA -->
        <div class="row g-3 mt-1">

            <div class="col-md-6">
                <div class="winner-card">
                    <h5>
                        <i data-lucide="crown" class="me-1" style="width:16px;"></i>
                        Peringkat 1 Kakang
                    </h5>

                    <?php if ($juara_kakang && $juara_kakang['total_vote'] > 0): ?>

                        <h3><?= htmlspecialchars($juara_kakang['nama']) ?></h3>

                        <p>
                            <?= htmlspecialchars($juara_kakang['nomor_peserta']) ?>
                            &bull;
                            <?= number_format($juara_kakang['total_vote']) ?> suara
                        </p>

                    <?php else: ?>

                        <h3>Belum Ada Pemenang</h3>
                        <p>Belum terdapat suara untuk kategori Kakang.</p>

                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="winner-card">
                    <h5>
                        <i data-lucide="crown" class="me-1" style="width:16px;"></i>
                        Peringkat 1 Senduk
                    </h5>

                    <?php if ($juara_senduk && $juara_senduk['total_vote'] > 0): ?>

                        <h3><?= htmlspecialchars($juara_senduk['nama']) ?></h3>

                        <p>
                            <?= htmlspecialchars($juara_senduk['nomor_peserta']) ?>
                            &bull;
                            <?= number_format($juara_senduk['total_vote']) ?> suara
                        </p>

                    <?php else: ?>

                        <h3>Belum Ada Pemenang</h3>
                        <p>Belum terdapat suara untuk kategori Senduk.</p>

                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- GRAFIK -->
        <div class="row g-3">

            <div class="col-lg-6">
                <section class="panel-card h-100">

                    <h5 class="panel-title">Grafik Voting Kakang</h5>
                    <p class="panel-description">Perbandingan jumlah suara setiap kontestan Kakang.</p>

                    <?php if (count($ranking_kakang) > 0): ?>

                        <div class="chart-container">
                            <canvas id="chartKakang"></canvas>
                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            <p>Belum ada data kontestan Kakang.</p>
                        </div>

                    <?php endif; ?>

                </section>
            </div>

            <div class="col-lg-6">
                <section class="panel-card h-100">

                    <h5 class="panel-title">Grafik Voting Senduk</h5>
                    <p class="panel-description">Perbandingan jumlah suara setiap kontestan Senduk.</p>

                    <?php if (count($ranking_senduk) > 0): ?>

                        <div class="chart-container">
                            <canvas id="chartSenduk"></canvas>
                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            <p>Belum ada data kontestan Senduk.</p>
                        </div>

                    <?php endif; ?>

                </section>
            </div>

        </div>

        <!-- TABEL RANKING KAKANG -->
        <section class="panel-card">

            <h5 class="panel-title">Ranking Kontestan Kakang</h5>
            <p class="panel-description">Peringkat berdasarkan jumlah suara masing-masing kontestan.</p>

            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>
                        <tr>
                            <th>Peringkat</th>
                            <th>Foto</th>
                            <th>Nomor Peserta</th>
                            <th>Nama Kontestan</th>
                            <th>Instansi</th>
                            <th>Total Suara</th>
                            <th>Persentase</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($ranking_kakang) > 0): ?>

                        <?php $no = 1; ?>

                        <?php foreach ($ranking_kakang as $k): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= $no === 1 ? '🥇' : ($no === 2 ? '🥈' : ($no === 3 ? '🥉' : $no)) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php if (!empty($k['foto'])): ?>
                                        <img
                                            src="../uploads/kontestan/<?= rawurlencode(basename($k['foto'])) ?>"
                                            class="foto-kontestan"
                                            alt="Foto kontestan">
                                    <?php else: ?>
                                        <div class="foto-kontestan d-flex align-items-center justify-content-center">
                                            <i data-lucide="user" style="color:#aaa;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($k['nomor_peserta']) ?></td>

                                <td class="fw-semibold"><?= htmlspecialchars($k['nama']) ?></td>

                                <td><?= htmlspecialchars($k['asal_instansi'] ?? '-') ?></td>

                                <td><strong><?= number_format($k['total_vote']) ?></strong></td>

                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1">
                                            <div
                                                class="progress-bar"
                                                style="width:<?= $k['persentase'] ?>%;background:#be185d;">
                                            </div>
                                        </div>
                                        <small><?= $k['persentase'] ?>%</small>
                                    </div>
                                </td>

                            </tr>

                            <?php $no++; ?>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Belum ada kontestan Kakang.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </section>

        <!-- TABEL RANKING SENDUK -->
        <section class="panel-card">

            <h5 class="panel-title">Ranking Kontestan Senduk</h5>
            <p class="panel-description">Peringkat berdasarkan jumlah suara masing-masing kontestan.</p>

            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>
                        <tr>
                            <th>Peringkat</th>
                            <th>Foto</th>
                            <th>Nomor Peserta</th>
                            <th>Nama Kontestan</th>
                            <th>Instansi</th>
                            <th>Total Suara</th>
                            <th>Persentase</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($ranking_senduk) > 0): ?>

                        <?php $no = 1; ?>

                        <?php foreach ($ranking_senduk as $s): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= $no === 1 ? '🥇' : ($no === 2 ? '🥈' : ($no === 3 ? '🥉' : $no)) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php if (!empty($s['foto'])): ?>
                                        <img
                                            src="../uploads/kontestan/<?= rawurlencode(basename($s['foto'])) ?>"
                                            class="foto-kontestan"
                                            alt="Foto kontestan">
                                    <?php else: ?>
                                        <div class="foto-kontestan d-flex align-items-center justify-content-center">
                                            <i data-lucide="user" style="color:#aaa;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($s['nomor_peserta']) ?></td>

                                <td class="fw-semibold"><?= htmlspecialchars($s['nama']) ?></td>

                                <td><?= htmlspecialchars($s['asal_instansi'] ?? '-') ?></td>

                                <td><strong><?= number_format($s['total_vote']) ?></strong></td>

                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1">
                                            <div
                                                class="progress-bar"
                                                style="width:<?= $s['persentase'] ?>%;background:#7c3aed;">
                                            </div>
                                        </div>
                                        <small><?= $s['persentase'] ?>%</small>
                                    </div>
                                </td>

                            </tr>

                            <?php $no++; ?>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Belum ada kontestan Senduk.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </section>

        <!-- WAKTU UPDATE -->
        <div class="text-end text-muted mt-3">
            <small>
                <i data-lucide="clock" style="width:14px;"></i>
                Suara terakhir masuk: <?= htmlspecialchars($terakhir_update) ?>
            </small>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
lucide.createIcons();

// Data grafik
const labelKakang = <?= json_encode($label_kakang, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const jumlahKakang = <?= json_encode($jumlah_kakang) ?>;

const labelSenduk = <?= json_encode($label_senduk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const jumlahSenduk = <?= json_encode($jumlah_senduk) ?>;

// GRAFIK KAKANG
const canvasKakang = document.getElementById('chartKakang');

if (canvasKakang) {
    new Chart(canvasKakang, {
        type: 'bar',
        data: {
            labels: labelKakang,
            datasets: [{
                label: 'Jumlah Suara',
                data: jumlahKakang,
                backgroundColor: '#be185d',
                borderRadius: 7,
                maxBarThickness: 45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

// GRAFIK SENDUK
const canvasSenduk = document.getElementById('chartSenduk');

if (canvasSenduk) {
    new Chart(canvasSenduk, {
        type: 'bar',
        data: {
            labels: labelSenduk,
            datasets: [{
                label: 'Jumlah Suara',
                data: jumlahSenduk,
                backgroundColor: '#7c3aed',
                borderRadius: 7,
                maxBarThickness: 45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}
</script>

</body>
</html>
