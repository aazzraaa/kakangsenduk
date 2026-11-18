
<header class="top-navbar">

    <div class="navbar-left">

        <button class="mobile-toggle" id="toggleSidebar">
            <i data-lucide="menu"></i>
        </button>

        <div class="navbar-title">
            <h5><?= $judul_halaman ?? 'Dashboard' ?></h5>
            <small>Admin Panel / <?= $judul_halaman ?? 'Dashboard' ?></small>
        </div>

    </div>

    <div class="navbar-right">

        <div class="profile-avatar">
            <i data-lucide="user-round"></i>
        </div>

        <div class="profile-info">
            <strong>
                <?= htmlspecialchars($_SESSION["nama"] ?? "Administrator") ?>
            </strong>
            <small>Administrator</small>
        </div>

        <a href="../auth/logout.php" class="logout-btn">
            <i data-lucide="log-out"></i>
            <span>Logout</span>
        </a>

    </div>

</header>
