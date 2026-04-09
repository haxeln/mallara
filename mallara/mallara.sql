-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 26, 2026 at 12:40 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mallara`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `size` varchar(10) DEFAULT 'M',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `size`, `created_at`) VALUES
(20, 3, 1, 1, 'S', '2026-02-23 07:05:42'),
(21, 3, 2, 1, 'S', '2026-02-23 12:56:05');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `invoice_code` varchar(50) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT 0.00,
  `status` enum('Not Paid','Packed','Delivery','Completed','Cancelled','Rating') DEFAULT 'Not Paid',
  `payment_method` varchar(50) DEFAULT NULL,
  `shipping_name` varchar(100) DEFAULT NULL,
  `shipping_phone` varchar(20) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `items_detail` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `invoice_code`, `total`, `status`, `payment_method`, `shipping_name`, `shipping_phone`, `shipping_address`, `items_detail`, `created_at`) VALUES
(1, 3, 'MLR-4FE29035', 269000.00, '', 'bank', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"id\":\"1\",\"user_id\":\"3\",\"product_id\":\"1\",\"quantity\":\"1\",\"size\":\"M\",\"created_at\":\"2026-02-22 06:44:36\",\"name\":\"Rib Polo\",\"price\":\"269000\",\"image\":\"IMG-6999e77b7ae042.79720541.jpeg\",\"category\":\"man\",\"max_stock\":\"20\"}]', '2026-02-22 00:59:42'),
(2, 3, 'MLR-1191B3B9', 359000.00, '', 'cod', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"product_id\":\"2\",\"name\":\"V-Knit Sweater\",\"price\":\"359000\",\"image\":\"IMG-699a32063659d8.86781465.jpeg\",\"category\":\"woman\",\"quantity\":1,\"size\":\"XL\",\"max_stock\":\"18\"}]', '2026-02-23 01:46:54'),
(3, 3, 'MLR-02F8C652', 2959000.00, '', 'ewallet', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"id\":\"8\",\"user_id\":\"3\",\"product_id\":\"1\",\"quantity\":\"9\",\"size\":\"L\",\"created_at\":\"2026-02-22 11:45:34\",\"name\":\"Rib Polo\",\"price\":\"269000\",\"image\":\"IMG-6999e77b7ae042.79720541.jpeg\",\"category\":\"man\",\"max_stock\":\"20\"},{\"id\":\"18\",\"user_id\":\"3\",\"product_id\":\"1\",\"quantity\":\"2\",\"size\":\"M\",\"created_at\":\"2026-02-22 12:58:42\",\"name\":\"Rib Polo\",\"price\":\"269000\",\"image\":\"IMG-6999e77b7ae042.79720541.jpeg\",\"category\":\"man\",\"max_stock\":\"20\"}]', '2026-02-23 06:12:29'),
(4, 3, 'MLR-F146B10F', 359000.00, 'Not Paid', NULL, NULL, NULL, NULL, NULL, '2026-02-23 12:56:25'),
(5, 3, 'MLR-B67F9095', 269000.00, '', NULL, NULL, NULL, NULL, NULL, '2026-02-23 13:01:55'),
(6, 3, 'MLR-B09925D1', 269000.00, 'Not Paid', 'bank', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"product_id\":\"1\",\"name\":\"Rib Polo\",\"price\":\"269000\",\"image\":\"IMG-6999e77b7ae042.79720541.jpeg\",\"category\":\"man\",\"quantity\":1,\"size\":\"L\"}]', '2026-02-23 14:44:42'),
(7, 3, 'MLR-6C8A5A95', 269000.00, 'Not Paid', NULL, NULL, NULL, NULL, NULL, '2026-02-23 14:46:04'),
(8, 3, 'MLR-D4068A0E', 359000.00, 'Not Paid', 'bank', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"product_id\":\"2\",\"name\":\"V-Knit Sweater\",\"price\":\"359000\",\"image\":\"IMG-699a32063659d8.86781465.jpeg\",\"category\":\"woman\",\"quantity\":\"1\",\"size\":\"S\"}]', '2026-02-23 14:47:15'),
(9, 3, 'MLR-96A77990', 359000.00, 'Not Paid', 'ewallet', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"product_id\":\"2\",\"name\":\"V-Knit Sweater\",\"price\":\"359000\",\"image\":\"IMG-699a32063659d8.86781465.jpeg\",\"category\":\"woman\",\"quantity\":1,\"size\":\"M\"}]', '2026-02-23 14:50:30'),
(10, 3, 'MLR-E6020A85', 269000.00, 'Not Paid', 'ewallet', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"product_id\":\"1\",\"name\":\"Rib Polo\",\"price\":\"269000\",\"image\":\"IMG-6999e77b7ae042.79720541.jpeg\",\"category\":\"man\",\"quantity\":\"1\",\"size\":\"XL\"}]', '2026-02-23 14:59:47'),
(11, 3, 'MLR-B70B089F', 269000.00, 'Not Paid', 'cod', 'Sopi Kepo', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '[{\"product_id\":\"1\",\"name\":\"Rib Polo\",\"price\":\"269000\",\"image\":\"IMG-6999e77b7ae042.79720541.jpeg\",\"category\":\"man\",\"quantity\":\"1\",\"size\":\"S\"}]', '2026-02-23 15:03:50');

-- --------------------------------------------------------

--
-- Table structure for table `order_detail`
--

CREATE TABLE `order_detail` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `qty` int(11) DEFAULT NULL,
  `size` varchar(10) NOT NULL,
  `price` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_detail`
