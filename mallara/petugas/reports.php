<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// --- 2. LOGIKA PENCARIAN & FILTER */
 $search = "";
 $whereClause = "";
 $dateFilter = ""; 

// 1. Handle Pencarian Teks (Search)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $whereClause = "WHERE (o.invoice_code LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}

// 2. Handle Filter Tanggal
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $selectedDate = mysqli_real_escape_string($conn, $_GET['date']);
    $dateFilter = $selectedDate; 

    if ($whereClause == "") {
        $whereClause = "WHERE DATE(o.created_at) = '$selectedDate'";
    } else {
        $whereClause .= " AND DATE(o.created_at) = '$selectedDate'";
    }
}

// --- 3. EKSEKUSI QUERY & HITUNG DATA (DIPINDAHKAN KE ATAS) ---
 $query = mysqli_query($conn, "SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id $whereClause ORDER BY o.id DESC");

 $totalOrders = 0;
 $totalRevenue = 0;
 $data_cache = []; // Untuk menyimpan data agar bisa dipakai di Tabel dan Chart

if($query && mysqli_num_rows($query) > 0){
    $totalOrders = mysqli_num_rows($query);
    while ($row = mysqli_fetch_assoc($query)) {
        
        // --- LOGIC UPDATE: Perhitungan Revenue sesuai Dashboard.php ---
        // (COD = Done, Non-COD = Process)
        $status = $row['status'];
        $method = strtolower($row['payment_method']);

        // Abaikan transaksi yang dibatalkan atau belum valid
        if ($status == 'Cancelled' || $status == 'Not Paid') {
            // Masukkan ke cache tabel tapi tidak dihitung revenue-nya
            $data_cache[] = $row;
            continue;
        }

        if ($method == 'cod') {
            // LOGIKA COD: Uang masuk HANYA saat status Done
            if ($status == 'Done') {
                $totalRevenue += $row['total'];
            }
        } else {
            // LOGIKA NON-COD (Bank/E-Wallet): Uang masuk HANYA saat status Process
            if ($status == 'Process') {
                $totalRevenue += $row['total'];
            }
        }

        $data_cache[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js untuk Grafik Penjualan -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --admin-primary: #8d1a1a;
            --admin-bg: #f3f4f6;
        }

        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--admin-bg);
            display: flex;
            min-height: 100vh;
            color: #333;
        }

        /* Sidebar */
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

        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }

        /* Header & Search */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; flex-wrap: wrap; gap: 15px;
        }
        .page-title { font-size: 24px; color: var(--admin-primary); font-family: 'Times New Roman', serif; margin: 0; }
        
        .search-box {
            display: flex; gap: 10px; align-items: center;
        }
        .search-input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; width: 250px; }
        .date-input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-search { padding: 10px 20px; background: var(--admin-primary); color: white; border: none; border-radius: 5px; cursor: pointer; }

        /* Stats Cards */
        .stats-cards {
            display: flex; gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 4px solid var(--admin-primary);
        }
        .stat-card h3 { margin: 0; font-size: 14px; color: #666; text-transform: uppercase; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: var(--admin-primary); margin: 5px 0 0 0; }

        /* Table */
        .table-container {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background-color: #f8f9fa; color: #555; font-weight: 600; text-transform: uppercase; }
        tr:hover { background-color: #fafafa; }

        /* --- UPDATE WARNA BADGE --- */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; text-transform: capitalize; display: inline-block; min-width: 80px; text-align: center; }
        
        .badge-process { background-color: #dc3545; } /* Merah */
        /* .badge-shipped DIHAPUS DARI LOGIKA, TAPI CSS DIBIARKAN */
        .badge-shipped { background-color: #fd7e14; } 
        .badge-delivered { background-color: #17a2b8; } /* Biru */
        .badge-done { background-color: #28a745; } /* Hijau */
        
        /* UPDATE CANCELLED SESUAI KODE DASHBOARD.PHP */
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        .badge-notpaid { background-color: #ffc107; color: #333; } /* Kuning */

        .btn-action { padding: 5px 10px; border-radius: 4px; text-decoration: none; color: white; margin-right: 5px; display: inline-block; font-size: 12px; }
        .btn-see { background-color: var(--admin-primary); }
        .btn-print { background-color: #333; }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
            .search-input, .date-input { width: 100%; }
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
            <a href="orders.php" class="menu-item"><i class="fas fa-shopping-bag"></i> <span>Transactions</span></a>
            <a href="users.php" class="menu-item"><i class="fas fa-users"></i> <span>Users</span></a>
            <a href="reports.php" class="menu-item active"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
            <a href="backup_data.php" class="menu-item"><i class="fas fa-database"></i> <span>Backup Data</span></a>    
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="menu-item" style="color: #ffcccc;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <div class="page-header">
            <h2 class="page-title">Sales Reports</h2>
            
            <div class="search-box">
                <form method="GET" style="display:flex; gap:10px;">
                    <input type="text" name="search" class="search-input" placeholder="Search Transaction..." value="<?= $search ?>">
                    <input type="date" name="date" class="date-input" value="<?= isset($_GET['date']) ? $_GET['date'] : '' ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Filter</button>
                    <a href="reports.php" style="text-decoration:none; color:#666; font-size:12px; padding:10px;">Reset</a>
                </form>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>Period</h3>
                <div class="value">
                    <?php 
                        if(!empty($dateFilter)){
                            echo date('d F Y', strtotime($dateFilter));
                        } else {
                            echo "All Time";
                        }
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="value"><?= $totalOrders ?> Orders</div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <!-- TOTAL REVENUE SEKARANG MENGGUNAKAN LOGIKA YANG SAMA DENGAN DASHBOARD -->
                <div class="value">IDR <?= number_format($totalRevenue, 0, ',', '.') ?></div>
            </div>
        </div>

        <!-- GRAFIK CHART (SHIPPED DIHAPUS) -->
        <div style="background:white; padding:20px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-bottom:30px; height:350px;">
            <canvas id="salesChart"></canvas>
        </div>

        <!-- TABEL TRANSAKSI (SHIPPED DIHAPUS DARI LOGIKA) -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data_cache) && count($data_cache) > 0): ?>
                        <?php foreach ($data_cache as $row): ?>
                        <tr>
                            <td><strong><?= $row['invoice_code'] ?></strong></td>
                            <td><?= $row['full_name'] ?></td>
                            <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
                            <td>IDR <?= number_format($row['total'], 0, ',', '.') ?></td>
                            <td>
                                <?php 
                                    $status = $row['status'];
                                    $badgeClass = '';
                                    // Logika Shipped DIHAPUS
                                    if ($status == 'Process') $badgeClass = 'badge-process';
                                    elseif ($status == 'Delivered') $badgeClass = 'badge-delivered';
                                    elseif ($status == 'Done') $badgeClass = 'badge-done';
                                    elseif ($status == 'Cancelled') $badgeClass = 'badge-cancelled';
                                    elseif ($status == 'Not Paid') $badgeClass = 'badge-notpaid';
                                    else $badgeClass = 'badge-notpaid';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                            </td>
                            <td>
                                <a href="order_detail.php?id=<?= $row['id'] ?>" class="btn-action btn-see">See</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #999;">Belum ada data transaksi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <!-- SCRIPT CHART (SHIPPED DIHAPUS) -->
    <script>
        // Inisialisasi variabel count (ShippedCount dihapus)
        let processCount = 0;
        let deliveredCount = 0;
        let doneCount = 0;
        let cancelledCount = 0;
        let notPaidCount = 0;

        // Hitung data dari PHP
        <?php 
        if(!empty($data_cache)){
            foreach ($data_cache as $row): 
                if($row['status'] == 'Process') echo "processCount++;";
                // Shipped dihapus dari loop
                elseif($row['status'] == 'Delivered') echo "deliveredCount++;";
                elseif($row['status'] == 'Done') echo "doneCount++;";
                elseif($row['status'] == 'Cancelled') echo "cancelledCount++;";
                elseif($row['status'] == 'Not Paid') echo "notPaidCount++;";
            endforeach; 
        }
        ?>

        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                // Label Shipped dihapus
                labels: ['Process', 'Delivered', 'Done', 'Cancelled', 'Not Paid'],
                datasets: [{
                    label: 'Total Orders',
                    // Data Shipped dihapus
                    data: [processCount, deliveredCount, doneCount, cancelledCount, notPaidCount],
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.8)',  /* Merah - Process */
                        // Warna Oranye Shipped dihapus
                        'rgba(23, 162, 184, 0.8)', /* Biru - Delivered */
                        'rgba(40, 167, 69, 0.8)',  /* Hijau - Done */
                        'rgba(248, 215, 218, 0.8)', /* Light Red - Cancelled */
                        'rgba(255, 193, 7, 0.8)'   /* Kuning - Not Paid */
                    ],
                    borderColor: [
                        'rgba(220, 53, 69, 1)',
                        // Border Oranye Shipped dihapus
                        'rgba(23, 162, 184, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(248, 215, 218, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 1
                }]
            }, 
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>

</body>
</html>