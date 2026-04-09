<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// --- 2. LOGIKA UPDATE STATUS ---
if (isset($_POST['update_status'])) {
    $id = $_POST['order_id'];
    $status = $_POST['status'];

    $query = mysqli_query($conn, "UPDATE orders SET status='$status' WHERE id='$id'");
    
    if ($query) {
        echo "<script>alert('Status berhasil diupdate!'); window.location.href='orders.php';</script>";
    } else {
        echo "<script>alert('Gagal update status!');</script>";
    }
}

// --- 3. LOGIKA PENCARIAN (SEARCH) */
 $search = "";
 $whereClause = "";
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    // Cari berdasarkan Kode Transaksi OR Nama Customer
    $whereClause = "WHERE (invoice_code LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}

// --- 4. AMBIL DATA TRANSAKSI ---
// Join dengan tabel users untuk mendapatkan nama customer
 $query = mysqli_query($conn, "SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id $whereClause ORDER BY o.id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --admin-primary: #8d1a1a;
            --admin-bg: #f3f4f6;
            --badge-process: #dc3545; /* Merah */
            --badge-shipped: #ffc107; /* Kuning */
            --badge-delivered: #7FBFFF; /* Biru Muda (Requested) */
            --badge-done: #28a745;    /* Hijau */
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
            margin-bottom: 30px;
            flex-wrap: wrap; gap: 15px;
        }
        .page-title { font-size: 24px; color: var(--admin-primary); font-family: 'Times New Roman', serif; margin: 0; }
        
        .search-box {
            display: flex; gap: 10px;
        }
        .search-input {
            padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; width: 300px;
        }
        .btn-search {
            padding: 10px 20px; background: var(--admin-primary); color: white; border: none; border-radius: 5px; cursor: pointer;
        }

        /* Table */
        .table-container {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background-color: #f8f9fa; color: #555; font-weight: 600; text-transform: uppercase; }
        tr:hover { background-color: #fafafa; }

        /* --- CSS BADGE STATUS --- */
        .badge { 
            padding: 6px 12px;      
            border-radius: 20px;    /* Oval */
            font-size: 12px; 
            font-weight: bold; 
            color: white; 
            text-transform: capitalize;
            display: inline-block;
            min-width: 80px;       
            text-align: center;
        }
        
        .badge-process { background-color: var(--badge-process); color: white; }
        .badge-shipped { background-color: var(--badge-shipped); color: #333; }
        .badge-delivered { background-color: var(--badge-delivered); color: #333; }
        .badge-done { background-color: var(--badge-done); color: white; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

        /* --- CSS PAYMENT BADGE (DIUBAH) --- */
        .payment-badge {
            display: inline-block;
            padding: 6px 12px;      /* Padding disamakan dengan badge status */
            border-radius: 8px;     /* Kotak Tumpul (Bukan oval/pill) */
            font-size: 12px;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
            border: 1px solid #dee2e6;
            min-width: 80px;       /* Lebar disamakan dengan badge status */
            text-align: center;    /* Teks di tengah */
        }
        /* ----------------------------------- */

        /* Action Buttons */
        .btn-action {
            padding: 6px 10px; border-radius: 4px; text-decoration: none;
            color: white; margin-right: 5px; display: inline-block; font-size: 12px;
            transition: 0.2s;
        }
        .btn-see { background-color: var(--admin-primary); }
        .btn-print { background-color: #333; }
        .btn-edit { background-color: #ffc107; color: #333; }
        
        .btn-action:hover { opacity: 0.8; transform: translateY(-1px); }

        /* Modal Edit Status */
        .modal {
            display: none; position: fixed; z-index: 2000; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe; margin: 15% auto; padding: 20px;
            border: 1px solid #888; width: 300px; border-radius: 5px; text-align: center;
        }
        .close-modal { float: right; font-size: 28px; font-weight: bold; cursor: pointer; }

        /* Responsif */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
            .search-input { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>MALLARA</h2>
            <span>PETUGAS PANEL</span>
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
            <a href="orders.php" class="menu-item active">
                <i class="fas fa-shopping-bag"></i> <span>Transactions</span>
            </a>
            <a href="users.php" class="menu-item">
                <i class="fas fa-users"></i> <span>Users</span>
            </a>
            <a href="reports.php" class="menu-item">
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

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <div class="page-header">
            <h2 class="page-title">Transaction Management</h2>
            
            <div class="search-box">
                <form method="GET">
                    <input type="text" name="search" class="search-input" placeholder="Search Code / Customer Name..." value="<?= $search ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Invoice Code</th>
                        <th>Date</th>
                        <th>Customer Name</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment System</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): 
                            // Format metode pembayaran
                            $payMethod = $row['payment_method'];
                            $payLabel = ucfirst($payMethod);
                            if($payMethod == 'bank') $payLabel = 'Transfer Bank';
                            elseif($payMethod == 'ewallet') $payLabel = 'E-Wallet';
                            elseif($payMethod == 'cod') $payLabel = 'COD';

                            // Logic Warna Badge Status
                            $status = $row['status'];
                            $badgeClass = '';
                            if ($status == 'Process') $badgeClass = 'badge-process';
                            elseif ($status == 'Shipped') $badgeClass = 'badge-shipped';
                            elseif ($status == 'Delivered') $badgeClass = 'badge-delivered';
                            elseif ($status == 'Done') $badgeClass = 'badge-done';
                            // Fallback untuk status lain
                            elseif ($status == 'Not Paid') $badgeClass = 'badge-shipped';
                            // UPDATE: Cancelled menggunakan class badge-cancelled (Sesuai Dashboard)
                            elseif ($status == 'Cancelled') $badgeClass = 'badge-cancelled';
                            else $badgeClass = 'badge-process'; // Default
                        ?>
                        <tr>
                            <td>
                                <strong><?= $row['invoice_code'] ?></strong>
                            </td>
                            <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
                            <td><?= $row['full_name'] ?></td>
                            <td>IDR <?= number_format($row['total'], 0, ',', '.') ?></td>
                            <td>
                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                            </td>
                            <td>
                                <span class="payment-badge">
                                    <i class="fas fa-credit-card"></i> <?= $payLabel ?>
                                </span>
                            </td>
                            
                            <td>
                                <!-- Tombol See Detail -->
                                <a href="order_detail.php?id=<?= $row['id'] ?>" class="btn-action btn-see" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- Tombol Edit Status -->
                                <a href="order_edit.php?id=<?= $row['id'] ?>" class="btn-action btn-edit" title="Edit Status">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                Belum ada transaksi.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <!-- MODAL POPUP UNTUK EDIT STATUS -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3 style="margin-top:0; color:var(--admin-primary);">Update Status</h3>
            <form method="POST">
                <input type="hidden" name="order_id" id="modalOrderId">
                <div style="margin: 20px 0;">
                    <label>Pilih Status:</label><br>
                    <select name="status" style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 5px; border: 1px solid #ddd;">
                        <option value="Process">Process (Merah)</option>
                        <option value="Shipped">Shipped (Kuning)</option>
                        <option value="Delivered">Delivered (Biru Muda)</option>
                        <option value="Done">Done (Hijau)</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn-see" style="width:100%; padding: 10px;">Update</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(orderId) {
            document.getElementById('modalOrderId').value = orderId;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Tutup modal jika klik di luar area
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeModal();
            }
        }
    </script>

</body>
</html>