
<aside class="sidebar" id="sidebar">

    <div class="brand">
        <div class="brand-icon">
            <i data-lucide="crown"></i>
        </div>

        <div>
            <h5>KAKANG SENDUK</h5>
            <small>PONOROGO VOTING</small>
        </div>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <nav class="sidebar-menu">

        <a href="dashboard.php"
           class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
        </a>

        <a href="kontestan.php"
           class="<?= basename($_SERVER['PHP_SELF']) === 'kontestan.php' ? 'active' : '' ?>">
            <i data-lucide="users"></i>
            <span>Data Kontestan</span>
        </a>

        <a href="pemilih.php"
           class="<?= basename($_SERVER['PHP_SELF']) === 'pemilih.php' ? 'active' : '' ?>">
            <i data-lucide="user-round"></i>
            <span>Data Pemilih</span>
        </a>

        <a href="hasil-voting.php"
           class="<?= basename($_SERVER['PHP_SELF']) === 'hasil-voting.php' ? 'active' : '' ?>">
            <i data-lucide="chart-no-axes-combined"></i>
            <span>Hasil Voting</span>
        </a>

        <a href="periode.php"
           class="<?= basename($_SERVER['PHP_SELF']) === 'periode.php' ? 'active' : '' ?>">
            <i data-lucide="calendar-days"></i>
            <span>Periode Voting</span>
        </a>

        <!-- MENU UPLOAD QRIS -->
        <a href="upload-qr.php"
           class="<?= basename($_SERVER['PHP_SELF']) === 'upload-qr.php' ? 'active' : '' ?>">
            <i data-lucide="qr-code"></i>
            <span>Upload QRIS</span>
        </a>

        <a href="verifikasi-voting.php"
   class="<?= basename($_SERVER['PHP_SELF']) === 'verifikasi-voting.php' ? 'active' : '' ?>">
    <i data-lucide="clipboard-check"></i>
    <span>Verifikasi Voting</span>
</a>

<a href="user.php"
   class="<?= basename($_SERVER['PHP_SELF']) === 'user.php' ? 'active' : '' ?>">
    <i data-lucide="user-cog"></i>
    <span>Manajemen User</span>
</a>

    </nav>

    <div class="menu-label">PENGATURAN</div>

    <nav class="sidebar-menu">

        <a href="../index.php">
            <i data-lucide="globe"></i>
            <span>Halaman Website</span>
        </a>

        <a href="../auth/logout.php">
            <i data-lucide="log-out"></i>
            <span>Keluar</span>
        </a>

    </nav>

    <div class="sidebar-bottom">
        <i data-lucide="shield-check"></i>

        <div>
            <strong>Admin Panel</strong>
            <p>Sistem Voting Kakang Senduk</p>
        </div>
    </div>

</aside>
