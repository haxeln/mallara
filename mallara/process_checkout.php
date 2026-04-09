<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit; }

// Cek Single Item (Checkout Langsung dari Order Man/Woman)
if (isset($_POST['single_item'])) {
    $item = json_decode($_POST['single_item'], true);
    // Simpan langsung ke session sebagai array
    $_SESSION['checkout_items'] = [$item]; // Jadikan array
    header("Location: checkout.php");
    exit;
}

// ... (Kode lama untuk multiple item dari Cart tetap ada di bawah) ...
if (isset($_POST['selected_indexes']) && is_array($_POST['selected_indexes'])) {
    $user_id = $_SESSION['user_id'];
    $query = mysqli_query($conn, "SELECT c.*, p.name, p.price, p.image, p.category, p.stock as max_stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = '$user_id' ORDER BY c.id ASC");
    $cartData = []; $finalCheckoutItems = [];
    while ($row = mysqli_fetch_assoc($query)) { $cartData[] = $row; }
    foreach ($_POST['selected_indexes'] as $idx) {
        if (isset($cartData[$idx])) { $finalCheckoutItems[] = $cartData[$idx]; }
    }
    $_SESSION['checkout_items'] = $finalCheckoutItems;
    header("Location: checkout.php");
    exit;
} else {
    header("Location: cart.php"); exit;
}
?>