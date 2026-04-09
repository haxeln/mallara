# mallara

user: sopi@gmail.com
123456
 admin:mallara-admin@gmail.com
admin_mallara123
 petugas:mallara-petugas@gmail.com
petugas_mallara123

Mallara

Aplikasi **e-commerce berbasis web** menggunakan **PHP native + MySQL (XAMPP)** tanpa framework, dengan 3 role:

* 🛡️ Admin
* 👔 Petugas
* 🛒 Customer

---

### ⚙️ Teknologi

* PHP (native)
* MySQL
* Apache (XAMPP)
* HTML, CSS, JavaScript
* Chart.js (grafik)

---

### 📁 Fitur Utama

* **Customer**: lihat produk, beli, upload bukti bayar, cek pesanan, review
* **Admin**: kelola produk, kategori, user, pesanan, laporan, backup
* **Petugas**: kelola produk, kategori, pesanan, laporan

---

### 🔄 Alur Pesanan

`Menunggu Pembayaran → Dibayar → Diproses → Dikirim → Selesai`
atau bisa **Dibatalkan**

---

### 🗄️ Database

Tabel utama:

* users
* products
* categories
* orders
* order_items
* reviews
* activity_logs

---

### 🚀 Cara Menjalankan

1. Install XAMPP
2. Copy project ke `htdocs/Mallara`
3. Buat database `bustore` di phpMyAdmin
4. Import SQL
5. Jalankan di browser:
   `http://localhost/Mallara

---

### 🔐 Catatan

* Password sudah aman (bcrypt)
* Perlu peningkatan: **SQL Injection, XSS, CSRF**
