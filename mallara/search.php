<?php
// ==========================================
// 1. START SESSION & KONEKSI DATABASE
// ==========================================
session_start();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/mallara/');
}

// Cek koneksi database
if (file_exists(__DIR__ . "/config/database.php")) {
    include __DIR__ . "/config/database.php";
} else {
    die("<h1 style='color:red; text-align:center; padding:50px;'>ERROR: File config/database.php tidak ditemukan!</h1>");
}

// Pastikan koneksi menggunakan UTF-8 agar pencarian akurat (Penting agar 'celana' != 'baju')
if ($conn) {
    mysqli_set_charset($conn, "utf8mb4");
}

// ==========================================
// LOGIKA SESSION AGAR TOMBOL BACK DI HALAMAN DETAIL BERFUNGSI
// ==========================================
// Jika ada keyword pencarian, simpan URL halaman ini ke Session.
// Nanti halaman detail produk akan membaca session ini untuk tombol Back.
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $_SESSION['last_search_url'] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// ==========================================
// 2. LOGIKA PENCARIAN (STRICT & AKURAT)
// ==========================================
// Ambil keyword dan bersihkan spasi
 $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
 $products = [];

if (!empty($keyword)) {
    // === PERUBAHAN LOGIKA (SESUAI KODE REACT) ===
    // Kode React mencari berdasarkan: Name || Category
    // Kita ubah SQL dari Name || Description menjadi: Name || Category
    // Ini agar produk "Baju" yang isinya kata "celana" di deskripsi TIDAK muncul saat cari "celana".
    
    $sql = "SELECT * FROM products 
            WHERE name LIKE ? 
               OR category LIKE ? 
            ORDER BY id DESC";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // Tambahkan wildcard % untuk pencarian parsial
        $searchParam = "%" . $keyword . "%";
        // Kita bind dua parameter string ("ss") untuk Name dan Category
        $stmt->bind_param("ss", $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search: <?= htmlspecialchars($keyword) ?> - Mallara</title>
    
    <!-- CSS UTAMA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- GOOGLE FONTS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        /* =========================================
           STYLE KHUSUS SEARCH PAGE
           ========================================= */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding-bottom: 0; 
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* --- TAMBAHAN: HEADER SEARCH --- */
        .search-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        /* 1. SIMPLE GRID PRODUK */
        .simple-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        /* 2. SIMPLE CARD */
        .simple-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }
        .simple-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }
        .card-img-wrapper {
            height: 220px;
            background: #f9f9f9;
            position: relative;
            overflow: hidden;
        }
        .card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.3s;
        }
        .card-info {
            padding: 20px;
            text-align: center;
        }
        .card-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #222;
            line-height: 1.4;
            min-height: 42px;
        }
        .card-price {
            color: #8b0000;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 15px;
            display: block;
        }
        .btn-simple-add {
            width: 100%;
            padding: 10px;
            background: #222;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-simple-add:hover {
            background: #8b0000;
        }

        /* 3. NOT FOUND DESIGN */
        .not-found-wrapper {
            padding: 60px 20px;
            display: flex;
            justify-content: center;
            flex-grow: 1; 
        }
        .not-found-box {
            background: white;
            padding: 60px 40px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            max-width: 500px;
            width: 100%;
        }
        .not-found-icon {
            font-size: 60px;
            color: #dcdcdc;
            margin-bottom: 20px;
            display: inline-block;
        }
        .not-found-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        .not-found-desc {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        /* --- STYLE TOMBOL BACK BARU (LINGKARAN PUTIH) --- */
        .btn-back-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background-color: white;
            color: #333; /* Ikon Hitam */
            border-radius: 50%; /* Membuatnya lingkaran */
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); /* Bayangan agar terlihat di bg abu */
            transition: 0.3s;
            font-size: 20px;
        }
        
        .btn-back-circle:hover {
            background-color: #8b0000; /* Berubah jadi Merah saat hover */
            color: white; /* Ikon jadi Putih saat hover */
            transform: translateX(-5px);
            box-shadow: 0 6px 15px rgba(139, 0, 0, 0.3);
        }

        /* Helpers */
        .out-of-stock-label {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-10deg);
            background: rgba(0,0,0,0.7); color: white; padding: 8px 15px; font-weight: bold;
            font-size: 18px; border: 2px solid white; pointer-events: none; z-index: 5;
        }
        .disabled { opacity: 0.7; pointer-events: none; }

        /* =========================================
           FOOTER STYLE (DARI INDEX.PHP)
           ========================================= */
        .footer {
            background: #FFEFD4;
            padding: 70px 0 30px;
            color: #8b0000;
            font-family: 'Poppins', sans-serif;
            margin-top: auto; 
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1.2fr;
            gap: 50px;
        }

        .footer h2 {
            font-family: 'Times New Roman', serif;
            font-size: 28px;
            margin: 0;
            letter-spacing: 2px;
        }

        .footer h4 {
            margin-bottom: 18px;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .footer p {
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 10px;
        }

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer ul li {
            margin-bottom: 10px;
        }

        .footer ul li a {
            text-decoration: none;
            color: #8b0000;
            font-size: 14px;
            transition: 0.3s;
        }

        .footer ul li a:hover {
            opacity: 0.7;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .footer-logo {
            width: 300px;
            margin-bottom: 18px;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 50px auto 0;
            padding-top: 20px;
            border-top: 1px solid rgba(139,0,0,0.2);
            text-align: center;
            font-size: 14px;
        }

        @media (max-width: 992px) {
            .footer-container {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
        }

        @media (max-width: 600px) {
            .footer-container {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .footer-brand {
                align-items: center;
            }
            .search-header {
                flex-direction: column;
                align-items: flex-start; /* Di mobile tetap rata kiri */
            }
        }
    </style>
</head>
<body>

    <!-- 1. NAVBAR -->
    <?php 
    $navPath = __DIR__ . "/partials/navbar.php";
    if (file_exists($navPath)) {
        include $navPath; 
    }
    ?>

    <!-- 2. KONTEN UTAMA -->
    <main>
        <?php if (empty($keyword)): ?>
            <!-- Jika halaman dibuka langsung tanpa keyword -->
            <div class="not-found-wrapper">
                <div class="not-found-box">
                    <i class="fas fa-search not-found-icon"></i>
                    <h3 class="not-found-title">Belum ada kata kunci</h3>
                    <p class="not-found-desc">Silakan gunakan kolom pencarian di bagian atas untuk menemukan produk.</p>
                    <!-- Tombol Back (Lingkaran Putih) -->
                    <div style="margin-top: 20px; display: flex; justify-content: center;">
                        <a href="<?= BASE_URL ?>index.php" class="btn-back-circle">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                </div>
            </div>

        <?php elseif (count($products) > 0): ?>
            <!-- JIKA ADA BARANG: TAMPILKAN GRID -->
            <div class="container">
                <!-- Header Search (Tombol Back Lingkaran) -->
                <div class="search-header">
                    <!-- Tombol Back to Home (Lingkaran Putih) -->
                    <a href="<?= BASE_URL ?>index.php" class="btn-back-circle">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>

                <!-- Grid Produk -->
                <div class="simple-grid">
                    <?php foreach ($products as $p): 
                        $localImg = BASE_URL . "assets/img/products/" . $p['category'] . "/" . $p['image'];
                        $fallbackImg = "https://picsum.photos/seed/".$p['name']."/300/400";
                        $isOutOfStock = ($p['stock'] <= 0);
                    ?>
                        <div class="simple-card">
                            <div class="card-img-wrapper">
                                <?php if($isOutOfStock): ?>
                                    <div class="out-of-stock-label">HABIS</div>
                                <?php endif; ?>
                                <a href="customer/order_detail.php?id=<?= $p['id'] ?>">
                                    <img src="<?= $localImg ?>" alt="<?= $p['name'] ?>" onerror="this.src='<?= $fallbackImg ?>'">
                                </a>
                            </div>
                            <div class="card-info">
                                <a href="customer/order_detail.php?id=<?= $p['id'] ?>" style="text-decoration:none; color:inherit;">
                                    <div class="card-title"><?= $p['name'] ?></div>
                                </a>
                                <div class="card-price">IDR <?= number_format($p['price'], 0, ',', '.') ?></div>
                                
                                <?php if(!$isOutOfStock): ?>
                                    <button class="btn-simple-add" onclick="addToCart(<?= $p['id'] ?>, <?= $p['stock'] ?>)">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- JIKA TIDAK ADA BARANG (count == 0): TAMPILKAN NOT FOUND -->
            <div class="not-found-wrapper">
                <div class="not-found-box">
                    <i class="far fa-sad-tear not-found-icon"></i>
                    <h3 class="not-found-title">Produk Tidak Ditemukan</h3>
                    <p class="not-found-desc">
                        Maaf, kami tidak bisa menemukan produk dengan kata kunci <strong>"<?= htmlspecialchars($keyword) ?>"</strong>.<br>
                        Coba cari dengan kata kunci lain.
                    </p>
                    <!-- Tombol Back (Lingkaran Putih) -->
                    <div style="margin-top: 20px; display: flex; justify-content: center;">
                        <a href="<?= BASE_URL ?>index.php" class="btn-back-circle">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- 3. FOOTER LENGKAP (DARI INDEX.PHP) -->
    <footer class="footer">
        <div class="footer-container">
            <!-- BRAND -->
            <div class="footer-brand">
                <img src="<?= BASE_URL ?>assets/img/logo footer/logo-footerr.png"
                     alt="Mallara Logo"
                     class="footer-logo"
                     onerror="this.style.display='none'">
            </div>

            <!-- SHOP -->
            <div>
                <h4>SHOP</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>customer/order_woman.php">Women</a></li>
                    <li><a href="<?= BASE_URL ?>customer/order_man.php">Men</a></li>
                    <li><a href="#">Tops</a></li>
                </ul>
            </div>

            <!-- COMPANY -->
            <div>
                <h4>COMPANY</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Delivery</a></li>
                    <li><a href="#">Collection</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>

            <!-- HELP -->
            <div>
                <h4>HELP</h4>
                <ul>
                    <li><a href="#">Customer Service</a></li>
                    <li><a href="#">Size Guide</a></li>
                    <li><a href="#">Shipping Information</a></li>
                    <li><a href="#">Returns & Exchanges</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>

            <!-- FOLLOW US -->
            <div>
                <h4>FOLLOW US</h4>
                <p>Stay connected and get the latest updates</p>
                <p>
                    <i class="fab fa-instagram"></i>
                    @mallara.officialstore
                </p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y'); ?> Mallara. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- JAVASCRIPT -->
    <script>
        // 1. Fungsi Add to Cart
        function addToCart(productId, maxStock) {
            <?php if(isset($_SESSION['user_id'])): ?>
                window.location.href = "<?= BASE_URL ?>cart.php?action=add&id=" + productId;
            <?php else: ?>
                alert("Silakan Login terlebih dahulu!");
                window.location.href = "<?= BASE_URL ?>auth/login.php";
            <?php endif; ?>
        }

        // 2. Script Khusus: Menjaga Keyword di Search Bar
        // Ini membuat tulisan di navbar tidak hilang setelah halaman search dimuat
        document.addEventListener("DOMContentLoaded", function() {
            var searchInput = document.querySelector('input[name="q"]');
            if(searchInput) {
                // Masukkan keyword PHP ke dalam value input HTML
                searchInput.value = "<?= htmlspecialchars($keyword) ?>";
            }
        });
    </script>

</body>
</html>