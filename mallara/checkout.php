<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

// --- LOGIC BARU: Handle Buy Now (Langsung Checkout dari Order Page) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'buy_now') {
    $prod_id = intval($_POST['buy_now_id']);
    $qty = intval($_POST['buy_now_qty']);
    $size = mysqli_real_escape_string($conn, $_POST['buy_now_size']);

    // Ambil detail produk dari DB
    $qProd = mysqli_query($conn, "SELECT * FROM products WHERE id = '$prod_id'");
    $product = mysqli_fetch_assoc($qProd);

    if ($product) {
        // Buat array item untuk checkout
        $checkoutItems = [];
        $checkoutItems[] = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'],
            'category' => $product['category'],
            'quantity' => $qty,
            'size' => $size,
            'max_stock' => $product['stock'] // Untuk validasi
        ];

        // Set Session
        $_SESSION['checkout_items'] = $checkoutItems;
        // Redirect agar method berubah GET (refresh halaman) dan load data session
        header("Location: checkout.php"); 
        exit;
    }
}
// --------------------------------------------------------------

// Jika tidak ada session (bukan buy now dan belum pilih dari cart)
if (!isset($_SESSION['checkout_items']) || empty($_SESSION['checkout_items'])) { 
    // Opsional: Redirect ke cart jika kosong
    // header("Location: cart.php"); exit;
}

 $checkoutItems = isset($_SESSION['checkout_items']) ? $_SESSION['checkout_items'] : [];
 $user_id = $_SESSION['user_id'];
 $qUser = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
 $user = mysqli_fetch_assoc($qUser);

 $totalBayar = 0;
