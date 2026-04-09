<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

 $user_id = $_SESSION['user_id'];

// --- 1. UPDATE SIZE (BARU) ---
if (isset($_POST['cart_id']) && isset($_POST['new_size'])) {
    $cart_id = intval($_POST['cart_id']);
    $new_size = $_POST['new_size'];
    
    // Update size di database
    mysqli_query($conn, "UPDATE cart SET size = '$new_size' WHERE id = '$cart_id' AND user_id = '$user_id'");
    header("Location: cart.php");
    exit;
}

// --- 2. UPDATE QUANTITY (LAMA) ---
if (isset($_GET['id']) && isset($_GET['qty'])) {
    $cart_id = intval($_GET['id']);
    $new_qty = intval($_GET['qty']);

    if ($new_qty < 1) {
        // Jika qty 0, hapus item (opsional, atau tetap 1)
        // mysqli_query($conn, "DELETE FROM cart WHERE id = '$cart_id' AND user_id = '$user_id'");
        $new_qty = 1;
    }

    // Cek Stok Produk
    $checkStock = mysqli_query($conn, "SELECT stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = '$cart_id'");
    $stockData = mysqli_fetch_assoc($checkStock);
    
    if ($stockData) {
        $max = $stockData['stock'];
        if ($new_qty > $max) {
            header("Location: cart.php?error=stock&max=" . $max);
            exit;
        }

        mysqli_query($conn, "UPDATE cart SET quantity = '$new_qty' WHERE id = '$cart_id' AND user_id = '$user_id'");
    }
    header("Location: cart.php");
    exit;
}
?>