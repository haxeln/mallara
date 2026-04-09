<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ADMIN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if ($_SESSION['role'] == 'customer') {
    header("Location: ../index.php");
    exit;
}

// --- 2. AMBIL DATA ORDER ---
if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

 $order_id = intval($_GET['id']);
 $order_query = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$order_id'");
 $order = mysqli_fetch_assoc($order_query);

if (!$order) {
    echo "<script>alert('Order not found!'); window.location.href='orders.php';</script>";
    exit;
}

// --- 3. LOGIKA UPDATE STATUS (DINONAKTIFKAN) ---
if (isset($_POST['update_status'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $update = mysqli_query($conn, "UPDATE orders SET status = '$new_status' WHERE id = '$order_id'");
    if ($update) {
        echo "<script>alert('Status updated successfully!'); window.location.href='order_detail.php?id=$order_id';</script>";
    } else {
        echo "<script>alert('Failed to update status: " . mysqli_error($conn) . "');</script>";
    }
}

// --- 4. MAPPING STATUS ---
function getStatusInfo($status) {
    $info = ['label' => $status, 'bg' => '#eee', 'text' => '#333'];
    
    switch($status) {
        case 'Not Paid':
            $info = ['label' => 'Waiting Payment', 'bg' => '#fff3cd', 'text' => '#856404'];
            break;
        case 'Process':
            $info = ['label' => 'Processed', 'bg' => '#d1ecf1', 'text' => '#0c5460'];
            break;
        case 'Delivered':
            $info = ['label' => 'Delivered', 'bg' => '#cce5ff', 'text' => '#004085'];
            break;
        case 'Done':
            $info = ['label' => 'Done', 'bg' => '#d4edda', 'text' => '#155724'];
            break;
        default:
            $info = ['label' => $status, 'bg' => '#eee', 'text' => '#333'];
    }
    return $info;
}

 $statusData = getStatusInfo($order['status']);

// --- 5. LOGIKA TOMBOL BACK DINAMIS (BARU DITAMBAHKAN) ---
// Default link kembali ke orders.php
 $back_link = 'orders.php'; 

// Cek apakah user datang dari halaman sebelumnya
if (isset($_SERVER['HTTP_REFERER'])) {
    // Jika URL sebelumnya mengandung kata 'reports.php', maka ubah linknya
    if (strpos($_SERVER['HTTP_REFERER'], 'reports.php') !== false) {
        $back_link = 'reports.php';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail - Mallara Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- GLOBAL STYLES --- */
        :root {
            --admin-primary: #8d1a1a;
            --admin-bg: #f3f4f6;
            --badge-process: #dc3545;
            --badge-shipped: #ffc107;
            --badge-delivered: #7FBFFF;
            --badge-done: #28a745;
            --text-main: #333;
            --text-muted: #666;
        }

        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--admin-bg);
            display: flex;
            min-height: 100vh;
            color: #333;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px; background-color: var(--admin-primary); color: white;
            position: fixed; top: 0; left: 0; height: 100%;
            display: flex; flex-direction: column; z-index: 1000;
        }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-header h2 { margin: 0; font-family: 'Times New Roman', serif; letter-spacing: 2px; font-size: 28px; }
        .sidebar-header span { font-size: 12px; opacity: 0.7; }
        .sidebar-menu { flex: 1; padding-top: 20px; }
        .menu-item {
            display: flex; align-items: center; padding: 15px 25px;
            color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s;
            border-left: 4px solid transparent; font-size: 15px;
        }
        .menu-item i { width: 25px; text-align: center; margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.15); color: white; border-left-color: #fff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* Main Content Layout */
        .main-content { 
            margin-left: 260px; 
            flex: 1; 
            padding: 30px; 
            width: calc(100% - 260px); 
        }

        /* --- DETAIL PAGE SPECIFIC STYLES --- */
        
        .card { 
            background: white; 
            border-radius: 8px; 
            padding: 30px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
        }

        /* 1. TOMBOL BACK LUAR CARD */
        .btn-back-outside {
            text-decoration: none;
            color: #555;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px; 
            transition: color 0.2s;
        }
        .btn-back-outside:hover {
            color: var(--admin-primary);
        }

        /* 2. HEADER CARD (TITLE KIRI, STATUS KANAN) */
        .card-header {
            display: flex;
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 20px;
        }

        .detail-title {
            margin: 0;
            font-size: 26px;
            color: var(--admin-primary);
            font-weight: 800;
            font-family: 'Segoe UI', sans-serif;
            text-align: left; /* Pastikan rata kiri */
        }

        /* Status Badge */
        .status-badge { 
            padding: 8px 16px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 700; 
            text-transform: uppercase; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .detail-separator {
            border: 0;
            height: 1px;
            background: #eee;
            margin: 0 0 25px 0;
        }

        /* 3. INFO GRID (DIRAPIHKAN SESUAI GAMBAR) */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Dua kolom: Kiri & Kanan */
            gap: 20px;
            background-color: #f8f9fa; /* Background abu-abu terang */
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #eee;
        }

        .info-item {
            text-align: left; /* Semua isi rata kiri */
        }

        .info-item label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7280; /* Abu-abu lebih gelap agar terbaca */
            font-weight: 700;
            margin-bottom: 8px; /* Jarak Label ke Isi */
            letter-spacing: 0.8px;
        }

        .info-item span {
            display: flex;
            align-items: center; /* Icon vertikal tengah terhadap teks */
            gap: 12px; /* Jarak icon ke teks */
            font-size: 15px;
            color: #1f2937;
            font-weight: 600;
        }

        .info-item i {
            color: var(--admin-primary);
            font-size: 16px;
            margin-top: 2px; /* Sedikit adjustment visual icon */
        }
        
        /* Timeline */
        .tracking-timeline {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin: 10px 0 30px 0; 
            position: relative;
        }
        .tracking-timeline::before {
            content: ''; 
            position: absolute; 
            top: 24px; 
            left: 0; 
            right: 0; 
            height: 3px; 
            background: #eee; 
            z-index: 0;
        }
        
        .track-step {
            position: relative; 
            z-index: 1; 
            text-align: center; 
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .track-icon {
            width: 50px; 
            height: 50px; 
            background: white; 
            border: 3px solid #e5e7eb; 
            border-radius: 50%;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 10px; 
            color: #d1d5db; 
            font-size: 18px;
            transition: all 0.3s ease;
        }
        
        .track-label { 
            font-size: 11px; 
            color: #999; 
            font-weight: 700; 
            text-transform: uppercase; 
        }
        
        .track-step.active .track-icon, 
        .track-step.completed .track-icon {
            border-color: var(--admin-primary); 
            background: var(--admin-primary); 
            color: white;
            box-shadow: 0 0 0 4px rgba(141, 26, 26, 0.1);
        }
        
        .track-step.active .track-label, 
        .track-step.completed .track-label { 
            color: var(--admin-primary); 
        }

        /* Table Wrapper */
        .table-responsive {
            overflow-x: auto; 
            border-radius: 4px;
            border: 1px solid #eee;
            margin-bottom: 25px;
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 600px;
        }
        
        th { 
            background-color: #f8f9fa; 
            color: #555;
            font-weight: 600; 
            font-size: 12px; 
            text-transform: uppercase; 
            padding: 15px 20px; 
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        td { 
            padding: 20px; 
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6; 
            font-size: 14px;
        }
        
        tr:last-child td { border-bottom: none; }

        .product-cell { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
        }
        
        /* IMAGE FIX */
        .product-img { 
            width: 150px; 
            height: 150px; 
            object-fit: contain; 
            object-position: center;
            background-color: #fff; 
            border-radius: 8px; 
            border: 1px solid #eee;
        }

        /* Footer Card */
        .card-footer { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding-top: 25px; 
            border-top: 1px solid #eee; 
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .total-price { 
            color: var(--admin-primary); 
            font-size: 24px; 
            font-weight: bold; 
        }
        
        .total-price span {
            font-size: 14px;
            color: #666;
            font-weight: normal;
            margin-right: 10px;
        }

        .btn-track {
            background: white;
            color: #333;
            border: 1px solid #ccc;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-track:hover {
            background: #f5f5f5;
            border-color: #999;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
            
            .card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .info-grid { grid-template-columns: 1fr; gap: 20px; } /* Stack di mobile */
            .detail-title { font-size: 20px; }
            
            .tracking-timeline::before { display: none; }
            .tracking-timeline { flex-direction: column; align-items: flex-start; gap: 20px; margin-left: 10px;}
            .track-step { flex-direction: row; gap: 15px; width: 100%; text-align: left; }
            .track-icon { margin-bottom: 0; width: 32px; height: 32px; font-size: 14px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>MALLARA</h2>
            <span>ADMIN PANEL</span>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="products.php" class="menu-item">
                <i class="fas fa-box"></i> <span>Products</span>
            </a>
            <a href="trending.php" class="menu-item">
                <i class="fas fa-fire"></i> <span>Trending</span>
            </a>
            <a href="orders.php" class="menu-item">
                <i class="fas fa-shopping-bag"></i> <span>Transactions</span>
            </a>
            <a href="users.php" class="menu-item">
                <i class="fas fa-users"></i> <span>Users</span>
            </a>
            <a href="reports.php" class="menu-item active">
                <i class="fas fa-chart-line"></i> <span>Reports</span>
            </a>
            <a href="backup_data.php" class="menu-item">
                <i class="fas fa-database"></i> <span>Backup Data</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="menu-item" style="color: #ffcccc;">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="main-content">
        
        <!-- TOMBOL BACK DI LUAR CARD (MENGGUNAKAN VARIABEL $back_link) -->
        <a href="<?= $back_link ?>" class="btn-back-outside">
            <i class="fas fa-arrow-left"></i> Back to Transaction
        </a>

        <!-- KARTU KONTEN UTAMA -->
        <div class="card">
            
            <!-- HEADER: TITLE KIRI, STATUS KANAN -->
            <div class="card-header">
                <h2 class="detail-title">Order Details #<?= sprintf('%04d', $order['id']) ?></h2>
                
                <span class="status-badge" style="background-color: <?= $statusData['bg'] ?>; color: <?= $statusData['text'] ?>;">
                    <?= $statusData['label'] ?>
                </span>
            </div>
            
            <hr class="detail-separator">

            <!-- INFO SECTION (DIRAPIHKAN POSISI: Label Kiri Atas, Isi Kiri Bawah) -->
            <div class="info-grid">
                <!-- Kolom Kiri: Order Date -->
                <div class="info-item">
                    <label>Order Date</label>
                    <span>
                        <i class="far fa-calendar-alt"></i>
                        <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                    </span>
                </div>
                
                <!-- Kolom Kanan: Customer Name -->
                <div class="info-item">
                    <label>Customer Name</label>
                    <span>
                        <i class="far fa-user"></i>
                        <?= htmlspecialchars($order['shipping_name']) ?>
                    </span>
                </div>
            </div>
            
            <!-- Visual Timeline (LOGIKA DIPERBAIKI) -->
            <div class="tracking-container">
                <div class="tracking-timeline">
                    <!-- Step 1: Payment -->
                    <div class="track-step <?= in_array($order['status'], ['Process', 'Delivered', 'Done']) ? 'completed' : 'active' ?>">
                        <div class="track-icon"><i class="fas fa-wallet"></i></div>
                        <div class="track-label">Payment</div>
                    </div>
                    
                    <!-- Step 2: Process -->
                    <div class="track-step <?= $order['status'] == 'Process' ? 'active' : (in_array($order['status'], ['Delivered', 'Done']) ? 'completed' : '') ?>">
                        <div class="track-icon"><i class="fas fa-box-open"></i></div>
                        <div class="track-label">Processed</div>
                    </div>
                    
                    <!-- Step 3: Shipped -->
                    <div class="track-step <?= $order['status'] == 'Delivered' ? 'active' : ($order['status'] == 'Done' ? 'completed' : '') ?>">
                        <div class="track-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="track-label">Delivered</div>
                    </div>
                    
                    <!-- Step 4: Done -->
                    <div class="track-step <?= $order['status'] == 'Done' ? 'active' : '' ?>">
                        <div class="track-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="track-label">Done</div>
                    </div>
                </div>
            </div>

            <!-- Tabel Produk -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50%;">Product Details</th>
                            <th style="width: 25%;">Variant & Qty</th>
                            <th style="width: 25%; text-align: right;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $items = json_decode($order['items_detail'], true);
                        if(!empty($items) && is_array($items)):
                            foreach($items as $item):
                                $subtotal = $item['price'] * $item['quantity'];
                                $cat = strtolower($item['category']);
                                $path = "../assets/img/products/" . $cat . "/" . $item['image'];
                                if(!file_exists($path)) $path = "https://via.placeholder.com/150?text=No+Image";
                        ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <img src="<?= $path ?>" class="product-img" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <div>
                                        <strong style="display:block; font-size:15px; margin-bottom:4px;"><?= htmlspecialchars($item['name']) ?></strong>
                                        <small style="color:#999; font-size:12px;">ID: <?= $item['product_id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="margin-bottom:4px;"><span style="background:#eee; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600;"><?= $item['size'] ?></span></div>
                                <div style="color:#666; font-size:13px;">x<?= $item['quantity'] ?></div>
                            </td>
                            <td style="text-align: right;">
                                <strong>IDR <?= number_format($subtotal, 0, ',', '.') ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="3" style="text-align:center; padding: 30px;">No items found in this order.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="card-footer">
                <div class="total-price">
                    <span>Total Amount</span>
                    IDR <?= number_format($order['total'], 0, ',', '.') ?>
                </div>
                <a href="tracking_pesanan.php?id=<?= $order['id'] ?>" target="_blank" class="btn-track">
                    <i class="fas fa-truck-moving"></i> Track Package
                </a>
            </div>
        </div>
    </main>
</body>
</html>