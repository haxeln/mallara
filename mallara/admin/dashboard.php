<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if ($_SESSION['role'] == 'customer') {
    header("Location: ../index.php");
    exit;
}

// --- HELPER: LABEL PEMBAYARAN ---
function getPaymentLabel($method) {
    $labels = [
        'bank' => 'Transfer Bank',
        'ewallet' => 'E-Wallet',
        'cod' => 'COD'
    ];
    return $labels[$method] ?? ucfirst($method);
}

// --- DATA STATISTIK ---
 $qTotalProd = mysqli_query($conn, "SELECT COUNT(*) as c FROM products");
 $totalProd = mysqli_fetch_assoc($qTotalProd)['c'];

// Mengambil semua data transaksi hari ini untuk perhitungan revenue yang benar
 $today = date('Y-m-d');
 $qTrxToday = mysqli_query($conn, "SELECT * FROM orders WHERE DATE(created_at) = '$today'");

 $todayRevenue = 0;
while ($row = mysqli_fetch_assoc($qTrxToday)) {
    $status = $row['status'];
    // Ubah menjadi huruf kecil agar case-insensitive (misal: 'COD' atau 'cod' sama saja)
    $method = strtolower($row['payment_method']);

    // Abaikan transaksi yang dibatalkan atau belum valid
    if ($status == 'Cancelled' || $status == 'Not Paid') continue;

    if ($method == 'cod') {
        // LOGIKA COD: Uang masuk HANYA saat status Done (Sesuai permintaan)
        if ($status == 'Done') {
            $todayRevenue += $row['total'];
        }
    } else {
        // LOGIKA NON-COD (Bank/E-Wallet): Uang masuk HANYA saat status Process (Sesuai permintaan)
        if ($status == 'Process') {
            $todayRevenue += $row['total'];
        }
    }
}
// Reset pointer query untuk tabel transaksi di bawah (opsional, jika $qTrxToday mau dipakai ulang)
mysqli_data_seek($qTrxToday, 0);

 $qMan = mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE category='Man'");
 $countMan = mysqli_fetch_assoc($qMan)['c'];

 $qWoman = mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE category='Woman'");
 $countWoman = mysqli_fetch_assoc($qWoman)['c'];

// Query terpisah untuk tabel transaksi di bawah (Limit 5 terbaru)
 $qTrx = mysqli_query($conn, "SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");

// --- 3. LOGIKA LOW STOCK & SAFE STOCK ---
// Ambil produk dengan stok < 5
 $qLowStock = mysqli_query($conn, "SELECT * FROM products WHERE stock < 5 ORDER BY stock ASC");
