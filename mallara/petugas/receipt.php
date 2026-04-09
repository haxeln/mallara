<?php
session_start();
require '../config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

 $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Cek kepemilikan (Jika Customer, hanya bisa lihat order sendiri)
 $user_role = $_SESSION['role'] ?? '';
 $user_id = $_SESSION['user_id'] ?? 0;

if ($user_role == 'customer') {
    $checkOwn = mysqli_query($conn, "SELECT id FROM orders WHERE id = '$order_id' AND user_id = '$user_id'");
    if (mysqli_num_rows($checkOwn) == 0) {
        die("<h1>Access Denied</h1><p>Anda tidak punya akses ke pesanan ini.</p>");
    }
}

// --- PROSES UPDATE STATUS (Hanya Admin/Petugas) ---
if (isset($_POST['update_status']) && ($user_role == 'admin' || $user_role == 'petugas')) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $update = mysqli_query($conn, "UPDATE orders SET status = '$new_status' WHERE id = '$order_id'");
    if ($update) {
        echo "<script>alert('Status berhasil diubah!'); window.location.href='receipt.php?id=$order_id';</script>";
    }
}

// Ambil Data Order
 $query = mysqli_query($conn, "SELECT o.*, u.full_name, u.email, u.phone, u.address 
                              FROM orders o 
                              JOIN users u ON o.user_id = u.id 
                              WHERE o.id = '$order_id'");
 $order = mysqli_fetch_assoc($query);

if (!$order) { die("Order tidak ditemukan."); }

// PERBAIKAN: Ambil Item Order dari tabel 'order_detail' dengan JOIN ke 'products'
 $items = mysqli_query($conn, "SELECT od.*, p.name as prod_name, p.image 
                              FROM order_detail od 
                              JOIN products p ON od.product_id = p.id 
                              WHERE od.order_id = '$order_id'");

// Format Status untuk Tampilan
function getDisplayStatus($s) {
    $map = [
        'Not Paid' => 'Waiting for payment',
        'Process' => 'Processed',
        'Shipped' => 'Delivered',
        'Done' => 'Done'
    ];
    return $map[$s] ?? $s;
}

 $displayStatus = getDisplayStatus($order['status']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $order['id'] ?> - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #8d1a1a; }
        body { background: #f3f4f6; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        
        .receipt-container { max-width: 700px; margin: 0 auto; background: white; padding: 40px; border-radius: 5px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); position: relative; }
        
        /* Header Struk */
        .receipt-header { text-align: center; border-bottom: 2px dashed #ddd; padding-bottom: 20px; margin-bottom: 20px; }
        .receipt-header h2 { margin: 0; color: var(--primary); font-family: 'Times New Roman', serif; font-size: 32px; letter-spacing: 3px; }
        .receipt-header p { margin: 5px 0 0; color: #666; font-size: 14px; }

        /* Info Order & Customer */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; font-size: 14px; }
        .info-box label { display: block; font-weight: bold; color: #888; font-size: 12px; text-transform: uppercase; margin-bottom: 3px; }
        .info-box div { color: #333; }

        /* Status Section & Edit Button */
        .status-section { 
            background: #f8f9fa; border: 1px solid #eee; padding: 15px; 
            border-radius: 5px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
        }
        .current-status { font-weight: bold; font-size: 16px; color: var(--primary); }
        
        /* Tombol Edit (Pulpen) - Hanya muncul di Admin */
        .btn-edit-status {
            background: var(--primary); color: white; border: none; padding: 8px 12px; 
            border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 13px;
        }
        .btn-edit-status:hover { background: #6d1212; }

        /* Tabel Produk */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { text-align: left; border-bottom: 2px solid #eee; padding: 10px 0; color: #888; font-size: 12px; text-transform: uppercase; }
        td { padding: 15px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; vertical-align: middle; }
        .prod-img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; background: #eee; }
        .col-qty { text-align: center; }
        .col-price { text-align: right; }
        .col-total { text-align: right; font-weight: bold; }

        /* Total Summary */
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .summary-row.final { font-size: 18px; font-weight: bold; color: var(--primary); border-top: 2px solid #eee; padding-top: 15px; margin-top: 10px; }

        /* Action Buttons Bottom */
        .actions { margin-top: 30px; text-align: center; display: flex; justify-content: center; gap: 15px; }
        .btn { padding: 10px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; border: none; cursor: pointer; }
        .btn-print { background: #333; color: white; }
        .btn-close { background: #ddd; color: #333; }

        /* Modal Update Status */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-box { background: white; padding: 25px; border-radius: 8px; width: 350px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; max-width: 100%; }
            .no-print { display: none !important; }
            .status-section { border: 1px solid #000; }
        }
        @media (max-width: 600px) {
            .receipt-container { padding: 20px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <!-- Header -->
    <div class="receipt-header">
        <h2>MALLARA</h2>
        <p>Fashion & Style Store</p>
        <small>Official Receipt</small>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-box">
            <label>Order Info</label>
            <div>INV-<?= sprintf('%04d', $order['id']) ?></div>
            <div><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></div>
        </div>
        <div class="info-box">
            <label>Customer</label>
            <div><?= htmlspecialchars($order['full_name']) ?></div>
            <div><?= htmlspecialchars($order['phone']) ?></div>
        </div>
    </div>

    <!-- Status Bar (Dengan Tombol Edit Pulpen) -->
    <div class="status-section">
        <div>
            <label style="font-size:12px; color:#888; display:block;">Current Status</label>
            <span class="current-status"><?= $displayStatus ?></span>
        </div>
        
        <!-- TOMBOL PULPEN: Hanya muncul untuk Admin/Petugas -->
        <?php if ($user_role == 'admin' || $user_role == 'petugas'): ?>
            <button class="btn-edit-status no-print" onclick="document.getElementById('editModal').style.display='flex'">
                <i class="fas fa-pen"></i> Update Status
            </button>
        <?php endif; ?>
    </div>

    <!-- Product Table -->
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="col-qty">Qty</th>
                <th class="col-price">Price</th>
                <th class="col-total">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if(mysqli_num_rows($items) > 0): ?>
                <?php while($item = mysqli_fetch_assoc($items)): 
                    // Cek gambar
                    $img = "../assets/img/products/" . $item['image'];
                    if(!file_exists($img)) $img = "https://picsum.photos/seed/".$item['product_id']."/50/50";
                ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="<?= $img ?>" class="prod-img">
                            <span><?= htmlspecialchars($item['prod_name']) ?></span>
                        </div>
                    </td>
                    <td class="col-qty"><?= $item['qty'] ?></td>
                    <td class="col-price">IDR <?= number_format($item['price'], 0, ',', '.') ?></td>
                    <td class="col-total">IDR <?= number_format($item['price'] * $item['qty'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align:center; color:#999;">Detail item tidak ditemukan di database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Summary -->
    <div style="text-align: right;">
        <div class="summary-row"><span>Subtotal</span> <span>IDR <?= number_format($order['total'], 0, ',', '.') ?></span></div>
        <div class="summary-row"><span>Shipping</span> <span>IDR 0 (Free)</span></div>
        <div class="summary-row final"><span>Total Paid</span> <span>IDR <?= number_format($order['total'], 0, ',', '.') ?></span></div>
    </div>

    <!-- Footer / Actions -->
    <div class="actions no-print">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Print Struk</button>
        <a href="javascript:history.back()" class="btn btn-close">Close</a>
    </div>
</div>

<!-- MODAL EDIT STATUS -->
<div id="editModal" class="modal">
    <div class="modal-box">
        <h3 style="margin-top:0; color:var(--primary);">Update Order Status</h3>
        <form method="POST">
            <div class="form-group">
                <label>Pilih Status Baru:</label>
                <select name="status" class="form-control" required>
                    <option value="Not Paid" <?= $order['status'] == 'Not Paid' ? 'selected' : '' ?>>Waiting for payment</option>
                    <option value="Process" <?= $order['status'] == 'Process' ? 'selected' : '' ?>>Processed</option>
                    <option value="Shipped" <?= $order['status'] == 'Shipped' ? 'selected' : '' ?>>Delivered</option>
                    <option value="Done" <?= $order['status'] == 'Done' ? 'selected' : '' ?>>Done</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-close" onclick="document.getElementById('editModal').style.display='none'">Batal</button>
                <button type="submit" name="update_status" class="btn btn-print" style="background:var(--primary);">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == document.getElementById('editModal')) {
        document.getElementById('editModal').style.display = "none";
    }
}
</script>

</body>
</html>