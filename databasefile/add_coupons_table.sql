-- ============================================================
-- TamizhMart — Coupon System Migration
-- Run this in phpMyAdmin on your tamizhmart_db database
-- ============================================================

-- 1. New coupons table
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id`     int(10) UNSIGNED NOT NULL,
  `code`        varchar(30) NOT NULL,
  `type`        enum('flat','percent') NOT NULL DEFAULT 'flat',
  `value`       decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order`   decimal(10,2) NOT NULL DEFAULT 0.00   COMMENT 'Minimum cart total required',
  `max_uses`    int(11) DEFAULT NULL                  COMMENT 'NULL = unlimited',
  `used_count`  int(11) NOT NULL DEFAULT 0,
  `expires_at`  date DEFAULT NULL                     COMMENT 'NULL = never expires',
  `is_active`   tinyint(1) NOT NULL DEFAULT 1,
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_code` (`shop_id`, `code`),
  KEY `idx_shop` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add discount columns to orders table
ALTER TABLE `orders`
  ADD COLUMN `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `shipping_fee`,
  ADD COLUMN `coupon_code` varchar(30) DEFAULT NULL AFTER `discount_amount`;
