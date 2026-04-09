<?php 
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mallara/');
}
 $isLoggedIn = isset($_SESSION['user_id']);
?>

<nav class="navbar">
    <!-- CONTAINER UTAMA (LOOP) -->
    <div class="nav-container">
        
        <!-- 1. LOGO (KIRI) -->
        <div class="nav-section nav-logo">
            <a href="<?= BASE_URL ?>index.php">
                <img src="<?= BASE_URL ?>assets/img/logo/logo.png" alt="Mallara">
            </a>
        </div>

        <!-- 2. SEARCH BAR (TENGAH) -->
        <div class="nav-section nav-search">
            <!-- PERBAIKAN: Menambahkan BASE_URL pada action -->
            <form action="<?= BASE_URL ?>search.php" method="GET">
                <input type="text" name="q" placeholder="Search...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <!-- 3. MENU & USER (KANAN) -->
        <div class="nav-section nav-actions">
            
            <!-- Link Home -->
            <a href="<?= BASE_URL ?>index.php" class="nav-link">Home</a>

            <!-- Dropdown Order (Man/Woman) -->
            <div class="dropdown">
                <a href="#" class="nav-link">Order <i class="fas fa-chevron-down" style="font-size:10px;"></i></a>
                <div class="dropdown-menu">
                    <a href="<?= BASE_URL ?>customer/order_woman.php">Woman</a>
                    <a href="<?= BASE_URL ?>customer/order_man.php">Man</a>
                </div>
            </div>

            <!-- Logic: Jika Belum Login -->
            <?php if(!$isLoggedIn): ?>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn-nav btn-login">Login</a>
            <!-- Logic: Jika Sudah Login (Muncul Cart & Profil) -->
            <?php else: ?>
                <a href="<?= BASE_URL ?>cart.php" class="icon-btn" title="Keranjang">
                    <i class="fas fa-shopping-cart"></i>
                </a>
                <a href="<?= BASE_URL ?>profile.php" class="icon-btn" title="Profil">
                    <i class="fas fa-user-circle"></i>
                </a>
                <a href="<?= BASE_URL ?>auth/logout.php" class="btn-nav btn-logout">Logout</a>
            <?php endif; ?>

        </div>

        <!-- Tombol Hamburger untuk Mobile -->
        <div class="hamburger">
            <i class="fas fa-bars"></i>
        </div>

    </div>
</nav>

<script>
    // Script sederhana untuk Toggle Menu di Mobile
    const hamburger = document.querySelector('.hamburger');
    const navContainer = document.querySelector('.nav-container');
    
    if(hamburger){
        hamburger.addEventListener('click', () => {
            navContainer.classList.toggle('active-mobile');
        });
    }
</script>