-- ============================================================
-- TamizhMart — Product Handler Feature Migration
-- Run this once against tamizhmart_db
-- ============================================================

-- 1. Handler accounts (created by shop owner)
CREATE TABLE IF NOT EXISTS `shop_handlers` (
  `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id`     INT(10) UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `email`       VARCHAR(180) NOT NULL,
  `password`    VARCHAR(255) NOT NULL,
  `phone`       VARCHAR(30) DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `cod_wallet`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_handler_email` (`email`),
  KEY `idx_handler_shop` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Audit trail — every action a handler takes
CREATE TABLE IF NOT EXISTS `handler_activity_log` (
  `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `handler_id`  INT(10) UNSIGNED NOT NULL,
  `shop_id`     INT(10) UNSIGNED NOT NULL,
  `order_id`    INT(10) UNSIGNED NOT NULL,
  `action`      VARCHAR(80) NOT NULL,
  `old_value`   VARCHAR(60) DEFAULT NULL,
  `new_value`   VARCHAR(60) DEFAULT NULL,
  `note`        TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hal_handler` (`handler_id`),
  KEY `idx_hal_order`   (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Delivery OTPs (one per order, replaced on resend)
CREATE TABLE IF NOT EXISTS `delivery_otps` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     INT(10) UNSIGNED NOT NULL,
  `handler_id`   INT(10) UNSIGNED NOT NULL,
  `otp_code`     VARCHAR(6) NOT NULL,
  `sent_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`   TIMESTAMP NOT NULL,
  `verified_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dotp_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. COD settlements (owner resets handler wallet to 0)
CREATE TABLE IF NOT EXISTS `handler_cod_settlements` (
  `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `handler_id`  INT(10) UNSIGNED NOT NULL,
  `shop_id`     INT(10) UNSIGNED NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `settled_by`  INT(10) UNSIGNED NOT NULL,
  `note`        VARCHAR(300) DEFAULT NULL,
  `settled_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hcs_handler` (`handler_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