// Ambil produk dengan stok >= 5 (Safe)
 $qSafeStock = mysqli_query($conn, "SELECT * FROM products WHERE stock >= 5 ORDER BY id DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- CSS ADMIN LAYOUT --- */
        :root { --admin-primary: #8d1a1a; --admin-bg: #f3f4f6; }
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: var(--admin-bg); display: flex; min-height: 100vh; color: #333; }

        .sidebar { width: 260px; background: var(--admin-primary); color: white; position: fixed; top: 0; left: 0; height: 100%; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 1000; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-header h2 { margin: 0; font-family: 'Times New Roman', serif; letter-spacing: 2px; font-size: 28px; }
        .sidebar-header span { font-size: 12px; opacity: 0.7; letter-spacing: 1px; }
        
        .sidebar-menu { flex: 1; padding-top: 20px; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; font-size: 15px; }
        .menu-item i { width: 25px; text-align: center; margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255,255, 0.15); color: white; border-left-color: #fff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        
        .admin-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .welcome-text h1 { margin: 0; color: var(--admin-primary); font-family: 'Times New Roman', serif; font-size: 32px; }
        .welcome-text p { margin: 5px 0 0; color: #666; font-size: 14px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 5px solid var(--admin-primary); }
        .stat-info h3 { margin: 0; font-size: 24px; color: var(--admin-primary); font-weight: bold; }
        .stat-info p { margin: 5px 0 0; color: #888; font-size: 13px; text-transform: uppercase; }

        .section-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .section-title { margin-top: 0; color: var(--admin-primary); border-bottom: 2px solid #eee; padding-bottom: 10px; font-size: 18px; }

        .stock-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .stock-item { display: flex; align-items: center; padding: 10px; border-radius: 5px; border: 1px solid #eee; }
        .stock-item img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px; }
        .stock-text h5 { margin: 0; font-size: 13px; color: #333; }
        .stock-text span { font-size: 11px; font-weight: bold; }
        
        /* Warna untuk Low Stock */
        .stock-item.low { background: #fff5f5; border-color: #ffecec; }
        .stock-item.low span { color: var(--admin-primary); }
        /* Warna untuk Safe Stock */
        .stock-item.safe { background: #f0fff4; border-color: #c6f6d5; }
        .stock-item.safe span { color: #2f855a; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; }

        /* --- 1. CSS BADGE STATUS (OVAL) --- */
        .badge { 
            padding: 6px 12px;      
            border-radius: 20px;    /* Oval Penuh */
            font-size: 12px; 
            font-weight: bold; 
            color: white; 
            text-transform: capitalize;
            display: inline-block;
            min-width: 80px;       /* Lebar Standar Status */
            text-align: center;
        }
        
        .badge-process { background-color: #dc3545; }
        .badge-shipped { background-color: #ffc107; color: #333; }
        .badge-delivered { background-color: #7FBFFF; color: #333; }
        .badge-done { background-color: #28a745; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        /* --- 2. CSS BADGE PAYMENT (KOTAK TUMPUL + LEBAR SERAGAM) --- */
        .payment-badge {
            display: inline-flex;      /* Flexbox untuk alignment icon dan teks */
            align-items: center;
            justify-content: center;
            gap: 6px;                 /* Jarak antara icon dan teks */
            
            padding: 6px 12px;        /* Sama dengan badge status */
            border-radius: 8px;       /* Kotak Tumpul (Bukan Oval) */
            font-size: 12px;
            font-weight: 600; 
            background: #e9ecef; 
            color: #495057;
            border: 1px solid #dee2e6;
            
            min-width: 150px;         /* Diperbesar agar muat teks panjang & seragam */
            text-align: center;
            white-space: nowrap;      /* Mencegah teks turun baris */
        }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><h2>MALLARA</h2><span>ADMIN PANEL</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="products.php" class="menu-item"><i class="fas fa-box"></i> <span>Products</span></a>
            <a href="trending.php" class="menu-item"><i class="fas fa-fire"></i> <span>Trending</span></a>
            <a href="orders.php" class="menu-item"><i class="fas fa-shopping-bag"></i> <span>Transactions</span></a>
            <a href="users.php" class="menu-item"><i class="fas fa-users"></i> <span>Users</span></a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
            <a href="backup_data.php" class="menu-item"><i class="fas fa-database"></i> <span>Backup Data</span></a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="menu-item" style="color: #ffcccc;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></div>
    </aside>

    <main class="main-content">
        <div class="admin-topbar">
            <div class="welcome-text"><h1>Dashboard</h1><p>Halo, <?= htmlspecialchars($_SESSION['full_name']) ?></p></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-info"><h3><?= $totalProd ?></h3><p>Total Product</p></div></div>
            <div class="stat-card"><div class="stat-info"><h3><?= $countMan ?></h3><p>Man Product</p></div></div>
            <div class="stat-card"><div class="stat-info"><h3><?= $countWoman ?></h3><p>Woman Product</p></div></div>
            <!-- PERBAIKAN: Menggunakan $todayRevenue yang sudah dihitung manual (Logic Fix: COD=Done, Non-COD=Process) -->
            <div class="stat-card"><div class="stat-info"><h3>IDR <?= number_format($todayRevenue, 0, ',', '.') ?></h3><p>Transaction Today</p></div></div>
        </div>

        <!-- 1. LOW STOCK (< 5) -->
        <div class="section-card">
            <h3 class="section-title">Low Stock Product (< 5)</h3>
            <div class="stock-list">
                <?php if (mysqli_num_rows($qLowStock) > 0): ?>
                    <?php while($ls = mysqli_fetch_assoc($qLowStock)): 
                        $imgPath = "../assets/img/products/" . $ls['category'] . "/" . $ls['image'];
                        if(!file_exists($imgPath)) $imgPath = "https://picsum.photos/seed/low/50/50";
                    ?>
                    <div class="stock-item low">
                        <img src="<?= $imgPath ?>" alt="img">
                        <div class="stock-text">
                            <h5><?= $ls['name'] ?></h5>
                            <span>Stock: <?= $ls['stock'] ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#888; font-style:italic;">All Stock is Safe.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. SAFE STOCK (>= 5) -->
        <div class="section-card">
            <h3 class="section-title">Product Stock is Safe (>= 5)</h3>
            <div class="stock-list">
                <?php if (mysqli_num_rows($qSafeStock) > 0): ?>
                    <?php while($ss = mysqli_fetch_assoc($qSafeStock)): 
                        $imgPath = "../assets/img/products/" . $ss['category'] . "/" . $ss['image'];
                        if(!file_exists($imgPath)) $imgPath = "https://picsum.photos/seed/safe/50/50";
                    ?>
                    <div class="stock-item safe">
                        <img src="<?= $imgPath ?>" alt="img">
                        <div class="stock-text">
                            <h5><?= $ss['name'] ?></h5>
                            <span>Stock: <?= $ss['stock'] ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#888;">Tidak ada produk dalam kategori ini.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-card">
            <h3 class="section-title">New Transaction</h3>
            <table>
                <thead><tr><th>ID Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($qTrx) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($qTrx)): 
                            // Cek class badge untuk status
                            $badgeClass = '';
                            $status = $row['status'];
                            if ($status == 'Process') $badgeClass = 'badge-process';
                            elseif ($status == 'Shipped') $badgeClass = 'badge-shipped';
                            elseif ($status == 'Delivered') $badgeClass = 'badge-delivered';
                            elseif ($status == 'Done') $badgeClass = 'badge-done';
                            elseif ($status == 'Cancelled') $badgeClass = 'badge-cancelled';
                            else $badgeClass = 'badge-pending';
                        ?>
                        <tr>
                            <td>#MLR-<?= sprintf('%04d', $row['id']) ?></td>
                            <td><?= $row['full_name'] ?></td>
                            <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
                            <td>IDR <?= number_format($row['total'], 0, ',', '.') ?></td>
                            <td>
                                <span class="payment-badge">
                                    <i class="fas fa-credit-card"></i> <?= getPaymentLabel($row['payment_method']) ?>
                                </span>
                            </td>
                            <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;">Belum ada transaksi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>