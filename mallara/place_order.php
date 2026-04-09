<?php
session_start();
require 'config/database.php';

// 1. Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// 2. Cek apakah ada data checkout di session
if (!isset($_SESSION['checkout_items']) || empty($_SESSION['checkout_items'])) {
    header("Location: customer/cart.php");
    exit;
}

 $user_id = $_SESSION['user_id'];
 $checkoutItems = $_SESSION['checkout_items'];

// 3. Proses Form Jika Tombol "Place Order" Ditekan
if (isset($_POST['place_order'])) {
    
    // A. Ambil Data Pengiriman
    $address_choice = $_POST['address_choice'];
    
    if ($address_choice == 'saved') {
        // Ambil data dari DB (Alamat Tersimpan)
        $qUser = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
        $user = mysqli_fetch_assoc($qUser);
        
        $shipping_name = $user['full_name'];
        $shipping_phone = $user['phone'];
        $shipping_address = $user['address'];
    } else {
        // Ambil data dari Form Input (Alamat Baru)
        $shipping_name = mysqli_real_escape_string($conn, $_POST['new_fullname']);
        $shipping_phone = mysqli_real_escape_string($conn, $_POST['new_phone']);
        $shipping_address = mysqli_real_escape_string($conn, $_POST['new_address']);
    }

    // B. Ambil Metode Pembayaran
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $billing_code = mysqli_real_escape_string($conn, $_POST['billing_code'] ?? '');

    // C. Hitung Total Bayar
    $totalBayar = 0;
    foreach ($checkoutItems as $item) {
        $totalBayar += ($item['price'] * $item['quantity']);
    }

    // D. Persiapkan Data Items untuk Kolom `items_detail`
    $final_items = [];
    foreach ($checkoutItems as $item) {
        $final_items[] = [
            'product_id' => $item['product_id'],
            'name'       => $item['name'],
            'price'      => $item['price'],
            'image'      => $item['image'],
            'category'   => $item['category'],
            'quantity'   => $item['quantity'],
            'size'       => $item['size']
        ];
    }
    $items_json = json_encode($final_items);
    $invoice_code = 'MLR-' . strtoupper(substr(md5(time()), 0, 8));

    // E. INSERT ke Database Tabel 'orders'
    $query = "INSERT INTO orders (
                user_id, 
                invoice_code, 
                total, 
                status, 
                payment_method, 
                shipping_name, 
                shipping_phone, 
                shipping_address, 
                items_detail
              ) VALUES (
                '$user_id', 
                '$invoice_code', 
                '$totalBayar', 
                'Not Paid', 
                '$payment_method', 
                '$shipping_name', 
                '$shipping_phone', 
                '$shipping_address', 
                '$items_json'
              )";

    // F. Eksekusi Query Order
    if (mysqli_query($conn, $query)) {
        
        // === START: FUNGSI KURANGI STOK ===
        foreach ($checkoutItems as $item) {
            $prod_id = $item['product_id'];
            $qty     = $item['quantity'];
            
            // Query Update Stok: Stock Lama = Stock Lama - Jumlah Beli
            $update_stock = mysqli_query($conn, "UPDATE products SET stock = stock - '$qty' WHERE id = '$prod_id'");
        }
        // === END: FUNGSI KURANGI STOK ===

        // Hapus session
        unset($_SESSION['checkout_items']);
        
        // Redirect
        header("Location: customer/orders.php");
        exit;
        
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// Jika bukan POST request
header("Location: customer/checkout.php");
exit;
?>