--

INSERT INTO `order_detail` (`id`, `order_id`, `product_id`, `image`, `category`, `qty`, `size`, `price`) VALUES
(1, 4, 2, 'IMG-699a32063659d8.86781465.jpeg', 'woman', 1, 'S', 359000),
(2, 5, 1, 'IMG-6999e77b7ae042.79720541.jpeg', 'man', 1, 'M', 269000),
(3, 7, 1, 'IMG-6999e77b7ae042.79720541.jpeg', 'man', 1, 'M', 269000);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `category` enum('man','woman') DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `rating` decimal(3,1) DEFAULT 0.0,
  `sold` int(11) DEFAULT 0,
  `is_trending` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `category`, `image`, `stock`, `rating`, `sold`, `is_trending`, `description`, `created_at`) VALUES
(1, 'Rib Polo', 269000, 'man', 'IMG-6999e77b7ae042.79720541.jpeg', 17, 0.0, 2, 1, 'Rib Polo adalah kaos polo dengan bahan rib yang lembut dan elastis sehingga nyaman dipakai sepanjang hari. Desainnya simpel namun tetap terlihat rapi dan stylish. Cocok digunakan untuk kegiatan santai maupun semi-formal. Kerah polo memberikan kesan lebih dewasa dan bersih pada penampilan.', '2026-02-21 17:12:27'),
(2, 'V-Knit Sweater', 359000, 'woman', 'IMG-699a32063659d8.86781465.jpeg', 17, 0.0, 1, 1, 'V-Knit Sweater adalah sweater rajut dengan model leher V yang memberikan kesan klasik dan elegan. Bahannya lembut dan hangat sehingga nyaman dipakai di cuaca sejuk. Desainnya cocok dipadukan dengan kemeja atau kaos sebagai inner. Sweater ini dapat digunakan untuk gaya kasual maupun semi-formal.', '2026-02-21 22:30:30');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT 0,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','petugas','customer') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `no_telp`, `email`, `username`, `password`, `role`, `created_at`, `phone`, `address`, `photo`) VALUES
(3, 'Sopi Kepo', '087654321', 'sopi@gmail.com', 'sopi_mallara', 'e10adc3949ba59abbe56e057f20f883e', 'customer', '2026-02-21 13:58:23', '081384168009', 'Jl. Mulia 2 no.63 Sukmajaya, Kota Depok', '1771708410_WhatsApp Image 2026-02-12 at 11.53.06.jpeg'),
(4, 'admin', '087654321', 'mallara-admin@gmail.com', 'mallara-admin@gmail.com', 'c61da4b8badf24f607c149a30f0a845e', 'admin', '2026-02-21 14:17:52', NULL, NULL, NULL),
(5, 'petugas', '087654321', 'mallara-petugas@gmail.com', 'mallara-petugas@gmail.com', 'a37dc2341dfee3d9a04ba63ba3429ae0', 'petugas', '2026-02-21 14:47:19', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product_size` (`user_id`,`product_id`,`size`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_detail`
--
ALTER TABLE `order_detail`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_detail`
--
ALTER TABLE `order_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


