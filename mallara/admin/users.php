<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// --- 2. LOGIKA HAPUS USER ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Cek apakah user ini bukan diri sendiri (biar tidak bisa hapus akun sendiri)
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('Anda tidak dapat menghapus akun sendiri!'); window.location.href='users.php';</script>";
        exit;
    }

    $query = mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    
    if ($query) {
        echo "<script>
                alert('User berhasil dihapus!');
                window.location.href='users.php';
              </script>";
    } else {
        echo "<script>alert('Gagal menghapus user!');</script>";
    }
}

// --- 3. AMBIL DATA USER ---
 $query = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Mallara</title>
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

        /* PERBAIKAN: Menambahkan jarak pada header */
        .page-header {
            margin-bottom: 30px;
        }

        /* Table */
        .table-container {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; text-transform: uppercase; }
        tr:hover { background-color: #fafafa; }

        /* Status Badges */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: capitalize; }
        .badge-admin { background-color: #333; color: white; }
        .badge-petugas { background-color: var(--admin-primary); color: white; }
        .badge-customer { background-color: #6c757d; color: white; }

        /* Action Buttons */
        .btn-action {
            padding: 5px 10px; border-radius: 4px; text-decoration: none;
            color: white; margin-right: 5px; display: inline-block; font-size: 12px;
        }
        .btn-delete { background-color: #dc3545; } /* Merah */

        /* Responsif */
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
            <!-- ACTIVE: USERS -->
            <a href="users.php" class="menu-item active">
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
            <h2 class="page-title" style="font-size: 24px; color: var(--admin-primary); font-family: 'Times New Roman', serif; margin:0;">Manajemen User</h2>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if (mysqli_num_rows($query) > 0): 
                        while ($row = mysqli_fetch_assoc($query)): 
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <strong><?= $row['full_name'] ?></strong><br>
                            <small style="color: #999;">Registered: <?= date('d-m-Y', strtotime($row['created_at'])) ?></small>
                        </td>
                        <td><?= $row['username'] ?></td>
                        <td><?= $row['email'] ?></td>
                        <td><?= $row['no_telp'] ?></td>
                        <td>
                            <?php 
                                $badgeClass = '';
                                if ($row['role'] == 'admin') $badgeClass = 'badge-admin';
                                elseif ($row['role'] == 'petugas') $badgeClass = 'badge-petugas';
                                else $badgeClass = 'badge-customer';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= $row['role'] ?></span>
                        </td>
                        <td>
                            <?php if($row['role'] != 'admin'): ?>
                                <span style="color: green; font-weight: bold;">Aktif</span>
                            <?php else: ?>
                                <span style="color: var(--admin-primary); font-weight: bold;">Super Admin</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['id'] != $_SESSION['user_id']): ?>
                                <!-- Tombol Hapus User -->
                                <a href="users.php?delete=<?= $row['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Yakin ingin menghapus user ini? Data user akan hilang permanen.')" title="Hapus User">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php else: ?>
                                <small style="color:#999;">Your Self</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                            Belum ada user terdaftar.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

</body>
</html>