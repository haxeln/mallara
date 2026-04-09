<?php
    // 1. Session Start di paling atas
    session_start();
    require '../config/database.php';

    // Cek Login
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $user_id_safe = intval($user_id);

    // --- FUNGSI HELPER UNTUK AMBIL ID ADMIN ---
    function getAdminId($conn) {
        $res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) return $row['id'];
        return 1; // Default fallback jika tidak ketemu
    }

    // --- API CHAT HANDLER (AJAX) ---
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'Invalid request'];

        // API: Kirim Pesan (Disesuaikan ke tabel 'messages')
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['api'] === 'send_chat') {
            $message = mysqli_real_escape_string($conn, $_POST['message']);
            $admin_target = getAdminId($conn);
            
            if(!empty($message)){
                // INSERT KE TABEL 'messages' (Bukan chat_messages)
                $query = "INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                          VALUES ('$user_id_safe', '$admin_target', '$message', NOW())";
                if(mysqli_query($conn, $query)){
                    $response = ['status' => 'success'];
                } else {
                    $response = ['status' => 'error', 'message' => 'DB Error: ' . mysqli_error($conn)];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Pesan kosong'];
            }
        }
        
        // API: Ambil Pesan (Disesuaikan ke tabel 'messages')
        elseif ($_GET['api'] === 'get_chat') {
            $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
            $admin_target = getAdminId($conn);

            // Ambil pesan antara User ini dan Admin
            $sql = "SELECT * FROM messages 
                    WHERE id > '$last_id' 
                    AND (
                        (sender_id = '$user_id_safe' AND receiver_id = '$admin_target') 
                        OR 
                        (sender_id = '$admin_target' AND receiver_id = '$user_id_safe')
                    )
                    ORDER BY created_at ASC";
                    
            $result = mysqli_query($conn, $sql);
            
            $messages = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $messages[] = [
                    'id' => $row['id'],
                    'sender' => ($row['sender_id'] == $user_id_safe) ? 'user' : 'admin', 
                    'message' => htmlspecialchars($row['message']),
                    'image' => $row['image'] ?? null,
                    'time' => date('H:i', strtotime($row['created_at']))
                ];
            }
            $response = ['status' => 'success', 'messages' => $messages];
        }

        echo json_encode($response);
        exit;
    }

    // --- HANDLE CANCELLATION REQUEST ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
        $order_id = intval($_POST['order_id']);
        $cancel_reason = isset($_POST['cancel_reason']) ? mysqli_real_escape_string($conn, $_POST['cancel_reason']) : '';
        
        $check_query = mysqli_query($conn, "SELECT id FROM orders WHERE id = '$order_id' AND user_id = '$user_id_safe' AND status = 'Not Paid'");
        
        if (mysqli_num_rows($check_query) > 0) {
            $update_query = "UPDATE orders SET status = 'Cancelled', cancel_reason = '$cancel_reason' WHERE id = '$order_id'";
            mysqli_query($conn, $update_query);
        }
        
        $current_filter = isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : '';
        echo "<script>window.location.href='orders.php" . $current_filter . "';</script>";
        exit;
    }

    // --- FILTER LOGIC ---
    $active_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $filter_sql = "";
    switch ($active_filter) {
        case 'unpaid': $filter_sql = "AND status = 'Not Paid'"; break;
        case 'process': $filter_sql = "AND (status = 'Process' OR status = 'Packed')"; break;
        case 'shipped': $filter_sql = "AND (status = 'Delivery' OR status = 'Delivered')"; break;
        case 'completed': $filter_sql = "AND (status = 'Completed' OR status = 'Done')"; break;
        case 'cancelled': $filter_sql = "AND status = 'Cancelled'"; break;
        default: $filter_sql = ""; break;
    }

    // --- COUNTER LOGIC ---
    $count_query = mysqli_query($conn, "
        SELECT 
            COUNT(*) as count_all,
            SUM(status = 'Not Paid') as count_unpaid,
            SUM(status IN ('Process', 'Packed')) as count_process,
            SUM(status IN ('Delivery', 'Delivered')) as count_shipped,
            SUM(status IN ('Completed', 'Done')) as count_completed,
            SUM(status = 'Cancelled') as count_cancelled
        FROM orders WHERE user_id = '$user_id_safe'
    ");
    
    $counts = mysqli_fetch_assoc($count_query);
    $count_all = intval($counts['count_all']);
    $count_unpaid = intval($counts['count_unpaid']);
    $count_process = intval($counts['count_process']);
    $count_shipped = intval($counts['count_shipped']);
    $count_completed = intval($counts['count_completed']);
    $count_cancelled = intval($counts['count_cancelled']);

    // 2. Query Orders
    $all_orders = mysqli_query($conn, "SELECT * FROM orders WHERE user_id = '$user_id_safe' $filter_sql ORDER BY id DESC");

    function getStatusLabel($status) {
        $label = $status; $class = '';
        switch($status) {
            case 'Not Paid': $label = 'Waiting For Payment'; $class = 'status-unpaid'; break;
            case 'Packed': case 'Process': $label = 'Being Processed'; $class = 'status-process'; break;
            case 'Delivery': case 'Delivered': $label = 'Being Shipped'; $class = 'status-shipped'; break;
            case 'Completed': case 'Done': $label = 'Order Completed'; $class = 'status-done'; break;
            case 'Cancelled': $label = 'Cancelled'; $class = 'status-cancelled'; break;
            default: $label = $status; $class = 'status-unpaid';
        }
        return ['label' => $label, 'class' => $class];
    }

    function getProductImage($item) {
        $item_image = $item['image'] ?? '';
        $item_category = strtolower($item['category'] ?? 'woman');
        $folder = ($item_category == 'man') ? 'man' : 'woman';
        $imgPath = "../assets/img/products/" . $folder . "/" . $item_image;
        if(!file_exists($imgPath)) {
            $altFolder = ($folder == 'man') ? 'woman' : 'man';
            $altPath = "../assets/img/products/" . $altFolder . "/" . $item_image;
            if(file_exists($altPath)) { $imgPath = $altPath; } else { $imgPath = "https://picsum.photos/seed/".md5($item_image)."/200/200.jpg"; }
        }
        return $imgPath;
    }

    function getPaymentLabel($method) {
        $labels = ['bank' => 'Transfer Bank', 'ewallet' => 'E-Wallet', 'cod' => 'COD (Cash On Delivery)'];
        return $labels[$method] ?? ucfirst($method ?? 'Belum dipilih');
    }

    function getEmptyMessage($filter) {
        switch($filter) {
            case 'unpaid': return 'Tidak ada pesanan menunggu pembayaran.';
            case 'process': return 'Tidak ada pesanan yang sedang diproses.';
            case 'shipped': return 'Tidak ada pesanan yang sedang dikirim.';
            case 'completed': return 'Belum ada pesanan yang selesai.';
            case 'cancelled': return 'Tidak ada pesanan yang dibatalkan.';
            default: return 'Anda belum memiliki riwayat pesanan.';
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Orders - Mallara</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root { 
                --primary: #8d1a1a; --bg: #f8f9fa; --text: #333; 
                --primary-light: #fbecec; --chat-bg: #e5ddd5;
                --tick-grey: #999999;
                --tick-blue: #34b7f1;
            }
            
            body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; color: var(--text); padding-bottom: 50px; }
            .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
            
            .page-top { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
            .back-btn { width: 40px; height: 40px; background: white; border: 1px solid #ddd; border-radius: 50%; color: var(--primary); text-decoration: none; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
            .back-btn:hover { background: var(--primary); color: white; }
            .page-title { font-size: 28px; color: var(--primary); margin: 0; font-family: 'Times New Roman', serif; }

            /* ==================== TAB NAVIGATION ==================== */
            .order-tabs-container { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 25px; overflow: hidden; border: 1px solid #eee; }
            .order-tabs { display: flex; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
            .order-tabs::-webkit-scrollbar { display: none; }
            .tab-item { padding: 15px 20px; color: #666; text-decoration: none; font-size: 14px; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.3s; display: flex; align-items: center; gap: 8px; cursor: pointer; }
            .tab-item:hover { color: var(--primary); background-color: #fafafa; }
            .tab-item.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: 700; background-color: var(--primary-light); }
            .tab-badge { background: #eee; color: #555; font-size: 11px; padding: 2px 8px; border-radius: 10px; min-width: 20px; text-align: center; transition: 0.3s; }
            .tab-item.active .tab-badge { background: var(--primary); color: white; }

            /* ==================== ORDER CARDS ==================== */
            .order-card { background: white; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eee; }
            .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; }
            .order-info h4 { margin: 0; font-size: 16px; color: #222; }
            .order-date { font-size: 13px; color: #888; margin-top: 4px; }
            .status-badge { padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
            .status-unpaid { background: #fff3cd; color: #856404; }
            .status-process { background: #d1ecf1; color: #0c5460; }
            .status-shipped { background: #cce5ff; color: #004085; }
            .status-done { background: #d4edda; color: #155724; }
            .status-cancelled { background: #f8d7da; color: #721c24; }

            .item-list { display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; }
            .item-card { display: flex; align-items: center; gap: 20px; background: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
            .item-thumb { width: 100px; height: 100px; object-fit: cover; border-radius: 6px; background: #ddd; flex-shrink: 0; border: 1px solid #e0e0e0; }
            .item-details { flex: 1; display: flex; flex-direction: column; justify-content: center; }
            .item-name { font-size: 16px; font-weight: 700; color: #222; margin-bottom: 8px; line-height: 1.3; }
            .item-meta-row { display: flex; gap: 15px; font-size: 13px; color: #666; align-items: center; margin-bottom: 5px;}
            .meta-tag { background: #fff; border: 1px solid #ddd; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; }
            .meta-tag.size { border-color: #8d1a1a; color: #8d1a1a; }
            .price-row { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 5px; width: 100%; }
            .item-price-label { font-size: 12px; color: #888; margin-bottom: 2px; }
            .item-price { font-weight: 700; color: #333; font-size: 15px; }
            .item-subtotal { font-weight: 800; color: var(--primary); font-size: 16px; margin-left: auto; }

            /* Timeline */
            .order-timeline { display: flex; justify-content: space-between; align-items: center; margin: 25px 0; padding: 10px 0; position: relative; }
            .order-timeline::before { content: ''; position: absolute; top: 20px; left: 30px; right: 30px; height: 2px; background: #eee; z-index: 0; }
            .step { position: relative; z-index: 1; text-align: center; flex: 1; }
            .step-icon { width: 40px; height: 40px; background: white; border: 2px solid #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; color: #ccc; transition: 0.3s; }
            .step-label { font-size: 11px; color: #999; font-weight: 600; text-transform: uppercase; }
            .step.active .step-icon { border-color: var(--primary); background: white; color: var(--primary); box-shadow: 0 0 0 4px rgba(141, 26, 26, 0.1); }
            .step.completed .step-icon { border-color: var(--primary); background: var(--primary); color: white; }
            .step.completed .step-label, .step.active .step-label { color: var(--primary); }

            .card-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px dashed #eee; }
            .total-price { color: var(--primary); font-size: 18px; font-weight: bold; font-family: 'Times New Roman', serif; }
            .btn-action { padding: 8px 20px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; cursor: pointer; border: none; }
            .btn-outline { background: white; color: var(--primary); border: 1px solid var(--primary); }
            .btn-outline:hover { background: var(--primary); color: white; }
            .btn-primary { background: var(--primary); color: white; border: 1px solid var(--primary); }
            .btn-primary:hover { opacity: 0.9; }
            .btn-danger { background: white; color: #8d1a1a; border: 1px solid #8d1a1a; }
            .btn-danger:hover { background: #8d1a1a; color: white; }
            .btn-chat { background: white; color: #28a745; border: 1px solid #28a745; }
            .btn-chat:hover { background: #28a745; color: white; }

            .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 1px dashed #ccc; }
            .empty-state i { font-size: 50px; color: #ddd; margin-bottom: 20px; }
            .empty-state h3 { margin: 0 0 10px; color: #555; font-family: 'Times New Roman', serif; }
            .btn-start-shopping { display: inline-block; background: var(--primary); color: white; padding: 12px 30px; border-radius: 4px; text-decoration: none; font-weight: 600; }

            /* ==================== MODAL GENERAL ==================== */
            .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; padding: 15px; }
            .modal-overlay.active { display: flex; animation: fadeIn 0.25s ease; }
            .modal-box { background: white; border-radius: 12px; width: 100%; max-width: 580px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); position: relative; margin: auto; animation: slideUp 0.3s ease; display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
            .modal-close { position: absolute; top: 15px; right: 15px; width: 36px; height: 36px; background: #f5f5f5; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #666; transition: 0.2s; z-index: 10; }
            .modal-close:hover { background: #e0e0e0; color: #333; }
            .modal-header { background: var(--primary); color: white; padding: 20px 25px; border-radius: 12px 12px 0 0; flex-shrink: 0; }
            .modal-header h3 { margin: 0; font-size: 20px; font-family: 'Times New Roman', serif; }
            .modal-header .modal-inv { font-size: 13px; opacity: 0.85; margin-top: 4px; }
            .modal-body { padding: 25px; flex: 1; overflow-y: auto; overflow-x: hidden; }
            .modal-body::-webkit-scrollbar { width: 6px; }
            .modal-body::-webkit-scrollbar-thumb { background-color: #ccc; border-radius: 3px; }
            
            .modal-section { margin-bottom: 20px; }
            .modal-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); margin-bottom: 12px; padding-bottom: 6px; border-bottom: 2px solid var(--primary); display: inline-block; }
            .info-grid { display: grid; grid-template-columns: 100px 1fr; gap: 8px 15px; font-size: 14px; }
            .info-label { color: #888; font-weight: 600; }
            .info-value { color: #333; }
            .modal-item { display: flex; gap: 15px; padding: 12px; background: #fafafa; border-radius: 8px; border: 1px solid #eee; margin-bottom: 10px; }
            .modal-item:last-child { margin-bottom: 0; }
            .modal-item img { width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #e0e0e0; }
            .modal-item-info { flex: 1; }
            .modal-item-name { font-weight: 700; font-size: 14px; color: #222; margin-bottom: 4px; }
            .modal-item-meta { font-size: 12px; color: #888; margin-bottom: 4px; }
            .modal-item-price { font-weight: 700; color: var(--primary); font-size: 14px; }
            .modal-total { text-align: right; font-size: 20px; font-weight: bold; color: var(--primary); font-family: 'Times New Roman', serif; padding-top: 15px; border-top: 2px dashed #eee; }
            
            /* Modal Timeline */
            .modal-timeline { display: flex; justify-content: space-between; position: relative; padding: 10px 0; }
            .modal-timeline::before { content: ''; position: absolute; top: 18px; left: 25px; right: 25px; height: 2px; background: #eee; z-index: 0; }
            .modal-step { position: relative; z-index: 1; text-align: center; flex: 1; }
            .modal-step-icon { width: 36px; height: 36px; background: white; border: 2px solid #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; color: #ccc; font-size: 13px; }
            .modal-step-label { font-size: 10px; color: #999; font-weight: 600; text-transform: uppercase; }
            .modal-step.completed .modal-step-icon { border-color: var(--primary); background: var(--primary); color: white; }
            .modal-step.active .modal-step-icon { border-color: var(--primary); color: var(--primary); box-shadow: 0 0 0 3px rgba(141,26,26,0.1); }
            .modal-step.completed .modal-step-label, .modal-step.active .modal-step-label { color: var(--primary); }
            .modal-footer { padding: 15px 25px; border-top: 1px solid #eee; text-align: center; flex-shrink: 0; background: white; }
            .modal-footer .btn-action { padding: 10px 30px; font-size: 14px; }

            /* ==================== CHAT MODAL STYLES ==================== */
            .chat-body { 
                background-color: #e5ddd5; 
                background-image: url('https://www.transparenttextures.com/patterns/subtle-white-feathers.png'); 
                flex: 1; 
                padding: 20px; 
                overflow-y: auto; 
                display: flex; 
                flex-direction: column; 
                gap: 10px; 
                border-bottom: 1px solid #ddd;
            }
            
            .message { 
                max-width: 80%; 
                padding: 10px 15px; 
                border-radius: 12px; 
                font-size: 14px; 
                line-height: 1.4; 
                position: relative; 
                box-shadow: 0 1px 2px rgba(0,0,0,0.1); 
                word-wrap: break-word;
            }
            
            .msg-admin { 
                background: #ffffff; 
                align-self: flex-start; 
                border-top-left-radius: 2px; 
                color: #333;
            }
            .msg-me { 
                background: #dcf8c6; 
                align-self: flex-end; 
                border-top-right-radius: 2px; 
                color: #333;
            }
            
            .msg-image {
                max-width: 100%;
                border-radius: 8px;
                margin-top: 5px;
                display: block;
                cursor: pointer;
            }

            .msg-meta {
                display: flex;
                justify-content: flex-end;
                align-items: center;
                gap: 5px;
                margin-top: 4px;
                font-size: 11px;
                float: right;
                margin-left: 10px;
                padding-top: 4px;
                clear: both;
            }

            .msg-time { color: #888; }
            .msg-status { 
                color: var(--tick-grey); 
                font-size: 12px; 
                display: flex; 
                align-items: center; 
            }
            .msg-status.read { color: var(--tick-blue); }

            .chat-footer { padding: 10px; background: #f0f0f0; display: flex; gap: 10px; align-items: center; }
            .chat-input { 
                flex: 1; padding: 12px 20px; border: 1px solid #ccc; border-radius: 25px; 
                outline: none; font-size: 14px;
            }
            .chat-input:focus { border-color: var(--primary); }
            .btn-send-chat { 
                width: 45px; height: 45px; border-radius: 50%; background: var(--primary); color: white; 
                border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;
                transition: 0.2s;
            }
            .btn-send-chat:hover { transform: scale(1.05); }
            .btn-send-chat:disabled { background: #ccc; cursor: not-allowed; }

            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

            @media (max-width: 600px) {
                .card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
                .item-card { flex-direction: column; align-items: flex-start; }
                .item-thumb { width: 100%; height: 200px; }
                .price-row { flex-direction: column; gap: 5px; margin-top: 10px; width: 100%; align-items: flex-start; }
                .item-subtotal { margin-left: 0; margin-top: 5px; width: 100%; text-align: right; }
                .card-footer { flex-direction: column; align-items: flex-start; gap: 15px; }
                .btn-action { width: 100%; justify-content: center; }
                .modal-box { margin: 0; width: 100%; height: 100%; max-height: 100vh; border-radius: 0; }
                .modal-overlay { padding: 0; align-items: flex-end; justify-content: flex-end; background: rgba(0,0,0,0.5); }
                .modal-overlay.active { display: flex; }
                .info-grid { grid-template-columns: 80px 1fr; font-size: 13px; }
            }
        </style>
    </head>
    <body>

        <!-- NAVBAR FALLBACK -->
        <?php 
            $navPath = __DIR__ . '/../partials/navbar.php';
            if (file_exists($navPath)) { include $navPath; } 
            else { 
        ?>
        <nav style="background:white; padding:15px 5%; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display:flex; justify-content:space-between; align-items: center; border-bottom: 1px solid #eee;">
            <div style="font-size: 24px; font-weight: bold; color: #333; font-family: 'Times New Roman', serif; letter-spacing: 1px;">
                <a href="index.php" style="text-decoration: none; color: #333;">MALLARA</a>
            </div>
        </nav>
        <?php } ?>

        <div class="container">
            <div class="page-top">
                <a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
                <h1 class="page-title">Pesanan Saya</h1>
            </div>

            <!-- TAB NAVIGATION -->
            <div class="order-tabs-container">
                <div class="order-tabs">
                    <a href="?filter=all" class="tab-item <?php echo $active_filter == 'all' ? 'active' : ''; ?>">All <span class="tab-badge"><?= $count_all ?></span></a>
                    <a href="?filter=unpaid" class="tab-item <?php echo $active_filter == 'unpaid' ? 'active' : ''; ?>">Waiting Payment <span class="tab-badge"><?= $count_unpaid ?></span></a>
                    <a href="?filter=process" class="tab-item <?php echo $active_filter == 'process' ? 'active' : ''; ?>">Processed <span class="tab-badge"><?= $count_process ?></span></a>
                    <a href="?filter=shipped" class="tab-item <?php echo $active_filter == 'shipped' ? 'active' : ''; ?>">Delivered <span class="tab-badge"><?= $count_shipped ?></span></a>
                    <a href="?filter=completed" class="tab-item <?php echo $active_filter == 'completed' ? 'active' : ''; ?>">Done <span class="tab-badge"><?= $count_completed ?></span></a>
                    <a href="?filter=cancelled" class="tab-item <?php echo $active_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled <span class="tab-badge"><?= $count_cancelled ?></span></a>
                </div>
            </div>

            <div id="orders-list">
                <?php if (mysqli_num_rows($all_orders) > 0): ?>
                    <?php while ($order = mysqli_fetch_assoc($all_orders)): 
                        $items_json = $order['items_detail'];
                        $items = !empty($items_json) ? json_decode($items_json, true) : [];
                        if (!is_array($items)) $items = [];

                        $statusData = getStatusLabel($order['status']);
                        $status = $order['status'];
                        
                        $isCOD = ($order['payment_method'] == 'cod');
                        $step1_class = ($isCOD) ? '' : (($status == 'Not Paid') ? 'active' : (($status != 'Cancelled') ? 'completed' : ''));
                        $step2_class = ($status == 'Process') ? 'active' : ((in_array($status, ['Delivered', 'Done', 'Completed'])) ? 'completed' : '');
                        $step3_class = ($status == 'Delivered') ? 'active' : ((in_array($status, ['Done', 'Completed'])) ? 'completed' : '');
                        $step4_class = (in_array($status, ['Done', 'Completed'])) ? 'completed' : '';
                        
                        $canReview = in_array($status, ['Done', 'Completed']);
                        $payMethod = getPaymentLabel($order['payment_method']);
                        $shipName = htmlspecialchars($order['shipping_name'] ?? '-');
                        $shipPhone = htmlspecialchars($order['shipping_phone'] ?? '-');
                        $shipAddr = htmlspecialchars($order['shipping_address'] ?? '-');
                        $invoiceCode = htmlspecialchars($order['invoice_code']);
                    ?>
                    <!-- ORDER CARD -->
                    <div class="order-card">
                        <div class="card-header">
                            <div class="order-info">
                                <h4>INV-<?= $invoiceCode ?></h4>
                                <div class="order-date"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
                            </div>
                            <span class="status-badge <?= $statusData['class'] ?>"><?= $statusData['label'] ?></span>
                        </div>
                        
                        <div class="item-list">
                            <?php if(!empty($items)): ?>
                                <?php foreach($items as $item): 
                                    $imgPath = getProductImage($item);
                                    $subtotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                                ?>
                                    <div class="item-card">
                                        <img src="<?= $imgPath ?>" alt="Product" class="item-thumb">
                                        <div class="item-details">
                                            <div class="item-name"><?= htmlspecialchars($item['name'] ?? 'Unknown') ?></div>
                                            <div class="item-meta-row">
                                                <span class="meta-tag">Qty: <?= $item['quantity'] ?? 1 ?></span>
                                                <span class="meta-tag size">Size: <?= htmlspecialchars($item['size'] ?? '-') ?></span>
                                            </div>
                                            <div class="price-row">
                                                <div>
                                                    <div class="item-price-label">Unit Price</div>
                                                    <div class="item-price">IDR <?= number_format($item['price'] ?? 0, 0, ',', '.') ?></div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <div class="item-price-label">Subtotal</div>
                                                    <div class="item-subtotal">IDR <?= number_format($subtotal, 0, ',', '.') ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="order-timeline">
                            <div class="step <?= $step1_class ?>"><div class="step-icon"><i class="fas fa-wallet"></i></div><div class="step-label">Paid</div></div>
                            <div class="step <?= $step2_class ?>"><div class="step-icon"><i class="fas fa-box-open"></i></div><div class="step-label">Process</div></div>
                            <div class="step <?= $step3_class ?>"><div class="step-icon"><i class="fas fa-truck"></i></div><div class="step-label">Delivered</div></div>
                            <div class="step <?= $step4_class ?>"><div class="step-icon"><i class="fas fa-check"></i></div><div class="step-label">Done</div></div>
                        </div>

                        <div class="card-footer">
                            <div class="total-price">Total Order: IDR <?= number_format($order['total'], 0, ',', '.') ?></div>
                            
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <button class="btn-action btn-outline" onclick="openModal(<?= $order['id'] ?>)">
                                    <i class="fas fa-eye"></i> Detail
                                </button>

                                <!-- Tombol Chat (Hanya butuh user_id, tapi kita passing order_id untuk konteks) -->
                                <button class="btn-action btn-chat" onclick="openChatModal(<?= $order['id'] ?>)">
                                    <i class="fas fa-comments"></i> Chat
                                </button>

                                <?php if($status == 'Not Paid'): ?>
                                <button class="btn-action btn-danger" onclick="openCancelModal(<?= $order['id'] ?>)">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>

                                <?php if($canReview): ?>
                                <a href="review_order.php?order_id=<?= $order['id'] ?>" class="btn-action btn-primary">
                                    <i class="fas fa-star"></i> Review
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL DETAIL -->
                    <div class="modal-overlay" id="modal-<?= $order['id'] ?>" onclick="closeModalOutside(event, <?= $order['id'] ?>)">
                        <div class="modal-box">
                            <button class="modal-close" onclick="closeModal(<?= $order['id'] ?>)"><i class="fas fa-times"></i></button>
                            <div class="modal-header">
                                <h3>Order Details</h3>
                                <div class="modal-inv">INV-<?= $invoiceCode ?></div>
                            </div>
                            <div class="modal-body">
                                <div class="modal-section">
                                    <div class="modal-section-title">Shipping Info</div>
                                    <div class="info-grid">
                                        <span class="info-label">Name</span><span class="info-value"><?= $shipName ?></span>
                                        <span class="info-label">Phone</span><span class="info-value"><?= $shipPhone ?></span>
                                        <span class="info-label">Address</span><span class="info-value"><?= $shipAddr ?></span>
                                        <span class="info-label">Payment</span><span class="info-value"><?= $payMethod ?></span>
                                    </div>
                                </div>
                                <div class="modal-section">
                                    <div class="modal-section-title">Products</div>
                                    <?php foreach($items as $item): 
                                        $subtotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                                        $imgPath = getProductImage($item);
                                    ?>
                                        <div class="modal-item">
                                            <img src="<?= $imgPath ?>" alt="Product">
                                            <div class="modal-item-info">
                                                <div class="modal-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                                <div class="modal-item-meta">Size: <?= htmlspecialchars($item['size']) ?> &bull; Qty: <?= $item['quantity'] ?></div>
                                                <div class="modal-item-price">IDR <?= number_format($item['price'], 0, ',', '.') ?> x <?= $item['quantity'] ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="modal-total">Total: IDR <?= number_format($order['total'], 0, ',', '.') ?></div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn-action btn-outline" onclick="closeModal(<?= $order['id'] ?>)">Close</button>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL CHAT -->
                    <div class="modal-overlay" id="modal-chat-<?= $order['id'] ?>">
                        <div class="modal-box" style="height: 600px; max-height: 80vh;">
                            <div class="modal-header">
                                <h3><i class="fas fa-headset"></i> Customer Support</h3>
                                <div class="modal-inv">Order: INV-<?= $invoiceCode ?></div>
                            </div>
                            
                            <div class="chat-body" id="chat-body-<?= $order['id'] ?>">
                                <div class="message msg-admin">
                                    Halo! Ada yang bisa kami bantu?
                                    <span class="msg-time">System</span>
                                </div>
                            </div>
                            
                            <div class="chat-footer">
                                <input type="text" class="chat-input" id="chat-input-<?= $order['id'] ?>" placeholder="Ketik pesan..." onkeypress="handleEnter(event, <?= $order['id'] ?>)">
                                <button class="btn-send-chat" onclick="sendMessage(<?= $order['id'] ?>)">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            
                            <button class="modal-close" onclick="closeChatModal(<?= $order['id'] ?>)" style="top: 10px; right: 10px;"><i class="fas fa-times"></i></button>
                        </div>
                    </div>

                    <!-- MODAL CANCEL -->
                    <div class="modal-overlay" id="modal-cancel-<?= $order['id'] ?>">
                        <div class="modal-box">
                            <button class="modal-close" onclick="closeCancelModal(<?= $order['id'] ?>)"><i class="fas fa-times"></i></button>
                            <div class="modal-header" style="background: #8d1a1a;">
                                <h3>Cancel Order</h3>
                            </div>
                            <div class="modal-body">
                                <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-bottom:15px;">Yakin ingin membatalkan pesanan ini?</div>
                                <form method="POST" action="orders.php" id="form-cancel-<?= $order['id'] ?>">
                                    <input type="hidden" name="cancel_order" value="1">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <div class="form-group">
                                        <label>Reason</label>
                                        <select name="cancel_reason" class="form-control" required>
                                            <option value="" disabled selected>Select Reason</option>
                                            <option value="Wrong item">Wrong item</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer" style="display: flex; justify-content: space-between; gap: 10px;">
                                <button class="btn-action btn-outline" onclick="closeCancelModal(<?= $order['id'] ?>)" style="flex:1">No</button>
                                <button type="submit" form="form-cancel-<?= $order['id'] ?>" class="btn-action btn-danger" style="flex:1">Yes, Cancel</button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <h3><?= getEmptyMessage($active_filter) ?></h3>
                        <a href="../index.php" class="btn-start-shopping">Start Shopping</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            // Modal Logic
            function openModal(id) { document.getElementById('modal-' + id).classList.add('active'); document.body.style.overflow = 'hidden'; }
            function closeModal(id) { document.getElementById('modal-' + id).classList.remove('active'); document.body.style.overflow = ''; }
            function closeModalOutside(e, id) { if(e.target.classList.contains('modal-overlay')) closeModal(id); }
            
            function openCancelModal(id) { document.getElementById('modal-cancel-' + id).classList.add('active'); }
            function closeCancelModal(id) { document.getElementById('modal-cancel-' + id).classList.remove('active'); }

            // Chat Logic
            const chatIntervals = {}; 
            const chatLastIds = {};

            function openChatModal(id) {
                const modal = document.getElementById('modal-chat-' + id);
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Load messages immediately
                loadMessages(id);
                
                // Set interval to poll messages
                if(!chatIntervals[id]) {
                    chatIntervals[id] = setInterval(() => { loadMessages(id); }, 2000);
                }
            }

            function closeChatModal(id) {
                document.getElementById('modal-chat-' + id).classList.remove('active');
                document.body.style.overflow = '';
                if(chatIntervals[id]) {
                    clearInterval(chatIntervals[id]);
                    delete chatIntervals[id];
                }
            }

            function handleEnter(event, id) { if (event.key === 'Enter') sendMessage(id); }

            async function sendMessage(orderId) {
                const input = document.getElementById('chat-input-' + orderId);
                const msg = input.value.trim();
                if(msg === "") return;

                // Tampilkan pesan user langsung (optimistic UI)
                const chatBody = document.getElementById('chat-body-' + orderId);
                const div = document.createElement('div');
                div.className = 'message msg-me';
                div.innerHTML = `${msg} <div class="msg-meta"><span class="msg-time">Sending...</span></div>`;
                chatBody.appendChild(div);
                chatBody.scrollTop = chatBody.scrollHeight;
                
                input.value = "";

                // Kirim ke API
                const formData = new FormData();
                formData.append('order_id', orderId); // Kirim order id untuk validasi (opsional)
                formData.append('message', msg);

                try {
                    const response = await fetch('orders.php?api=send_chat', { method: 'POST', body: formData });
                    // Tidak perlu alert sukses, biarkan polling mengupdate status
                } catch (error) {
                    console.error("Error sending:", error);
                }
            }

            async function loadMessages(orderId) {
                const lastId = chatLastIds[orderId] || 0;
                try {
                    const response = await fetch(`orders.php?api=get_chat&order_id=${orderId}&last_id=${lastId}`);
                    const data = await response.json();

                    if (data.status === 'success' && data.messages.length > 0) {
                        const chatBody = document.getElementById('chat-body-' + orderId);
                        
                        data.messages.forEach(msg => {
                            // Cek apakah pesan sudah ada (untuk menghindari duplikat)
                            if (!document.getElementById('msg-' + msg.id)) {
                                const div = document.createElement('div');
                                div.className = `message ${msg.sender === 'user' ? 'msg-me' : 'msg-admin'}`;
                                div.id = 'msg-' + msg.id;
                                div.innerHTML = `
                                    ${msg.message}
                                    <div class="msg-meta">
                                        <span class="msg-time">${msg.time}</span>
                                    </div>
                                `;
                                chatBody.appendChild(div);
                                chatBody.scrollTop = chatBody.scrollHeight;
                            }
                            chatLastIds[orderId] = msg.id;
                        });
                    }
                } catch (error) {
                    console.error("Error loading chat:", error);
                }
            }
        </script>
    </body>
    </html>