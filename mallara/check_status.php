<?php
session_start();
require 'config/database.php';

// Hanya admin atau user pemilik pesanan yang boleh cek status (opsional, tapi di sini kita izinkan session user juga agar timeline di order bisa real-time)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false]);
    exit;
}

// Cek parameter order_id
if (!isset($_POST['order_id'])) {
    echo json_encode(['status' => false]);
    exit;
}

 $order_id = intval($_POST['order_id']);

// Cek pesanan ini milik user yang sedang login?
// (Sesuaikan kebutuhan privasi Anda, jika admin bisa cek semua, hapus logika cek owner di bawah)
// $checkOwner = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$order_id'");

// Ambil status terbaru dari database
 $query = mysqli_query($conn, "SELECT status FROM orders WHERE id = '$order_id'");

if ($query) {
    $row = mysqli_fetch_assoc($query);
    echo json_encode(['status' => $row['status']]);
} else {
    echo json_encode(['status' => false]);
}
?>