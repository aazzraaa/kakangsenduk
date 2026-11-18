
<?php
session_start();
require_once __DIR__ . "/config/koneksi.php";

date_default_timezone_set('Asia/Jakarta');

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'pemilih') {
    header("Location: admin/dashboard.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Pemilih';

if (empty($_SESSION['csrf_voting'])) {
    $_SESSION['csrf_voting'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_voting'];

/* Ambil periode voting */
$periodeQuery = mysqli_query($conn, "
    SELECT *
    FROM periode_voting
    WHERE status = 'Aktif'
    ORDER BY id DESC
    LIMIT 1
");

$periode = $periodeQuery ? mysqli_fetch_assoc($periodeQuery) : null;

$sekarang = date('Y-m-d H:i:s');

$votingDibuka = $periode
    && $sekarang >= $periode['tanggal_mulai']
    && $sekarang <= $periode['tanggal_selesai'];

/* Ambil pengaturan harga dan QRIS */
$pengaturanQuery = mysqli_query($conn, "
    SELECT *
    FROM pengaturan_vote
    ORDER BY id DESC
    LIMIT 1
");

$pengaturan = $pengaturanQuery
    ? mysqli_fetch_assoc($pengaturanQuery)
    : null;

$hargaPerVote = (float)($pengaturan['harga_per_vote'] ?? 0);
$qrisImage = $pengaturan['qris_image'] ?? '';

$qrisTersedia = $qrisImage !== ''
    && is_file(__DIR__ . '/' . $qrisImage);

/* Ambil kategori yang sudah memiliki pesanan aktif */
$pesananAktif = [];

$stmt = mysqli_prepare($conn, "
    SELECT kategori, status
    FROM pesanan_vote
    WHERE user_id = ?
    AND status NOT IN ('Dibatalkan', 'Ditolak')
");

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $pesananAktif[$row['kategori']] = $row['status'];
}

mysqli_stmt_close($stmt);

/* Ambil seluruh kontestan */
$queryKontestan = mysqli_query($conn, "
    SELECT *
    FROM kontestan
    ORDER BY kategori ASC, nomor_peserta ASC
");

$kontestan = [];

if ($queryKontestan) {
    while ($row = mysqli_fetch_assoc($queryKontestan)) {
        $kontestan[] = $row;
    }
}

$totalKontestan = count($kontestan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar Kontestan | Kakang Senduk Ponorogo</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --maroon: #641b32;
            --dark: #3d1020;
            --gold: #d8b56d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #f8f5f5;
            font-family: 'Poppins', sans-serif;
            color: #30262a;
        }

        .navbar {
            background: white;
            box-shadow: 0 3px 18px rgba(0,0,0,.05);
            padding: 14px 0;
        }

        .brand {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 800;
            font-size: 13px;
        }

        .brand-icon {
            display: inline-grid;
            place-items: center;
            width: 39px;
            height: 39px;
            border-radius: 11px;
            background: var(--maroon);
            color: var(--gold);
            margin-right: 8px;
            vertical-align: middle;
        }

        .btn-maroon {
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: 9px;
            padding: 10px 17px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-maroon:hover {
            background: var(--dark);
            color: white;
        }

        .hero {
            background: linear-gradient(115deg, #35101e, #70253d);
            color: white;
            padding: 42px 0 75px;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 800;
        }

        .hero p {
            color: rgba(255,255,255,.75);
            font-size: 13px;
        }

        .main-content {
            margin-top: -35px;
            padding-bottom: 70px;
        }

        .search-panel {
            background: white;
            padding: 22px;
            border-radius: 15px;
            box-shadow: 0 7px 25px rgba(0,0,0,.06);
            margin-bottom: 25px;
        }

        .search-panel label {
            font-size: 11px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border-radius: 9px;
            font-size: 12px;
            padding: 11px 13px;
        }

        .result-info {
            color: #82767b;
            font-size: 12px;
        }

        .contestant-card {
            background: white;
            border: 1px solid #eee6e8;
            border-radius: 14px;
            overflow: hidden;
            height: 100%;
            box-shadow: 0 5px 20px rgba(0,0,0,.04);
            transition: .2s;
        }

        .contestant-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,.08);
        }

        .contestant-photo {
            height: 230px;
            background: linear-gradient(135deg, #f3e5e9, #e8d3da);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .contestant-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .contestant-body {
            padding: 17px;
        }

        .category-tag {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            background: #f8eaf0;
            color: var(--maroon);
            font-size: 10px;
            font-weight: 700;
        }

        .contestant-body h5 {
            color: var(--dark);
            font-size: 16px;
            font-weight: 700;
            margin: 12px 0 6px;
        }

        .contestant-body p {
            color: #887b80;
            font-size: 11px;
            min-height: 30px;
        }

        .vote-button {
            width: 100%;
            border: none;
            background: var(--maroon);
            color: white;
            border-radius: 8px;
            padding: 11px;
            font-size: 12px;
            font-weight: 600;
        }

        .vote-button:hover {
            background: var(--dark);
        }

        .vote-button:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .quantity-input {
            margin-bottom: 10px;
        }

        .pagination-area {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 30px;
        }

        .pagination-area button {
            border: 1px solid #e5d5da;
            background: white;
            color: var(--maroon);
            min-width: 37px;
            height: 37px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .pagination-area button.active {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }

        .pagination-area button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .empty-box {
            background: white;
            border: 1px dashed #d9c5cb;
            border-radius: 13px;
            text-align: center;
            padding: 45px 20px;
            color: #81767a;
            font-size: 13px;
            grid-column: 1 / -1;
        }

        .note-box {
            background: #fffaf0;
            border: 1px solid #f0dfb8;
            border-radius: 12px;
            padding: 15px;
            font-size: 11px;
            line-height: 1.8;
        }

        footer {
            background: #2c101a;
            color: rgba(255,255,255,.65);
            padding: 22px;
            text-align: center;
            font-size: 11px;
        }

        @media(max-width: 576px) {
            .hero h1 {
                font-size: 29px;
            }

            .contestant-photo {
                height: 190px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="brand">
            <span class="brand-icon"><i data-lucide="crown"></i></span>
            KAKANG SENDUK PONOROGO
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="voting.php" class="btn btn-outline-secondary btn-sm">
                Kembali ke Pesanan
            </a>
            <a href="auth/logout.php" class="btn btn-maroon">
                Keluar
            </a>
        </div>
    </div>
</nav>

<header class="hero">
    <div class="container">
        <div style="font-size:11px;color:var(--gold);letter-spacing:2px;font-weight:700;">
            HALAMAN PEMILIHAN
        </div>

        <h1 class="mt-2">Seluruh Kontestan</h1>

        <p>
            Cari nama kandidat, pilih kontestan favoritmu,
            dan tentukan jumlah suara yang ingin diberikan.
        </p>
    </div>
</header>

<main class="container main-content">

    <div class="search-panel">
        <div class="row g-3 align-items-end">

            <div class="col-md-7">
                <label for="searchKontestan">CARI NAMA KONTestan</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i data-lucide="search" style="width:17px;"></i>
                    </span>

                    <input
                        type="search"
                        id="searchKontestan"
                        class="form-control"
                        placeholder="Ketik nama kandidat..."
                        autocomplete="off"
                    >
                </div>
            </div>

            <div class="col-md-3">
                <label for="filterKategori">FILTER KATEGORI</label>
                <select id="filterKategori" class="form-select">
                    <option value="semua">Semua Kategori</option>
                    <option value="Kakang">Kakang</option>
                    <option value="Senduk">Senduk</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="button" id="resetFilter" class="btn btn-outline-secondary w-100">
                    <i data-lucide="refresh-ccw" style="width:14px;"></i>
                    Reset
                </button>
            </div>

        </div>

        <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
            <span class="result-info" id="hasilPencarian">
                Menampilkan <?= $totalKontestan ?> kontestan.
            </span>

            <span class="result-info">
                Total data: <strong><?= $totalKontestan ?></strong> kontestan
            </span>
        </div>
    </div>

    <div class="note-box mb-4">
        <strong>Informasi voting:</strong><br>
        Setiap akun hanya dapat memiliki satu pesanan aktif untuk kategori Kakang
        dan satu pesanan aktif untuk kategori Senduk. Jika pesanan sebelumnya
        ditolak, kamu dapat membuat ulang pesanan untuk kategori tersebut.
        Suara dihitung setelah pembayaran disetujui admin.
    </div>

    <div class="row g-4" id="daftarKontestan">

        <?php foreach ($kontestan as $peserta): ?>

            <?php
            $foto = trim($peserta['foto'] ?? '');
            $lokasiFoto = "uploads/kontestan/" . basename($foto);
            $fotoAda = $foto !== '' && is_file(__DIR__ . "/" . $lokasiFoto);

            $kategoriPeserta = $peserta['kategori'];
            $sudahMemesan = isset($pesananAktif[$kategoriPeserta]);

            $bolehVote = $votingDibuka
                && $hargaPerVote > 0
                && $qrisTersedia
                && !$sudahMemesan;
            ?>

            <div
                class="col-sm-6 col-lg-4 kandidat-item"
                data-nama="<?= e($peserta['nama']) ?>"
                data-nomor="<?= e($peserta['nomor_peserta']) ?>"
                data-kategori="<?= e($kategoriPeserta) ?>"
                data-instansi="<?= e($peserta['asal_instansi'] ?? '') ?>"
            >

                <div class="contestant-card">

                    <div class="contestant-photo">
                        <?php if ($fotoAda): ?>
                            <img src="<?= e($lokasiFoto) ?>" alt="<?= e($peserta['nama']) ?>">
                        <?php else: ?>
                            <div class="text-center text-secondary">
                                <i data-lucide="user-round" style="width:45px;height:45px;"></i>
                                <div class="small mt-2">Foto belum tersedia</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="contestant-body">

                        <span class="category-tag">
                            <?= e($kategoriPeserta) ?> · <?= e($peserta['nomor_peserta']) ?>
                        </span>

                        <h5><?= e($peserta['nama']) ?></h5>

                        <p>
                            <?= e($peserta['asal_instansi'] ?: 'Asal instansi belum tersedia') ?>
                        </p>

                        <?php if ($hargaPerVote > 0): ?>
                            <div class="small text-secondary mb-2">
                                Harga per suara: <strong><?= rupiah($hargaPerVote) ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($bolehVote): ?>

                            <form method="POST" action="voting.php">

                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="aksi" value="buat_pesanan">
                                <input type="hidden" name="kontestan_id" value="<?= (int)$peserta['id'] ?>">

                                <label class="small fw-semibold mb-1">
                                    Jumlah suara
                                </label>

                                <input
                                    type="number"
                                    name="jumlah_vote"
                                    class="form-control quantity-input"
                                    min="1"
                                    max="1000000"
                                    value="1"
                                    required
                                >

                                <button type="submit" class="vote-button">
                                    <i data-lucide="vote" style="width:15px;vertical-align:middle;"></i>
                                    Vote Kontestan
                                </button>

                            </form>

                        <?php elseif ($sudahMemesan): ?>

                            <button type="button" class="vote-button" disabled>
                                Sudah Memesan Kategori Ini
                            </button>

                            <small class="text-secondary d-block mt-2">
                                Status pesanan: <?= e($pesananAktif[$kategoriPeserta]) ?>
                            </small>

                        <?php elseif (!$votingDibuka): ?>

                            <button type="button" class="vote-button" disabled>
                                Voting Tidak Dibuka
                            </button>

                        <?php elseif (!$qrisTersedia): ?>

                            <button type="button" class="vote-button" disabled>
                                QRIS Belum Tersedia
                            </button>

                        <?php else: ?>

                            <button type="button" class="vote-button" disabled>
                                Harga Voting Belum Diatur
                            </button>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

        <?php endforeach; ?>

        <div class="empty-box" id="tidakAdaHasil" style="display:none;">
            <i data-lucide="search-x" style="width:35px;height:35px;"></i>
            <h6 class="fw-bold mt-3">Kontestan Tidak Ditemukan</h6>
            <p class="mb-0">Coba gunakan kata kunci atau kategori yang berbeda.</p>
        </div>

    </div>

    <div class="pagination-area" id="pagination"></div>

    <div class="text-center result-info mt-3" id="infoHalaman"></div>

</main>

<footer>
    © <?= date('Y') ?> Kakang Senduk Ponorogo — Sistem Informasi Pemilihan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    lucide.createIcons();

    const searchInput = document.getElementById('searchKontestan');
    const filterKategori = document.getElementById('filterKategori');
    const resetFilter = document.getElementById('resetFilter');

    const semuaItem = Array.from(
        document.querySelectorAll('.kandidat-item')
    );

    const pagination = document.getElementById('pagination');
    const infoHalaman = document.getElementById('infoHalaman');
    const hasilPencarian = document.getElementById('hasilPencarian');
    const tidakAdaHasil = document.getElementById('tidakAdaHasil');

    const ITEMS_PER_PAGE = 9;
    let halamanAktif = 1;

    function tampilkanKontestan() {
        const keyword = searchInput.value.toLowerCase().trim();
        const kategori = filterKategori.value;

        const hasilFilter = semuaItem.filter(item => {
            const nama = item.dataset.nama.toLowerCase();
            const nomor = item.dataset.nomor.toLowerCase();
            const instansi = item.dataset.instansi.toLowerCase();
            const kategoriItem = item.dataset.kategori;

            const cocokKata =
                nama.includes(keyword) ||
                nomor.includes(keyword) ||
                instansi.includes(keyword);

            const cocokKategori =
                kategori === 'semua' || kategoriItem === kategori;

            return cocokKata && cocokKategori;
        });

        const totalHasil = hasilFilter.length;
        const totalHalaman = Math.ceil(totalHasil / ITEMS_PER_PAGE);

        if (halamanAktif > totalHalaman) {
            halamanAktif = Math.max(1, totalHalaman);
        }

        const mulai = (halamanAktif - 1) * ITEMS_PER_PAGE;
        const selesai = mulai + ITEMS_PER_PAGE;

        semuaItem.forEach(item => {
            item.style.display = 'none';
        });

        hasilFilter.slice(mulai, selesai).forEach(item => {
            item.style.display = '';
        });

        tidakAdaHasil.style.display = totalHasil === 0 ? 'block' : 'none';

        hasilPencarian.textContent =
            `Ditemukan ${totalHasil} kontestan.`;

        buatPagination(totalHalaman);

        if (totalHasil > 0) {
            infoHalaman.textContent =
                `Halaman ${halamanAktif} dari ${totalHalaman}`;
        } else {
            infoHalaman.textContent = '';
        }
    }

    function buatPagination(totalHalaman) {
        pagination.innerHTML = '';

        if (totalHalaman <= 1) {
            return;
        }

        const tombolSebelumnya = document.createElement('button');
        tombolSebelumnya.textContent = '‹';
        tombolSebelumnya.disabled = halamanAktif === 1;

        tombolSebelumnya.addEventListener('click', () => {
            halamanAktif--;
            tampilkanKontestan();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        pagination.appendChild(tombolSebelumnya);

        for (let i = 1; i <= totalHalaman; i++) {
            const tombol = document.createElement('button');
            tombol.textContent = i;

            if (i === halamanAktif) {
                tombol.classList.add('active');
            }

            tombol.addEventListener('click', () => {
                halamanAktif = i;
                tampilkanKontestan();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            pagination.appendChild(tombol);
        }

        const tombolSelanjutnya = document.createElement('button');
        tombolSelanjutnya.textContent = '›';
        tombolSelanjutnya.disabled = halamanAktif === totalHalaman;

        tombolSelanjutnya.addEventListener('click', () => {
            halamanAktif++;
            tampilkanKontestan();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        pagination.appendChild(tombolSelanjutnya);
    }

    searchInput.addEventListener('input', () => {
        halamanAktif = 1;
        tampilkanKontestan();
    });

    filterKategori.addEventListener('change', () => {
        halamanAktif = 1;
        tampilkanKontestan();
    });

    resetFilter.addEventListener('click', () => {
        searchInput.value = '';
        filterKategori.value = 'semua';
        halamanAktif = 1;
        tampilkanKontestan();
    });

    document.querySelectorAll('.kandidat-item form').forEach(form => {
        form.addEventListener('submit', function(event) {
            const jumlah = parseInt(
                this.querySelector('[name="jumlah_vote"]').value
            ) || 0;

            if (jumlah < 1 || jumlah > 1000000) {
                event.preventDefault();
                alert('Jumlah suara harus antara 1 sampai 1.000.000.');
                return;
            }

            const nama = this.closest('.contestant-card')
                .querySelector('h5').textContent;

            const yakin = confirm(
                'Konfirmasi Voting\n\n' +
                'Kontestan: ' + nama + '\n' +
                'Jumlah suara: ' + jumlah.toLocaleString('id-ID') +
                '\nTotal pembayaran: ' +
                '<?= json_encode(rupiah($hargaPerVote)) ?>'.replace(
                    '<?= json_encode(rupiah($hargaPerVote)) ?>',
                    formatRupiah(jumlah * <?= json_encode($hargaPerVote) ?>)
                ) +
                '\n\nLanjutkan membuat pesanan?'
            );

            if (!yakin) {
                event.preventDefault();
            }
        });
    });

    function formatRupiah(angka) {
        return 'Rp ' + Number(angka).toLocaleString('id-ID');
    }

    tampilkanKontestan();
</script>

</body>
</html>
