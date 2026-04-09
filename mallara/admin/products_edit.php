<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// --- 2. LOGIKA UPDATE PRODUK ---
if (isset($_POST['update_product'])) {
    $id       = $_POST['id'];
    $name     = mysqli_real_escape_string($conn, $_POST['name']);
    $price    = mysqli_real_escape_string($conn, $_POST['price']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $stock    = mysqli_real_escape_string($conn, $_POST['stock']);
    $desc     = mysqli_real_escape_string($conn, $_POST['description']);
    $rating   = mysqli_real_escape_string($conn, $_POST['rating']);
    $sold     = mysqli_real_escape_string($conn, $_POST['sold']);

    // --- LOGIKA UPDATE GAMBAR (Opsional) ---
    $image = $_FILES['image']['name'];
    $tmp_name = $_FILES['image']['tmp_name'];
    $error = $_FILES['image']['error'];

    // Cek apakah user mengupload gambar baru
    if ($error === 0 && $image != "") {
        $img_ex = pathinfo($image, PATHINFO_EXTENSION);
        $img_ex_lc = strtolower($img_ex);
        $allowed_exs = array("jpg", "jpeg", "png", "webp");

        if (in_array($img_ex_lc, $allowed_exs)) {
            $new_img_name = uniqid("IMG-", true) . '.' . $img_ex_lc;
            $upload_path = '../assets/img/products/' . $category . '/';
            
            // Buat folder jika belum ada
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            move_uploaded_file($tmp_name, $upload_path . $new_img_name);

            // Hapus gambar lama
            $oldData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id='$id'"));
            $oldPath = "../assets/img/products/" . $oldData['category'] . "/" . $oldData['image'];
            if(file_exists($oldPath)) { unlink($oldPath); }

            // Update query dengan gambar baru
            $query = "UPDATE products SET name='$name', price='$price', category='$category', image='$new_img_name', description='$desc', stock='$stock', rating='$rating', sold='$sold' WHERE id='$id'";
        } else {
            echo "<script>alert('Format gambar salah!');</script>";
        }
    } else {
        // Update query TANPA mengubah gambar
        $query = "UPDATE products SET name='$name', price='$price', category='$category', description='$desc', stock='$stock', rating='$rating', sold='$sold' WHERE id='$id'";
    }

    if (mysqli_query($conn, $query)) {
        echo "<script>
                alert('Produk berhasil diperbarui!');
                window.location.href='products.php';
              </script>";
    } else {
        echo "<script>alert('Gagal memperbarui produk!');</script>";
    }
}

// --- 3. AMBIL DATA PRODUK LAMA UNTUK DI EDIT ---
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

 $id = $_GET['id'];
 $data = mysqli_query($conn, "SELECT * FROM products WHERE id='$id'");
 $product = mysqli_fetch_assoc($data);

if (!$product) {
    echo "<script>alert('Produk tidak ditemukan!'); window.location.href='products.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --admin-primary: #8d1a1a; --admin-bg: #f3f4f6; }
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background-color: var(--admin-bg); display: flex; min-height: 100vh; color: #333; }
        
        /* Sidebar */
        .sidebar { width: 260px; background-color: var(--admin-primary); color: white; position: fixed; top: 0; left: 0; height: 100%; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-header h2 { margin: 0; font-family: 'Times New Roman', serif; letter-spacing: 2px; font-size: 28px; }
        .sidebar-header span { font-size: 12px; opacity: 0.7; }
        .sidebar-menu { flex: 1; padding-top: 20px; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; font-size: 15px; }
        .menu-item i { width: 25px; text-align: center; margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.15); color: white; border-left-color: #fff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        
        /* Form */
        .form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); max-width: 800px; }
        .form-title { color: var(--admin-primary); margin-top: 0; font-family: 'Times New Roman', serif; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px;}
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .form-control:focus { border-color: var(--admin-primary); outline: none; }
        textarea.form-control { resize: vertical; height: 100px; }
        
        .preview-img { width: 100px; height: 100px; object-fit: cover; border-radius: 5px; margin-bottom: 10px; border: 1px solid #ddd; }

        .btn-submit { background: var(--admin-primary); color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.3s; }
        .btn-submit:hover { background: #600000; }
        .btn-cancel { background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-left: 10px; }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .sidebar-footer span { display: none; }
            .sidebar-header { padding: 15px 0; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { margin: 0; font-size: 18px; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR (FINAL) -->
    <aside class="sidebar">
        <div class="sidebar-header"><h2>MALLARA</h2><span>ADMIN PANEL</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="products.php" class="menu-item active"><i class="fas fa-box"></i> <span>Products</span></a>
            <a href="trending.php" class="menu-item"><i class="fas fa-fire"></i> <span>Trending</span></a>
            <a href="orders.php" class="menu-item"><i class="fas fa-shopping-bag"></i> <span>Transactions</span></a>
            <a href="users.php" class="menu-item"><i class="fas fa-users"></i> <span>Users</span></a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
            <a href="backup_data.php" class="menu-item"><i class="fas fa-database"></i> <span>Backup Data</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="menu-item" style="color: #ffcccc;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="form-container">
            <h2 class="form-title">Edit Product</h2>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $product['id'] ?>">
                
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-control" value="<?= $product['name'] ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control" required>
                        <option value="man" <?= $product['category'] == 'man' ? 'selected' : '' ?>>Man</option>
                        <option value="woman" <?= $product['category'] == 'woman' ? 'selected' : '' ?>>Woman</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Price (IDR)</label>
                    <input type="number" name="price" class="form-control" value="<?= $product['price'] ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Stock</label>
                    <input type="number" name="stock" class="form-control" value="<?= $product['stock'] ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Rating (1-5)</label>
                    <input type="number" name="rating" class="form-control" value="<?= $product['rating'] ?>" min="0" max="5" step="0.1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Sold</label>
                    <input type="number" name="sold" class="form-control" value="<?= $product['sold'] ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Image (Biarkan kosong jika tidak ingin mengganti)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <!-- Tampilkan gambar lama sebagai preview -->
                    <img src="../assets/img/products/<?= $product['category'] ?>/<?= $product['image'] ?>" class="preview-img" onerror="this.src='https://via.placeholder.com/100'">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Deskripsi produk..."><?= $product['description'] ?></textarea>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="update_product" class="btn-submit">Update Product</button>
                    <a href="products.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>

</body>
</html>