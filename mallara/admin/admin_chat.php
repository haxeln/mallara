<?php
session_start();
require '../config/database.php';

// --- 1. CEK KEAMANAN ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

 $admin_id = (int)$_SESSION['user_id'];
 // Ambil ID Customer dari URL (dikirim dari tombol chat di orders.php)
 $customer_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($customer_id == 0) {
    header("Location: orders.php");
    exit;
}

// --- 2. AMBIL DATA CUSTOMER ---
 $custQuery = mysqli_query($conn, "SELECT full_name, photo FROM users WHERE id = '$customer_id'");
 $customer = mysqli_fetch_assoc($custQuery);
 $custName = $customer['full_name'] ?? 'Unknown';
 $custPhoto = $customer['photo'] ?? '';

// --- 3. LOGIKA KIRIM PESAN ADMIN ---
if (isset($_POST['send_message'])) {
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $chatImage = null;

    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] == 0) {
        $targetDir = __DIR__ . "/../uploads/chat/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . '_admin_' . basename($_FILES["chat_file"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["chat_file"]["tmp_name"], $targetFilePath)) {
                $chatImage = $fileName;
            }
        }
    }

    if (!empty($message) || !empty($chatImage)) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, message, image, created_at) 
                VALUES ('$admin_id', '$customer_id', '$message', '$chatImage', NOW())";
        
        if(mysqli_query($conn, $sql)) {
            header("Location: admin_chat.php?user_id=$customer_id");
            exit;
        }
    }
}

