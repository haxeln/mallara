<?php
session_start();

// TAMBAHKAN INI: Definisikan BASE_URL agar Footer Link & Gambar berfungsi
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mallara/');
}

require __DIR__ . '/../config/database.php';

// Ambil ID Produk
if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

 $id = $_GET['id'];
 $query = mysqli_query($conn, "SELECT * FROM products WHERE id='$id'");
 $product = mysqli_fetch_assoc($query);

if (!$product) {
    echo "<script>alert('Produk tidak ditemukan!'); window.location.href='../index.php';</script>";
    exit;
}

// ==========================================
// LOGIKA TOMBOL BACK OTOMATIS
// ==========================================
// Jika user datang dari halaman sebelumnya (misal Search), tombol back akan mengarah ke sana.
// Jika tidak ada referer (misal dibuka tab baru / direct link), akan kembali ke Home.
 $backUrl = "../index.php";
if (isset($_SERVER['HTTP_REFERER'])) {
    $backUrl = $_SERVER['HTTP_REFERER'];
}

// --- LOGIKA HITUNG RATING KESELURUHAN ---
 $ratingQuery = mysqli_query($conn, "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE product_id = '$id'");
 $ratingData = mysqli_fetch_assoc($ratingQuery);

 $avgRating = 0;
 $totalReviews = 0;

if ($ratingData) {
    $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
    $totalReviews = $ratingData['total_reviews'] ?? 0;
}

// Logika untuk menentukan jumlah bintang Full, Half, dan Empty
 $fullStars = floor($avgRating);
 $hasHalfStar = ($avgRating - $fullStars) >= 0.5;
 $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);

