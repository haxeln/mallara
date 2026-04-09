<?php
session_start();
require 'config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

 $user_id = $_SESSION['user_id'];

// =========================================================================
// BAGIAN BARU: LOGIKA MENANGKAP DATA DARI MODAL (ORDER MAN/WOMAN)
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] == 'add_from_modal') {
    
    // 1. Ambil data yang dikirim
    $product_id = intval($_POST['product_id']);
    $qty = intval($_POST['qty']);
    $size = mysqli_real_escape_string($conn, $_POST['size']);

    // 2. Validasi data
    if ($product_id > 0 && $qty > 0) {
        
        // 3. Cek: Apakah item yang SAMA (produk + ukuran) sudah ada di keranjang?
        // Jika Size berbeda, hasil query akan 0, sehingga masuk ke ELSE (Insert Baru)
        $checkQuery = mysqli_query($conn, "SELECT * FROM cart WHERE user_id = '$user_id' AND product_id = '$product_id' AND size = '$size'");

        if (mysqli_num_rows($checkQuery) > 0) {
            // --- JIKA SIZE SAMA ---
            // Update quantity (tambahkan ke yang lama)
            $existingRow = mysqli_fetch_assoc($checkQuery);
            $newQty = $existingRow['quantity'] + $qty;

            // Cek stok maksimal agar tidak melebihi stok gudang
            $stockCheck = mysqli_query($conn, "SELECT stock FROM products WHERE id = '$product_id'");
            $stockData = mysqli_fetch_assoc($stockCheck);
            
            if ($stockData && $newQty <= $stockData['stock']) {
                mysqli_query($conn, "UPDATE cart SET quantity = '$newQty' WHERE id = '" . $existingRow['id'] . "'");
            } else {
                // Jika melebihi stok, set ke maksimal stok yang tersedia
                $maxStock = $stockData['stock'];
                mysqli_query($conn, "UPDATE cart SET quantity = '$maxStock' WHERE id = '" . $existingRow['id'] . "'");
            }

        } else {
            // --- JIKA SIZE BEDA (ATAU BARU SAMA SEKALI) ---
            // Insert data baru ke database (Baris Cart Baru)
            // Kode ini hanya berhasil jika Unique Key Database sudah diperbaiki
            $insertQuery = "INSERT INTO cart (user_id, product_id, quantity, size) VALUES ('$user_id', '$product_id', '$qty', '$size')";
            mysqli_query($conn, $insertQuery);
        }
    }

    // Redirect kembali ke cart agar data refresh
    header("Location: cart.php");
    exit;
}
// =========================================================================
// AKHIR BAGIAN BARU
// =========================================================================


// --- 1. PROSES HAPUS ITEM (LAMA) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $cart_id = intval($_GET['id']);
    $delete = mysqli_query($conn, "DELETE FROM cart WHERE id = '$cart_id' AND user_id = '$user_id'");
    if ($delete) {
        header("Location: cart.php");
        exit;
    }
}

// --- 2. PROSES TAMBAH (LAMA - Opsional, untuk link langsung) ---
if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_GET['id'])) {
    $prod_id = intval($_GET['id']);
    $size = isset($_GET['size']) ? $_GET['size'] : 'M';
    
    $check = mysqli_query($conn, "SELECT * FROM cart WHERE user_id = '$user_id' AND product_id = '$prod_id' AND size = '$size'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE cart SET quantity = quantity + 1 WHERE user_id = '$user_id' AND product_id = '$prod_id' AND size = '$size'");
    } else {
        mysqli_query($conn, "INSERT INTO cart (user_id, product_id, quantity, size) VALUES ('$user_id', '$prod_id', 1, '$size')");
    }
    header("Location: cart.php");
    exit;
}

// --- 3. AMBIL DATA CART ---
// PERBAIKAN: Menambahkan ORDER BY c.id DESC agar barang baru muncul di paling atas
 $query = mysqli_query($conn, "SELECT c.*, p.name, p.price, p.image, p.category, p.stock as max_stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = '$user_id' ORDER BY c.id DESC");

 $totalHargaSemua = 0;
 $totalItemTerpilih = 0;

// --- 4. CEK PESAN ERROR ---
 $errorMessage = '';