// --- 4. HANDLE AJAX REQUEST (FIX: Tambahkan Logika Order di sini juga) ---
if (isset($_GET['get_chat'])) {
    
    // --- AMBIL DATA ORDER UNTUK AJAX (Agar tidak hilang saat refresh) ---
    $orderQueryAjax = mysqli_query($conn, "SELECT * FROM orders WHERE user_id = '$customer_id' ORDER BY id DESC LIMIT 1");
    $orderDataAjax = mysqli_fetch_assoc($orderQueryAjax);

    // OUTPUT KARTU ORDER
    if($orderDataAjax) {
        echo '<div class="message order-summary">
                <div class="order-header">
                    <span>Halo! Terima kasih telah menghubungi CS Mallara</span>
                    <i class="fas fa-receipt" style="opacity:0.5"></i>
                </div>
                <div class="order-info-row">
                    <span class="order-label">Order Code:</span>
                    <span class="order-value">'.htmlspecialchars($orderDataAjax['invoice_no'] ?? 'INV-XXXX').'</span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">Date:</span>
                    <span class="order-value">'.date('d M Y, H:i', strtotime($orderDataAjax['created_at'])).'</span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">Payment:</span>
                    <span class="order-value">'.htmlspecialchars($orderDataAjax['payment_method'] ?? 'E-Wallet').'</span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">Status:</span>
                    <span class="order-value" style="color: green;">'.htmlspecialchars($orderDataAjax['status'] ?? 'Being Processed').'</span>
                </div>
                <div class="order-items">
                    <img src="https://via.placeholder.com/40" class="order-item-img" alt="Item">
                    <div class="order-item-details">
                        <span class="order-item-name">Black Tie Midi Dress</span>
                        <span class="order-item-meta">XL, 1 Item</span>
                    </div>
                </div>
                <div class="order-total">
                    Total: Rp '.number_format($orderDataAjax['total_price'] ?? 0, 0, ',', '.').'
                </div>
              </div>';
    }

    // --- LANJUT AMBIL PESAN ---
    $chatQueryAjax = mysqli_query($conn, "
        SELECT * FROM messages 
        WHERE sender_id = '$customer_id' OR receiver_id = '$customer_id'
        ORDER BY created_at ASC
    ");
    
    // LOGIKA CENTANG BIRU (READ RECEIPT)
    // Cek apakah customer sudah membaca pesan admin (read_at tidak null)
    
    if ($chatQueryAjax && mysqli_num_rows($chatQueryAjax) > 0) {
        while($row = mysqli_fetch_assoc($chatQueryAjax)): 
            $isMe = ($row['sender_id'] == $admin_id);
            $msgClass = $isMe ? 'admin' : 'customer';
            
            // Tentukan tipe centang
            $checks = '';
            if ($isMe) {
                // Jika pesan dari admin dan read_at ada isinya = Biru
                $isRead = !empty($row['read_at']);
                if ($isRead) {
                    $checks = '<i class="fas fa-check-double check-icon blue"></i>'; // Sudah dibaca (Biru)
                } else {
                    $checks = '<i class="fas fa-check-double check-icon gray"></i>'; // Belum dibaca (Abu-abu)
                }
            }
    ?>
            <div class="message <?= $msgClass ?>">
                <?php if(!empty($row['message'])): ?>
                    <span class="msg-text"><?= nl2br(htmlspecialchars($row['message'])) ?></span> <?= $checks ?>
                <?php endif; ?>

                <?php if(!empty($row['image'])): ?>
                    <div>
                        <img src="../uploads/chat/<?= htmlspecialchars($row['image']) ?>" class="chat-image" onclick="window.open(this.src)">
                        <?= $checks ?>
                    </div>
                <?php endif; ?>

                <span class="msg-time" style="color: <?= $isMe ? '#8d1a1a' : '#666' ?>;">
                    <?= date('H:i', strtotime($row['created_at'])) ?>
                </span>
            </div>
    <?php 
        endwhile;
    } else {
        echo '<div style="text-align:center; color:#999; margin-top:20px; font-style:italic;">Belum ada pesan.</div>';
    }
    exit;
}

// --- 5. LOAD PERTAMA (INITIAL LOAD) ---
// Ambil data order untuk tampilan awal
 $orderQuery = mysqli_query($conn, "SELECT * FROM orders WHERE user_id = '$customer_id' ORDER BY id DESC LIMIT 1");
 $orderData = mysqli_fetch_assoc($orderQuery);

 $chatQuery = mysqli_query($conn, "
    SELECT * FROM messages 
    WHERE sender_id = '$customer_id' OR receiver_id = '$customer_id'
    ORDER BY created_at ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat - Mallara</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background-color: #f3f4f6; display: flex; min-height: 100vh; color: #333; }
        .sidebar { width: 260px; background-color: #8d1a1a; color: white; position: fixed; top: 0; left: 0; height: 100%; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-header h2 { margin: 0; font-family: 'Times New Roman', serif; letter-spacing: 2px; font-size: 28px; }
        .sidebar-header span { font-size: 12px; opacity: 0.7; }
        .sidebar-menu { flex: 1; padding-top: 20px; overflow-y: auto; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; font-size: 15px; }
        .menu-item i { width: 25px; text-align: center; margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.15); color: white; border-left-color: #fff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); display: flex; flex-direction: column; height: calc(100vh - 60px); box-sizing: border-box; }
        .chat-header { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .btn-back { background: white; color: #8d1a1a; border: 1px solid #8d1a1a; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 14px; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; width: fit-content; }
        .btn-back:hover { background: #8d1a1a; color: white; }
        .chat-wrapper { background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; flex-direction: column; flex: 1; overflow: hidden; border: 1px solid #ddd; }
        .chat-top-bar { background: #8d1a1a; color: white; padding: 15px 20px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #6d0000; }
        .chat-avatar { width: 45px; height: 45px; border-radius: 50%; background: white; color: #8d1a1a; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; overflow: hidden; flex-shrink: 0; }
        .chat-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .chat-body { flex: 1; padding: 20px; overflow-y: auto; background: #f9f9f9; display: flex; flex-direction: column; gap: 15px; }
        
        /* --- STYLE PESAN --- */
        .message { 
            max-width: 70%; 
            padding: 10px 15px; 
            border-radius: 15px; 
            font-size: 14px; 
            line-height: 1.4; 
            position: relative; 
            word-wrap: break-word; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.1); 
            border: none; 
        }

        .message.admin { 
            align-self: flex-end; 
            background: #fff0f0 !important; /* Merah Muda Pucat */
            color: #8d1a1a !important;      /* Teks Merah */
            border-bottom-right-radius: 2px; 
        }

        .message.customer { 
            align-self: flex-start; 
            background: #ffffff !important; /* Putih */
            color: #333 !important;         /* Hitam */
            border-bottom-left-radius: 2px; 
        }

        /* --- STYLE ORDER CARD --- */
        .message.order-summary {
            align-self: center; 
            background: #fff9c4 !important; /* Kuning Muda */
            color: #333;
            width: 90%;
            max-width: 100%;
            border: 1px solid #fbc02d;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: left;
        }
        .order-header {
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 8px;
            margin-bottom: 8px;
            font-weight: bold;
            color: #8d1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .order-label { color: #666; }
        .order-value { font-weight: 600; }
        .order-items {
            background: rgba(255,255,255,0.5);
            padding: 8px;
            border-radius: 5px;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .order-item-img {
            width: 40px; height: 40px;
            background: #ddd;
            border-radius: 4px;
            object-fit: cover;
        }
        .order-item-details { flex: 1; }
        .order-item-name { font-weight: bold; font-size: 14px; display: block; }
        .order-item-meta { font-size: 12px; color: #666; }
        .order-total {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed rgba(0,0,0,0.1);
            text-align: right;
            font-weight: bold;
            font-size: 16px;
        }

        .chat-image { 
            max-width: 200px; 
            border-radius: 8px; 
            margin-top: 5px; 
            display: block; 
            cursor: pointer; 
            border: none;
        }
        
        .msg-time { font-size: 10px; display: block; margin-top: 5px; opacity: 0.7; text-align: right; }
        
        /* Style Centang */
        .check-icon { font-size: 12px; margin-left: 4px; vertical-align: middle; }
        .check-icon.blue { color: #34b7f1; } /* Biru */
        .check-icon.gray { color: #999; }  /* Abu-abu */

        .chat-input-area { padding: 15px; background: white; border-top: 1px solid #eee; display: flex; gap: 10px; align-items: center; }
        .chat-input-wrapper { position: relative; flex: 1; display: flex; align-items: center; }
        .chat-input { flex: 1; padding: 12px 45px 12px 15px; border: 1px solid #ddd; border-radius: 25px; outline: none; font-size: 14px; }
        .chat-input:focus { border-color: #8d1a1a; }
        .btn-attach { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #888; cursor: pointer; font-size: 18px; transition: 0.3s; }
        .btn-attach:hover { color: #8d1a1a; }
        .btn-send { background: #8d1a1a; color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; flex-shrink: 0; }
        .btn-send:hover { background: #6d0000; transform: scale(1.05); }
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h2, .sidebar-header span, .menu-item span { display: none; } .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 15px; } }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><h2>MALLARA</h2><span>ADMIN PANEL</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="products.php" class="menu-item"><i class="fas fa-box"></i> <span>Products</span></a>
            <a href="trending.php" class="menu-item"><i class="fas fa-fire"></i> <span>Trending</span></a>
            <a href="orders.php" class="menu-item active"><i class="fas fa-shopping-bag"></i> <span>Transactions</span></a>
            <a href="users.php" class="menu-item"><i class="fas fa-users"></i> <span>Users</span></a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
            <a href="backup_data.php" class="menu-item"><i class="fas fa-database"></i> <span>Backup Data</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="menu-item" style="color: #ffcccc;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="chat-header">
            <a href="orders.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Transaksi</a>
            <h2 style="margin: 0; color: #8d1a1a; font-family: 'Times New Roman', serif; font-size: 24px;">Customer Chat</h2>
        </div>

        <div class="chat-wrapper">
            <div class="chat-top-bar">
                <div class="chat-avatar">
                    <?php if(!empty($custPhoto) && file_exists(__DIR__ . '/../uploads/' . $custPhoto)): ?>
                        <img src="../uploads/<?= htmlspecialchars($custPhoto) ?>" alt="Customer">
                    <?php else: ?>
                        <?= strtoupper(substr($custName, 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight: bold; font-size: 16px;"><?= htmlspecialchars($custName) ?></div>
                    <div style="font-size: 12px; opacity: 0.8;">Customer ID: #<?= $customer_id ?></div>
                </div>
            </div>

            <div class="chat-body" id="chatBody">
                <!-- TAMPILAN AWAL: KARTU ORDER -->
                <?php if($orderData): ?>
                    <div class="message order-summary">
                        <div class="order-header">
                            <span>Halo! Terima kasih telah menghubungi CS Mallara</span>
                            <i class="fas fa-receipt" style="opacity:0.5"></i>
                        </div>
                        <div class="order-info-row">
                            <span class="order-label">Order Code:</span>
                            <span class="order-value"><?= htmlspecialchars($orderData['invoice_no'] ?? 'INV-XXXX') ?></span>
                        </div>
                        <div class="order-info-row">
                            <span class="order-label">Date:</span>
                            <span class="order-value"><?= date('d M Y, H:i', strtotime($orderData['created_at'])) ?></span>
                        </div>
                        <div class="order-info-row">
                            <span class="order-label">Payment:</span>
                            <span class="order-value"><?= htmlspecialchars($orderData['payment_method'] ?? 'E-Wallet') ?></span>
                        </div>
                        <div class="order-info-row">
                            <span class="order-label">Status:</span>
                            <span class="order-value" style="color: green;"><?= htmlspecialchars($orderData['status'] ?? 'Being Processed') ?></span>
                        </div>
                        <div class="order-items">
                            <img src="https://via.placeholder.com/40" class="order-item-img" alt="Item">
                            <div class="order-item-details">
                                <span class="order-item-name">Black Tie Midi Dress</span>
                                <span class="order-item-meta">XL, 1 Item</span>
                            </div>
                        </div>
                        <div class="order-total">
                            Total: Rp <?= number_format($orderData['total_price'] ?? 0, 0, ',', '.') ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($chatQuery && mysqli_num_rows($chatQuery) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($chatQuery)): 
                        $isMe = ($row['sender_id'] == $admin_id);
                        $msgClass = $isMe ? 'admin' : 'customer';
                        
                        $checks = '';
                        if ($isMe) {
                            $isRead = !empty($row['read_at']);
                            if ($isRead) {
                                $checks = '<i class="fas fa-check-double check-icon blue"></i>';
                            } else {
                                $checks = '<i class="fas fa-check-double check-icon gray"></i>';
                            }
                        }
                    ?>
                        <div class="message <?= $msgClass ?>">
                            <?php if(!empty($row['message'])): ?>
                                <span class="msg-text"><?= nl2br(htmlspecialchars($row['message'])) ?></span> <?= $checks ?>
                            <?php endif; ?>
                            <?php if(!empty($row['image'])): ?>
                                <div>
                                    <img src="../uploads/chat/<?= htmlspecialchars($row['image']) ?>" class="chat-image" onclick="window.open(this.src)">
                                    <?= $checks ?>
                                </div>
                            <?php endif; ?>
                            <span class="msg-time" style="color: <?= $isMe ? '#8d1a1a' : '#666' ?>;">
                                <?= date('H:i', strtotime($row['created_at'])) ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; color:#999; margin-top:20px; font-style:italic;">Belum ada pesan.</div>
                <?php endif; ?>
            </div>

            <form method="POST" class="chat-input-area" enctype="multipart/form-data">
                <div class="chat-input-wrapper">
                    <input type="text" name="message" class="chat-input" placeholder="Ketik balasan admin..." autocomplete="off">
                </div>
                <button type="submit" name="send_message" class="btn-send" title="Kirim Pesan"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
    </main>

    <script>
        function scrollToBottom() {
            const chatBody = document.getElementById('chatBody');
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function loadNewMessages() {
            const xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    const chatBody = document.getElementById('chatBody');
                    // Simpan posisi scroll lama
                    let oldScrollHeight = chatBody.scrollHeight;
                    let oldScrollTop = chatBody.scrollTop;
                    
                    chatBody.innerHTML = this.responseText;
                    
                    // Cek apakah user sedang membaca chat lama (scroll naik)
                    // Jika user dekat bawah (< 100px), auto scroll ke bawah
                    // Jika user di atas (baca pesan lama), jangan scroll otomatis
                    const isNearBottom = (oldScrollHeight - oldScrollTop - chatBody.clientHeight) < 100;
                    
                    if (isNearBottom) {
                         scrollToBottom();
                    }
                }
            };
            xhttp.open("GET", "admin_chat.php?user_id=<?= $customer_id ?>&get_chat=1", true);
            xhttp.send();
        }

        scrollToBottom();
        setInterval(loadNewMessages, 3000);
    </script>
</body>
</html>