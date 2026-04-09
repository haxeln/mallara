<?php
session_start();
// HAPUS SEMUA SESSION LAMA AGAR BERSIH
session_unset();
session_destroy();

// Mulai session baru
session_start();

include "../config/database.php";

 $error = '';

if(isset($_POST['login'])){
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));

    // Query cek user
    $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND password='".md5($password)."'");
    
    if(mysqli_num_rows($query) > 0){
        $data = mysqli_fetch_assoc($query);

        // DEBUG: Tampilkan Role yang diambil dari Database (Hapus baris ini setelah berhasil)
        echo "<script>console.log('Role dari DB: " . $data['role'] . "');</script>";

        // Set Session FRESH (Baru)
        $_SESSION['user_id'] = $data['id'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['full_name'] = $data['full_name'];
        $_SESSION['role'] = $data['role']; // PENTING: Mengambil role DARI DATABASE
        $_SESSION['email'] = $data['email'];

        // Redirect berdasarkan Role FRESH
        if($data['role']=="admin"){
            header("Location: ../admin/dashboard.php");
            exit;
        } elseif($data['role']=="petugas"){
            header("Location: ../petugas/dashboard.php");
            exit;
        } else {
            header("Location: ../index.php");
            exit;
        }

    } else {
        $error = "Email atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mallara</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS SAMA SEPERTI SEBELUMNYA */
        :root { --mallara-red: #8b0000; }
        body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f0f0f0; }
        .auth-container { display: flex; width: 900px; height: 550px; background: white; border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); overflow: hidden; }
        .auth-left { flex: 1; background: #333; position: relative; overflow: hidden; }
        .auth-left img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
        .brand-overlay { position: absolute; top: 30px; left: 30px; color: white; z-index: 2; }
        .brand-overlay h2 { margin: 0; font-family: 'Times New Roman', serif; font-size: 36px; letter-spacing: 2px; }
        .auth-right { flex: 1; padding: 50px; display: flex; flex-direction: column; justify-content: center; color: #333; }
        .auth-header h2 { margin: 0 0 10px 0; font-family: 'Times New Roman', serif; font-size: 32px; color: var(--mallara-red); }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; text-transform: uppercase; }
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .form-control { width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; outline: none; }
        .form-control:focus { border-color: var(--mallara-red); }
        .btn-login { width: 100%; padding: 14px; background: var(--mallara-red); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; text-transform: uppercase; transition: 0.3s; }
        .btn-login:hover { background: #600000; }
        .divider { display: flex; align-items: center; text-align: center; margin: 20px 0; color: #aaa; font-size: 12px; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #eee; }
        .divider span { padding: 0 10px; }
        .auth-footer { text-align: center; font-size: 14px; }
        .auth-footer a { color: var(--mallara-red); font-weight: bold; text-decoration: none; }
        .alert-error { background: #ffe6e6; color: #d8000c; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #d8000c; }
        @media (max-width: 768px) { .auth-container { flex-direction: column; width: 90%; } .auth-left { height: 200px; } .auth-right { padding: 30px 20px; } }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-left">
            <div class="brand-overlay"><h2>MALLARA</h2><p>Welcome Back</p></div>
            <img src="../assets/img/login-regis.jpeg" alt="Login Fashion" onerror="this.src='https://picsum.photos/seed/fashionlogin/600/800'">
        </div>
        <div class="auth-right">
            <div class="auth-header"><h2>LOGIN</h2><p>Silakan masuk ke akun Anda</p></div>
            <?php if($error): ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label class="form-label">Email / Username</label>
                    <div class="input-group"><i class="fas fa-envelope"></i><input type="text" name="email" class="form-control" placeholder="contoh@email.com" required></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" class="form-control" placeholder="••••••••" required></div>
                </div>
                <button type="submit" name="login" class="btn-login">LOGIN</button>
            </form>
            <div class="divider"><span>or</span></div>
            <div class="auth-footer">Belum punya akun? <a href="register.php">Registrasi disini</a></div>
        </div>
    </div>
</body>
</html>