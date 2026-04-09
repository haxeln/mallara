<?php
session_start();
require '../config/database.php';

// 1. CEK KEAMANAN ADMIN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if ($_SESSION['role'] == 'customer') {
    header("Location: ../index.php");
    exit;
}

// 2. AMBIL ID ORDER
if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

 $id = intval($_GET['id']);
 $order_query = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$id'");
 $order = mysqli_fetch_assoc($order_query);

if (!$order) {
    echo "<script>alert('Pesanan tidak ditemukan!'); window.location.href='orders.php';</script>";
    exit;
}

// 3. LOGIKA UPDATE STATUS (DIPERBAIKI)
if (isset($_POST['update_status'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Update status di database
    $update = mysqli_query($conn, "UPDATE orders SET status = '$new_status' WHERE id = '$id'");
    
    if ($update) {
        // Redirect ke halaman detail yang sama untuk melihat perubahan
        echo "<script>alert('Status berhasil diupdate!'); window.location.href='order_detail.php?id=$id';</script>";
    } else {
        // Tampilkan error MySQL jika gagal (untuk debugging)
        $error_msg = mysqli_error($conn);
        echo "<script>alert('Gagal update status! Error: $error_msg');</script>";
    }
}

// 4. MAPPING STATUS UNTUK TAMPILAN
function getStatusInfo($status) {
    $info = ['label' => $status, 'color' => '#6c757d'];
    
    switch($status) {
        case 'Not Paid':
            $info = ['label' => 'Waiting Payment', 'color' => '#ffc107', 'bg' => '#fff3cd', 'text' => '#856404'];
            break;
        case 'Packed':
        case 'Process':
            $info = ['label' => 'Processed', 'color' => '#17a2b8', 'bg' => '#d1ecf1', 'text' => '#0c5460'];
            break;
        case 'Delivery':
        case 'Delivered':
            $info = ['label' => 'Delivered', 'color' => '#28a745', 'bg' => '#d4edda', 'text' => '#155724'];
            break;
        case 'Completed':
        case 'Done':
            $info = ['label' => 'Done', 'color' => '#28a745', 'bg' => '#d4edda', 'text' => '#155724'];
            break;
        case 'Cancelled':
            $info = ['label' => 'Cancelled', 'color' => '#dc3545', 'bg' => '#f8d7da', 'text' => '#721c24'];
            break;
    }
    return $info;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order - Mallara Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --admin-primary: #8d1a1a; --admin-bg: #f3f4f6; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--admin-bg); margin: 0; padding: 0; color: #333; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--admin-primary); color: white; position: fixed; top: 0; left: 0; height: 100%; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-header h2 { margin: 0; font-family: 'Times New Roman', serif; letter-spacing: 2px; font-size: 28px; }
        .sidebar-menu { flex: 1; padding-top: 20px; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; font-size: 15px; }
        .menu-item i { width: 25px; text-align: center; margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.15); color: white; border-left-color: #fff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        
        /* Back Button */
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #666; margin-bottom: 20px; font-weight: 500; }
        .back-link:hover { color: var(--admin-primary); }

        .form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .page-title { color: var(--admin-primary); font-family: 'Times New Roman', serif; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
        
        /* Order Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .info-label { font-size: 12px; color: #888; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .info-value { font-size: 16px; font-weight: 500; color: #333; }

        /* Status Form */
        .status-form { background: #fff3cd; border: 1px solid #ffeeba; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; }
        .btn-update { background: var(--admin-primary); color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-update:hover { background: #600000; }

        /* Table Items */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: 600; font-size: 13px; }
        .product-img-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }

        /* Timeline Visual */
        .timeline-track { display: flex; align-items: center; justify-content: space-between; margin: 30px 0; position: relative; padding: 20px 0; }
        .timeline-track::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #eee; z-index: 0; }
        
        .track-step { position: relative; z-index: 1; text-align: center; flex: 1; }
        .track-icon { 
            width: 45px; height: 45px; background: white; border: 2px solid #ddd; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; color: #ccc; font-size: 18px; transition: 0.3s;
        }
        .track-label { font-size: 12px; font-weight: 600; color: #999; text-transform: uppercase; }
        
        /* Active State Timeline */
        .track-step.active .track-icon, .track-step.completed .track-icon { border-color: var(--admin-primary); background: var(--admin-primary); color: white; }
        .track-step.active .track-label, .track-step.completed .track-label { color: var(--admin-primary); }
        .track-step. { content: ''; position: absolute; top: 20px; left: 50%; transform: translateX(-50%); width: 8px; height: 8px; background: var(--admin-primary); border-radius: 50%; }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header"><h2>MALLARA</h2><span>PETUGAS PANEL</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="products.php" class="menu-item"><i class="fas fa-box"></i> <span>Products</span></a>
            <a href="trending.php" class="menu-item"><i class="fas fa-fire"></i> <span>Trending</span></a>
            <a href="orders.php" class="menu-item active"><i class="fas fa-shopping-bag"></i> <span>Transactions</span></a>
            <a href="users.php" class="menu-item"><i class="fas fa-users"></i> <span>Users</span></a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
            <a href="backup_data.php" class="menu-item"><i class="fas fa-database"></i> <span>Backup Data</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="menu-item" style="color: #ffcccc;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <a href="orders.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Transaction</a>
        
        <div class="form-container">
            <h2 class="page-title">Order Details #<?= sprintf('%04d', $order['id']) ?></h2>
            
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Customer</div>
                    <div class="info-value"><?= $order['shipping_name'] ?> (<?= $order['shipping_phone'] ?>)</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Date Order</div>
                    <div class="info-value"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></div>
                </div>
            </div>

            <!-- FORM UPDATE STATUS -->
            <div class="status-form">
                <h4 style="margin-top:0;">Order Status Updates</h4>
                <form action="" method="POST">
                    <input type="hidden" name="id" value="<?= $order['id'] ?>">
                    <div class="form-group">
                        <label style="font-weight:600; display:block; margin-bottom:5px;">Select New Status:</label>
                        <select name="status" class="form-control">
                            <option value="Not Paid" <?= $order['status'] == 'Not Paid' ? 'selected' : '' ?>>Waiting Payment</option>
                            <option value="Process" <?= $order['status'] == 'Process' ? 'selected' : '' ?>>Processed</option>
                            <option value="Delivered" <?= $order['status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="Done" <?= $order['status'] == 'Done' ? 'selected' : '' ?>>Done</option>
                            <option value="Cancelled" <?= $order['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn-update">Save Changes</button>
                </form>
            </div>

            <!-- ITEM LIST -->
            <h4>Item Product</h4>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Variant (Size/Qty)</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Decode JSON Items dari tabel orders
                        $items = json_decode($order['items_detail'], true);
                        if(!empty($items) && is_array($items)):
                            foreach($items as $item):
                                $subtotal = $item['price'] * $item['quantity'];
                                // Cek path gambar
                                $img = $item['image'];
                                $cat = strtolower($item['category']);
                                $path = "../assets/img/products/" . $cat . "/" . $img;
                                if(!file_exists($path)) $path = "https://via.placeholder.com/50";
                        ?>
                        <tr>
                            <td><img src="<?= $path ?>" class="product-img-thumb"></td>
                            <td>
                                <strong><?= $item['name'] ?></strong><br>
                                <small style="color:#888;">ID: <?= $item['product_id'] ?></small>
                            </td>
                            <td>
                                Size: <?= $item['size'] ?><br>
                                Qty: <?= $item['quantity'] ?>
                            </td>
                            <td>IDR <?= number_format($item['price'], 0, ',', '.') ?></td>
                            <td><strong>IDR <?= number_format($subtotal, 0, ',', '.') ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">Data item tidak tersedia.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TOTAL -->
            <div style="text-align: right; font-size: 20px; font-weight: bold; color: var(--admin-primary); margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                Total Payment: IDR <?= number_format($order['total'], 0, ',', '.') ?>
            </div>

            <!-- TIMELINE VISUAL -->
            <h4 style="margin-top: 40px; margin-bottom: 20px;">Tracking Status</h4>
            <?php
                // Logika Timeline Visual
                $currentStatus = $order['status'];
                // Map status ke step index (0: Paid, 1: Process, 2: Delivered, 3: Done)
                $stepIndex = 0;
                if($currentStatus == 'Not Paid') $stepIndex = 0;
                elseif($currentStatus == 'Process') $stepIndex = 1;
                elseif($currentStatus == 'Delivered') $stepIndex = 2;
                elseif($currentStatus == 'Done') $stepIndex = 3;
                else $stepIndex = 0;
            ?>
            <div class="timeline-track">
                <div class="track-step <?= $stepIndex >= 0 ? 'completed' : '' ?>">
                    <div class="track-icon"><i class="fas fa-wallet"></i></div>
                    <div class="track-label">Paid</div>
                </div>
                <div class="track-step <?= $stepIndex >= 1 ? 'completed' : '' ?>">
                    <div class="track-icon"><i class="fas fa-box-open"></i></div>
                    <div class="track-label">Process</div>
                </div>
                <div class="track-step <?= $stepIndex >= 2 ? 'completed' : '' ?>">
                    <div class="track-icon"><i class="fas fa-truck"></i></div>
                    <div class="track-label">Delivered</div>
                </div>
                <div class="track-step <?= $stepIndex >= 3 ? 'completed' : '' ?>">
                    <div class="track-icon"><i class="fas fa-check"></i></div>
                    <div class="track-label">Done</div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>