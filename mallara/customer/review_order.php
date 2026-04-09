<?php
// --- 1. AKTIFKAN ERROR REPORTING (Untuk melihat pesan error jika ada) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require '../config/database.php';

// --- 2. FITUR OTOMATIS PERBAIKI DATABASE (AUTO-REPAIR SCHEMA) ---
// Kode ini akan mengecek apakah tabel reviews memiliki kolom 'size' dan 'image'.
// Jika tidak ada, kode akan menambahkannya otomatis agar tidak error.

 $column_check = mysqli_query($conn, "SHOW COLUMNS FROM `reviews` LIKE 'size'");
if (mysqli_num_rows($column_check) == 0) {
    // Kolom size tidak ada, coba buat
    $alter_size = mysqli_query($conn, "ALTER TABLE `reviews` ADD COLUMN `size` VARCHAR(20) DEFAULT NULL AFTER `user_id`");
    if (!$alter_size) {
        die("<strong>FATAL ERROR:</strong> Gagal menambahkan kolom 'size' ke database secara otomatis. Error: " . mysqli_error($conn));
    }
}

 $column_check_img = mysqli_query($conn, "SHOW COLUMNS FROM `reviews` LIKE 'image'");
if (mysqli_num_rows($column_check_img) == 0) {
    // Kolom image tidak ada, coba buat
    $alter_img = mysqli_query($conn, "ALTER TABLE `reviews` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL AFTER `comment`");
    if (!$alter_img) {
        // Warning saja untuk image, karena tidak semua review wajib pakai gambar
        // Tapi kita log errornya jika perlu
    }
}
// -------------------------------------------------------------------

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

 $user_id = $_SESSION['user_id'];
 $user_id_safe = intval($user_id);
 $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Ambil data order
 $order_query = mysqli_query($conn, "SELECT * FROM `orders` WHERE `id` = '$order_id' AND `user_id` = '$user_id_safe'");

// Cek error query order
if (!$order_query) {
    die("Error mengambil data pesanan: " . mysqli_error($conn));
}

 $order = mysqli_fetch_assoc($order_query);

if (!$order) {
    echo "<script>alert('Pesanan tidak ditemukan!'); window.location.href='orders.php';</script>";
    exit;
}

// Validasi Status
if (!in_array($order['status'], ['Done', 'Completed'])) {
    echo "<script>alert('Pesanan belum selesai. Anda hanya bisa memberikan ulasan jika pesanan sudah selesai.'); window.location.href='orders.php';</script>";
    exit;
}

// Parse items dari JSON dengan aman (jika null, jadikan array kosong)
 $items = [];
if (!empty($order['items_detail'])) {
    $decoded = json_decode($order['items_detail'], true);
    if (is_array($decoded)) {
        $items = $decoded;
    }
}

// ========== UPLOAD DIRECTORY ==========
 $upload_dir = __DIR__ . '/../assets/img/reviews/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ========== PROSES SUBMIT REVIEW ==========
 $success_msg = '';
 $error_msg = '';
 $last_reviewed_pid = 0;

