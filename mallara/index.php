<?php
// 1. START SESSION & KONEKSI DATABASE
session_start();

// Cek koneksi database
if (file_exists(__DIR__ . "/config/database.php")) {
    include __DIR__ . "/config/database.php";
} else {
    die("<h1 style='color:red; text-align:center; padding:50px;'>ERROR: File config/database.php tidak ditemukan! Mohon cek struktur folder Anda.</h1>");
}

// 2. DEFINISI BASE URL
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mallara/');
}

// 3. AMBIL DATA PRODUK (TRENDING)
// Hanya mengambil produk yang ditandai sebagai trending oleh Admin
 $queryProducts = mysqli_query($conn, "SELECT * FROM products WHERE is_trending = 1 ORDER BY id DESC LIMIT 8");

// Cek Error Query
if (!$queryProducts) {
    $queryProducts = null; 
    $dbError = mysqli_error($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mallara Store - Timeless Elegance</title>
    
    <!-- CSS UTAMA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- TAMBAHAN STYLE KHUSUS INDEX -->
    <style>
        /* 1. HERO SECTION */
        .hero {
            background: linear-gradient(rgba(139, 0, 0, 0.5), rgba(139, 0, 0, 0.5)), url('<?= BASE_URL ?>assets/img/hero-bg.png'), #8b0000;
            background-size: cover;
            background-position: center;
            height: 500px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
        }
        
        .hero-content h1 { font-family: 'Times New Roman', serif; font-size: 48px; margin: 0; text-transform: uppercase; }
        .hero-content p { font-size: 18px; margin-top: 10px; }
        .btn-hero {
            display: inline-block; padding: 12px 30px; background: white; color: #8b0000;
            text-decoration: none; font-weight: bold; border-radius: 4px; margin: 0 10px;
            transition: 0.3s; text-transform: uppercase;
        }
        .btn-hero:hover { background: #8b0000; color: white; border: 1px solid white; }

        /* 2. PROMO SECTION - BACKGROUND MERAH */
        .promo {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 40px 20px;
            background-color: #8b0000; /* BACKGROUND MERAH */
        }
        .promo-box { width: 48%; position: relative; overflow: hidden; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .promo-box img { width: 100%; height: 250px; object-fit: cover; transition: 0.5s; display: block; }
        .promo-box:hover img { transform: scale(1.05); }

        /* 3. TRENDING PRODUCTS - BACKGROUND MERAH */
        .products { 
            padding: 50px 20px; 
            background-color: #8b0000; /* BACKGROUND MERAH */
            text-align: center; 
            color: white; /* Teks default putih */
        }
        /* Judul Products Putih agar terlihat di merah */
        .products h2 { 
            font-family: 'Times New Roman', serif; 
            color: white; /* DIUBAH JADI PUTIH */
            font-size: 32px; 
            margin-bottom: 40px; 
            text-transform: uppercase;
        }
        
        .grid {
            display: grid;
            /* PERBAIKAN: Ubah 1fr menjadi 240px agar ukuran FIX/TETAP */
            grid-template-columns: repeat(auto-fit, minmax(240px, 240px));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
            justify-content: start; /* Agar rata kiri jika layar lebar */
        }

        .card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: 0.3s;
            position: relative;
            text-align: left;
        }
        .card:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.4); }

        /* Perbaikan Tinggi Gambar agar proporsional dengan kartu yang lebih kecil */
        .card-img-wrapper { height: 220px; overflow: hidden; position: relative; }
        .card img { width: 100%; height: 100%; object-fit: cover; }

        .card-info { padding: 15px; }
        .card h4 { margin: 0 0 10px 0; font-size: 14px; color: #333; height: 35px; overflow: hidden; line-height: 1.2; }
        .card .price { color: #8b0000; font-weight: bold; font-size: 16px; margin-bottom: 15px; display: block; }

        /* Tombol Container untuk Add to Cart & Review */
        .card-actions {
            display: flex;
            gap: 10px;
        }

        .btn-add-cart {
            flex: 1; 
            padding: 8px 5px; 
            background: #333; 
            color: white;
            border: none; 
            cursor: pointer; 
            text-transform: uppercase; 
            font-size: 11px;
            font-weight: bold; 
            transition: 0.3s;
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .btn-add-cart:hover { background: #8b0000; }

        /* Tombol Lihat Ulasan (Visual) */
        .btn-review {
            flex: 1; 
            padding: 8px 5px; 
            background: white; 
            color: #8b0000;
            border: 1px solid #8b0000;
            cursor: pointer; 
            text-transform: uppercase; 
            font-size: 11px;
            font-weight: bold; 
            transition: 0.3s;
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center; gap: 5px;
            text-decoration: none;
        }
        .btn-review:hover { background: #8b0000; color: white; }

        /* 4. WELCOME SECTION - FULL WIDTH IMAGE */
        .welcome { 
            text-align: center; 
            width: 100%; /* Pastikan container penuh */
            line-height: 0; /* Hilangkan spasi di bawah gambar */
        }
        .welcome img { 
            width: 100%; /* FULL WIDTH / LEBAR PENUH */
            height: auto; /* Tinggi menyesuaikan rasio */
            display: block; 
        }

        /* 5. SERVICES  */
        .services { 
            display: flex; 
            justify-content: center; 
            gap: 100px; 
            padding: 90px 90px; 
            background: #8b0000; 
            flex-wrap: wrap; 
        }
        .service-item { text-align: center; flex: 1; min-width: 200px; }
        .service-item img { width: 90px; margin-bottom: 15px; }
        .service-item h4 { color: #ffffff; margin: 10px 0; text-transform: uppercase; }
        .service-item p { color: #ffffff; font-size: 14px; }

        /* 6. BLOG - BACKGROUND MERAH */
        .blog { 
            padding: 60px 20px; 
            background-color: #8b0000; /* BACKGROUND MERAH */
            text-align: center; 
            color: white;
        }
        .blog h2 { 
            font-family: 'Times New Roman', serif; 
            color: white; /* DIUBAH JADI PUTIH */
            font-size: 32px; 
            margin-bottom: 40px; 
            text-transform: uppercase;
        }
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .blog-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.2); text-align: left; }
        .blog-card img { width: 100%; height: 200px; object-fit: cover; }
        .blog-card h4 { padding: 20px; margin: 0; font-size: 16px; color: #333; }

        /* Responsif */
        @media (max-width: 768px) {
            .promo { flex-direction: column; }
            .promo-box { width: 100%; }
            .hero-content h1 { font-size: 32px; }
        }
    </style>
</head>
<body>

    <!-- 1. NAVBAR -->
    <?php 
    $navPath = __DIR__ . "/partials/navbar.php";
    if (file_exists($navPath)) {
        include $navPath; 
    } else {
        echo "<div style='background:red; color:white; padding:10px; text-align:center;'>Navbar tidak ditemukan di: $navPath</div>";
    }
    ?>

    <!-- 2. HERO SECTION -->
    <section class="hero">
        <div class="hero-content">
            <h1>Timeless Elegance</h1>
            <p>Curated pieces for refined style</p>
            <div class="hero-buttons">
                <a href="<?= BASE_URL ?>customer/order_woman.php" class="btn-hero">Woman</a>
                <a href="<?= BASE_URL ?>customer/order_man.php" class="btn-hero">Man</a>
            </div>
        </div>
    </section>

    <!-- 3. PROMO SECTION -->
    <section class="promo">
        <div class="promo-box">
            <img src="<?= BASE_URL ?>assets/img/banner-promo-1.jpg" alt="Promo 1" onerror="this.src='https://picsum.photos/seed/promo1/600/300'">
        </div>
        <div class="promo-box">
            <img src="<?= BASE_URL ?>assets/img/banner-promo-2.jpg" alt="Promo 2" onerror="this.src='https://picsum.photos/seed/promo2/600/300'">
        </div>
    </section>

    <!-- 4. TRENDING PRODUCTS -->
    <section class="products">
        <h2>Trending This Season</h2>

        <?php if(isset($dbError)): ?>
            <p style="color:white; background:rgba(0,0,0,0.5); padding:10px;">Database Error: <?= $dbError ?></p>
        <?php elseif($queryProducts && mysqli_num_rows($queryProducts) > 0): ?>
            
            <!-- Di dalam section .products -->
<div class="grid">
    <?php while($p = mysqli_fetch_assoc($queryProducts)): 
        $localImg = BASE_URL . "assets/img/products/" . $p['category'] . "/" . $p['image'];
        $fallbackImg = "https://picsum.photos/seed/".$p['name']."/300/400";
        $isOutOfStock = ($p['stock'] <= 0);
    ?>
        <div class="card <?= $isOutOfStock ? 'disabled' : '' ?>">
            <div class="card-img-wrapper">
                <?php if($isOutOfStock): ?>
                    <div class="out-of-stock-label">HABIS</div>
                <?php endif; ?>
                <a href="customer/order_detail.php?id=<?= $p['id'] ?>">
                    <img src="<?= $localImg ?>" alt="<?= $p['name'] ?>" onerror="this.src='<?= $fallbackImg ?>'">
                </a>
            </div>
            <div class="card-info">
                <h4><?= $p['name'] ?></h4>
                <span class="price">IDR <?= number_format($p['price'], 0, ',', '.') ?></span>
                
                <div class="card-actions">
                    <!-- Hanya tampilkan tombol jika stok ada -->
                    <?php if(!$isOutOfStock): ?>
                        <button class="btn-add-cart" onclick="addToCart(<?= $p['id'] ?>, <?= $p['stock'] ?>)">
                            <i class="fas fa-shopping-cart"></i> Add
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php endwhile; ?>
</div>

        <?php else: ?>
            <p>Belum ada produk trending yang dipilih admin.</p>
        <?php endif; ?>
    </section>

    <!-- 5. WELCOME SECTION (FULL WIDTH) -->
    <section class="welcome">
        <img src="<?= BASE_URL ?>assets/img/welcome.jpeg" alt="Welcome" onerror="this.src='https://picsum.photos/seed/welcome/1200/400'">
    </section>

    <!-- 6. SERVICES -->
    <section class="services">
        <div class="service-item">
            <img src="<?= BASE_URL ?>assets/img/icon-delivery.png" alt="Delivery" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3692/3692288.png'">
            <h4>FREE DELIVERY</h4>
            <p>Free delivery no hassle</p>
        </div>
        <div class="service-item">
            <img src="<?= BASE_URL ?>assets/img/icon-online-support.png" alt="Support" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3692/3692288.png'">
            <h4>ONLINE SUPPORT</h4>
            <p>Always ready to help</p>
        </div>
        <div class="service-item">
            <img src="<?= BASE_URL ?>assets/img/icon-money-return.png" alt="Return" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3692/3692288.png'">
            <h4>MONEY RETURN</h4>
            <p>Shop with peace of mind</p>
        </div>
    </section>

    <!-- 7. BLOG SECTION -->
    <section class="blog">
        <h2>Our Blog</h2>
        <div class="blog-grid">
            <div class="blog-card">
                <img src="<?= BASE_URL ?>assets/img/our-blog1.png" alt="Blog 1" onerror="this.src='https://picsum.photos/seed/blog1/400/300'">
                <h4>A Simple and Stylish Outfit for Daily Wear</h4>
                <p>Looking neat and stylish doesn't have to be complicated. With a simple cut and the right color, this outfit is perfect for everyday activities without losing its fashionable feel.</p>
            </div>
            <div class="blog-card">
                <img src="<?= BASE_URL ?>assets/img/our-blog2.png" alt="Blog 2" onerror="this.src='https://picsum.photos/seed/blog2/400/300'">
                <h4>How to Mix & Match Styles</h4>
                <p>The right mix and match can make both men and women look more attractive without having to wear too many items. Balance tops, bottoms, and accessories to create a comfortable, neat, and stylish look for a variety of activities.</p>
            </div>
            <div class="blog-card">
                <img src="<?= BASE_URL ?>assets/img/our-blog3.png" alt="Blog 3" onerror="this.src='https://picsum.photos/seed/blog3/400/300'">
                <h4>Neutral Colors Must Have</h4>
                <p>Neutral colors are the key to effortless, easy-to-coordinate style. Besides creating a clean look, these colors are also suitable for any occasion and for any occasion.</p>
            </div>
        </div>
    </section>

    <!-- 8. FOOTER -->
<!-- FOOTER START -->
<style>
.footer {
    background: #FFEFD4;
    padding: 70px 0 30px;
    color: #8b0000;
    font-family: 'Poppins', sans-serif;
}

/* Container dibuat fix width seperti website desktop profesional */
.footer-container {
    max-width: 1200px; /* Lebar desktop ideal */
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1.2fr;
    gap: 50px;
}

/* Typography */
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

/* BRAND AREA */
.footer-brand {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.footer-logo {
    width: 300px;
    margin-bottom: 18px;
}

/* BOTTOM */
.footer-bottom {
    max-width: 1200px;
    margin: 50px auto 0;
    padding-top: 20px;
    border-top: 1px solid rgba(139,0,0,0.2);
    text-align: center;
    font-size: 14px;
}

/* RESPONSIVE */
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
}
/* Tambahkan ini di style index.php */
.out-of-stock-label {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-10deg);
    background: rgba(0,0,0,0.7); color: white; padding: 10px 20px; font-weight: bold;
    font-size: 24px; border: 2px solid white; pointer-events: none; z-index: 10;
}
.card.disabled { opacity: 0.7; pointer-events: none; }
</style>

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
        <p>© <?= date('Y'); ?> Mallara. All Rights Reserved</p>
    </div>

</footer>
<!-- FOOTER END -->
 
    <!-- JAVASCRIPT -->
    <script>
        function addToCart(productId) {
            <?php if(isset($_SESSION['user_id'])): ?>
                window.location.href = "<?= BASE_URL ?>cart.php?action=add&id=" + productId;
            <?php else: ?>
                alert("Silakan Login terlebih dahulu!");
                window.location.href = "<?= BASE_URL ?>auth/login.php";
            <?php endif; ?>
        }

        function addToCart(productId, maxStock) {
            <?php if(isset($_SESSION['user_id'])): ?>
                // Logika penambahan stok nanti di sini
                // Untuk sekarang kita cek simulasi di halaman cart/checkout
                window.location.href = "cart.php?action=add&id=" + productId;
            <?php else: ?>
                alert("Silakan Login terlebih dahulu!");
                window.location.href = "<?= BASE_URL ?>auth/login.php";
            <?php endif; ?>
        }
    </script>

</body>
</html>