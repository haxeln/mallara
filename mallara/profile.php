<?php
session_start();
// Definisikan BASE_URL agar sesuai dengan index.php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mallara/');
}

// Koneksi Database
if (file_exists(__DIR__ . "/config/database.php")) {
    include __DIR__ . "/config/database.php";
} else {
    die("<h1 style='color:red; text-align:center;'>ERROR: Database config tidak ditemukan.</h1>");
}

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

 $user_id = $_SESSION['user_id'];

// ==========================================
// LOGIC UPDATE PROFILE (DIGABUNG DISINI)
// ==========================================
if (isset($_POST['update_profile'])) {
    
    // 1. Ambil Data dari Form
    // Jika input email disabled/readonly, ambil dari database lama agar tidak kosong
    $oldDataQuery = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
    $oldData = mysqli_fetch_assoc($oldDataQuery);

    $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
    $phone     = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $address   = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    
    // Gunakan email lama jika input kosong (karena disabled)
    $email     = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : $oldData['email'];

    // 2. Handle Upload Foto
    $photoName = $oldData['photo']; // Default pakai foto lama
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $targetDir = __DIR__ . "/uploads/";
        
        // Buat folder jika belum ada
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES["photo"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFilePath)) {
                // Hapus foto lama
                if (!empty($oldData['photo']) && file_exists($targetDir . $oldData['photo'])) {
                    unlink($targetDir . $oldData['photo']);
                }
                $photoName = $fileName;
            }
        }
    }

    // 3. Update Database
    $sql = "UPDATE users SET 
                full_name = '$full_name', 
                email = '$email', 
                phone = '$phone', 
                address = '$address', 
                photo = '$photoName' 
            WHERE id = '$user_id'";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['profile_msg'] = "Profile Updated Successfully!";
        // Refresh halaman agar data terbaru muncul dan POST reset
        header("Location: " . BASE_URL . "profile.php");
        exit;
    } else {
        $_SESSION['profile_msg'] = "Gagal update: " . mysqli_error($conn);
    }
}
// ==========================================
// END LOGIC UPDATE
// ==========================================

// Ambil data user terbaru untuk ditampilkan
 $query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
 $user = mysqli_fetch_assoc($query);

// Pesan sukses
 $msg = '';
