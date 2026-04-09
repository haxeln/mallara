<?php
session_start();
require '../config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// 2. VALIDASI ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<h3 style='text-align:center; margin-top:50px; color:red;'>Invalid Order ID.</h3>");
}

 $order_id = intval($_GET['id']);

// 3. QUERY DATA ORDER (Aman, tanpa shipping_cost)
 $sql_order = "SELECT 
                o.id, 
                o.invoice_code, 
                o.created_at, 
                o.status, 
                o.payment_method, 
                o.total, 
                o.user_id,
                u.full_name, 
                u.email, 
                u.phone, 
                u.address 
              FROM orders o 
              JOIN users u ON o.user_id = u.id 
              WHERE o.id = '$order_id' 
              LIMIT 1";

 $result_order = mysqli_query($conn, $sql_order);

if (!$result_order || mysqli_num_rows($result_order) == 0) {
    die("<h3 style='text-align:center; margin-top:50px; color:red;'>Order Data Not Found.</h3>");
}

 $order = mysqli_fetch_assoc($result_order);

// 4. QUERY ITEM (DIUBAH: Menggunakan tabel 'order_details' BUKAN 'order_items')
// Jika ini masih error, lihat pesan error merah yang muncul di layar.
 $sql_items = "SELECT 
                od.qty, 
                od.price, 
                p.name as product_name 
              FROM order_details od 
              JOIN products p ON od.product_id = p.id 
              WHERE od.order_id = '$order_id'";

 $result_items = mysqli_query($conn, $sql_items);

// CEK ERROR JIKA TABEL order_details JUGA TIDAK ADA
if (!$result_items) {
    // Jika error ini muncul, berarti nama tabelnya bukan 'order_details'.
    // Cek nama tabel yang benar di phpMyAdmin, lalu ganti 'order_details' di baris 54.
    die("<h3 style='text-align:center; color:red; margin-top:50px;'>
            ERROR: Tabel item tidak ditemukan.<br>
            Kemungkinan nama tabelnya bukan 'order_details'.<br>
            Pesan Sistem: " . mysqli_error($conn) . "
         </h3>");
}

 $calculated_subtotal = 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($order['invoice_code']) ?></title>
    
    <!-- LIBRARY UNTUK POP UP NOTIFIKASI (SWEETALERT2) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0; padding: 20px; color: #333;
        }
        .invoice-box {
            max-width: 800px; margin: auto; background: white; padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1); border-radius: 8px;
            border-top: 5px solid #8d1a1a;
        }
        .header {
            display: flex; justify-content: space-between; border-bottom: 2px solid #eee;
            padding-bottom: 20px; margin-bottom: 30px;
        }
        .brand h1 { margin: 0; color: #8d1a1a; font-family: 'Times New Roman', serif; letter-spacing: 2px; font-size: 32px; }
        .invoice-info { text-align: right; }
        .invoice-info h2 { margin: 0; color: #333; font-size: 24px; }
        .invoice-info p { margin: 5px 0; color: #555; }

        .info-grid { display: flex; justify-content: space-between; margin-bottom: 30px; gap: 20px; }
        .info-col { width: 48%; }
        .section-title { font-weight: bold; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px; color: #8d1a1a; text-transform: uppercase; font-size: 14px; }
        .info-row { margin-bottom: 8px; font-size: 14px; }
        .label { font-weight: bold; display: inline-block; width: 100px; color: #555; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #f8f9fa; border-bottom: 2px solid #ddd; padding: 12px; text-align: left; font-size: 13px; color: #555; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        td:last-child { text-align: right; font-weight: bold; }
        th:last-child { text-align: right; }

        .totals { text-align: right; margin-top: 20px; }
        .row { display: flex; justify-content: flex-end; margin-bottom: 8px; font-size: 14px; }
        .label-summary { width: 150px; padding-right: 20px; color: #666; }
        .value-summary { width: 150px; font-weight: bold; }
        .grand-total { font-size: 18px; color: #8d1a1a; font-weight: bold; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }

        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }

        .controls { text-align: center; margin-top: 30px; padding-bottom: 30px; }
        .btn-print {
            padding: 12px 30px; background-color: #333; color: white; text-decoration: none;
            border-radius: 5px; cursor: pointer; border: none; font-size: 16px;
        }
        .btn-print:hover { background-color: #000; }

        @media print {
            body { background-color: white; padding: 0; margin: 0; }
            .invoice-box { box-shadow: none; margin: 0; padding: 0; width: 100%; max-width: 100%; border: none; }
            .controls { display: none; }
        }
    </style>
</head>
<body>

    <div class="invoice-box">
        <div class="header">
            <div class="brand">
                <h1>MALLARA</h1>
                <p>Official Sales Invoice</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p>No: <strong><?= htmlspecialchars($order['invoice_code']) ?></strong></p>
                <p>Date: <?= date('d F Y', strtotime($order['created_at'])) ?></p>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-col">
                <div class="section-title">Bill To</div>
                <div class="info-row"><span class="label">Name:</span> <?= htmlspecialchars($order['full_name']) ?></div>
                <div class="info-row"><span class="label">Phone:</span> <?= htmlspecialchars($order['phone'] ?? '-') ?></div>
                <div class="info-row"><span class="label">Email:</span> <?= htmlspecialchars($order['email']) ?></div>
                <div class="info-row"><span class="label">Address:</span> <?= htmlspecialchars($order['address'] ?? '-') ?></div>
            </div>
            <div class="info-col">
                <div class="section-title">Order Details</div>
                <div class="info-row"><span class="label">Status:</span> <strong><?= strtoupper($order['status']) ?></strong></div>
                <div class="info-row"><span class="label">Payment:</span> <?= strtoupper($order['payment_method']) ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Item Description</th>
                    <th style="text-align: center;">Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (mysqli_num_rows($result_items) > 0):
                    while ($item = mysqli_fetch_assoc($result_items)):
                        $subtotal = $item['qty'] * $item['price'];
                        $calculated_subtotal += $subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td style="text-align: center;"><?= $item['qty'] ?></td>
                    <td>IDR <?= number_format($item['price'], 0, ',', '.') ?></td>
                    <td>IDR <?= number_format($subtotal, 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="4" style="text-align: center; padding: 20px;">No items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <div class="label-summary">Subtotal:</div>
                <div class="value-summary">IDR <?= number_format($calculated_subtotal, 0, ',', '.') ?></div>
            </div>
            
            <div class="row grand-total">
                <div class="label-summary">GRAND TOTAL:</div>
                <div class="value-summary">IDR <?= number_format($order['total'], 0, ',', '.') ?></div>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your order. This document serves as an official receipt.</p>
        </div>
    </div>

    <!-- TOMBOL PRINT -->
    <div class="controls no-print">
        <button onclick="printAndNotify()" class="btn-print">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
        <br>
        <a href="javascript:window.close()" style="color:#666; text-decoration:none; font-size:12px; margin-top:10px; display:inline-block;">Tutup Jendela</a>
    </div>

    <!-- SCRIPT NOTIFIKASI POP UP -->
    <script>
        function printAndNotify() {
            // 1. Jalankan Print
            window.print();

            // 2. Munculkan Pop Up setelah selesai print
            window.onafterprint = function() {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'File Invoice telah didownload atau dicetak.',
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            };
        }
    </script>

</body>
</html>