if (isset($_GET['error']) && $_GET['error'] == 'stock') {
    $max = isset($_GET['max']) ? $_GET['max'] : 0;
    $errorMessage = "<div class='alert-error'>Gagal! Stok tidak mencukupi. Maksimal pembelian: <b>$max</b> barang.</div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - Mallara</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { --primary: #8d1a1a; --bg: #f8f9fa; --text: #333; --checkbox-color: var(--primary); }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; padding: 0; color: var(--text); }

        /* ALERT ERROR */
        .alert-error {
            background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px;
            border: 1px solid #f5c6cb; border-radius: 8px; text-align: center; font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); animation: shake 0.5s;
        }
        @keyframes shake {
            0% { transform: translateX(0); } 25% { transform: translateX(-5px); } 50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); } 100% { transform: translateX(0); }
        }

        .back-btn {
            position: fixed; top: 90px; left: 20px; z-index: 999;
            width: 45px; height: 45px; background-color: white; border: 1px solid #ddd;
            display: flex; align-items: center; justify-content: center; font-size: 18px;
            color: var(--primary); cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: 0.3s; text-decoration: none; border-radius: 8px;
        }
        .back-btn:hover { background-color: var(--primary); color: white; border-color: var(--primary); transform: translateX(-3px); }

        .container { max-width: 1200px; margin: 60px auto 40px auto; padding: 0 20px; }
        
        .page-header { margin-bottom: 40px; text-align: center; padding-top: 20px; }
        .page-title { color: var(--primary); font-family: 'Times New Roman', serif; font-size: 36px; margin: 0; text-transform: uppercase; letter-spacing: 2px; }

        .cart-wrapper { display: flex; gap: 30px; flex-wrap: wrap; align-items: flex-start; }
        
        .cart-items { flex: 2; min-width: 300px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
        
        .cart-header { padding: 20px; background: #f1f1f1; border-bottom: 1px solid #eee; font-weight: bold; color: #555; text-transform: uppercase; font-size: 14px; letter-spacing: 1px; display: flex; justify-content: space-between; align-items: center; }
        .select-all-container { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: normal; cursor: pointer; }

        .cart-item { display: flex; gap: 20px; padding: 25px; border-bottom: 1px solid #f0f0f0; transition: 0.3s; position: relative; }
        .cart-item:last-child { border-bottom: none; }
        .cart-item:hover { background-color: #fafafa; }

        /* CHECKBOX STYLE CUSTOM */
        .checkbox-wrapper { display: flex; align-items: flex-start; padding-top: 5px; }
        .custom-checkbox {
            appearance: none; width: 22px; height: 22px; border: 2px solid #ccc; border-radius: 4px;
            cursor: pointer; position: relative; transition: 0.2s; flex-shrink: 0; margin-top: 5px;
        }
        .custom-checkbox:checked { background-color: var(--checkbox-color); border-color: var(--checkbox-color); }
        .custom-checkbox:checked::after {
            content: '\f00c'; font-family: "Font Awesome 6 Free"; font-weight: 900;
            color: white; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 14px;
        }

        .item-img { width: 120px; height: 140px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }
        .item-details { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        
        .item-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .item-name { font-size: 18px; font-weight: 600; margin: 0 0 8px; color: #222; line-height: 1.3; }
        .item-cat { font-size: 13px; color: #888; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .item-price { color: var(--primary); font-weight: bold; font-size: 18px; }
        
        .item-remove { color: #bbb; cursor: pointer; font-size: 20px; transition: 0.3s; text-decoration: none; }
        .item-remove:hover { color: var(--primary); }

        .item-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        
        /* EDIT SIZE & QTY WRAPPER */
        .item-controls-wrapper {
            display: flex; flex-direction: column; gap: 12px; width: 100%; max-width: 200px;
        }

        /* CUSTOM SELECT FOR SIZE */
        .size-select {
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 14px; 
            font-weight: 500; 
            color: #333; 
            outline: none; 
            cursor: pointer;
            background-color: #ffffff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%238d1a1a' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center; 
            appearance: none; 
            -webkit-appearance: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            transition: all 0.3s ease;
        }
        .size-select:hover { border-color: #b0b0b0; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .size-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(141, 26, 26, 0.1); background-color: #fff; }

        /* QTY CONTROL (UPDATED DESIGN) */
        .qty-control { 
            display: flex; 
            align-items: center; 
            background-color: white; 
            border-radius: 8px; 
            border: 1px solid #e0e0e0; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            padding: 2px; 
            width: 100%;
        }
        
        .qty-btn { 
            width: 32px; 
            height: 32px; 
            background: #f8f8f8; 
            border: none; 
            border-radius: 50%; 
            font-size: 14px; 
            cursor: pointer; 
            color: #555; 
            transition: all 0.2s ease; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            text-decoration: none; 
            margin: 0; 
        }
        
        .qty-btn:hover { 
            background: var(--primary); 
            color: white; 
            transform: scale(1.1); 
        }
        
        .qty-btn.disabled { 
            opacity: 0.3; 
            cursor: not-allowed; 
            background: #eee;
            color: #999;
        }
        
        .qty-val { 
            flex: 1; 
            text-align: center; 
            font-weight: 600; 
            font-size: 16px; 
            color: #333; 
            line-height: 32px; 
        }


        .cart-summary { flex: 1; min-width: 300px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); padding: 30px; position: sticky; top: 100px; }
        .summary-title { font-size: 20px; font-weight: bold; margin-bottom: 25px; color: #333; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--primary); padding-bottom: 10px; display: inline-block; }
        
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 14px; color: #666; }
        .summary-row span:last-child { font-weight: 600; color: #333; }
        
        .summary-total { display: flex; justify-content: space-between; margin-top: 25px; padding-top: 20px; border-top: 2px solid #eee; font-size: 20px; font-weight: bold; color: #333; margin-bottom: 25px; }
        .total-price { color: var(--primary); font-size: 24px; }

        .btn-checkout {
            width: 100%; padding: 16px; background: var(--primary); color: white; border: none;
            border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
            transition: 0.3s; box-shadow: 0 4px 10px rgba(141, 26, 26, 0.3);
        }
        .btn-checkout:disabled { background: #ccc; cursor: not-allowed; box-shadow: none; }
        .btn-checkout:hover:not(:disabled) { background: #6d1212; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(141, 26, 26, 0.4); }

        .empty-cart { text-align: center; padding: 80px 20px; background: white; border-radius: 12px; color: #888; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .empty-cart i { font-size: 60px; margin-bottom: 20px; color: #ddd; }
        .empty-cart h3 { color: #333; margin-bottom: 10px; }
        .btn-shop { display: inline-block; margin-top: 25px; padding: 12px 30px; background: var(--primary); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; text-transform: uppercase; transition: 0.3s; }
        .btn-shop:hover { background: #6d1212; }

        @media (max-width: 768px) {
            .cart-wrapper { flex-direction: column; }
            .item-img { width: 80px; height: 100px; }
            .page-title { font-size: 28px; }
            .back-btn { top: 80px; left: 15px; width: 40px; height: 40px; }
        }
    </style>
</head>
<body>

    <!-- INCLUDE NAVBAR -->
    <?php 
    $navPath = __DIR__ . "/partials/navbar.php";
    if (file_exists($navPath)) {
        include $navPath; 
    } else {
        // Optional: Fallback jika navbar tidak ada, biar tidak error fatal
        // echo "<div style='background:red; color:white; padding:10px; text-align:center;'>Error: Navbar tidak ditemukan</div>";
    }
    ?>

    <!-- TOMBOL BACK -->
    <a href="index.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container">
        <!-- PESAN ERROR -->
        <?= $errorMessage ?>

        <div class="page-header">
            <h1 class="page-title">My Cart</h1>
        </div>

        <?php if (mysqli_num_rows($query) > 0): ?>
            <div class="cart-wrapper">
                
                <!-- KIRI: DAFTAR BARANG -->
                <div class="cart-items">
                    <div class="cart-header">
                        <span>Product Details</span>
                        <label class="select-all-container">
                            <!-- TAMBAHKAN ATRIBUT CHECKED DI SINI -->
                            <input type="checkbox" id="selectAll" class="custom-checkbox" style="width:18px; height:18px; margin:0;" checked>
                            Select All
                        </label>
                    </div>
                    
                    <?php while ($row = mysqli_fetch_assoc($query)): 
                        // Cek stok
                        $isMaxStock = ($row['quantity'] >= $row['max_stock']);
                        $imgPath = 'assets/img/products/' . $row['category'] . '/' . $row['image'];
                        if(!file_exists($imgPath)) $imgPath = 'https://picsum.photos/seed/cart/120/140';
                    ?>
                        <div class="cart-item">
                            <!-- CHECKBOX PER ITEM -->
                            <div class="checkbox-wrapper">
                                <input type="checkbox" class="item-checkbox custom-checkbox" 
                                       data-price="<?= $row['price'] ?>" 
                                       data-qty="<?= $row['quantity'] ?>"
                                       checked>
                            </div>
                            
                            <img src="<?= $imgPath ?>" alt="Product" class="item-img">
                            
                            <div class="item-details">
                                <div class="item-top">
                                    <div>
                                        <h4 class="item-name"><?= $row['name'] ?></h4>
                                        <span class="item-cat">Stock: <?= $row['max_stock'] ?></span>
                                        <div class="item-price">IDR <?= number_format($row['price'], 0, ',', '.') ?></div>
                                    </div>
                                    <a href="cart.php?action=delete&id=<?= $row['id'] ?>" class="item-remove" title="Remove Item">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>

                                <div class="item-bottom">
                                    <!-- WRAPPER KONTROL (SIZE & QTY) -->
                                    <div class="item-controls-wrapper">
                                        
                                        <!-- SELECT SIZE -->
                                        <form action="update_cart.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="cart_id" value="<?= $row['id'] ?>">
                                            <select name="new_size" class="size-select" onchange="this.form.submit()">
                                                <option value="S" <?= $row['size'] == 'S' ? 'selected' : '' ?>>Size: S</option>
                                                <option value="M" <?= $row['size'] == 'M' ? 'selected' : '' ?>>Size: M</option>
                                                <option value="L" <?= $row['size'] == 'L' ? 'selected' : '' ?>>Size: L</option>
                                                <option value="XL" <?= $row['size'] == 'XL' ? 'selected' : '' ?>>Size: XL</option>
                                            </select>
                                        </form>

                                        <!-- QTY CONTROL -->
                                        <div class="qty-control">
                                            <a href="update_cart.php?id=<?= $row['id'] ?>&qty=<?= $row['quantity'] - 1 ?>" class="qty-btn"><i class="fas fa-minus"></i></a>
                                            <span class="qty-val"><?= $row['quantity'] ?></span>
                                            <?php if($isMaxStock): ?>
                                                <span class="qty-btn disabled"><i class="fas fa-plus"></i></span>
                                            <?php else: ?>
                                                <a href="update_cart.php?id=<?= $row['id'] ?>&qty=<?= $row['quantity'] + 1 ?>" class="qty-btn"><i class="fas fa-plus"></i></a>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                    <div style="font-size:16px; font-weight:600;">Subtotal: <span class="item-subtotal" style="color:var(--primary);">IDR <?= number_format($row['price'] * $row['quantity'], 0, ',', '.') ?></span></div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- KANAN: SUMMARY -->
                <div class="cart-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>total of selected items</span>
                        <span id="totalItemsDisplay">0</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span id="subtotalDisplay">IDR 0</span>
                    </div>

                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>Free</span>
                    </div>

                    <div class="summary-total">
                        <span>Total</span>
                        <span class="total-price" id="totalPriceDisplay">IDR 0</span>
                    </div>

                    <button id="btnCheckout" class="btn-checkout" disabled onclick="processCheckout()">
                        Proceed to Checkout (<span id="btnCount">0</span>)
                    </button>
                    
                    <div style="text-align:center; margin-top:20px;">
                        <a href="index.php" style="color:#666; text-decoration:none; font-size:13px;">&larr; Continue Shopping</a>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-basket"></i>
                <h3>Your Cart is Empty</h3>
                <p>Looks like you haven't added anything to your cart yet.</p>
                <a href="index.php" class="btn-shop">Start Shopping</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- JAVASCRIPT LOGIKA CHECKBOX -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const totalItemsDisplay = document.getElementById('totalItemsDisplay');
            const subtotalDisplay = document.getElementById('subtotalDisplay');
            const totalPriceDisplay = document.getElementById('totalPriceDisplay');
            const btnCheckout = document.getElementById('btnCheckout');
            const btnCount = document.getElementById('btnCount');

            function calculateTotal() {
                let total = 0;
                let count = 0;
                let items = 0;

                itemCheckboxes.forEach(checkbox => {
                    const price = parseFloat(checkbox.getAttribute('data-price'));
                    const qty = parseFloat(checkbox.getAttribute('data-qty'));
                    
                    if (checkbox.checked) {
                        total += (price * qty);
                        count++;
                        items += qty;
                    }
                });

                totalItemsDisplay.innerText = items;
                subtotalDisplay.innerText = 'IDR ' + total.toLocaleString('id-ID');
                totalPriceDisplay.innerText = 'IDR ' + total.toLocaleString('id-ID');
                btnCount.innerText = count;

                if (count > 0) {
                    btnCheckout.disabled = false;
                } else {
                    btnCheckout.disabled = true;
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    itemCheckboxes.forEach(cb => cb.checked = this.checked);
                    calculateTotal();
                });
            }

            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                    if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
                    calculateTotal();
                });
            });

            calculateTotal();
        });

        function processCheckout() {
            const selectedIndexes = [];
            const checkboxes = document.querySelectorAll('.item-checkbox');
            
            checkboxes.forEach((cb, index) => {
                if(cb.checked) {
                    selectedIndexes.push(index);
                }
            });

            if (selectedIndexes.length === 0) {
                alert("Pilih barang terlebih dahulu!");
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process_checkout.php';

            selectedIndexes.forEach(idx => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_indexes[]'; 
                input.value = idx;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>

</body>
</html>