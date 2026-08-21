-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql:3306
-- Generation Time: Aug 18, 2026 at 01:33 PM
-- Server version: 10.11.18-MariaDB-ubu2204
-- PHP Version: 8.3.26

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
(2, 5, 'Chocolates', NULL, 1, '2026-07-23 06:14:14'),
(4, 1, 'Rice', 'cat_6a6448832f2fd.jpg', 1, '2026-07-25 04:51:48'),
(5, 1, 'Ghee', 'cat_6a64487cc24ba.jpg', 1, '2026-07-25 04:51:52'),
(6, 1, 'Nuts', 'cat_6a6448768360f.jpg', 1, '2026-07-25 04:51:58'),
(7, 1, 'Chocolates', 'cat_6a64486105c46.jpg', 1, '2026-07-25 04:52:05'),
(8, 1, 'Nutritional Drinks', 'cat_6a64488e33da8.jpg', 1, '2026-07-25 04:52:12'),
(9, 1, 'Dates', 'cat_6a6448982c04c.jpg', 1, '2026-07-25 04:52:16'),
(10, 1, 'Attas & Mixes', 'cat_6a6448a22f2f7.webp', 1, '2026-07-25 04:52:26'),
(11, 1, 'Shampoos', 'cat_6a6448a82b21c.jpg', 1, '2026-07-25 04:52:32'),
(13, 9, 'Shoes', NULL, 1, '2026-08-07 04:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `commission_log`
--

CREATE TABLE `commission_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_amount` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `commission_amount` decimal(12,2) NOT NULL,
  `collected` tinyint(1) NOT NULL DEFAULT 0,
  `collected_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commission_log`
--

INSERT INTO `commission_log` (`id`, `shop_id`, `order_id`, `order_amount`, `commission_rate`, `commission_amount`, `collected`, `collected_at`, `created_at`) VALUES
(1, 1, 28, 821.51, 2.00, 16.43, 0, NULL, '2026-08-09 10:27:34'),
(2, 9, 29, 4500.00, 2.00, 90.00, 0, NULL, '2026-08-09 10:30:03'),
(3, 1, 31, 6213.02, 2.00, 124.26, 0, NULL, '2026-08-12 07:58:40'),
(4, 1, 32, 3036.51, 2.00, 60.73, 0, NULL, '2026-08-12 10:53:47'),
(5, 1, 33, 60.00, 2.00, 1.20, 0, NULL, '2026-08-14 09:36:44'),
(6, 1, 34, 539740.00, 2.00, 10794.80, 0, NULL, '2026-08-14 09:41:14'),
(7, 1, 35, 30000.00, 2.00, 600.00, 0, NULL, '2026-08-14 09:45:08'),
(8, 1, 36, 40.00, 2.00, 0.80, 0, NULL, '2026-08-14 09:52:48'),
(9, 1, 37, 160.00, 2.00, 3.20, 0, NULL, '2026-08-17 10:50:22'),
(10, 1, 38, 20.00, 2.00, 0.40, 0, NULL, '2026-08-17 10:56:29');

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
(1, 1, 1, 1, 490.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni Edamaal Street', '', 0.00, '2026-07-18 06:55:08', '2026-07-25 06:43:16'),
(2, 1, 1, 2, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 06:55:41', '2026-07-25 06:44:22'),
(3, 1, 1, 3, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:08:16', '2026-07-25 06:44:15'),
(4, 1, 1, 4, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'MDU', '', 0.00, '2026-07-18 07:20:21', '2026-07-25 06:44:11'),
(5, 1, 1, 5, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Gujarat', '', 0.00, '2026-07-18 07:21:28', '2026-07-25 06:44:06'),
(6, 1, 1, 6, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Dindigul', '', 0.00, '2026-07-18 07:24:59', '2026-07-25 06:44:01'),
(7, 1, 1, 7, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:30:54', '2026-07-25 06:43:55'),
(8, 1, 1, 8, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:39:02', '2026-07-25 06:43:44'),
(9, 1, 1, 9, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 07:43:36', '2026-07-25 06:43:36'),
(10, 1, 1, 10, 980.00, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti, Periyakulam TK, Theni DT', 'Leave at door inside', 0.00, '2026-07-18 07:47:59', '2026-07-25 06:43:30'),
(11, 1, 1, 11, 1225.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 16:15:00', '2026-08-09 09:34:54'),
(12, 1, 1, 12, 490.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-07-18 16:37:55', '2026-07-25 06:42:55'),
(13, 1, 1, 13, 490.00, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-07-23 06:04:32', '2026-08-09 09:34:59'),
(14, 1, 1, 14, 245.00, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-07-23 06:11:21', '2026-08-09 09:35:04'),
(15, 1, 1, 15, 8505.00, 'delivered', 'online', 'paid', 'order_THchEcunWwLTj1', 'pay_THchPMwzlIcz5C', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-07-25 05:40:02', '2026-08-09 09:34:48'),
(16, 1, 1, 16, 440.00, 'delivered', 'online', 'paid', 'order_THdi0ybZZTzftU', 'pay_THdi8rgqjH7Shg', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-07-25 06:39:20', '2026-07-25 06:42:40'),
(17, 1, 1, 17, 3965.00, 'delivered', 'online', 'paid', 'order_THe3k2VMrSWt4k', 'pay_THe3w8gNVNKKM7', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-07-25 06:59:56', '2026-08-09 09:34:43'),
(18, 1, 2, 18, 2525.00, 'delivered', 'online', 'paid', 'order_TIOlO6ZldHpQFS', 'pay_TIOlaM5uwU0lsU', 'Ooty', '', 0.00, '2026-07-27 04:41:09', '2026-07-27 04:42:24'),
(22, 1, 1, 19, 1300.00, 'delivered', 'online', 'paid', 'order_TLEXQ5q1xL1OJM', 'pay_TLEXnG8jLGmUd3', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-03 08:38:10', '2026-08-09 09:34:36'),
(23, 1, 1, 20, 1320.00, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-06 17:54:57', '2026-08-09 09:34:30'),
(24, 1, 1, 21, 580.00, 'delivered', 'online', 'paid', 'order_TMlcFJY8nP149V', 'pay_TMle9Yk7uXQwLo', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-07 05:40:17', '2026-08-09 09:34:26'),
(25, 1, 1, 22, 1190.00, 'delivered', 'online', 'paid', 'order_TMm5mIFZHEYnWB', 'pay_TMm5v9lvQKsdjK', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-07 06:06:20', '2026-08-07 06:08:42'),
(26, 1, 1, 23, 8775.00, 'delivered', 'online', 'paid', 'order_TNceuDbDNp5gsz', 'pay_TNcfAO74KudhR6', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-09 09:32:01', '2026-08-09 09:34:22'),
(27, 9, 3, 1, 45000.00, 'delivered', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-08-09 10:20:08', '2026-08-09 10:29:07'),
(28, 1, 1, 24, 821.51, 'delivered', 'online', 'paid', 'order_TNdc0CKEymZnlH', 'pay_TNdc6jDNm7zv6C', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-09 10:27:34', '2026-08-12 08:03:23'),
(29, 9, 3, 2, 4500.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-08-09 10:30:03', '2026-08-09 10:30:03'),
(30, 1, 1, 25, 21776.51, 'delivered', 'online', 'paid', 'order_TOjqruzSE6RdOy', 'pay_TOjr0pIxDnA2vN', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-12 05:13:06', '2026-08-12 07:56:40'),
(31, 1, 1, 26, 6213.02, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-12 07:58:40', '2026-08-12 08:03:17'),
(32, 1, 4, 27, 3036.51, 'delivered', 'online', 'paid', 'order_TOpeYLXvgQSdXq', 'pay_TOpf8N785Cip6D', 'Pochampalli, Krishnagiri', '', 0.00, '2026-08-12 10:53:47', '2026-08-14 10:22:13'),
(33, 1, 1, 28, 60.00, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-14 09:36:44', '2026-08-14 09:42:23'),
(34, 1, 1, 29, 539740.00, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-14 09:41:14', '2026-08-14 09:41:56'),
(35, 1, 1, 30, 30000.00, 'delivered', 'online', 'paid', 'order_TPbY9p2SjRscsS', 'pay_TPbYpwF6qfBXsg', '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-14 09:45:08', '2026-08-14 09:53:19'),
(36, 1, 1, 31, 40.00, 'delivered', 'cod', 'pending', NULL, NULL, '5-2-49, Vaniyar Street, Vadugapatti', '', 0.00, '2026-08-14 09:52:48', '2026-08-14 09:53:25'),
(37, 1, 7, 32, 160.00, 'confirmed', 'online', 'paid', 'order_TQoGySI6SJOrgn', 'pay_TQoH7nFYgpHlE7', 'Iduvampalayam, Sultanpet', '', 0.00, '2026-08-17 10:50:22', '2026-08-17 10:50:22'),
(38, 1, 7, 33, 20.00, 'pending', 'cod', 'pending', NULL, NULL, 'Theni', '', 0.00, '2026-08-17 10:56:29', '2026-08-17 10:56:29');

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
(50, 15, 11, 2, 75.00),
(51, 15, 13, 1, 320.00),
(52, 15, 14, 1, 240.00),
(53, 15, 15, 1, 240.00),
(54, 15, 18, 1, 330.00),
(55, 15, 21, 1, 1600.00),
(56, 15, 22, 1, 1460.00),
(57, 15, 23, 1, 45.00),
(58, 15, 30, 1, 180.00),
(59, 15, 31, 1, 2300.00),
(60, 15, 35, 1, 440.00),
(61, 15, 39, 2, 120.00),
(62, 15, 40, 1, 170.00),
(63, 15, 41, 1, 260.00),
(64, 15, 42, 2, 45.00),
(65, 15, 43, 1, 440.00),
(66, 16, 43, 1, 440.00),
(67, 17, 44, 2, 35.00),
(68, 17, 43, 1, 440.00),
(69, 17, 38, 1, 170.00),
(70, 17, 32, 1, 525.00),
(71, 17, 37, 1, 460.00),
(72, 17, 31, 1, 2300.00),
(73, 18, 9, 1, 40.00),
(74, 18, 13, 1, 320.00),
(75, 18, 14, 1, 240.00),
(76, 18, 15, 1, 240.00),
(77, 18, 41, 1, 260.00),
(78, 18, 43, 3, 440.00),
(79, 18, 44, 3, 35.00),
(95, 22, 44, 1, 35.00),
(96, 22, 43, 1, 440.00),
(97, 22, 39, 1, 120.00),
(98, 22, 30, 1, 180.00),
(99, 22, 32, 1, 525.00),
(100, 23, 17, 1, 75.00),
(101, 23, 13, 1, 320.00),
(102, 23, 43, 2, 440.00),
(103, 23, 42, 1, 45.00),
(104, 24, 44, 4, 35.00),
(105, 24, 43, 1, 440.00),
(106, 25, 45, 1, 20.00),
(107, 25, 36, 3, 390.00),
(108, 26, 41, 3, 260.00),
(109, 26, 42, 3, 45.00),
(110, 26, 32, 2, 525.00),
(111, 26, 19, 1, 1450.00),
(112, 26, 22, 1, 1460.00),
(113, 26, 21, 1, 1600.00),
(114, 26, 31, 1, 2300.00),
(115, 27, 47, 10, 4500.00),
(116, 28, 43, 1, 821.51),
(117, 29, 47, 1, 4500.00),
(118, 30, 31, 3, 2300.00),
(119, 30, 30, 2, 180.00),
(120, 30, 29, 2, 70.00),
(121, 30, 28, 1, 100.00),
(122, 30, 37, 1, 460.00),
(123, 30, 36, 2, 390.00),
(124, 30, 35, 1, 440.00),
(125, 30, 32, 1, 525.00),
(126, 30, 41, 2, 260.00),
(127, 30, 40, 2, 170.00),
(128, 30, 39, 2, 120.00),
(129, 30, 38, 2, 170.00),
(130, 30, 42, 1, 45.00),
(131, 30, 44, 1, 35.00),
(132, 30, 45, 1, 20.00),
(133, 30, 43, 1, 821.51),
(134, 30, 9, 1, 40.00),
(135, 30, 11, 1, 75.00),
(136, 30, 12, 1, 330.00),
(137, 30, 16, 1, 285.00),
(138, 30, 15, 1, 240.00),
(139, 30, 14, 1, 240.00),
(140, 30, 13, 1, 320.00),
(141, 30, 21, 2, 1600.00),
(142, 30, 19, 2, 1450.00),
(143, 30, 18, 1, 330.00),
(144, 30, 17, 1, 75.00),
(145, 30, 22, 1, 1460.00),
(146, 30, 23, 1, 45.00),
(147, 30, 25, 1, 40.00),
(148, 30, 26, 1, 130.00),
(149, 31, 43, 2, 821.51),
(150, 31, 21, 2, 1600.00),
(151, 31, 36, 2, 390.00),
(152, 31, 38, 3, 170.00),
(153, 31, 44, 1, 35.00),
(154, 31, 42, 1, 45.00),
(155, 32, 49, 1, 20.00),
(156, 32, 45, 1, 20.00),
(157, 32, 38, 1, 170.00),
(158, 32, 43, 1, 821.51),
(159, 32, 28, 1, 100.00),
(160, 32, 35, 1, 440.00),
(161, 32, 18, 1, 330.00),
(162, 32, 13, 1, 320.00),
(163, 32, 11, 1, 75.00),
(164, 32, 41, 1, 260.00),
(165, 32, 15, 2, 240.00),
(166, 33, 49, 3, 20.00),
(167, 34, 49, 26987, 20.00),
(168, 35, 49, 1500, 20.00),
(169, 36, 49, 2, 20.00),
(170, 37, 49, 4, 20.00),
(171, 37, 48, 4, 20.00),
(172, 38, 49, 1, 20.00);

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
(1, 'Siva Balaji', 'sivabalaji2335@gmail.com', '$2y$10$Nq1hMywzlQI81Z.qUJbf6ePBKb88.nfq5ujQPysFDKjtltYN4wMV6', NULL, 0, '2026-07-17 07:18:49'),
(3, 'Suresh Kumar', 'bsiva6808@gmail.com', '$2y$10$xe6hlc72XneFPqsDuHilH.7J/mq3qn/5eoUpN.Rjvx4Zy2.F8wvSK', NULL, 0, '2026-07-22 05:15:03'),
(4, 'Muthusamy P', 'sivabalaji900@gmail.com', '$2y$10$3DlZnnreR1RCWR.gGLjuounHVUF5oiUcdJFNPHAdpWHxIOBpPztUS', NULL, 0, '2026-07-23 06:12:32'),
(5, 'Rajesh Kumar', 'sivabalaji189@gmail.com', '$2y$10$C8D8TTORXgbe2cObqkJRA.7Uq1ZK/b7pTLHMaqI32jUlB7rMCjesO', NULL, 0, '2026-07-25 07:03:08'),
(8, 'Siddharth', 'sidranda001@gmail.com', '$2y$10$R.wx84vqDKbxIn9FDmnRke/CI7XjrNxKv7Dv7ePOVGBlQXc9cC1Ae', NULL, 0, '2026-08-07 04:51:47'),
(9, 'Baskaran', 'baskaran@gmail.com', '$2y$10$sVTOHantklzMm0Akvdsu3eCC4xoLPG7UAAdHhbvfkDhIsRmn5/RzW', NULL, 0, '2026-08-14 13:10:54');

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(60) NOT NULL,
  `slug` varchar(30) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` int(11) NOT NULL DEFAULT 30,
  `product_limit` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
  `order_limit` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `slug`, `price`, `duration_days`, `product_limit`, `order_limit`, `commission_rate`, `features`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Free Trial', 'trial', 0.00, 30, 20, 50, 0.00, '[\"20 products\",\"50 orders/month\",\"Email notifications\",\"Basic analytics\",\"COD only\"]', 1, 0, '2026-08-07 04:32:37'),
(2, 'Elite', 'elite', 3000.00, 30, 100, 500, 2.00, '[\"100 products\",\"500 orders\\/month\",\"Email + WhatsApp alerts\",\"Full analytics\",\"COD + Razorpay\",\"Bulk upload\",\"Priority listing\"]', 1, 1, '2026-08-07 04:32:37'),
(3, 'Premium', 'premium', 5000.00, 30, NULL, NULL, 0.00, '[\"Unlimited products\",\"Unlimited orders\",\"Email + WhatsApp alerts\",\"Full analytics + Export\",\"COD + Razorpay\",\"Bulk upload\",\"Custom domain support\",\"Priority support\"]', 1, 2, '2026-08-07 04:32:37');

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
(1, 'site_name', 'TamizhMart (Beta)'),
(2, 'contact_email', 'admin@tamizhmart.com'),
(3, 'maintenance_mode', '0'),
(4, 'maintenance_message', 'இந்த இணையதளம் தற்கலிகமாக பராமரிப்பு பணியில் உள்ளது....விரைவில் உங்கள் செயல்பாட்டிருக்குவருகிறது....நன்றி!!!'),
(5, 'registration_open', '1'),
(6, 'razorpay_enabled', '0'),
(7, 'razorpay_key_id', ''),
(8, 'razorpay_key_secret', ''),
(51, 'site_city', 'Madurai');

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

--
-- Dumping data for table `popups`
--

INSERT INTO `popups` (`id`, `shop_id`, `title`, `message`, `image`, `button_text`, `button_link`, `is_active`, `start_date`, `end_date`, `created_at`) VALUES
(4, 4, 'Weekend OFFER', 'Free foog', NULL, 'Shop now', '', 1, '2026-07-22', '2026-10-20', '2026-07-22 05:22:17'),
(5, 1, 'Independence day month Special Offer', 'Free Door Delivery for Each and Every Order!!!', 'popup_6a644b9762a38.jpg', 'Shop now', '', 1, '2026-07-25', '0000-00-00', '2026-07-25 05:37:27'),
(6, 10, 'Festival Special', 'Claim offer upto 10% Discount', 'popup_6a7f14b966114.webp', 'Claim Offer', '', 1, '2026-08-14', '2026-08-18', '2026-08-14 13:14:33');

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
(8, 5, 2, 'Ferro Roacher (500g)', NULL, 450.00, NULL, NULL, NULL, NULL, NULL, 20, 0, 1, '2026-07-23 06:14:14'),
(9, 1, 7, 'KitKat', '', 45.00, 40.00, 'img_6a64418ea5355.jpg', NULL, NULL, NULL, 18, 33, 1, '2026-07-25 04:54:38'),
(11, 1, 10, 'Aashirvaad Wheat Flour (1kg)', '', 80.00, 75.00, 'img_6a6441c70fea8.png', NULL, NULL, NULL, 16, 32, 1, '2026-07-25 04:55:35'),
(12, 1, 10, 'Manna Sprouted Ragi & Rice (6 Months)', 'Manna Baby Mixes', 345.00, 330.00, 'img_6a644213c5b5f.webp', NULL, NULL, NULL, 24, 31, 1, '2026-07-25 04:56:51'),
(13, 1, 10, 'Manna Badam Mix', '', 340.00, 320.00, 'img_6a6442301df5c.webp', NULL, NULL, NULL, 15, 30, 1, '2026-07-25 04:57:20'),
(14, 1, 8, 'Horlicks (500g)', '', 250.00, 240.00, 'img_6a6442500d5df.webp', NULL, NULL, NULL, 22, 29, 1, '2026-07-25 04:57:52'),
(15, 1, 8, 'Boost (500g)', '', 260.00, 240.00, 'img_6a64426a999bc.webp', NULL, NULL, NULL, 20, 28, 1, '2026-07-25 04:58:18'),
(16, 1, 8, 'Complan (500g)', '', 300.00, 285.00, 'img_6a644286700f3.jpg', NULL, NULL, NULL, 24, 27, 1, '2026-07-25 04:58:46'),
(17, 1, 10, 'Pillsbury Maida (500g)', '', 80.00, 75.00, 'img_6a6442aa1a9e0.webp', NULL, NULL, NULL, 23, 26, 1, '2026-07-25 04:59:22'),
(18, 1, 10, 'Pillsbury Cake Mix (750g)', '', 350.00, 330.00, 'img_6a6442d87ecd1.jpg', NULL, NULL, NULL, 7, 25, 1, '2026-07-25 05:00:08'),
(19, 1, 4, 'India Gate Basmati (15kg)', '', 1500.00, 1450.00, 'img_6a64431766c38.jpg', NULL, NULL, NULL, 17, 24, 1, '2026-07-25 05:01:11'),
(21, 1, 4, 'India Gate Basmati Tibar (15kg)', '', 1650.00, 1600.00, 'img_6a64434248f9f.webp', NULL, NULL, NULL, 6, 1, 1, '2026-07-25 05:01:54'),
(22, 1, 4, 'Swami Ayyappa Ponni (26kg)', '', 1500.00, 1460.00, 'img_6a6443738d108.jpeg', NULL, NULL, NULL, 7, 23, 1, '2026-07-25 05:02:43'),
(23, 1, 7, 'Kinder Joy', '', 45.00, 45.00, 'img_6a64438dcedf5.webp', NULL, NULL, NULL, 48, 22, 1, '2026-07-25 05:03:09'),
(25, 1, 7, 'Milky Bar (30g)', '', 40.00, NULL, 'img_6a6443d1b0e31.webp', NULL, NULL, NULL, 49, 21, 1, '2026-07-25 05:04:17'),
(26, 1, 9, 'Lion Dates (500g)', '', 140.00, 130.00, 'img_6a6443eb68af0.jpg', NULL, NULL, NULL, 14, 14, 1, '2026-07-25 05:04:43'),
(28, 1, 9, 'Lion Dates Syrup (250ml)', '', 110.00, 100.00, 'img_6a64441888fdc.jpg', NULL, NULL, NULL, 18, 20, 1, '2026-07-25 05:05:28'),
(29, 1, 9, 'Karuda Dates (250g)', '', 75.00, 70.00, 'img_6a64443165265.jpg', NULL, NULL, NULL, 8, 19, 1, '2026-07-25 05:05:53'),
(30, 1, 9, 'Kimia Dates (500g)', '', 200.00, 180.00, 'img_6a6444568444c.jpg', NULL, NULL, NULL, 11, 18, 1, '2026-07-25 05:06:30'),
(31, 1, 4, 'Saaral Seeraga Samba (26kg)', '', 2340.00, 2300.00, 'img_6a64448aabdea.jpg', NULL, NULL, NULL, 42, 17, 1, '2026-07-25 05:07:22'),
(32, 1, 4, 'India Gate Seeraga Samba (5kg)', '', 550.00, 525.00, 'img_6a6444b644df7.webp', NULL, NULL, NULL, 10, 16, 1, '2026-07-25 05:08:06'),
(35, 1, 5, 'GRB Ghee (500ml)', '', 450.00, 440.00, 'img_6a644502f2a5b.webp', NULL, NULL, NULL, 7, 15, 1, '2026-07-25 05:09:22'),
(36, 1, 5, 'RKG Ghee (500ml)', '', 400.00, 390.00, 'img_6a64451d423a4.jpg', NULL, NULL, NULL, 31, 13, 1, '2026-07-25 05:09:49'),
(37, 1, 5, 'Cavin\'s Ghee (500ml)', '', 480.00, 460.00, 'img_6a64453ea51bd.jpg', NULL, NULL, NULL, 8, 12, 1, '2026-07-25 05:10:22'),
(38, 1, 11, 'Head & Shoulders (200ml)', '', 180.00, 170.00, 'img_6a644560991d9.jpg', NULL, NULL, NULL, 23, 6, 1, '2026-07-25 05:10:56'),
(39, 1, 11, 'Clinic Plus (200ml)', '', 130.00, 120.00, 'img_6a6445775fa02.jpg', NULL, NULL, NULL, 52, 11, 1, '2026-07-25 05:11:19'),
(40, 1, 11, 'Meera (200ml)', '', 180.00, 170.00, 'img_6a6445950a82b.jpg', NULL, NULL, NULL, 7, 10, 1, '2026-07-25 05:11:49'),
(41, 1, 6, 'AGR Cashews (250g)', '', 280.00, 260.00, 'img_6a6445b181fd2.jpg', NULL, NULL, NULL, 22, 2, 1, '2026-07-25 05:12:17'),
(42, 1, 7, 'MNR Kadaimuttai', '', 50.00, 45.00, 'img_6a6445c59f1c5.webp', NULL, NULL, NULL, 12, 3, 1, '2026-07-25 05:12:37'),
(43, 1, 7, 'Ferro Rocher (500g)', '', 874.00, 821.51, 'img_6a6447ce8bdee.webp', NULL, NULL, NULL, 20, 4, 1, '2026-07-25 05:21:18'),
(44, 1, 7, 'Dairy Milk', '', 40.00, 35.00, 'img_6a6447f6b9998.webp', NULL, NULL, NULL, 138, 9, 1, '2026-07-25 05:21:58'),
(45, 1, 7, '5Star (20g)', '', 20.00, NULL, 'img_6a644812a2f3b.webp', NULL, NULL, NULL, 17, 8, 1, '2026-07-25 05:22:26'),
(47, 9, 13, 'Nike jordan', '', 4500.00, NULL, 'img_6a75658714904.jpg', NULL, NULL, NULL, 998, 0, 1, '2026-08-07 04:55:04'),
(48, 1, 7, 'Kurkure Naughty Tomato', '', 25.00, 20.00, NULL, 'https://www.bbassets.com/media/uploads/p/l/294308_8-kurkure-namkeen-naughty-tomatoes.jpg', NULL, NULL, 16, 7, 1, '2026-08-12 10:10:04'),
(49, 1, 7, 'Kurukure Classic', '', 20.00, NULL, NULL, 'https://m.media-amazon.com/images/I/81Tu78PWNUL._SX679_.jpg', NULL, NULL, 25493, 5, 1, '2026-08-12 10:12:48');

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
(1, 1, 'SM Stores', 'sm-stores', 'Retail and Wholesale Available', 'logo_6a6449d0143b7.png', 'banner_6a644b12255dd.jpg', 'Free Delivery for every First order!!!', 1, '#059669', '#047857', '#f0fdf4', '#064e3b', 'Syne', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-17 07:18:49'),
(4, 3, 'SM Wholesales', 'sm-wholesales', 'We offer products price less than any other', NULL, NULL, 'Opening offer Free Delivery over Rs 5000', 1, '#22c55e', '#15803d', '#f0fdf4', '#052e16', 'DM Sans', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-22 05:15:03'),
(5, 4, 'Muthu Store', 'muthu-store', 'We offer a high quality utility products', NULL, NULL, 'Opening offer free tea for customers', 1, '#c8a97e', '#8b6428', '#faf7f2', '#1a1208', 'Nunito', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-23 06:12:32'),
(6, 5, 'Rajesh Sweets & Bakeries', 'rajesh-sweets-bakeries', '', NULL, NULL, NULL, 0, '#c8a97e', '#8b6428', '#faf7f2', '#1a1208', 'Poppins', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-25 07:03:08'),
(9, 8, 'Splash Wear', 'splash-wear', 'We offer a high quality sports wear', NULL, NULL, 'Flash 10', 1, '#18b973', '#8b6428', '#faf7f2', '#1a1208', 'Poppins', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-08-07 04:51:47'),
(10, 9, 'Baskaran Bakery', 'baskaran-bakery', '', 'logo_6a7f14688a30a.webp', NULL, NULL, 0, '#c8a97e', '#8b6428', '#faf7f2', '#1a1208', 'Josefin Sans', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-08-14 13:10:54');

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
(1, 1, 'setup_complete', '1'),
(23, 4, 'phone', '9698492120'),
(24, 4, 'setup_complete', '1'),
(25, 4, 'whatsapp', '9698492120'),
(26, 4, 'instagram', ''),
(27, 4, 'facebook', ''),
(28, 4, 'twitter', ''),
(29, 4, 'youtube', ''),
(30, 4, 'website', ''),
(31, 4, 'email_contact', ''),
(33, 4, 'address', 'Theni'),
(43, 5, 'phone', '96857412365'),
(44, 5, 'setup_complete', '1'),
(45, 1, 'tax_enabled', '1'),
(46, 1, 'cgst_rate', '2.5'),
(47, 1, 'sgst_rate', '2.5'),
(51, 1, 'phone', '7904538655'),
(52, 1, 'address', '188, Chinna Kadai Veethi, Vadugapatti'),
(53, 1, 'razorpay_enabled', '1'),
(54, 1, 'razorpay_key_id', 'rzp_test_TEyKuN8yqXR9Gu'),
(55, 1, 'razorpay_key_secret', '21HDlQKymLByxV3duM0IANH6'),
(75, 1, 'whatsapp', '7904538655'),
(76, 1, 'instagram', 'siva_balaji007'),
(77, 1, 'facebook', 'SM Stores'),
(78, 1, 'twitter', ''),
(79, 1, 'youtube', ''),
(80, 1, 'website', ''),
(81, 1, 'email_contact', 'sivathetechie24@gmail.com'),
(84, 6, 'setup_complete', '1'),
(96, 9, 'setup_complete', '1'),
(100, 10, 'setup_complete', '1');

-- --------------------------------------------------------

--
-- Table structure for table `shop_subscriptions`
--

CREATE TABLE `shop_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `status` enum('trial','active','grace','suspended','cancelled') NOT NULL DEFAULT 'trial',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `grace_until` datetime DEFAULT NULL,
  `payment_ref` varchar(200) DEFAULT NULL COMMENT 'UPI/bank ref number',
  `payment_note` text DEFAULT NULL,
  `activated_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'super_admin id',
  `activated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shop_subscriptions`
--

INSERT INTO `shop_subscriptions` (`id`, `shop_id`, `plan_id`, `status`, `started_at`, `expires_at`, `grace_until`, `payment_ref`, `payment_note`, `activated_by`, `activated_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'active', '2026-08-12 00:00:00', '2026-09-30 00:00:00', '2026-09-18 00:00:00', '', '', 1, '2026-08-12 07:56:07', '2026-08-07 04:32:37', '2026-08-12 08:07:51'),
(2, 4, 3, 'active', '2026-08-09 00:00:00', '2026-09-08 00:00:00', '2026-09-15 00:00:00', '', '', 1, '2026-08-09 10:20:45', '2026-08-07 04:32:37', '2026-08-09 10:20:45'),
(3, 5, 1, 'trial', '2026-07-23 06:12:32', '2026-08-22 06:12:32', '2026-08-29 06:12:32', NULL, NULL, NULL, NULL, '2026-08-07 04:32:37', '2026-08-07 04:32:37'),
(4, 6, 1, 'trial', '2026-07-25 07:03:08', '2026-08-24 07:03:08', '2026-08-31 07:03:08', NULL, NULL, NULL, NULL, '2026-08-07 04:32:37', '2026-08-07 04:32:37'),
(8, 7, 1, 'trial', '2026-08-07 04:37:22', '2026-09-06 04:37:22', '2026-09-13 04:37:22', NULL, NULL, NULL, NULL, '2026-08-07 04:37:22', '2026-08-07 04:37:22'),
(9, 8, 1, 'trial', '2026-08-07 04:49:54', '2026-09-06 04:49:54', '2026-09-13 04:49:54', NULL, NULL, NULL, NULL, '2026-08-07 04:49:54', '2026-08-07 04:49:54'),
(10, 9, 2, 'active', '2026-08-09 00:00:00', '2026-09-08 00:00:00', '2026-09-15 00:00:00', '78574268784', '', 1, '2026-08-09 10:19:24', '2026-08-07 04:51:47', '2026-08-09 10:19:24'),
(11, 10, 1, 'trial', '2026-08-14 13:10:54', '2026-09-13 13:10:54', '2026-09-20 13:10:54', NULL, NULL, NULL, NULL, '2026-08-14 13:10:54', '2026-08-14 13:10:54');

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
(1, 'Super Admin', 'admin@tamizhmart.com', '$2y$10$EkXp7HTZNB1WPzuk4wtgfOiY/sN9F7e.n0tMH1ncc4ikBwgDchDuu', '2026-07-17 07:17:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `google_id` varchar(128) DEFAULT NULL,
  `auth_provider` enum('local','google') NOT NULL DEFAULT 'local'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `shop_id`, `name`, `email`, `password`, `phone`, `address`, `is_active`, `created_at`, `google_id`, `auth_provider`) VALUES
(1, 1, 'Siva Balaji', 'sivabalaji800@gmail.com', '$2y$10$t/9lCyzuYKunb6WA9J9nCOmZK7Q5piN22QuJ8PiSV.QKmi2hxJCIe', '7904538655', '5-2-49, Vaniyar Street, Vadugapatti', 1, '2026-07-18 06:54:35', NULL, 'local'),
(2, 1, 'Sharma', 'sharmamedhini1975@gmail.com', '$2y$10$YSW7sAuyu0tHPHPtrxosjeQnsH.NgU2bMoomoqC13LOJgjLW0pi9.', '8110079936', 'Ooty', 1, '2026-07-27 04:39:53', NULL, 'local'),
(3, 9, 'Raja', 'sivabalaji800@gmail.com', '$2y$10$COMFAR94OyZMrZC8L8pQ4eIk/1ugcOHqUldoSr.JkH7jYik0Ml9IC', '74185296374', 'Theni', 1, '2026-08-09 10:18:30', NULL, 'local'),
(4, 1, 'Sasi', 'rsasi5972@gmail.com', '$2y$10$fcGHyeqQFBG6JEVpIQaPgOZoIIIDScFr3GJvIa9XlqXK5Cw62EV46', '6385672778', 'Pochampalli, Krishnagiri', 1, '2026-08-12 10:48:52', '104922149306396636830', 'google'),
(7, 1, 'Siva Balaji', 'sivabalaji2335@gmail.com', NULL, '7904538655', '', 1, '2026-08-15 16:53:38', '106486502399939543042', 'google');

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
-- Indexes for table `commission_log`
--
ALTER TABLE `commission_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop` (`shop_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_collected` (`collected`);

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
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

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
-- Indexes for table `shop_subscriptions`
--
ALTER TABLE `shop_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop` (`shop_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires` (`expires_at`);

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
  ADD UNIQUE KEY `shop_user_email` (`shop_id`,`email`),
  ADD UNIQUE KEY `uq_google_id_shop` (`google_id`,`shop_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `commission_log`
--
ALTER TABLE `commission_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=173;

--
-- AUTO_INCREMENT for table `owners`
--
ALTER TABLE `owners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `popups`
--
ALTER TABLE `popups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `shop_settings`
--
ALTER TABLE `shop_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `shop_subscriptions`
--
ALTER TABLE `shop_subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
