-- OrderReviews safe delivery migration
-- Production OpenCart table prefix confirmed by the site owner: oc_
-- Run this only on the intended live OpenCart database.
-- This migration is additive: it does not delete or change existing OrderReviews data.

CREATE TABLE IF NOT EXISTS `oc_orderreviews_delivery` (
  `delivery_id` INT(11) NOT NULL AUTO_INCREMENT,
  `store_id` INT(11) NOT NULL DEFAULT 0,
  `order_id` INT(11) NOT NULL,
  `reviewmail_id` INT(11) NOT NULL DEFAULT 0,
  `email` VARCHAR(254) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `available_at` DATETIME NOT NULL,
  `locked_at` DATETIME NULL DEFAULT NULL,
  `last_attempt_at` DATETIME NULL DEFAULT NULL,
  `sent_at` DATETIME NULL DEFAULT NULL,
  `last_smtp_code` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `last_error` VARCHAR(1000) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`delivery_id`),
  UNIQUE KEY `uniq_orderreviews_delivery_order` (`store_id`, `order_id`),
  KEY `idx_orderreviews_delivery_queue` (`status`, `available_at`, `delivery_id`),
  KEY `idx_orderreviews_delivery_attempt` (`last_attempt_at`),
  KEY `idx_orderreviews_delivery_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `oc_orderreviews_suppression` (
  `suppression_id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(254) NOT NULL,
  `reason` VARCHAR(1000) NULL DEFAULT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'worker',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`suppression_id`),
  UNIQUE KEY `uniq_orderreviews_suppression_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Add lookup indexes to legacy log tables when those module tables exist.
SET @or_mail_log_table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_mail_log'
);
SET @or_mail_log_index_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'oc_orderreviews_mail_log'
    AND INDEX_NAME = 'idx_orderreviews_mail_log_order'
);
SET @or_mail_log_index_sql := IF(
  @or_mail_log_table_exists = 0 OR @or_mail_log_index_exists > 0,
  'SELECT 1',
  'ALTER TABLE `oc_orderreviews_mail_log` ADD KEY `idx_orderreviews_mail_log_order` (`store_id`, `order_id`)'
);
PREPARE or_mail_log_index_stmt FROM @or_mail_log_index_sql;
EXECUTE or_mail_log_index_stmt;
DEALLOCATE PREPARE or_mail_log_index_stmt;

SET @or_review_log_table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_log'
);
SET @or_review_log_index_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'oc_orderreviews_log'
    AND INDEX_NAME = 'idx_orderreviews_log_order'
);
SET @or_review_log_index_sql := IF(
  @or_review_log_table_exists = 0 OR @or_review_log_index_exists > 0,
  'SELECT 1',
  'ALTER TABLE `oc_orderreviews_log` ADD KEY `idx_orderreviews_log_order` (`order_id`)'
);
PREPARE or_review_log_index_stmt FROM @or_review_log_index_sql;
EXECUTE or_review_log_index_stmt;
DEALLOCATE PREPARE or_review_log_index_stmt;

-- Preserve the one address that the previous implementation explicitly blocked.
INSERT INTO `oc_orderreviews_suppression`
       (`email`, `reason`, `source`, `created_at`, `updated_at`)
VALUES (LOWER(TRIM('anusicluka22@gmail.com')), 'Migrated from legacy blocklist', 'migration', NOW(), NOW())
ON DUPLICATE KEY UPDATE
       `reason` = VALUES(`reason`), `source` = VALUES(`source`), `updated_at` = NOW();
