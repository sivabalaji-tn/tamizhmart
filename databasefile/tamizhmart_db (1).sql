-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2026 at 09:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25
-- END OF THE DB UPDATE ON 18-07-2023 / SATURDAY 
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tamizhmart_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `shop_id`, `name`, `image`, `is_active`, `created_at`) VALUES
(1, 1, 'Groceries', 'cat_6a5b22c6e60d0.webp', 1, '2026-07-18 06:52:54');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `shop_order_number` int(10) UNSIGNED DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','confirmed','processing','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `payment_method` enum('cod','online') DEFAULT 'cod',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `address` text NOT NULL,
  `notes` text DEFAULT NULL,
  `shipping_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `shop_id`, `user_id`, `shop_order_number`, `total_amount`, `status`, `payment_method`, `payment_status`, `razorpay_order_id`, `razorpay_payment_id`, `address`, `notes`, `shipping_fee`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 490.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni Edamaal Street', '', 0.00, '2026-07-18 06:55:08', '2026-07-18 06:55:08'),
(2, 1, 1, 2, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 06:55:41', '2026-07-18 06:55:41'),
(3, 1, 1, 3, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:08:16', '2026-07-18 07:08:16'),
(4, 1, 1, 4, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'MDU', '', 0.00, '2026-07-18 07:20:21', '2026-07-18 07:20:21'),
(5, 1, 1, 5, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'Gujarat', '', 0.00, '2026-07-18 07:21:28', '2026-07-18 07:21:28'),
(6, 1, 1, 6, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'Dindigul', '', 0.00, '2026-07-18 07:24:59', '2026-07-18 07:24:59'),
(7, 1, 1, 7, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:30:54', '2026-07-18 07:30:54'),
(8, 1, 1, 8, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:39:02', '2026-07-18 07:39:02'),
(9, 1, 1, 9, 245.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:43:36', '2026-07-18 07:43:36'),
(10, 1, 1, 10, 980.00, 'pending', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti, Periyakulam TK, Theni DT', 'Leave at door inside', 0.00, '2026-07-18 07:47:59', '2026-07-18 07:47:59');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(36, 1, 2, 2, 245.00),
(38, 3, 2, 1, 245.00),
(39, 4, 2, 1, 245.00),
(40, 5, 2, 1, 245.00),
(41, 6, 2, 1, 245.00),
(42, 7, 2, 1, 245.00),
(43, 8, 2, 1, 245.00),
(44, 9, 2, 1, 245.00),
(45, 10, 2, 4, 245.00);

-- --------------------------------------------------------

--
-- Table structure for table `owners`
--

CREATE TABLE `owners` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_suspended` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `owners`
--

INSERT INTO `owners` (`id`, `name`, `email`, `password`, `phone`, `is_suspended`, `created_at`) VALUES
(1, 'Siva Balaji', 'sivabalaji2335@gmail.com', '$2y$10$Nq1hMywzlQI81Z.qUJbf6ePBKb88.nfq5ujQPysFDKjtltYN4wMV6', NULL, 0, '2026-07-17 07:18:49');

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'site_name', 'TamizhMart'),
(2, 'contact_email', 'admin@tamizhmart.com'),
(3, 'maintenance_mode', '0'),
(4, 'maintenance_message', 'We are under maintenance. Back soon!'),
(5, 'registration_open', '1'),
(6, 'razorpay_enabled', '0'),
(7, 'razorpay_key_id', ''),
(8, 'razorpay_key_secret', '');

-- --------------------------------------------------------

--
-- Table structure for table `popups`
--

CREATE TABLE `popups` (
  `id` int(11) NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `button_text` varchar(80) DEFAULT NULL,
  `button_link` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `shop_id`, `category_id`, `name`, `description`, `price`, `discount_price`, `image`, `image_url`, `sku`, `hsn_code`, `stock`, `sort_order`, `is_active`, `created_at`) VALUES
(2, 1, 1, 'Manna Health Mix', 'An healthy products makes life heathy', 250.00, 245.00, 'img_6a5b22ff2d61b.webp', NULL, NULL, NULL, 7, 0, 1, '2026-07-18 06:53:51');

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `announcement` varchar(500) DEFAULT NULL,
  `announcement_active` tinyint(1) DEFAULT 0,
  `theme_primary` varchar(20) DEFAULT '#c8a97e',
  `theme_secondary` varchar(20) DEFAULT '#8b6428',
  `theme_bg` varchar(20) DEFAULT '#faf7f2',
  `theme_text` varchar(20) DEFAULT '#1a1208',
  `theme_font` varchar(60) DEFAULT 'Poppins',
  `is_active` tinyint(1) DEFAULT 1,
  `is_suspended` tinyint(1) DEFAULT 0,
  `approved_at` timestamp NULL DEFAULT NULL,
  `address` varchar(300) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `owner_id`, `name`, `slug`, `description`, `logo`, `banner`, `announcement`, `announcement_active`, `theme_primary`, `theme_secondary`, `theme_bg`, `theme_text`, `theme_font`, `is_active`, `is_suspended`, `approved_at`, `address`, `city`, `state`, `pincode`, `created_at`) VALUES
(1, 1, 'SM Stores', 'sm-stores', 'We are the only shop who cares customer really', 'logo_699f1fd9a3ead.png', 'banner_699f1fdfed1e2.jpg', NULL, 0, '#c8a97e', '#8b6428', '#faf7f2', '#1a1208', 'Poppins', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-17 07:18:49');

-- --------------------------------------------------------

--
-- Table structure for table `shop_settings`
--

CREATE TABLE `shop_settings` (
  `id` int(11) NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shop_settings`
--

INSERT INTO `shop_settings` (`id`, `shop_id`, `setting_key`, `setting_value`) VALUES
(1, 1, 'setup_complete', '1');

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `name`, `email`, `password`, `created_at`) VALUES
(1, 'Super Admin', 'admin@tamizhmart.com', '$2y$10$5aH7w6EUn0faM/GIQRHL/OZphq8ELcERZyogt/F3gInjZi5cN8cT.', '2026-07-17 07:17:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `shop_id`, `name`, `email`, `password`, `phone`, `address`, `is_active`, `created_at`) VALUES
(1, 1, 'Siva Balaji', 'sivabalaji800@gmail.com', '$2y$10$t/9lCyzuYKunb6WA9J9nCOmZK7Q5piN22QuJ8PiSV.QKmi2hxJCIe', '7904538655', '', 1, '2026-07-18 06:54:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cart_item` (`user_id`,`shop_id`,`product_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_status` (`shop_id`,`status`),
  ADD KEY `idx_user_orders` (`user_id`,`shop_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `owners`
--
ALTER TABLE `owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `popups`
--
ALTER TABLE `popups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_active` (`shop_id`,`is_active`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_sort` (`shop_id`,`sort_order`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `shop_settings`
--
ALTER TABLE `shop_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_setting` (`shop_id`,`setting_key`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_user_email` (`shop_id`,`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `owners`
--
ALTER TABLE `owners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `popups`
--
ALTER TABLE `popups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shop_settings`
--
ALTER TABLE `shop_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `popups`
--
ALTER TABLE `popups`
  ADD CONSTRAINT `popups_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shops`
--
ALTER TABLE `shops`
  ADD CONSTRAINT `shops_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_settings`
--
ALTER TABLE `shop_settings`
  ADD CONSTRAINT `shop_settings_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