if (isset($_POST['submit_review'])) {
    $product_id = intval($_POST['product_id']);
    
    // Ambil size, pastikan string dan aman
    $item_size_raw = $_POST['item_size'] ?? ''; 
    $item_size = mysqli_real_escape_string($conn, trim($item_size_raw));
    
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));

    // Validasi Input
    if ($rating < 1 || $rating > 5) {
        $error_msg = "Pilih rating 1-5 bintang.";
    } elseif (empty($comment)) {
        $error_msg = "Tulis ulasan terlebih dahulu.";
    } else {
        $review_image = '';
        
        // Handle File Upload
        if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] == 0) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_name = $_FILES['review_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_ext)) {
                if ($_FILES['review_image']['size'] <= 5 * 1024 * 1024) {
                    // Bersihkan karakter khusus pada size untuk nama file
                    $safe_size_for_filename = preg_replace('/[^A-Za-z0-9\-]/', '', $item_size);
                    $new_name = 'review_uid' . $user_id . '_pid' . $product_id . '_' . $safe_size_for_filename . '_' . time() . '.' . $file_ext;
                    $target_path = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($_FILES['review_image']['tmp_name'], $target_path)) {
                        $review_image = $new_name;
                    } else {
                        $error_msg = "Gagal mengupload gambar (Permission issue atau folder tidak ditemukan).";
                    }
                } else {
                    $error_msg = "Ukuran gambar maksimal 5MB.";
                }
            } else {
                $error_msg = "Format gambar harus JPG, PNG, GIF, atau WebP.";
            }
        }

        if (empty($error_msg)) {
            // Cek apakah review sudah ada (Berdasarkan PID + User + Size)
            // Menggunakan Backticks untuk keamanan nama kolom
            $check_review = mysqli_query($conn, "SELECT * FROM `reviews` WHERE `product_id` = '$product_id' AND `user_id` = '$user_id_safe' AND `size` = '$item_size' LIMIT 1");
            
            if (!$check_review) {
                $error_msg = "Database Error (Cek Review): " . mysqli_error($conn);
            } else {
                $existing_review = mysqli_fetch_assoc($check_review);

                // LOGIKA DIUBAH: Jika review sudah ada, tidak boleh di-update/edit langsung.
                // User harus menghapus dulu jika ingin mengganti.
                if ($existing_review) {
                     $error_msg = "You've already reviewed this product. Please delete the old review first if you'd like to change it.";
                } else {
                    // Logika Insert Review Baru
                    $insert = mysqli_query($conn, "INSERT INTO `reviews` (`product_id`, `user_id`, `size`, `rating`, `comment`, `image`) VALUES ('$product_id', '$user_id_safe', '$item_size', '$rating', '$comment', '$review_image')");
                    
                    if ($insert) {
                        $success_msg = "Review successfully added!";
                        $last_reviewed_pid = $product_id;
                    } else {
                        $error_msg = "Gagal menambahkan ulasan. " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// ========== PROSES HAPUS REVIEW ==========
if (isset($_GET['delete_review'])) {
    $review_id = intval($_GET['delete_review']);
    $check = mysqli_query($conn, "SELECT * FROM `reviews` WHERE `id` = '$review_id' AND `user_id` = '$user_id_safe'");
    if ($check && mysqli_num_rows($check) > 0) {
        $review_data = mysqli_fetch_assoc($check);
        if (!empty($review_data['image']) && file_exists($upload_dir . $review_data['image'])) {
            unlink($upload_dir . $review_data['image']);
        }
        mysqli_query($conn, "DELETE FROM `reviews` WHERE `id` = '$review_id'");
        $success_msg = "Ulasan berhasil dihapus.";
        // Redirect bersih agar URL tidak membawa parameter delete lagi
        echo "<script>window.location.href='review_order.php?order_id=$order_id';</script>";
        exit;
    }
}

// Helper Functions
function getProductName($conn, $pid) {
    $pid = intval($pid);
    $q = mysqli_query($conn, "SELECT `name` FROM `products` WHERE `id` = '$pid' LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $r = mysqli_fetch_assoc($q);
        return $r['name'];
    }
    return 'Produk #' . $pid;
}

function getProductImage($item) {
    $item_image = $item['image'] ?? '';
    // Default ke woman jika kategori tidak jelas
    $item_category = strtolower($item['category'] ?? 'woman'); 
    $folder = ($item_category == 'man') ? 'man' : 'woman';
    
    // Cek path file
    $imgPath = "../assets/img/products/" . $folder . "/" . $item_image;
    
    if (!file_exists($imgPath)) {
        // Fallback 1: Coba folder sebaliknya
        $altFolder = ($folder == 'man') ? 'woman' : 'man';
        $altPath = "../assets/img/products/" . $altFolder . "/" . $item_image;
        if (file_exists($altPath)) { 
            $imgPath = $altPath; 
        } else {
            // Fallback 2: Placeholder
            $imgPath = "https://picsum.photos/seed/" . md5($item_image . $item_category) . "/200/200.jpg";
        }
    }
    return $imgPath;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #8d1a1a; --bg: #f8f9fa; --text: #333; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; color: var(--text); padding-bottom: 60px; }
        .container { max-width: 700px; margin: 30px auto; padding: 0 20px; }

        .page-top { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .back-btn { width: 40px; height: 40px; background: white; border: 1px solid #ddd; border-radius: 50%; color: var(--primary); text-decoration: none; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .back-btn:hover { background: var(--primary); color: white; }
        .page-title { font-size: 24px; color: var(--primary); margin: 0; font-family: 'Times New Roman', serif; }

        .alert-success { background: #d4edda; color: #155724; padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-success .success-title { font-weight: 700; font-size: 15px; margin-bottom: 6px; }
        .alert-success .success-links { margin-top: 10px; padding-top: 10px; border-top: 1px solid #b8d9be; }
        .alert-success .success-links a { display: inline-flex; align-items: center; gap: 6px; color: #155724; font-weight: 600; text-decoration: none; font-size: 13px; margin-right: 10px; padding: 6px 14px; background: #c3e6cb; border-radius: 6px; transition: 0.2s; }
        .alert-success .success-links a:hover { background: #a8d5b5; text-decoration: none; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; display: flex; align-items: center; gap: 10px; }

        .order-summary { background: white; border-radius: 10px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .order-summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px; }
        .order-summary-row .label { color: #888; }
        .order-summary-row .value { font-weight: 600; color: #333; }
        .order-summary-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: var(--primary); padding-top: 10px; border-top: 1px dashed #ddd; margin-top: 8px; }

        .review-card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .review-card-header { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; }
        .review-card-header img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #e0e0e0; }
        .review-card-header .product-info { flex: 1; }
        .review-card-header .product-name { font-size: 16px; font-weight: 700; color: #222; margin-bottom: 4px; }
        .review-card-header .product-price { font-size: 14px; color: var(--primary); font-weight: 600; }

        .item-tags { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
        .item-tag { font-size: 11px; padding: 3px 10px; border-radius: 12px; font-weight: 600; }
        .tag-size { background: #fce4ec; color: #c62828; border: 1px solid #f8bbd0; }
        .tag-qty { background: #e8eaf6; color: #283593; border: 1px solid #c5cae9; }
        .tag-pid { background: #f5f5f5; color: #757575; border: 1px solid #e0e0e0; }

        .review-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 15px; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-locked { background: #e2e3e5; color: #383d41; }

        .existing-review { background: #f9f9f9; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .existing-review .review-stars { color: #f39c12; font-size: 16px; margin-bottom: 8px; }
        .existing-review .review-text { color: #555; font-style: italic; line-height: 1.6; margin-bottom: 10px; }
        .existing-review .review-img { max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 10px; }
        .existing-review .review-time { font-size: 12px; color: #999; }

        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-weight: 600; font-size: 14px; color: #333; margin-bottom: 8px; }
        .form-label .required { color: #dc3545; }

        .star-input { direction: rtl; display: inline-block; }
        .star-input input { display: none; }
        .star-input label { font-size: 32px; color: #ddd; cursor: pointer; padding: 0 3px; transition: 0.15s; }
        .star-input input:checked ~ label, .star-input label:hover, .star-input label:hover ~ label { color: #f39c12; }

        textarea.form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: 'Segoe UI', sans-serif; resize: vertical; min-height: 100px; transition: border-color 0.2s; box-sizing: border-box; }
        textarea.form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(141,26,26,0.1); }

        .file-upload-wrapper { position: relative; border: 2px dashed #ddd; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: 0.2s; background: #fafafa; }
        .file-upload-wrapper:hover { border-color: var(--primary); background: #fff5f5; }
        .file-upload-wrapper i { font-size: 28px; color: #ccc; margin-bottom: 8px; }
        .file-upload-wrapper .upload-text { font-size: 13px; color: #888; }
        .file-upload-wrapper .upload-text strong { color: var(--primary); }
        .file-upload-wrapper input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-preview { margin-top: 10px; }
        .file-preview img { max-width: 150px; max-height: 150px; border-radius: 6px; border: 1px solid #ddd; }
        .file-name { font-size: 12px; color: #666; margin-top: 5px; }

        .btn-submit { display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: #6d1414; }

        .btn-delete-review { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background: white; color: #dc3545; border: 1px solid #dc3545; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-delete-review:hover { background: #dc3545; color: white; }

        .btn-view-product { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background: white; color: var(--primary); border: 1px solid var(--primary); border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-view-product:hover { background: var(--primary); color: white; }

        .btn-skip { display: inline-flex; align-items: center; gap: 6px; padding: 12px 30px; background: white; color: #666; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-skip:hover { background: #f5f5f5; }

        .action-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 5px; }

        .no-products { text-align: center; padding: 40px; color: #888; background: white; border-radius: 10px; border: 1px dashed #ccc; }

        @media (max-width: 600px) {
            .review-card-header { flex-direction: column; align-items: flex-start; }
            .review-card-header img { width: 100%; height: 200px; }
            .action-row { flex-direction: column; align-items: stretch; }
            .btn-submit, .btn-skip, .btn-delete-review, .btn-view-product { justify-content: center; }
        }
    </style>
</head>
<body>

    <?php
        $navPath = __DIR__ . '/../partials/navbar.php';
        if (file_exists($navPath)) { include $navPath; }
        else {
    ?>
    <nav style="background:white; padding:15px 5%; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display:flex; justify-content:space-between; align-items: center; border-bottom: 1px solid #eee;">
        <div style="font-size: 24px; font-weight: bold; color: #333; font-family: 'Times New Roman', serif; letter-spacing: 1px;">
            <a href="index.php" style="text-decoration: none; color: #333;">MALLARA</a>
        </div>
    </nav>
    <?php } ?>

    <div class="container">
        <div class="page-top">
            <a href="orders.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h1 class="page-title">Review</h1>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-success">
                <div class="success-title"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
                <?php if ($last_reviewed_pid > 0): ?>
                    <?php $reviewed_product_name = getProductName($conn, $last_reviewed_pid); ?>
                    <div style="font-size: 13px;">Review for: <strong><?= htmlspecialchars($reviewed_product_name) ?></strong></div>
                    <div class="success-links">
                        <a href="order_detail.php?id=<?= $last_reviewed_pid ?>" target="_blank">
                            <i class="fas fa-external-link-alt"></i> View Product Page.
                        </a>
                        <a href="orders.php"><i class="fas fa-arrow-left"></i> Return to Order</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error_msg ?></span>
            </div>
        <?php endif; ?>

        <div class="order-summary">
            <div class="order-summary-row">
                <span class="label">Invoice</span><span class="value">INV-<?= $order['invoice_code'] ?></span>
            </div>
            <div class="order-summary-row">
                <span class="label">Date</span><span class="value"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="order-summary-row">
                <span class="label">Status</span><span class="value" style="color: #155724; font-weight: 700;">Ordered Completed</span>
            </div>
            <div class="order-summary-total">
                <span>Total</span><span>IDR <?= number_format($order['total'], 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- ========== LOOP ITEM ========== -->
        <?php if (!empty($items) && is_array($items)): ?>
            <?php foreach ($items as $index => $item):
                // Ambil data item dengan aman menggunakan Null Coalescing Operator (??)
                $pid = intval($item['product_id'] ?? $item['id'] ?? 0);
                $item_name = $item['name'] ?? 'Unknown Product';
                $item_price = $item['price'] ?? 0;
                $item_size = $item['size'] ?? 'Standard'; // Default jika size tidak ada di JSON
                $item_qty = $item['quantity'] ?? 1;
                $imgPath = getProductImage($item);

                // Cek Review di Database
                $item_size_safe = mysqli_real_escape_string($conn, $item_size);
                
                // Query dengan backticks
                $check_rev = mysqli_query($conn, "SELECT * FROM `reviews` WHERE `product_id` = '$pid' AND `user_id` = '$user_id_safe' AND `size` = '$item_size_safe' LIMIT 1");
                
                $existing_rev = ($check_rev) ? mysqli_fetch_assoc($check_rev) : null;
                $has_review = ($existing_rev !== null);

                // --- PERUBAHAN LOGIKA: Langsung kunci jika ada review ---
                $is_editable = false;
                $is_locked = false;
                
                if ($has_review) {
                    // Tidak ada hitungan waktu. Langsung kunci.
                    $is_locked = true; 
                }

                $rev_img_path = '';
                if ($has_review && !empty($existing_rev['image'])) {
                    $full = __DIR__ . '/../assets/img/reviews/' . $existing_rev['image'];
                    if (file_exists($full)) { $rev_img_path = '../assets/img/reviews/' . $existing_rev['image']; }
                }

                $uid = $index . '_' . $pid . '_' . preg_replace('/[^A-Za-z0-9]/', '', $item_size);
            ?>
                <div class="review-card">
                    <div class="review-card-header">
                        <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($item_name) ?>">
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($item_name) ?></div>
                            <div class="product-price">IDR <?= number_format($item_price, 0, ',', '.') ?></div>
                            <div class="item-tags">
                                <span class="item-tag tag-size"><i class="fas fa-ruler"></i> Size: <?= htmlspecialchars($item_size) ?></span>
                                <span class="item-tag tag-qty"><i class="fas fa-boxes-stacked"></i> Qty: <?= $item_qty ?></span>
                                <span class="item-tag tag-pid">ID: #<?= $pid ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($has_review && $is_locked): ?>
                        <span class="review-status-badge badge-locked"><i class="fas fa-lock"></i> review Locked</span>
                        <div class="existing-review">
                            <div class="review-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" style="<?= $i <= $existing_rev['rating'] ? '' : 'color:#ddd;' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="review-text">"<?= htmlspecialchars($existing_rev['comment']) ?>"</div>
                            <?php if ($rev_img_path): ?>
                                <img src="<?= $rev_img_path ?>" alt="Review Photo" class="review-img">
                            <?php endif; ?>
                            <div class="review-time"><?= date('d M Y, H:i', strtotime($existing_rev['created_at'])) ?></div>
                        </div>
                        <p style="font-size: 13px; color: #666; margin-bottom: 12px;">
                            <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                            This review cannot be edited. To change your review, please delete this one first.
                        </p>
                        <div class="action-row">
                            <a href="review_order.php?order_id=<?= $order_id ?>&delete_review=<?= $existing_rev['id'] ?>"
                               class="btn-delete-review" onclick="return confirm('Yakin ingin menghapus ulasan ini?')">
                                <i class="fas fa-trash-alt"></i> Delete Review
                            </a>
                            <a href="order_detail.php?id=<?= $pid ?>" class="btn-view-product" target="_blank">
                                <i class="fas fa-external-link-alt"></i> View Product Page.
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Tampilan Form Input Baru (Jika belum ada review) -->
                        <span class="review-status-badge badge-pending"><i class="fas fa-pencil-alt"></i> No Reviews </span>
                        <form action="review_order.php?order_id=<?= $order_id ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                            <input type="hidden" name="item_size" value="<?= htmlspecialchars($item_size) ?>">
                            <div class="form-group">
                                <label class="form-label">Rating <span class="required">*</span></label>
                                <div class="star-input">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star-<?= $uid ?>-<?= $i ?>" name="rating" value="<?= $i ?>" required>
                                        <label for="star-<?= $uid ?>-<?= $i ?>">&#9733;</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Review <span class="required">*</span></label>
                                <textarea name="comment" class="form-control" required placeholder="Write your experience about this product..."></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Image (opsional)</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-camera"></i>
                                    <div class="upload-text"><strong>Click to upload photo</strong><br>JPG, PNG, GIF, WebP (maks 5MB)</div>
                                    <input type="file" name="review_image" accept="image/*" onchange="previewFile(this, 'preview-<?= $uid ?>')">
                                </div>
                                <div class="file-preview" id="preview-<?= $uid ?>"></div>
                            </div>
                            <div class="action-row">
                                <button type="submit" name="submit_review" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Review</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div style="text-align: center; margin-top: 10px;">
                <a href="orders.php" class="btn-skip"><i class="fas fa-arrow-left"></i> Back to Order</a>
            </div>
        <?php else: ?>
            <div class="no-products">
                <i class="fas fa-box-open" style="font-size: 40px; color: #ddd; margin-bottom: 15px; display: block;"></i>
                <h3 style="margin: 0 0 8px; color: #555;">Tidak ada produk untuk diulas</h3>
                <p style="color: #888;">Detail produk pesanan ini tidak tersedia.</p>
                <a href="orders.php" class="btn-skip" style="margin-top: 15px;"><i class="fas fa-arrow-left"></i> kembali ke pesanan</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function previewFile(input, previewId) {
            var preview = document.getElementById(previewId);
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                var file = input.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    preview.innerHTML = '<span style="color:#dc3545;font-size:13px;"><i class="fas fa-exclamation-triangle"></i> File terlalu besar (maks 5MB)</span>';
                    input.value = ''; return;
                }
                var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                if (!allowed.includes(file.type)) {
                    preview.innerHTML = '<span style="color:#dc3545;font-size:13px;"><i class="fas fa-exclamation-triangle"></i> Format tidak didukung</span>';
                    input.value = ''; return;
                }
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width:150px;max-height:150px;border-radius:6px;border:1px solid #ddd;">' +
                        '<div class="file-name">' + file.name + '</div>';
                };
                reader.readAsDataURL(file);
            }
        }
        // Script auto-refresh dihilangkan karena tidak perlu lagi untuk update countdown edit
    </script>
</body>
</html>