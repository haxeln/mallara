<?php
session_start();
require 'config/database.php';

// Cek apakah ada parameter order_id yang perlu diproses
if (isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    
    // Ambil data order
    $q = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$order_id'");
    $order = mysqli_fetch_assoc($q);

    // LOGIKA AUTO UPDATE STATUS BARU
    // Jika status masih 'Not Paid' dan total > 0, otomatis ubah jadi 'Processed'
    if ($order['status'] == 'Not Paid' && $order['total'] > 0) {
        
        // Update status menjadi 'Processed'
        mysqli_query($conn, "UPDATE orders SET status = 'Processed', updated_at = NOW() WHERE id = '$order_id'");
        
        // Kirim respon sukses
        echo json_encode(['status' => 'Processed']);
    } else {
        echo json_encode(['status' => $order['status']]);
    }

} else {
    echo json_encode(['status' => false]);
}
?>