-- TamizhMart Email Campaigns
-- Run manually only if your server does not allow the PHP pages to create tables automatically.

CREATE TABLE IF NOT EXISTS `email_campaign_allowances` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int unsigned NOT NULL,
  `month_key` char(7) NOT NULL,
  `monthly_limit` int unsigned NOT NULL DEFAULT 2,
  `used_count` int unsigned NOT NULL DEFAULT 0,
  `reset_count` int unsigned NOT NULL DEFAULT 0,
  `reset_by` int unsigned DEFAULT NULL,
  `reset_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shop_month` (`shop_id`,`month_key`),
  KEY `idx_month_key` (`month_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_campaigns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int unsigned NOT NULL,
  `owner_id` int unsigned NOT NULL,
  `month_key` char(7) NOT NULL,
  `preset` varchar(40) NOT NULL,
  `campaign_type` varchar(40) NOT NULL,
  `audience_type` varchar(40) NOT NULL DEFAULT 'all_registered',
  `subject` varchar(180) NOT NULL,
  `headline` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `offer_text` varchar(220) DEFAULT NULL,
  `coupon_code` varchar(60) DEFAULT NULL,
  `cta_label` varchar(80) DEFAULT 'Shop Now',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `recipient_count` int unsigned NOT NULL DEFAULT 0,
  `sent_count` int unsigned NOT NULL DEFAULT 0,
  `failed_count` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_month` (`shop_id`,`month_key`),
  KEY `idx_shop_status` (`shop_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_campaign_products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_campaign_recipients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `email` varchar(180) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
