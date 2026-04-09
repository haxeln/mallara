

CREATE TABLE IF NOT EXISTS `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `size` varchar(10) DEFAULT 'M',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_product_size` (`user_id`,`product_id`,`size`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cart` VALUES("20","3","1","1","S","2026-02-23 14:05:42");
INSERT INTO `cart` VALUES("21","3","2","1","S","2026-02-23 19:56:05");
INSERT INTO `cart` VALUES("22","3","1","1","M","2026-02-26 08:33:40");
INSERT INTO `cart` VALUES("23","4","1","1","M","2026-04-04 17:35:09");
INSERT INTO `cart` VALUES("24","4","11","1","M","2026-04-04 18:57:26");
INSERT INTO `cart` VALUES("25","3","17","1","M","2026-04-04 18:58:29");
INSERT INTO `cart` VALUES("26","3","5","1","S","2026-04-04 18:58:44");



