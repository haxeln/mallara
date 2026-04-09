<?php
session_start();

// 1. DEFINISI BASE URL
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mallara/');
}

// 2. KONEKSI DATABASE
require '../config/database.php';

// --- QUERY PRODUK ---
 $query = mysqli_query($conn, "SELECT p.*, COUNT(r.id) as review_count, IFNULL(AVG(r.rating), 0) as rating 
                             FROM products p 
                             LEFT JOIN reviews r ON p.id = r.product_id 
                             WHERE p.category = 'Woman' 
                             GROUP BY p.id 
                             ORDER BY p.id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Women's Collection - Mallara</title>
    
    <!-- CSS UTAMA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { 
            --primary: #8d1a1a; 
            --bg: #f4f4f4; 
            --modal-bg: #ffc0cb; 
            --footer-bg: #FFEFD4; 
            --footer-text: #8b0000;
            --star-color: #f1c40f;
        }
        
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: var(--bg); 
            margin: 0; 
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Container */
        .container { 
            max-width: 1200px; 
            margin: 40px auto 50px auto; 
            padding: 0 20px; 
            width: 100%;
            box-sizing: border-box;
        }
        
        .page-title { 
            text-align: center; 
            color: var(--primary); 
            font-family: 'Times New Roman', serif; 
            margin-bottom: 40px; 
            font-size: 32px;
        }
        
        /* Grid Layout */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 30px;
            justify-content: center;
        }

        /* CARD STYLE */
        .card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
            transition: transform 0.3s;
            display: flex;
            flex-direction: column;
            cursor: pointer; 
        }
        .card:hover { transform: translateY(-8px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }

        .card-img-top { 
            width: 100%; 
            height: 320px; 
            object-fit: contain; 
            background-color: white; 
            display: block;
            padding: 10px; 
            box-sizing: border-box;
        }
        
        .card-body { 
            padding: 20px; 
            text-align: center; 
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .card-title { 
            margin: 0 0 10px; 
            font-size: 15px; 
            font-weight: 600; 
            color: #333;
            line-height: 1.4;
            min-height: 42px; 
        }

        .card-rating { color: var(--star-color); font-size: 12px; margin-bottom: 5px; }
        .card-review-count { font-size: 11px; color: #888; margin-bottom: 10px; }
        .price-text {
            color: var(--primary);
            font-weight: bold;
            font-size: 18px;
            display: block;
            margin-bottom: 15px;
        }

        /* --- FIX PERBAIKAN KLIK CARD --- */
        .action-buttons { 
            position: absolute; 
            top: 15px; right: 15px; 
            display: flex; flex-direction: column; gap: 10px; z-index: 10; 
            pointer-events: none; /* Klik tembus */
        }
        .btn-icon { 
            width: 40px; height: 40px; 
            border-radius: 50%; border: none; 
            display: flex; align-items: center; justify-content: center; 
            cursor: pointer; color: white; 
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
            transition: 0.2s;
            z-index: 20;
            pointer-events: auto; /* Tombol aktif */
        }
        .btn-cart { background-color: var(--primary); }
        .btn-checkout { background-color: #333; }
        .btn-icon:hover { transform: scale(1.1); }

        .out-of-stock-label { 
            position: absolute; 
            top: 50%; left: 50%; 
            transform: translate(-50%, -50%) rotate(-10deg); 
            background: rgba(0,0,0,0.7); color: white; 
            padding: 10px 20px; font-weight: bold; font-size: 24px; 
            border: 2px solid white; pointer-events: none; z-index: 10; 
        }
        .card.disabled { opacity: 0.7; filter: grayscale(100%); cursor: pointer; }

        /* --- MODAL UMUM --- */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; bottom: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: flex-end; justify-content: center; }
        
        .modal-content {
            background-color: var(--modal-bg);
            width: 100%; max-width: 500px; margin: 0 auto;
            border-top-left-radius: 20px; border-top-right-radius: 20px;
            padding: 25px; box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
            position: relative; animation: slideUp 0.3s ease-out;
            
            display: flex;
            flex-direction: column;
            height: 85vh; /* Default height untuk Detail Modal */
            max-height: 90vh;
            overflow: hidden;
        }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

        .close-btn { 
            position: absolute; top: 15px; right: 20px; 
            font-size: 28px; font-weight: bold; color: #555; 
            cursor: pointer; z-index: 50; background: rgba(255,255,255,0.5); 
            width: 35px; height: 35px; border-radius: 50%; text-align: center; line-height: 35px;
        }
        .close-btn:hover { color: #d32f2f; background: rgba(255,255,255,0.8); }

        /* --- PERBAIKAN KHUSUS: MODAL PEMBELIAN (#productModal) --- */
        /* Kita override height agar tidak panjang ke atas */
        #productModal .modal-content {
            height: auto !important; /* Tinggi menyesuaikan isi */
            max-height: 90vh;      /* Batas maksimal layar */
            padding-bottom: 30px;  /* Jarak bawah yang nyaman */
        }

        /* --- MODAL DETAIL (#detailModal) --- */
        /* Detail modal TETAP tinggi agar scroll berfungsi */
        #detailModal .modal-content {
            height: 85vh;
            max-height: 85vh;
        }

        .detail-image-container { 
            width: 100%; 
            flex-shrink: 0; 
            height: 250px; 
            background: #fff; 
            margin-bottom: 20px; 
            border-radius: 15px; 
            overflow: hidden; 
            display: flex; 
            align-items: center; justify-content: center; 
            padding: 20px; 
            box-sizing: border-box;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .detail-image { 
            max-width: 100%; max-height: 100%; object-fit: contain; display: block;
        }
        
        .modal-info {
            flex-grow: 1;
            overflow-y: auto; 
            min-height: 0; 
            padding-right: 5px; 
            padding-bottom: 20px;
            scrollbar-width: thin;
            scrollbar-color: rgba(0,0,0,0.2) transparent;
        }

        .modal-info::-webkit-scrollbar { width: 5px; }
        .modal-info::-webkit-scrollbar-track { background: transparent; }
        .modal-info::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.2); border-radius: 10px; border: 2px solid transparent; background-clip: content-box; }
        .modal-info::-webkit-scrollbar-thumb:hover { background-color: rgba(0,0,0,0.4); }

        .detail-title { font-family: 'Times New Roman', serif; font-size: 24px; color: #333; margin-bottom: 5px; text-align: center; }
        .detail-price { font-size: 18px; color: #000; font-weight: bold; margin-bottom: 10px; text-align: center; }
        .detail-rating { color: var(--star-color); margin-bottom: 15px; font-size: 16px; text-align: center; }
        .detail-rating span { color: #555; font-size: 14px; margin-left: 5px; }
        .detail-desc-title { font-weight: bold; font-size: 15px; margin-bottom: 8px; color: #333; margin-top: 10px; }
        .detail-description { color: #444; line-height: 1.5; font-size: 13px; white-space: pre-wrap; text-align: justify; margin-bottom: 20px; }

        /* --- STYLE BARU UNTUK REVIEW --- */
        .reviews-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed rgba(0,0,0,0.1);
        }
        .review-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
            color: #555;
        }
        .review-user { font-weight: bold; color: #333; }
        .review-text { font-size: 13px; color: #555; margin: 0; line-height: 1.4; }
        .no-reviews { text-align: center; color: #888; font-size: 13px; font-style: italic; padding: 10px; }

        /* --- MODAL PEMBELIAN --- */
        .modal-header { display: flex; gap: 15px; align-items: flex-start; margin-bottom: 20px; flex-shrink: 0; }
        .modal-img { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 2px solid white; }
        .modal-info h3 { margin: 0 0 5px; color: #333; font-size: 16px; }
        .modal-price { color: var(--primary); font-weight: bold; font-size: 18px; }

        .size-row { margin-bottom: 15px; }
        .size-row label { display: block; font-size: 13px; color: #555; margin-bottom: 8px; font-weight: 600; }
        .size-options { display: flex; gap: 10px; justify-content: center; }
        .size-btn { padding: 8px 20px; border: 1px solid white; border-radius: 20px; background: white; cursor: pointer; font-size: 14px; }
        .size-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .qty-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-top: 1px solid rgba(0,0,0,0.1); border-bottom: 1px solid rgba(0,0,0,0.1); padding: 15px 0; }
        .qty-controls { display: flex; align-items: center; gap: 15px; }
        .qty-btn { 
            width: 35px; height: 35px; border-radius: 50%; border: none; 
            background: white; cursor: pointer; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 18px; color: #333; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: 0.2s;
        }
        .qty-btn:hover { background-color: #f0f0f0; }
        .qty-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .qty-input { width: 50px; text-align: center; border: none; font-size: 18px; font-weight: bold; background: transparent; }
        .stock-info { font-size: 12px; color: #555; }

        .btn-action { width: 100%; padding: 15px; border: none; border-radius: 30px; font-size: 16px; font-weight: bold; cursor: pointer; color: white; flex-shrink: 0;}
        .btn-add { background-color: var(--primary); }
        .btn-checkout { background-color: #333; }

        /* Footer Style */
        .footer { margin-top: auto; background: var(--footer-bg); padding: 70px 0 30px; color: var(--footer-text); font-family: 'Poppins', sans-serif; }
        .footer-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1.2fr; gap: 50px; }
        .footer h2 { font-family: 'Times New Roman', serif; font-size: 28px; margin: 0; letter-spacing: 2px; }
        .footer h4 { margin-bottom: 18px; font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .footer p { font-size: 14px; line-height: 1.6; margin: 0 0 10px; }
        .footer ul { list-style: none; padding: 0; margin: 0; }
        .footer ul li { margin-bottom: 10px; }
        .footer ul li a { text-decoration: none; color: var(--footer-text); font-size: 14px; transition: 0.3s; }
        .footer ul li a:hover { opacity: 0.7; }
        .footer-brand { display: flex; flex-direction: column; align-items: flex-start; }
        .footer-logo { width: 300px; margin-bottom: 18px; }
        .footer-bottom { max-width: 1200px; margin: 50px auto 0; padding-top: 20px; border-top: 1px solid rgba(139,0,0,0.2); text-align: center; font-size: 14px; }
        
        /* --- STYLE TAMBAHAN: TOMBOL BACK CIRCULAR --- */
        .btn-back-circle {
            position: fixed;
            top: 90px;
            left: 20px;
            width: 50px;
            height: 50px;
            background-color: white;
            color: #333;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: 0.3s;
            font-size: 20px;
            z-index: 999;
        }
        .btn-back-circle:hover {
            background-color: #8b0000;
            color: white;
            transform: translateX(-5px);
            box-shadow: 0 6px 15px rgba(139, 0, 0, 0.3);
        }

        @media (max-width: 992px) { .footer-container { grid-template-columns: 1fr 1fr; gap: 30px; } }
        @media (max-width: 600px) { .footer-container { grid-template-columns: 1fr; text-align: center; } .footer-brand { align-items: center; } .product-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); } .detail-image-container { height: 200px; } }
    </style>
</head>
<body>

    <!-- 1. NAVBAR -->
    <?php 
    $navPath = __DIR__ . "/../partials/navbar.php";
    if (file_exists($navPath)) {
        include $navPath; 
    } else {
        echo '<div style="background:#8b0000; color:white; padding:15px; text-align:center;">Navbar tidak ditemukan: ' . $navPath . '</div>';
    }
    ?>

    <!-- TAMBAHAN: TOMBOL BACK CIRCULAR -->
    <a href="<?= BASE_URL ?>index.php" class="btn-back-circle" title="Back to Home">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container">
        <h2 class="page-title">Women's Collection</h2>
        
        <div class="product-grid">
            <?php while ($row = mysqli_fetch_assoc($query)): 
                $isOutOfStock = ($row['stock'] <= 0);
                
                // Path gambar
                $imgPath = BASE_URL . 'assets/img/products/woman/' . $row['image'];
                if(!file_exists(str_replace(BASE_URL, '../', $imgPath)) || empty($row['image'])){
                    $imgPath = 'https://picsum.photos/seed/'.urlencode($row['name']).'/300/400';
                }

                // Data Rating & Review Count
                $rating = isset($row['rating']) ? $row['rating'] : 0;
                $reviewCount = isset($row['review_count']) ? $row['review_count'] : 0;
                $description = isset($row['description']) ? $row['description'] : 'Tidak ada deskripsi tersedia untuk produk ini.';

                // --- AMBIL DETAIL REVIEW UNTUK TIAP PRODUK (FIX: JOIN TABEL USERS) ---
                $reviewsList = [];
                // Menggunakan LEFT JOIN agar jika user dihapus, review tetap muncul
                $revQuery = mysqli_query($conn, "SELECT r.*, u.full_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.product_id = " . $row['id'] . " ORDER BY r.id DESC");
                while($rev = mysqli_fetch_assoc($revQuery)){
                    $reviewsList[] = $rev;
                }
                // Encode ke JSON untuk dikirim ke JavaScript
                $reviewsJson = htmlspecialchars(json_encode($reviewsList), ENT_QUOTES, 'UTF-8');
            ?>
                <!-- CARD -->
                <div class="card <?= $isOutOfStock ? 'disabled' : '' ?>" 
                     onclick="openDetailModal(
                        '<?= addslashes(str_replace(array("\r\n","\r","\n"), " ", $row['name'])) ?>', 
                        '<?= $imgPath ?>', 
                        '<?= $row['price'] ?>', 
                        '<?= $rating ?>', 
                        '<?= $reviewCount ?>', 
                        '<?= addslashes(str_replace(array("\r\n","\r","\n"), " ", $description)) ?>',
                        '<?= $reviewsJson ?>'
                     )">
                    
                    <?php if($isOutOfStock): ?>
                        <div class="out-of-stock-label">HABIS</div>
                    <?php endif; ?>

                    <img src="<?= $imgPath ?>" class="card-img-top" alt="<?= $row['name'] ?>">
                    
                    <div class="card-body">
                        <div class="card-title"><?= $row['name'] ?></div>
                        <div class="card-rating">
                            <?php 
                            for($i=1; $i<=5; $i++) {
                                if($i <= round($rating)) echo '<i class="fas fa-star"></i>';
                                else echo '<i class="far fa-star"></i>';
                            }
                            ?>
                        </div>
                        <div class="card-review-count"><?= $reviewCount ?> Reviews</div>
                        <span class="price-text">IDR <?= number_format($row['price'], 0, ',', '.') ?></span>
                    </div>

                    <?php if(!$isOutOfStock): ?>
                    <div class="action-buttons">
                        <button class="btn-icon btn-cart" onclick="event.stopPropagation(); openModal('cart', <?= $row['id'] ?>, '<?= addslashes(str_replace(array("\r\n","\r","\n"), " ", $row['name'])) ?>', <?= $row['price'] ?>, <?= $row['stock'] ?>, '<?= $imgPath ?>')">
                            <i class="fas fa-shopping-cart"></i>
                        </button>
                        <button class="btn-icon btn-checkout" onclick="event.stopPropagation(); openModal('checkout', <?= $row['id'] ?>, '<?= addslashes(str_replace(array("\r\n","\r","\n"), " ", $row['name'])) ?>', <?= $row['price'] ?>, <?= $row['stock'] ?>, '<?= $imgPath ?>')">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- 1. MODAL DETAIL PRODUK (DENGAN REVIEW) -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeDetailModal()">&times;</span>
            
            <!-- GAMBAR FIXED -->
            <div class="detail-image-container">
                <img id="detailImg" src="" alt="Product" class="detail-image">
            </div>
            
            <!-- AREA YANG BISA DISCROLL (INFO + DESKRIPSI + REVIEW) -->
            <div class="modal-info">
                <h2 id="detailName" class="detail-title">Product Name</h2>
                <div id="detailPrice" class="detail-price">IDR 0</div>
                
                <div class="detail-rating">
                    <span id="detailStars"></span>
                    <span id="detailReviews">(0 Reviews)</span>
                </div>
                
                <div class="detail-desc-title">Product Description</div>
                <p id="detailDesc" class="detail-description"></p>

                <!-- BAGIAN REVIEW -->
                <div class="reviews-section">
                    <div class="detail-desc-title">Customer Reviews</div>
                    <div id="reviewsListContainer">
                        <!-- List review akan dimuat lewat JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. MODAL POP UP PEMBELIAN -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            
            <div class="modal-header">
                <img id="modalImg" src="" alt="Product" class="modal-img">
                <div class="modal-info">
                    <h3 id="modalProductName">Nama Produk</h3>
                    <div class="modal-price" id="modalProductPrice">IDR 0</div>
                    <div class="stock-info" id="stockInfo">Stok: 0</div>
                </div>
            </div>
            
            <div class="size-row">
                <label>Choose Size:</label>
                <div class="size-options">
                    <button class="size-btn" onclick="selectSize(this, 'S')">S</button>
                    <button class="size-btn" onclick="selectSize(this, 'M')">M</button>
                    <button class="size-btn" onclick="selectSize(this, 'L')">L</button>
                    <button class="size-btn" onclick="selectSize(this, 'XL')">XL</button>
                </div>
            </div>

            <div class="qty-row">
                <span>Total Orders:</span>
                <div class="qty-controls">
                    <button class="qty-btn" onclick="updateQty(-1)">-</button>
                    <input type="text" id="qtyInput" class="qty-input" value="1" readonly>
                    <button class="qty-btn" onclick="updateQty(1)">+</button>
                </div>
            </div>

            <div id="modalActionArea">
                <!-- Tombol dinamis via JS -->
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <img src="<?= BASE_URL ?>assets/img/logo footer/logo-footerr.png" alt="Mallara Logo" class="footer-logo" onerror="this.style.display='none'">
            </div>
            <div>
                <h4>SHOP</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>customer/order_woman.php">Women</a></li>
                    <li><a href="<?= BASE_URL ?>customer/order_man.php">Men</a></li>
                    <li><a href="#">Tops</a></li>
                </ul>
            </div>
            <div>
                <h4>COMPANY</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Delivery</a></li>
                    <li><a href="#">Collection</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
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
            <div>
                <h4>FOLLOW US</h4>
                <p>Stay connected and get the latest updates</p>
                <p><i class="fab fa-instagram"></i> @mallara.officialstore</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?= date('Y'); ?> Mallara. All Rights Reserved</p>
        </div>
    </footer>

    <!-- JAVASCRIPT -->
    <script>
        let currentStock = 0;
        let currentProductId = 0; 
        let currentActionType = ''; 

        // --- FUNGSI MODAL DETAIL (UPDATE DITAMBAH PARAMETER REVIEWS) ---
        function openDetailModal(name, img, price, rating, reviews, desc, reviewsData) {
            document.getElementById('detailName').innerText = name;
            document.getElementById('detailImg').src = img;
            document.getElementById('detailPrice').innerText = 'IDR ' + parseInt(price).toLocaleString();
            document.getElementById('detailReviews').innerText = '(' + reviews + ' Reviews)';
            document.getElementById('detailDesc').innerText = desc;
            
            document.getElementById('detailStars').innerHTML = generateStars(rating);
            
            // --- LOGIKA TAMPILKAN REVIEW ---
            let reviewsHtml = '';
            // reviewsData adalah JSON string dari PHP, perlu di-parse
            let reviewsArray = JSON.parse(reviewsData);

            if(reviewsArray && reviewsArray.length > 0) {
                reviewsArray.forEach(rev => {
                    // --- PERBAIKAN DI SINI ---
                    // Prioritaskan full_name yang diambil dari JOIN database
                    let user = rev.full_name || rev.username || rev.user || rev.name || rev.customer_name || 'User'; 
                    
                    // Coba ambil review_text, jika tidak ada coba comment, lalu ulasan, dst.
                    let text = rev.review_text || rev.comment || rev.review || rev.ulasan || rev.content || 'Tidak ada komentar';
                    
                    let date = rev.date || rev.created_at ? (rev.date || rev.created_at).substring(0, 10) : ''; 
                    
                    // Generate bintang kecil per review
                    let stars = '';
                    for(let s=1; s<=5; s++){
                        if(s <= Math.round(rev.rating || 0)) stars += '<i class="fas fa-star" style="font-size:10px; color:#f1c40f"></i> ';
                        else stars += '<i class="far fa-star" style="font-size:10px; color:#f1c40f"></i> ';
                    }

                    reviewsHtml += `
                        <div class="review-item">
                            <div class="review-header">
                                <span class="review-user">${user}</span>
                                <span>${date}</span>
                            </div>
                            <div style="margin-bottom:4px;">${stars}</div>
                            <p class="review-text">${text}</p>
                        </div>
                    `;
                });
            } else {
                reviewsHtml = '<div class="no-reviews">There are no reviews for this product yet..</div>';
            }
            document.getElementById('reviewsListContainer').innerHTML = reviewsHtml;
            
            document.getElementById('detailModal').style.display = 'flex';
        }

        function closeDetailModal() { 
            document.getElementById('detailModal').style.display = 'none'; 
        }

        function generateStars(rating) {
            let html = '';
            for(let i=1; i<=5; i++) {
                if(i <= Math.round(rating)) {
                    html += '<i class="fas fa-star" style="color:var(--star-color)"></i>';
                } else {
                    html += '<i class="far fa-star" style="color:var(--star-color)"></i>';
                }
            }
            return html;
        }

        // --- FUNGSI MODAL PEMBELIAN ---
        function openModal(type, id, name, price, stock, imgUrl) {
            currentProductId = id;
            currentActionType = type;
            
            document.getElementById('modalProductName').innerText = name;
            document.getElementById('modalProductPrice').innerText = 'IDR ' + parseInt(price).toLocaleString();
            document.getElementById('modalImg').src = imgUrl;
            currentStock = parseInt(stock);
            document.getElementById('stockInfo').innerText = 'Sisa Stok: ' + currentStock;
            
            const qtyInput = document.getElementById('qtyInput');
            qtyInput.value = 1;
            
            document.querySelectorAll('.size-btn').forEach(el => el.classList.remove('active'));

            const actionArea = document.getElementById('modalActionArea');
            if (type === 'cart') {
                actionArea.innerHTML = `<button class="btn-action btn-add" onclick="processAction('add')">ADD TO CART</button>`;
            } else {
                actionArea.innerHTML = `<button class="btn-action btn-checkout" onclick="processAction('buy')">CHECKOUT NOW</button>`;
            }
            document.getElementById('productModal').style.display = 'flex';
        }

        function closeModal() { document.getElementById('productModal').style.display = 'none'; }

        function updateQty(change) {
            let input = document.getElementById('qtyInput');
            let currentVal = parseInt(input.value);
            let newVal = currentVal + change;

            if (newVal < 1) newVal = 1;
            if (newVal > currentStock) {
                alert('Stok tidak mencukupi! Maksimal pembelian: ' + currentStock);
                newVal = currentStock;
            }
            input.value = newVal;
        }

        function selectSize(btn, size) {
            document.querySelectorAll('.size-btn').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
        }

        function processAction(actionType) {
            let sizeSelected = document.querySelector('.size-btn.active');
            if (!sizeSelected) { alert('Mohon pilih ukuran terlebih dahulu!'); return; }
            
            let size = sizeSelected.innerText;
            let qty = document.getElementById('qtyInput').value;

            if(qty < 1) { alert('Jumlah tidak valid'); return; }
            if(parseInt(qty) > currentStock) { alert('Stok tidak mencukupi'); return; }

            let form = document.createElement('form');
            form.method = 'POST';
            
            if (currentActionType === 'cart') {
                form.action = '<?= BASE_URL ?>cart.php'; 
                addInput(form, 'product_id', currentProductId);
                addInput(form, 'qty', qty);
                addInput(form, 'size', size);
                addInput(form, 'action', 'add_from_modal'); 
            } else {
                form.action = '<?= BASE_URL ?>checkout.php'; 
                addInput(form, 'buy_now_id', currentProductId);
                addInput(form, 'buy_now_qty', qty);
                addInput(form, 'buy_now_size', size);
                addInput(form, 'action', 'buy_now'); 
            }

            document.body.appendChild(form);
            form.submit();
        }

        function addInput(form, name, value) {
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('productModal')) closeModal();
            if (event.target == document.getElementById('detailModal')) closeDetailModal();
        }
    </script>
</body>
</html>