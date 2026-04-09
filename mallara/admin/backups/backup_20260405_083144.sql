

CREATE TABLE IF NOT EXISTS `order_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `qty` int(11) DEFAULT NULL,
  `size` varchar(10) NOT NULL,
  `price` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `order_detail` VALUES("1","4","2","IMG-699a32063659d8.86781465.jpeg","woman","1","S","359000");
INSERT INTO `order_detail` VALUES("2","5","1","IMG-6999e77b7ae042.79720541.jpeg","man","1","M","269000");
INSERT INTO `order_detail` VALUES("3","7","1","IMG-6999e77b7ae042.79720541.jpeg","man","1","M","269000");



