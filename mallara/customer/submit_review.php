<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

 $user_id = $_SESSION['user_id'];
 $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Cek Order
 $qOrder = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$order_id' AND user_id = '$user_id'");
 $order = mysqli_fetch_assoc($qOrder);

if (!$order || $order['status'] != 'Completed') {
    header("Location: orders.php"); exit;
}

// Proses Submit Review
if (isset($_POST['submit_review'])) {
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    // Handle Foto Review
    $photoName = $order['photo'] ?? ''; // Foto lama jika ada
    if (isset($_FILES['review_photo']) && $_FILES['review_photo']['error'] === 0) {
        $targetDir = "../uploads/reviews/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . '_review_' . basename($_FILES["review_photo"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        if (in_array($fileType, array('jpg','png','jpeg','gif'))) {
            if (move_uploaded_file($_FILES["review_photo"]["tmp_name"], $targetFilePath)) {
                $photoName = 'uploads/reviews/' . $fileName;
            }
        }
    }

    // Simpan Review (Untuk setiap item di order ini, kita simpan 1 review utama untuk order tsb demi simplifikasi, atau bisa loop items)
    // Di sini kita simpan review terkait Order ID
    $insert = mysqli_query($conn, "INSERT INTO reviews (user_id, order_id, product_id, rating, comment, photo) VALUES ('$user_id', '$order_id', '0', '$rating', '$comment', '$photoName')");
    
    // Update status order jadi 'Rating'
    mysqli_query($conn, "UPDATE orders SET status = 'Rating' WHERE id = '$order_id'");

    header("Location: orders.php?status=success");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Review - Mallara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #8d1a1a; --bg: #f8f9fa; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 50px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .page-title { color: var(--primary); font-family: 'Times New Roman', serif; text-align: center; margin-bottom: 20px; }
        .star-rating { display: flex; justify-content: center; gap: 10px; margin: 20px 0; font-size: 30px; color: #ddd; cursor: pointer; }
        .star-rating i.active { color: #ffc107; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

    <div class="container">
        <h2 class="page-title">Berikan Ulasan Anda</h2>
        <p style="text-align: center; margin-bottom: 20px; color: #666;">Order: <?= $order['invoice_code'] ?></p>
        
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="rating" id="ratingInput" value="0" required>
            
            <div class="star-rating" id="starContainer">
                <i class="fas fa-star" data-val="1"></i>
                <i class="fas fa-star" data-val="2"></i>
                <i class="fas fa-star" data-val="3"></i>
                <i class="fas fa-star" data-val="4"></i>
                <i class="fas fa-star" data-val="5"></i>
            </div>

            <div class="form-group">
                <label>Ulasan</label>
                <textarea name="comment" class="form-control" rows="4" placeholder="Tulis pengalaman Anda..." required></textarea>
            </div>

            <div class="form-group">
                <label>Upload Foto (Opsional)</label>
                <input type="file" name="review_photo" class="form-control" accept="image/*">
            </div>

            <button type="submit" name="submit_review" class="btn-submit">Kirim Ulasan</button>
        </form>
    </div>

    <script>
        const stars = document.querySelectorAll('.star-rating i');
        const ratingInput = document.getElementById('ratingInput');

        stars.forEach(star => {
            star.addEventListener('click', function() {
                const val = this.getAttribute('data-val');
                ratingInput.value = val;
                updateStars(val);
            });
        });

        function updateStars(val) {
            stars.forEach(s => {
                if (s.getAttribute('data-val') <= val) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        }
    </script>

</body>
</html>