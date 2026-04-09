<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// --- 2. LOGIKA UPDATE STATUS TRENDING ---
if (isset($_POST['update_trending'])) {
    // 1. Matikan dulu SEMUA produk (reset)
    mysqli_query($conn, "UPDATE products SET is_trending = 0");

    // 2. Hidupkan hanya yang dicentang
    if (isset($_POST['trending_ids'])) {
        foreach ($_POST['trending_ids'] as $id) {
            $id = intval($id);
            mysqli_query($conn, "UPDATE products SET is_trending = 1 WHERE id = '$id'");
        }
    }
    
    echo "<script>
            alert('Trending list updated successfully!');
            window.location.href='trending.php';
          </script>";
}

// --- 3. AMBIL DATA PRODUK (DENGAN LOGIKA SEARCH) ---
// Cek apakah ada parameter search di URL
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $keyword = mysqli_real_escape_string($conn, $_GET['search']);
    // Query mencari produk berdasarkan nama
    $query = mysqli_query($conn, "SELECT * FROM products WHERE name LIKE '%$keyword%' ORDER BY id DESC");
} else {
    // Jika tidak ada search, tampilkan semua
    $query = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trending Management - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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

        /* SIDEBAR */
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

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }

        /* HEADER & BUTTON */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 20px; flex-wrap: wrap; gap: 15px; 
        }
        .page-header-left { display: flex; flex-direction: column; }
        .page-title { font-size: 24px; color: var(--admin-primary); font-family: 'Times New Roman', serif; margin: 0; }
        
        .header-actions {
            display: flex; gap: 10px; align-items: center;
        }

        /* STYLE SEARCH BAR */
        .search-box {
            display: flex;
        }
        .search-input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-right: none;
            border-radius: 5px 0 0 5px;
            outline: none;
            width: 250px;
            font-size: 14px;
        }
        .search-input:focus {
            border-color: var(--admin-primary);
        }
        .btn-search {
            background: #fff; color: #555; border: 1px solid #ddd;
            padding: 0 15px; border-radius: 0 5px 5px 0; cursor: pointer;
            transition: 0.3s;
        }
        .btn-search:hover { background: #eee; }

        .btn-save {
            background: var(--admin-primary); color: white; padding: 10px 20px;
            text-decoration: none; border-radius: 5px; font-weight: bold;
            border: none; cursor: pointer; transition: 0.3s;
        }
        .btn-save:hover { background: #600000; }

        /* TABLE */
        .table-container {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; text-transform: uppercase; }
        tr:hover { background-color: #fafafa; }

        .product-img-thumb {
            width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;
        }
        
        /* Badge Kategori */
        .badge-cat {
            padding: 4px 8px; background: #eee; border-radius: 4px; font-size: 11px; text-transform: capitalize;
        }

        /* Responsif */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
            .search-input { width: 150px; }
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
            <!-- MENU TRENDING (ACTIVE) -->
            <a href="trending.php" class="menu-item active">
                <i class="fas fa-fire"></i> <span>Trending</span>
            </a>
            <a href="orders.php" class="menu-item">
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
            <div class="page-header-left">
                <h2 class="page-title">Trending Management</h2>
                <p style="margin:5px 0 0 0; color:#666; font-size: 14px;">Select the products you want to display on the front page.</p>
            </div>
            
            <div class="header-actions">
                <!-- SEARCH BAR (ENGLISH) -->
                <form method="GET" class="search-box">
                    <input type="text" name="search" class="search-input" placeholder="Search product name..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <div class="table-container">
            <form method="POST">
                <div style="text-align: right; margin-bottom: 15px;">
                    <button type="submit" name="update_trending" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">Select</th>
                            <th style="width: 80px;">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($query) > 0): 
                            while ($row = mysqli_fetch_assoc($query)): 
                                $imgPath = "../assets/img/products/" . $row['category'] . "/" . $row['image'];
                                $isTrending = ($row['is_trending'] == 1) ? 'checked' : '';
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="trending_ids[]" value="<?= $row['id'] ?>" <?= $isTrending ?>>
                            </td>
                            <td>
                                <img src="<?= $imgPath ?>" alt="<?= $row['name'] ?>" class="product-img-thumb" onerror="this.src='https://via.placeholder.com/50'">
                            </td>
                            <td>
                                <strong><?= $row['name'] ?></strong>
                            </td>
                            <td>
                                <span class="badge-cat"><?= $row['category'] ?></span>
                            </td>
                            <td>IDR <?= number_format($row['price'], 0, ',', '.') ?></td>
                            <td>
                                <?php if($row['is_trending'] == 1): ?>
                                    <span style="color: green; font-weight: bold;"><i class="fas fa-check-circle"></i> Active</span>
                                <?php else: ?>
                                    <span style="color: #999;">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #888;">
                                <?php if(isset($_GET['search'])): ?>
                                    No products found. <a href="trending.php" style="color: var(--admin-primary);">View All Products</a>
                                <?php else: ?>
                                    No products found. Please add products first.
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

    </main>

</body>
</html>