<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// --- 2. LOGIKA HAPUS PRODUK ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Hapus gambar fisik dari folder
    $getData = mysqli_query($conn, "SELECT image, category FROM products WHERE id='$id'");
    if(mysqli_num_rows($getData) > 0){
        $p = mysqli_fetch_assoc($getData);
        $path = "../assets/img/products/" . $p['category'] . "/" . $p['image'];
        if(file_exists($path)){
            unlink($path); // Hapus file
        }
    }

    // Hapus dari Database
    $query = mysqli_query($conn, "DELETE FROM products WHERE id='$id'");
    
    if ($query) {
        echo "<script>
                alert('Produk berhasil dihapus!');
                window.location.href='products.php';
              </script>";
    } else {
        echo "<script>alert('Gagal menghapus produk!');</script>";
    }
}

// --- 3. AMBIL DATA PRODUK (DENGAN LOGIKA SEARCH) ---
// Cek apakah ada parameter search di URL
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $keyword = mysqli_real_escape_string($conn, $_GET['search']);
    // Query mencari produk berdasarkan nama
    $queryProducts = mysqli_query($conn, "SELECT * FROM products WHERE name LIKE '%$keyword%' ORDER BY id DESC");
} else {
    // Jika tidak ada search, tampilkan semua
    $queryProducts = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- CSS SAMA DENGAN DASHBOARD --- */
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

        .btn-add {
            background: var(--admin-primary); color: white; padding: 10px 20px;
            text-decoration: none; border-radius: 5px; font-weight: bold;
            display: inline-flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .btn-add:hover { background: #600000; }

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

        /* ACTION BUTTONS */
        .btn-action {
            padding: 6px 10px; border-radius: 4px; text-decoration: none;
            color: white; margin-right: 5px; display: inline-block; font-size: 14px;
        }
        .btn-edit { background-color: #ffc107; color: #333; } /* Kuning */
        .btn-delete { background-color: #dc3545; } /* Merah */

        /* RESPONSIVE */
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

    <!-- SIDEBAR (FINAL DENGAN TRENDING) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>MALLARA</h2>
            <span>PETUGAS PANEL</span>
        </div>
        <nav class="sidebar-menu">
            <!-- 1. Dashboard -->
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <!-- 2. Products (Active) -->
            <a href="products.php" class="menu-item active">
                <i class="fas fa-box"></i> <span>Products</span>
            </a>
            <!-- 3. Trending (BARU DITAMBAHKAN) -->
            <a href="trending.php" class="menu-item">
                <i class="fas fa-fire"></i> <span>Trending</span>
            </a>
            <!-- 4. Transactions -->
            <a href="orders.php" class="menu-item">
                <i class="fas fa-shopping-bag"></i> <span>Transactions</span>
            </a>
            <!-- 5. Users -->
            <a href="users.php" class="menu-item">
                <i class="fas fa-users"></i> <span>Users</span>
            </a>
            <!-- 6. Reports -->
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
            <h2 class="page-title">Products Management</h2>
            
            <div class="header-actions">
                <!-- SEARCH BAR BARU -->
                <form action="" method="GET" class="search-box">
                    <input type="text" name="search" class="search-input" placeholder="Search Product..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                </form>

                <!-- TOMBOL ADD -->
                <a href="products_add.php" class="btn-add"><i class="fas fa-plus"></i> Add New Product</a>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">No</th>
                        <th style="width: 100px;">Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if (mysqli_num_rows($queryProducts) > 0): 
                        while ($row = mysqli_fetch_assoc($queryProducts)): 
                            $imgPath = "../assets/img/products/" . $row['category'] . "/" . $row['image'];
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <img src="<?= $imgPath ?>" alt="<?= $row['name'] ?>" class="product-img-thumb" onerror="this.src='https://via.placeholder.com/50'">
                        </td>
                        <td>
                            <strong><?= $row['name'] ?></strong><br>
                            <small style="color: #888;"><?= substr($row['description'], 0, 50) ?>...</small>
                        </td>
                        <td>
                            <span style="padding: 4px 8px; background: #eee; border-radius: 4px; font-size: 12px; text-transform: capitalize;">
                                <?= $row['category'] ?>
                            </span>
                        </td>
                        <td>IDR <?= number_format($row['price'], 0, ',', '.') ?></td>
                        <td>
                            <!-- Tombol Edit (Pulpen) -->
                            <a href="products_edit.php?id=<?= $row['id'] ?>" class="btn-action btn-edit" title="Edit Product">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <!-- Tombol Hapus (Sampah) -->
                            <a href="products.php?delete=<?= $row['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Yakin ingin menghapus produk ini?')" title="Delete Product">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #888;">
                            <?php if(isset($_GET['search'])): ?>
                                Product not Found. <a href="products.php" style="color: var(--admin-primary);">See All Products</a>
                            <?php else: ?>
                                Belum ada produk. <a href="products_add.php" style="color: var(--admin-primary);">Tambah Produk Sekarang</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

</body>
</html>