if (isset($_SESSION['profile_msg'])) {
    $msg = '<div class="alert alert-success">' . $_SESSION['profile_msg'] . '</div>';
    unset($_SESSION['profile_msg']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Mallara</title>
    
    <!-- CSS UTAMA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* =========================================
           STYLE KHUSUS PROFILE (DIPERTAHANKAN)
           ========================================= */
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; width: 100%; box-sizing: border-box;}
        
        /* Header Section */
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 15px;
        }
        
        .section-title { font-family: 'Times New Roman', serif; color: #8b0000; font-size: 32px; margin: 0; }

        /* Tombol Back */
        .btn-back {
            background: white; color: #8b0000; border: 1px solid #8b0000;
            padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;
            transition: 0.3s; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-back:hover { background: #8b0000; color: white; }

        /* Alert Box */
        .alert { padding: 15px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 25px; border: 1px solid #c3e6cb; font-weight: 500; }

        /* Layout Grid */
        .profile-container { display: flex; gap: 40px; flex-wrap: wrap; }
        
        /* Sidebar */
        .profile-sidebar { flex: 1; min-width: 280px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center; }
        .avatar-large { width: 160px; height: 160px; border-radius: 50%; object-fit: cover; border: 4px solid #8b0000; margin-bottom: 20px; }
        .username { font-size: 20px; font-weight: bold; color: #333; margin-bottom: 5px; }
        .user-email { color: #666; font-size: 14px; margin-bottom: 25px; }
        
        .menu-link {
            display: block; padding: 12px 15px; color: #555; text-decoration: none;
            border-radius: 4px; margin-bottom: 5px; transition: 0.3s; text-align: left; border: 1px solid transparent;
        }
        .menu-link:hover, .menu-link.active { background: #fff0f0; color: #8b0000; border-color: #8b0000; }
        .menu-link i { width: 25px; }

        /* Content */
        .profile-content { flex: 2; min-width: 300px; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .profile-content h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; color: #8b0000; }

        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #444; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; transition: 0.3s; }
        .form-control:focus { border-color: #8b0000; outline: none; box-shadow: 0 0 0 2px rgba(139, 0, 0, 0.1); }
        .form-control[readonly] { background: #f9f9f9; color: #555; cursor: not-allowed; }
        
        .btn-save { 
            background: #8b0000; color: white; border: none; padding: 12px 30px; 
            border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; 
            transition: 0.3s; width: 100%; text-transform: uppercase; letter-spacing: 1px;
        }
        .btn-save:hover { background: #6d0000; transform: translateY(-2px); }

        /* =========================================
           FOOTER STYLE (SAMA PERSIS DENGAN INDEX)
           ========================================= */
        .footer {
            background: #FFEFD4;
            padding: 70px 0 30px;
            color: #8b0000;
            font-family: 'Poppins', sans-serif;
            margin-top: auto; /* Penting agar footer menempel bawah jika konten pendek */
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1.2fr;
            gap: 50px;
        }

        .footer h2 {
            font-family: 'Times New Roman', serif;
            font-size: 28px;
            margin: 0;
            letter-spacing: 2px;
        }

        .footer h4 {
            margin-bottom: 18px;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .footer p {
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 10px;
        }

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer ul li {
            margin-bottom: 10px;
        }

        .footer ul li a {
            text-decoration: none;
            color: #8b0000;
            font-size: 14px;
            transition: 0.3s;
        }

        .footer ul li a:hover {
            opacity: 0.7;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .footer-logo {
            width: 300px;
            margin-bottom: 18px;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 50px auto 0;
            padding-top: 20px;
            border-top: 1px solid rgba(139,0,0,0.2);
            text-align: center;
            font-size: 14px;
        }
        
        @media (max-width: 992px) {
            .footer-container {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
        }

        @media (max-width: 600px) {
            .footer-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-brand {
                align-items: center;
            }
        }
    </style>
</head>
<body>

    <!-- 1. NAVBAR -->
    <?php 
    $navPath = __DIR__ . "/partials/navbar.php";
    if (file_exists($navPath)) {
        include $navPath; 
    } else {
        echo '<nav style="background:white; padding:15px 50px; box-shadow:0 2px 5px rgba(0,0,0,0.1); display:flex; justify-content:space-between; align-items:center;">
                <div style="font-family:\'Times New Roman\', serif; font-size:24px; font-weight:bold; color:#8b0000;">MALLARA</div>
                <div>
                    <a href="'.BASE_URL.'index.php" style="text-decoration:none; color:#333; margin-right:20px;">Home</a>
                    <a href="'.BASE_URL.'cart.php" style="text-decoration:none; color:#333; margin-right:20px;"><i class="fas fa-shopping-cart"></i></a>
                </div>
              </nav>';
    }
    ?>

    <!-- 2. MAIN CONTENT -->
    <div class="container">
        
        <div class="profile-header">
            <h2 class="section-title">Account Settings</h2>
            <a href="<?= BASE_URL ?>index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>

        <?= $msg ?>

        <div class="profile-container">
            
            <!-- Sidebar Foto & Menu -->
            <div class="profile-sidebar">
                <?php 
                    // Logic Foto
                    $userPhoto = isset($user['photo']) ? $user['photo'] : '';
                    $userName  = isset($user['full_name']) ? $user['full_name'] : 'User';
                    $userEmail = isset($user['email']) ? $user['email'] : '';

                    $fsPath = __DIR__ . '/uploads/' . $userPhoto;
                    $photoPath = BASE_URL . 'uploads/' . $userPhoto;
                    $fallbackImg = "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=8b0000&color=fff&size=200";
                    
                    $finalPhoto = (empty($userPhoto) || !file_exists($fsPath)) ? $fallbackImg : $photoPath;
                ?>
                <img src="<?= $finalPhoto ?>" alt="Profile" class="avatar-large">
                
                <div class="username"><?= htmlspecialchars($userName) ?></div>
                <div class="user-email"><?= htmlspecialchars($userEmail) ?></div>
                
                <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                
                <nav>
                    <a href="profile.php" class="menu-link active"><i class="fas fa-user"></i> Edit Profile</a>
                    <a href="customer/orders.php" class="menu-link"><i class="fas fa-box"></i> My Orders</a>
                    <a href="<?= BASE_URL ?>auth/logout.php" class="menu-link" style="color: #dc3545;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Form Edit -->
            <div class="profile-content">
                <h3>Edit Personal Information</h3>
                
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label>Photo Profile</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <small style="color: #888;">Leave blank if you don't want to change the photo.</small>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                        <small style="color: #888;">Email cannot be changed..</small>
                    </div>

                    <div class="form-group">
                        <label>Phone Number (Nomor HP/WA)</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Contoh: 08123456789">
                    </div>

                    <div class="form-group">
                        <label>Delivery Address (Alamat Lengkap)</label>
                        <textarea name="address" class="form-control" rows="4" placeholder="Masukkan alamat lengkap..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" name="update_profile" class="btn-save">Save Changes</button>

                </form>
            </div>
        </div>
    </div>

    <!-- 3. FOOTER (SAMA PERSIS DENGAN INDEX) -->
    <footer class="footer">
        <div class="footer-container">
            <!-- BRAND -->
            <div class="footer-brand">
                <img src="<?= BASE_URL ?>assets/img/logo footer/logo-footerr.png"
                     alt="Mallara Logo"
                     class="footer-logo"
                     onerror="this.style.display='none'">
            </div>

            <!-- SHOP -->
            <div>
                <h4>SHOP</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>customer/order_woman.php">Women</a></li>
                    <li><a href="<?= BASE_URL ?>customer/order_man.php">Men</a></li>
                    <li><a href="#">Tops</a></li>
                </ul>
            </div>

            <!-- COMPANY -->
            <div>
                <h4>COMPANY</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Delivery</a></li>
                    <li><a href="#">Collection</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>

            <!-- HELP -->
            <div>
                <h4>HELP</h4>
                <ul>
                    <li><a href="#">Customer Service</a></li>
                    <li><a href="#">Size Guide</a></li>
                    <li><a href="#">Shipping Information</a></li>
                    <li><a href="#">Returns & Exchanges</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>

            <!-- FOLLOW US -->
            <div>
                <h4>FOLLOW US</h4>
                <p>Stay connected and get the latest updates</p>
                <p>
                    <i class="fab fa-instagram"></i>
                    @mallara.officialstore
                </p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© <?= date('Y'); ?> Mallara. All Rights Reserved</p>
        </div>
    </footer>

</body>
</html>