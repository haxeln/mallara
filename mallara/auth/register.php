<?php
session_start();
require '../config/database.php';

 $error = '';
 $success = '';

if (isset($_POST['register'])) {
    // Ambil semua data dari form (termasuk email baru)
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']); 
    $notelp   = mysqli_real_escape_string($conn, $_POST['notelp']);
    $role     = 'customer'; 

    // 1. Cek apakah username sudah ada
    $cekUser = mysqli_query($conn, "SELECT username FROM users WHERE username = '$username'");
    if (mysqli_num_rows($cekUser) > 0) {
        $error = "Username sudah digunakan!";
    } 
    // 2. Cek apakah email sudah ada
    else {
        $cekEmail = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");
        if (mysqli_num_rows($cekEmail) > 0) {
            $error = "Email sudah digunakan!";
        } 
        // 3. Jika aman, insert data
        else {
            $password_hash = md5($password);
            
            // Query INSERT sekarang mencakup kolom 'email'
            $query = "INSERT INTO users (username, email, password, full_name, no_telp, role) VALUES ('$username', '$email', '$password_hash', '$fullname', '$notelp', '$role')";
            
            if (mysqli_query($conn, $query)) {
                $success = "Registrasi berhasil! Silakan login.";
                // Redirect otomatis setelah 2 detik
                header("refresh:2;url=login.php");
            } else {
                $error = "Terjadi kesalahan: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - Mallara</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- CSS AUTH STYLE --- */
        :root {
            --mallara-red: #8b0000;
            --mallara-cream: #FFEFD4;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #fffbfb;
        }

        .auth-container {
            display: flex;
            width: 1000px; /* Ukuran lebar yang diminta */
            height: auto; 
            min-height: 600px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .auth-left {
            flex: 1;
            background: #333;
            position: relative;
            overflow: hidden;
        }

        .auth-left img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.8;
        }

        .brand-overlay {
            position: absolute;
            top: 30px;
            left: 30px;
            color: white;
            z-index: 2;
        }
        .brand-overlay h2 {
            margin: 0;
            font-family: 'Times New Roman', serif;
            font-size: 36px;
            letter-spacing: 2px;
        }
        .brand-overlay p {
            margin: 5px 0 0 0;
            font-size: 14px;
            letter-spacing: 1px;
            opacity: 0.8;
        }

        .auth-right {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: #333;
        }

        .auth-header h2 {
            margin: 0 0 10px 0;
            font-family: 'Times New Roman', serif;
            font-size: 32px;
            color: var(--mallara-red);
        }
        .auth-header p {
            margin: 0 0 20px 0;
            color: #777;
            font-size: 14px;
        }

        .form-group { margin-bottom: 15px; }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--mallara-red); }

        .btn-register {
            width: 100%;
            padding: 12px;
            background: var(--mallara-red);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
        }
        .btn-register:hover { background: #600000; }

        .auth-footer { text-align: center; margin-top: 15px; font-size: 14px; }
        .auth-footer a { color: var(--mallara-red); font-weight: bold; text-decoration: none; }
        
        .alert-error { color: red; font-size: 13px; margin-bottom: 10px; background: #ffe6e6; padding: 10px; border-radius: 5px; border-left: 4px solid red; }
        .alert-success { color: green; font-size: 13px; margin-bottom: 10px; background: #e6ffe6; padding: 10px; border-radius: 5px; border-left: 4px solid green; }

        @media (max-width: 768px) {
            .auth-container { flex-direction: column; width: 90%; }
            .auth-left { height: 200px; }
            .auth-right { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <!-- KIRI: GAMBAR -->
        <div class="auth-left">
            <div class="brand-overlay">
                <h2>MALLARA</h2>
                <p>Join Our Community</p>
            </div>
            <img src="../assets/img/login-regis.jpeg" alt="Register Fashion" onerror="this.src='https://picsum.photos/seed/fashionregis/600/800'">
        </div>

        <!-- KANAN: FORM REGISTER -->
        <div class="auth-right">
            <div class="auth-header">
                <h2>REGISTRASI</h2>
                <p>Buat akun baru Anda</p>
            </div>

            <?php if($error): ?>
                <div class="alert-error"><?= $error ?></div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert-success"><?= $success ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="fullname" class="form-control" placeholder="Nama Lengkap" required>
                </div>
                
                <!-- KOLOM EMAIL BARU DITAMBAHKAN DI SINI -->
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="contoh@email.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label">No. Telp</label>
                    <input type="number" name="notelp" class="form-control" placeholder="08xxxxxxxxxx" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Buat Username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Buat Password" required>
                </div>
                <button type="submit" name="register" class="btn-register">DAFTAR SEKARANG</button>
            </form>

            <div class="auth-footer">
                Sudah punya akun? <a href="login.php">Login disini</a>
            </div>
        </div>
    </div>

</body>
</html>

<!--
    PENTING: JALANKAN SQL INI DI PHPMYADMIN AGAR TIDAK ERROR
    ===============================================
    ALTER TABLE users ADD COLUMN email VARCHAR(100) AFTER username;
    ===============================================
-->