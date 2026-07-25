CREATE DATABASE IF NOT EXISTS `shopping_cart_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `shopping_cart_db`;

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Staff') NOT NULL DEFAULT 'Staff',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `email`, `password`, `role`, `created_at`, `status`) VALUES
(1, 'test', '', '$2y$10$3Cnjym46zvav/YFRoKu4p.M4sG/VNAR3ak5u90hP9HBVvBCoYfqQ.', 'Super Admin', '2026-07-20 20:25:54', 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `status`, `created_at`) VALUES
(1, 'Flower pot', 1, '2026-07-20 20:27:46'),
(2, 'Wala', 1, '2026-07-20 20:27:46'),
(4, 'Sky cracker', 1, '2026-07-20 20:31:44');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(20) NOT NULL,
  `notes` text,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT '18.00',
  `gst_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `subtotal_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `order_status` enum('Pending','Confirmed','Packed','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `orders`
--

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`) VALUES
(1, 1, 1, 'Classic Cotton T-Shirt', 499.00, 1, 499.00),
(2, 1, 2, 'Denim Casual Shorts', 899.00, 1, 899.00),
(3, 1, 3, 'Cotton Co-Ord Set', 1299.00, 1, 1299.00),
(4, 1, 4, 'skyy rocket', 200.00, 1, 200.00),
(5, 2, 1, 'Classic Cotton T-Shirt', 499.00, 2, 998.00),
(6, 2, 2, 'Denim Casual Shorts', 899.00, 1, 899.00),
(7, 2, 3, 'Cotton Co-Ord Set', 1299.00, 1, 1299.00),
(8, 2, 4, 'skyy rocket', 200.00, 1, 200.00),
(9, 3, 1, 'Classic Cotton T-Shirt', 499.00, 3, 1497.00),
(10, 3, 2, 'Denim Casual Shorts', 899.00, 2, 1798.00),
(11, 3, 3, 'Cotton Co-Ord Set', 1299.00, 1, 1299.00),
(12, 3, 4, 'skyy rocket', 200.00, 1, 200.00),
(13, 4, 1, 'Classic Cotton T-Shirt', 499.00, 1, 499.00),
(14, 4, 3, 'Cotton Co-Ord Set', 1299.00, 1, 1299.00),
(15, 5, 9, 'JAWAN CRACKERS', 2000.00, 2, 4000.00),
(16, 5, 10, '15cm ELECTRIC SPARKLERS', 2000.00, 2, 4000.00),
(17, 5, 3, '100-wala', 1299.00, 2, 2598.00),
(18, 5, 7, '1000 Wala Crackers', 1499.00, 2, 2998.00),
(19, 6, 1, 'flower pot', 499.00, 6, 2994.00),
(20, 6, 2, 'flower pot', 899.00, 1, 899.00),
(21, 6, 4, 'skyy rocket', 200.00, 8, 1600.00),
(22, 6, 3, '100-wala', 1299.00, 3, 3897.00),
(23, 7, 3, '100-wala', 1299.00, 1, 1299.00),
(24, 7, 7, '1000 Wala Crackers', 1499.00, 1, 1499.00),
(25, 8, 45, 'COLOUR ROCKET', 500.00, 1, 500.00),
(26, 8, 3, '100-wala', 1299.00, 1, 1299.00),
(27, 8, 7, '1000 Wala Crackers', 1499.00, 1, 1499.00),
(28, 9, 5, 'Sky Rocket (Special)', 1000.00, 1, 1000.00),
(29, 10, 1, 'flower pot', 499.00, 1, 499.00),
(30, 10, 2, 'flower pot', 899.00, 1, 899.00),
(31, 10, 62, 'BLUE BLOOM', 500.00, 2, 1000.00),
(32, 11, 9, 'JAWAN CRACKERS', 2000.00, 1, 2000.00),
(33, 11, 10, '15cm ELECTRIC SPARKLERS', 2000.00, 5, 10000.00),
(34, 11, 11, '15cm COLOURED SPARKLERS', 2000.00, 1, 2000.00),
(35, 11, 12, '15cm RUBY SPARKLERS', 2000.00, 1, 2000.00),
(36, 11, 14, '50cm COLOURED SPARKLERS', 2000.00, 1, 2000.00),
(37, 11, 1, 'flower pot', 499.00, 1, 499.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(255) NOT NULL,
  `product_category` varchar(100) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=Active, 0=Disabled',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `product_category`, `sku`, `description`, `price`, `image`, `status`, `created_at`) VALUES
(1, 'flower pot', 'Flower pot', 'TSHIRT-001', 'Soft and breathable 100% combed cotton t-shirt.', 499.00, NULL, 1, '2026-07-20 20:06:20');

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('next_order_number', '1');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