foreach ($checkoutItems as $item) { $totalBayar += ($item['price'] * $item['quantity']); }
 $billingCode = 'MLR-' . strtoupper(substr(md5(time()), 0, 8));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Mallara</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #8d1a1a; --bg: #f8f9fa; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; color: #333; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { text-align: center; color: var(--primary); font-family: 'Times New Roman', serif; font-size: 32px; margin-bottom: 40px; text-transform: uppercase; }
        .checkout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .billing-box { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 15px; font-family: monospace; font-size: 18px; text-align: center; letter-spacing: 2px; font-weight: bold; }
        .checkout-item { display: flex; gap: 15px; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }
        .checkout-item img { width: 60px; height: 70px; object-fit: cover; border-radius: 5px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .summary-total { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; }
        .btn-pay { width: 100%; padding: 15px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; text-transform: uppercase; }
        .btn-pay:hover { background: #6d1212; }
        @media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <?php $navPath = __DIR__ . "/partials/navbar.php"; if (file_exists($navPath)) include $navPath; ?>

    <a href="cart.php" class="back-btn" style="top: 90px; position: fixed; left: 20px; text-decoration: none; color: var(--primary); background: white; border: 1px solid #ddd; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);"><i class="fas fa-arrow-left"></i></a>

    <div class="container">
        <h1 class="page-title">Checkout</h1>
        
        <form action="place_order.php" method="POST" id="checkoutForm">
            <div class="checkout-grid">
                <div class="left-col">
                    <div class="card">
                        <h3>Shipping Information</h3>
                        <div class="form-group">
                            <label>Select a Shipping Address</label>
                            <select class="form-control" name="address_choice" id="addressChoice" onchange="toggleAddressField()">
                                <option value="saved">Use Saved Addresses</option>
                                <option value="new">New Address</option>
                            </select>
                        </div>
                        <div id="savedAddressBlock">
                            <div class="form-group"><label>Full Name</label><input type="text" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" readonly style="background:#f9f9f9;"></div>
                            <div class="form-group"><label>Phone Number</label><input type="text" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" readonly style="background:#f9f9f9;"></div>
                            <div class="form-group"><label>Address</label><textarea class="form-control" rows="3" readonly style="background:#f9f9f9;"><?= htmlspecialchars($user['address']) ?></textarea></div>
                        </div>
                        <div id="newAddressBlock" style="display:none;">
                            <div class="form-group"><label>Full Name (Baru)</label><input type="text" name="new_fullname" class="form-control"></div>
                            <div class="form-group"><label>Phone Number (Baru)</label><input type="text" name="new_phone" class="form-control"></div>
                            <div class="form-group"><label>New Address</label><textarea name="new_address" class="form-control" rows="3"></textarea></div>
                        </div>
                    </div>
                    <div class="card">
                        <h3>Payment Method</h3>
                        <div class="form-group">
                            <select class="form-control" name="payment_method" id="paymentMethod" onchange="togglePaymentFields()">
                                <option value="">-- Choose Payment Method --</option>
                                <option value="bank">Transfer Bank</option>
                                <option value="ewallet">E-Wallet (GoPay/OVO)</option>
                                <option value="cod">COD (Cash on Delivery)</option>
                            </select>
                        </div>
                        <div id="paymentFields" style="display:none;">
                            <div class="billing-box" id="billingCodeBox">
                                KODE BILLING: <?= $billingCode ?> 
                                <button type="button" onclick="copyBillingCode()" style="background:none; border:none; color:inherit; cursor:pointer; font-size:14px; margin-left:10px; text-decoration:underline;"><i class="far fa-copy"></i> Salin</button>
                            </div>
                            <div class="form-group">
                                <label>Masukkan Nomor Rek / E-Wallet Anda</label>
                                <input type="text" name="payment_number" class="form-control" placeholder="Contoh: 0812...">
                                <small style="color:#666;">Nomor ini hanya untuk pencocokan pembayaran.</small>
                            </div>
                        </div>
                        <button type="submit" name="place_order" class="btn-pay">PLACE ORDER</button>
                    </div>
                </div>
                <div class="right-col">
                    <div class="card">
                        <h3>Order Summary</h3>
                        <?php foreach ($checkoutItems as $item): 
                            $imgPath = 'assets/img/products/' . $item['category'] . '/' . $item['image'];
                            if(!file_exists($imgPath)) $imgPath = 'https://picsum.photos/seed/checkout/60/70';
                        ?>
                            <div class="checkout-item">
                                <img src="<?= $imgPath ?>" alt="img">
                                <div class="item-details">
                                    <h4><?= $item['name'] ?></h4>
                                    <p>Size: <?= $item['size'] ?> x <?= $item['quantity'] ?></p>
                                    <p style="font-weight:bold; color:var(--primary);">IDR <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="summary-row"><span>Subtotal</span><span>IDR <?= number_format($totalBayar, 0, ',', '.') ?></span></div>
                        <div class="summary-row"><span>Shipping</span><span>Free</span></div>
                        <div class="summary-total"><span>Total</span><span style="color:var(--primary);">IDR <?= number_format($totalBayar, 0, ',', '.') ?></span></div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function toggleAddressField() {
            const choice = document.getElementById('addressChoice').value;
            document.getElementById('savedAddressBlock').style.display = (choice === 'new') ? 'none' : 'block';
            document.getElementById('newAddressBlock').style.display = (choice === 'new') ? 'block' : 'none';
        }
        function togglePaymentFields() {
            const method = document.getElementById('paymentMethod').value;
            document.getElementById('paymentFields').style.display = (method === 'bank' || method === 'ewallet') ? 'block' : 'none';
        }
        function copyBillingCode() {
            var billingText = "<?= $billingCode ?>";
            navigator.clipboard.writeText(billingText).then(function() {
                var btn = document.querySelector('#billingCodeBox button');
                var originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
                btn.style.color = 'green'; btn.style.fontWeight = 'bold';
                setTimeout(function() { btn.innerHTML = originalHtml; btn.style.color = 'inherit'; btn.style.fontWeight = 'normal'; }, 2000);
            });
        }
    </script>
</body>
</html>