// --- LOGIKA AMBIL DATA REVIEW (LIST) ---
 $reviewListQuery = mysqli_query($conn, "SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = '$id' ORDER BY r.id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Detail - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Import Font Poppins untuk Footer -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        :root { --mallara-red: #8b0000; --bg-color: #f9f9f9; }
        
        .navbar { position: fixed !important; top: 0; left: 0; width: 100%; z-index: 1000; background-color: #8b0000; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        body { font-family: 'Segoe UI', sans-serif; background: var(--bg-color); color: #333; margin: 0; padding-top: 80px; display: flex; flex-direction: column; min-height: 100vh; }

        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        
        /* Back Button */
        .back-button-wrapper { position: fixed; top: 90px; left: 20px; z-index: 999; }
        .btn-back {
            background: white; color: #333; width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); transition: 0.3s; font-size: 18px;
        }
        .btn-back:hover { background: var(--mallara-red); color: white; transform: scale(1.1); }

        /* Layout Produk */
        .product-detail-wrapper { display: flex; gap: 40px; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 40px; }
        .product-gallery { flex: 1; max-width: 400px; }
        .product-gallery img { width: 100%; height: auto; border-radius: 10px; object-fit: cover; border: 1px solid #eee; }
        .product-info { flex: 1.2; display: flex; flex-direction: column; justify-content: space-between; }

        .product-category { color: var(--mallara-red); font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .product-title { font-size: 32px; font-family: 'Times New Roman', serif; color: #333; margin: 0 0 15px 0; line-height: 1.2; }
        .product-price { font-size: 28px; color: var(--mallara-red); font-weight: bold; margin-bottom: 10px; }
        
        /* --- STYLE BARU UNTUK RATING KESELURUHAN --- */
        .product-rating { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .product-rating .stars { color: #f39c12; font-size: 18px; }
        .product-rating .rating-count { color: #666; font-size: 14px; }
        .product-rating .rating-count span { font-weight: bold; color: #333; }
        
        .product-description { color: #666; line-height: 1.6; margin-bottom: 20px; }

        /* --- FITUR SIZE & JUMLAH --- */
        .product-meta { background: #fdfdfd; padding: 20px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 20px; }
        .meta-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-label { font-weight: bold; color: #333; }
        
        .size-options { display: flex; gap: 10px; }
        .size-btn { padding: 8px 20px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px; transition: 0.2s; }
        .size-btn.active { background: var(--mallara-red); color: white; border-color: var(--mallara-red); }
        
        .qty-wrapper { display: flex; align-items: center; gap: 10px; }
        .qty-btn { width: 30px; height: 30px; border: 1px solid #ddd; background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 4px; transition: 0.2s; }
        .qty-btn:hover:not(:disabled) { background: #eee; }
        .qty-btn:disabled { opacity: 0.5; cursor: not-allowed; background: #f9f9f9; }
        .qty-input { width: 40px; height: 30px; text-align: center; border: 1px solid #ddd; font-weight: bold; border-radius: 4px; }

        /* Tombol Aksi */
        .action-buttons { display: flex; gap: 15px; }
        .btn-cart { flex: 1; padding: 15px; background: #333; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 14px; transition: 0.3s; }
        .btn-cart:hover { background: #000; }
        .btn-buy { flex: 1; padding: 15px; background: var(--mallara-red); color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 14px; transition: 0.3s; }
        .btn-buy:hover { background: #600000; }

        /* Review Section */
        .review-section { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 40px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        .section-header h3 { margin: 0; color: var(--mallara-red); font-family: 'Times New Roman', serif; font-size: 24px; }

        .review-list { margin-top: 20px; }
        .review-item { padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .review-item:last-child { border-bottom: none; }
        .reviewer-name { font-weight: bold; color: #333; margin-bottom: 5px; }
        .review-date { font-size: 12px; color: #999; margin-bottom: 10px; display: block; }
        .review-rating { color: #f39c12; margin-bottom: 10px; }
        .review-text { color: #555; line-height: 1.6; font-style: italic; }
        
        .empty-review { text-align: center; padding: 40px; color: #888; font-style: italic; }

        @media (max-width: 768px) {
            .product-detail-wrapper { flex-direction: column; }
            .product-gallery { max-width: 100%; }
        }

        /* =========================================
           STYLE FOOTER (DISALIN DARI INDEX)
           ========================================= */
        .footer {
            background: #FFEFD4;
            padding: 70px 0 30px;
            color: #8b0000;
            font-family: 'Poppins', sans-serif;
            margin-top: auto; /* Agar footer selalu di bawah jika konten pendek */
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
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <?php 
        $navPath = __DIR__ . '/../partials/navbar.php';
        if (file_exists($navPath)) { include $navPath; } 
    ?>

    <!-- TOMBOL BACK (MENGGUNAKAN $backUrl DINAMIS) -->
    <div class="back-button-wrapper">
        <a href="<?= $backUrl ?>" class="btn-back" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>

    <div class="container">
        
        <div class="product-detail-wrapper">
            <div class="product-gallery">
                <img src="../assets/img/products/<?= $product['category'] ?>/<?= $product['image'] ?>" alt="<?= $product['name'] ?>">
            </div>
            <div class="product-info">
                <div>
                    <div class="product-category"><?= $product['category'] ?></div>
                    <h1 class="product-title"><?= $product['name'] ?></h1>
                    <div class="product-price">IDR <?= number_format($product['price'], 0, ',', '.') ?></div>
                    
                    <!-- RATING KESELURUHAN -->
                    <div class="product-rating">
                        <div class="stars">
                            <?php for($i=0; $i<$fullStars; $i++): ?>
                                <i class="fas fa-star"></i>
                            <?php endfor; ?>
                            <?php if($hasHalfStar): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php endif; ?>
                            <?php for($i=0; $i<$emptyStars; $i++): ?>
                                <i class="far fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-count">
                            <span><?= $avgRating ?></span> / 5.0 (<?= $totalReviews ?> Review)
                        </div>
                    </div>

                    <div class="product-description">
                        <?= nl2br($product['description']) ?>
                    </div>

                    <!-- FITUR SIZE & JUMLAH -->
                    <div class="product-meta">
                        <div class="meta-row">
                            <span class="meta-label">Choose Size:</span>
                            <div class="size-options">
                                <div class="size-btn" onclick="selectSize(this, 'S')">S</div>
                                <div class="size-btn" onclick="selectSize(this, 'M')">M</div>
                                <div class="size-btn" onclick="selectSize(this, 'L')">L</div>
                                <div class="size-btn" onclick="selectSize(this, 'XL')">XL</div>
                            </div>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Total:</span>
                            <div class="qty-wrapper">
                                <button class="qty-btn" onclick="updateQty(-1)">-</button>
                                <input type="text" id="qtyInput" class="qty-input" value="1" readonly>
                                <button class="qty-btn" onclick="updateQty(1)">+</button>
                            </div>
                            <small style="color:#888;">Stok: <?= $product['stock'] ?></small>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="btn-cart" onclick="processAction('cart')">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                    <button class="btn-buy" onclick="processAction('buy')">
                        Buy Now
                    </button>
                </div>
            </div>
        </div>

        <!-- REVIEW SECTION -->
        <div class="review-section">
            <div class="section-header">
                <h3>Customer Reviews</h3>
            </div>

            <div class="review-list">
                <?php if (mysqli_num_rows($reviewListQuery) > 0): ?>
                    <?php while ($review = mysqli_fetch_assoc($reviewListQuery)): ?>
                        <div class="review-item">
                            <div class="reviewer-name"><?= htmlspecialchars($review['full_name']) ?></div>
                            <span class="review-date"><?= date('d F Y', strtotime($review['created_at'])) ?></span>
                            <div class="review-rating">
                                <?php for($i=1; $i<=$review['rating']; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="review-text">"<?= htmlspecialchars($review['comment']) ?>"</div>
                            <?php if(!empty($review['size'])): ?>
                                <small style="color:#999; display:block; margin-top:5px;">Size: <?= htmlspecialchars($review['size']) ?></small>
                            <?php endif; ?>
                            <?php if(!empty($review['image'])): ?>
                                <div style="margin-top:10px;">
                                    <img src="../assets/img/reviews/<?= $review['image'] ?>" alt="Review Image" style="max-width: 150px; border-radius: 8px; border: 1px solid #ddd;">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-review">
                        <h4>No Reviews</h4>
                        <p>Be the first to leave a review for this product after purchase!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- FOOTER (SAMA PERSIS DENGAN INDEX) -->
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

    <!-- JAVASCRIPT -->
    <script>
        let selectedSize = null; 
        let currentStock = <?= $product['stock'] ?>;
        let productId = <?= $product['id'] ?>;

        function selectSize(btn, size) {
            document.querySelectorAll('.size-btn').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            selectedSize = size;
        }

        function updateQty(change) {
            let input = document.getElementById('qtyInput');
            let val = parseInt(input.value);
            let newVal = val + change;

            if (newVal < 1) newVal = 1;
            if (newVal > currentStock) {
                alert('Stok tidak mencukupi! Maksimal: ' + currentStock);
                newVal = currentStock;
            }
            input.value = newVal;
        }

        function addInput(form, name, value) {
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        function processAction(type) {
            let qty = document.getElementById('qtyInput').value;

            <?php if(!isset($_SESSION['user_id'])): ?>
                alert("Silakan Login terlebih dahulu!");
                window.location.href = "<?= BASE_URL ?>auth/login.php";
                return;
            <?php endif; ?>

            if(!selectedSize) { 
                alert("Pilih ukuran terlebih dahulu!"); 
                return; 
            }

            let form = document.createElement('form');
            form.method = 'POST';

            if (type === 'cart') {
                form.action = '../cart.php';
                addInput(form, 'action', 'add_from_modal');
                addInput(form, 'product_id', productId);
                addInput(form, 'qty', qty);
                addInput(form, 'size', selectedSize);
            } else {
                form.action = '../checkout.php';
                addInput(form, 'action', 'buy_now');
                addInput(form, 'buy_now_id', productId);
                addInput(form, 'buy_now_qty', qty);
                addInput(form, 'buy_now_size', selectedSize);
            }

            document.body.appendChild(form);
            form.submit();
        }
    </script>

</body>
</html>