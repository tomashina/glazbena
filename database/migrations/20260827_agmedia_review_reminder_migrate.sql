-- AGmedia Review Reminder: independent schema and conservative OrderReviews migration
-- Production OpenCart table prefix confirmed by the site owner: oc_
-- Target database: MySQL 8.0+ (uses JSON_EXTRACT for operational setting migration).
--
-- IMPORTANT CUTOVER RULE:
-- Disable the legacy OrderReviews EasyCron job and wait for any running request to
-- finish before executing this file. The new module must remain disabled until the
-- verification queries at the end have been reviewed.
--
-- This migration is repeatable and never deletes legacy data. As an intentional,
-- reversible cutover step it changes only `oc_orderreviews_setting.Enabled` to `no`;
-- it does not alter any other iSenseLabs data, files, licence values, message
-- templates, reviews, or coupons.

CREATE TABLE IF NOT EXISTS `oc_agmedia_review_reminder_delivery` (
  `delivery_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` INT UNSIGNED NOT NULL DEFAULT '0',
  `order_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(254) NOT NULL DEFAULT '',
  `language_id` INT UNSIGNED NOT NULL DEFAULT '0',
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
  `next_attempt_at` DATETIME DEFAULT NULL,
  `claimed_at` DATETIME DEFAULT NULL,
  `sending_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `message_id` VARCHAR(191) NOT NULL DEFAULT '',
  `last_error` TEXT,
  `date_added` DATETIME NOT NULL,
  `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`delivery_id`),
  UNIQUE KEY `store_order` (`store_id`, `order_id`),
  KEY `status_next` (`status`, `next_attempt_at`),
  KEY `store_status` (`store_id`, `status`),
  KEY `store_email` (`store_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_agmedia_review_reminder_suppression` (
  `suppression_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` INT UNSIGNED NOT NULL DEFAULT '0',
  `email` VARCHAR(254) NOT NULL DEFAULT '',
  `reason` VARCHAR(191) NOT NULL DEFAULT '',
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`suppression_id`),
  UNIQUE KEY `store_email` (`store_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_agmedia_review_reminder_attempt` (
  `attempt_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_id` BIGINT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NOT NULL DEFAULT '0',
  `order_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(254) NOT NULL DEFAULT '',
  `outcome` VARCHAR(24) NOT NULL DEFAULT 'started',
  `smtp_code` VARCHAR(16) DEFAULT NULL,
  `error` TEXT,
  `date_added` DATETIME NOT NULL,
  `date_finished` DATETIME DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `date_added` (`date_added`),
  KEY `store_date_added` (`store_id`, `date_added`),
  KEY `delivery_id` (`delivery_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discover optional legacy tables. Dynamic SQL lets the migration remain safe on
-- a clean installation where one or more legacy tables do not exist.
SET @arr_has_legacy_delivery := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_delivery'
);
SET @arr_has_legacy_mail_log := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_mail_log'
);
SET @arr_has_legacy_review_log := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_log'
);
SET @arr_has_legacy_suppression := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_suppression'
);
SET @arr_has_legacy_mail_settings := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_mail'
);
SET @arr_has_legacy_global_settings := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_orderreviews_setting'
);

-- Retire the legacy sender before the independent worker is ever enabled. The
-- core setting is also checked by the old endpoint, so a stale/leaked EasyCron
-- URL cannot run a second worker with a separate lock and hourly counter.
UPDATE `oc_setting`
SET `value` = '1', `serialized` = 0
WHERE `store_id` = 0
  AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_legacy_endpoint_disabled';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_legacy_endpoint_disabled', '1', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `oc_setting`
  WHERE `store_id` = 0
    AND `code` = 'module_agmedia_review_reminder'
    AND `key` = 'module_agmedia_review_reminder_legacy_endpoint_disabled'
);

SET @arr_disable_legacy_module_sql := IF(
  @arr_has_legacy_global_settings = 0,
  'SELECT 1',
  'UPDATE `oc_orderreviews_setting`
   SET `value` = JSON_SET(`value`, ''$.Enabled'', ''no'')
   WHERE `store_id` = 0
     AND `code` = ''orderreviews''
     AND `key` = ''orderreviews''
     AND JSON_VALID(`value`)'
);
PREPARE arr_disable_legacy_module_stmt FROM @arr_disable_legacy_module_sql;
EXECUTE arr_disable_legacy_module_stmt;
DEALLOCATE PREPARE arr_disable_legacy_module_stmt;

-- Import the safe-delivery queue before terminal logs. State mapping is based on
-- the old worker's actual SMTP boundary:
--   pending/retry    -> pending/retry (definitely not accepted)
--   processing       -> retry (old state was set before SMTP DATA; attempt undone)
--   sending/unknown  -> unknown (possibly accepted; never auto-retry)
--   sent             -> sent
--   failed/cancelled -> failed/cancelled
--   suppressed       -> suppressed
-- Any unrecognised state is terminal `unknown`, which is the duplicate-safe choice.
SET @arr_import_delivery_sql := IF(
  @arr_has_legacy_delivery = 0,
  'SELECT 1',
  'INSERT IGNORE INTO `oc_agmedia_review_reminder_delivery`
    (`store_id`, `order_id`, `email`, `language_id`, `status`, `attempts`,
     `next_attempt_at`, `claimed_at`, `sending_at`, `sent_at`, `message_id`,
     `last_error`, `date_added`, `date_modified`)
   SELECT
     CAST(ld.`store_id` AS UNSIGNED),
     CAST(ld.`order_id` AS UNSIGNED),
     LOWER(TRIM(COALESCE(NULLIF(ld.`email`, ''''), o.`email`, ''''))),
     CAST(COALESCE(o.`language_id`, 0) AS UNSIGNED),
     CASE LOWER(ld.`status`)
       WHEN ''pending'' THEN ''pending''
       WHEN ''retry'' THEN ''retry''
       WHEN ''processing'' THEN ''retry''
       WHEN ''sending'' THEN ''unknown''
       WHEN ''unknown'' THEN ''unknown''
       WHEN ''sent'' THEN ''sent''
       WHEN ''failed'' THEN ''failed''
       WHEN ''cancelled'' THEN ''cancelled''
       WHEN ''suppressed'' THEN ''suppressed''
       ELSE ''unknown''
     END,
     CASE
       WHEN LOWER(ld.`status`) = ''processing'' AND CAST(ld.`attempts` AS UNSIGNED) > 0
         THEN CAST(ld.`attempts` AS UNSIGNED) - 1
       WHEN LOWER(ld.`status`) = ''processing'' THEN 0
       ELSE CAST(ld.`attempts` AS UNSIGNED)
     END,
     CASE
       WHEN LOWER(ld.`status`) IN (''pending'', ''retry'')
         THEN CASE WHEN ld.`available_at` IS NOT NULL AND YEAR(ld.`available_at`) > 0
           THEN ld.`available_at` ELSE NOW() END
       WHEN LOWER(ld.`status`) = ''processing'' THEN NOW()
       ELSE NULL
     END,
     NULL,
     CASE WHEN LOWER(ld.`status`) IN (''sending'', ''unknown'')
       THEN COALESCE(ld.`locked_at`, ld.`last_attempt_at`) ELSE NULL END,
     CASE WHEN ld.`sent_at` IS NOT NULL AND YEAR(ld.`sent_at`) > 0
       THEN ld.`sent_at` ELSE NULL END,
     '''',
     CONCAT(
       CASE LOWER(ld.`status`)
         WHEN ''processing'' THEN ''Migrated from legacy pre-SMTP processing state; safe to retry. ''
         WHEN ''sending'' THEN ''Migrated with UNKNOWN SMTP outcome; automatic retry blocked. ''
         WHEN ''unknown'' THEN ''Migrated with UNKNOWN SMTP outcome; automatic retry blocked. ''
         WHEN ''cancelled'' THEN ''Migrated from legacy cancelled state. ''
         ELSE ''Migrated from legacy delivery state. ''
       END,
       COALESCE(ld.`last_error`, '''')
     ),
     CASE WHEN ld.`created_at` IS NOT NULL AND YEAR(ld.`created_at`) > 0
       THEN ld.`created_at` ELSE NOW() END,
     CASE WHEN ld.`updated_at` IS NOT NULL AND YEAR(ld.`updated_at`) > 0
       THEN ld.`updated_at` ELSE NOW() END
   FROM `oc_orderreviews_delivery` ld
   LEFT JOIN `oc_order` o
     ON o.`store_id` = ld.`store_id` AND o.`order_id` = ld.`order_id`
   WHERE ld.`order_id` > 0'
);
PREPARE arr_import_delivery_stmt FROM @arr_import_delivery_sql;
EXECUTE arr_import_delivery_stmt;
DEALLOCATE PREPARE arr_import_delivery_stmt;

-- If a row already existed in the new table, legacy terminal evidence must still
-- stop it. A confirmed new/legacy send is never downgraded; uncertain delivery is
-- stronger than suppression/failure; active new work is otherwise terminalised.
SET @arr_reconcile_delivery_sql := IF(
  @arr_has_legacy_delivery = 0,
  'SELECT 1',
  'UPDATE `oc_agmedia_review_reminder_delivery` nd
   INNER JOIN `oc_orderreviews_delivery` ld
     ON ld.`store_id` = nd.`store_id` AND ld.`order_id` = nd.`order_id`
   SET
     nd.`status` = CASE
       WHEN nd.`status` = ''sent'' OR LOWER(ld.`status`) = ''sent'' THEN ''sent''
       WHEN nd.`status` = ''unknown'' OR LOWER(ld.`status`) IN (''sending'', ''unknown'') THEN ''unknown''
       WHEN nd.`status` = ''suppressed'' OR LOWER(ld.`status`) = ''suppressed'' THEN ''suppressed''
       WHEN nd.`status` IN (''failed'', ''cancelled'') THEN nd.`status`
       WHEN LOWER(ld.`status`) = ''cancelled'' THEN ''cancelled''
       ELSE ''failed''
     END,
     nd.`next_attempt_at` = NULL,
     nd.`claimed_at` = NULL,
     nd.`sending_at` = CASE
       WHEN LOWER(ld.`status`) IN (''sending'', ''unknown'')
         THEN COALESCE(nd.`sending_at`, ld.`locked_at`, ld.`last_attempt_at`)
       ELSE nd.`sending_at`
     END,
     nd.`sent_at` = CASE
       WHEN LOWER(ld.`status`) = ''sent'' THEN COALESCE(nd.`sent_at`, ld.`sent_at`, ld.`updated_at`)
       ELSE nd.`sent_at`
     END,
     nd.`last_error` = CASE
       WHEN nd.`last_error` IS NULL OR nd.`last_error` = ''''
         THEN CONCAT(''Reconciled from terminal legacy state: '', LOWER(ld.`status`), ''.'')
       ELSE nd.`last_error`
     END,
     nd.`date_modified` = NOW()
   WHERE LOWER(ld.`status`) IN (''sent'', ''sending'', ''unknown'', ''failed'', ''cancelled'', ''suppressed'')
      OR LOWER(ld.`status`) NOT IN (''pending'', ''retry'', ''processing'')'
);
PREPARE arr_reconcile_delivery_stmt FROM @arr_reconcile_delivery_sql;
EXECUTE arr_reconcile_delivery_stmt;
DEALLOCATE PREPARE arr_reconcile_delivery_stmt;

-- A submitted legacy review is terminal evidence that no reminder is needed. It is
-- represented as suppressed unless stronger sent/unknown evidence already exists.
SET @arr_import_review_log_sql := IF(
  @arr_has_legacy_review_log = 0,
  'SELECT 1',
  'INSERT INTO `oc_agmedia_review_reminder_delivery`
    (`store_id`, `order_id`, `email`, `language_id`, `status`, `attempts`,
     `next_attempt_at`, `claimed_at`, `sending_at`, `sent_at`, `message_id`,
     `last_error`, `date_added`, `date_modified`)
   SELECT
     rl.`store_id`, rl.`order_id`, LOWER(TRIM(COALESCE(o.`email`, ''''))),
     COALESCE(o.`language_id`, 0), ''suppressed'', 0,
     NULL, NULL, NULL, NULL, '''',
     ''Migrated legacy review: reminder is no longer required.'',
     rl.`reviewed_at`, rl.`reviewed_at`
   FROM (
     SELECT `store_id`, `order_id`,
       COALESCE(MAX(CASE WHEN `date_created` IS NOT NULL AND YEAR(`date_created`) > 0
         THEN `date_created` END), NOW()) AS `reviewed_at`
     FROM `oc_orderreviews_log`
     WHERE `order_id` > 0
     GROUP BY `store_id`, `order_id`
   ) rl
   LEFT JOIN `oc_order` o
     ON o.`store_id` = rl.`store_id` AND o.`order_id` = rl.`order_id`
   ON DUPLICATE KEY UPDATE
     `status` = CASE
       WHEN `status` IN (''sent'', ''unknown'') THEN `status`
       ELSE ''suppressed''
     END,
     `next_attempt_at` = NULL,
     `claimed_at` = NULL,
     `last_error` = CASE
       WHEN `last_error` IS NULL OR `last_error` = '''' THEN VALUES(`last_error`)
       ELSE `last_error`
     END,
     `date_modified` = NOW()'
);
PREPARE arr_import_review_log_stmt FROM @arr_import_review_log_sql;
EXECUTE arr_import_review_log_stmt;
DEALLOCATE PREPARE arr_import_review_log_stmt;

-- The legacy sent-mail log is the strongest evidence and therefore runs last. It
-- forces a terminal sent state even if another source was ambiguous.
SET @arr_import_mail_log_sql := IF(
  @arr_has_legacy_mail_log = 0,
  'SELECT 1',
  'INSERT INTO `oc_agmedia_review_reminder_delivery`
    (`store_id`, `order_id`, `email`, `language_id`, `status`, `attempts`,
     `next_attempt_at`, `claimed_at`, `sending_at`, `sent_at`, `message_id`,
     `last_error`, `date_added`, `date_modified`)
   SELECT
     ml.`store_id`, ml.`order_id`, LOWER(TRIM(COALESCE(o.`email`, ''''))),
     COALESCE(o.`language_id`, 0), ''sent'', 1,
     NULL, NULL, NULL, ml.`sent_at`, '''',
     ''Migrated from legacy sent-mail log (confirmed sent).'',
     ml.`sent_at`, ml.`sent_at`
   FROM (
     SELECT `store_id`, `order_id`,
       COALESCE(MAX(CASE WHEN `date` IS NOT NULL AND YEAR(`date`) > 0 THEN `date` END), NOW()) AS `sent_at`
     FROM `oc_orderreviews_mail_log`
     WHERE `order_id` > 0
     GROUP BY `store_id`, `order_id`
   ) ml
   LEFT JOIN `oc_order` o
     ON o.`store_id` = ml.`store_id` AND o.`order_id` = ml.`order_id`
   ON DUPLICATE KEY UPDATE
     `email` = CASE WHEN `oc_agmedia_review_reminder_delivery`.`email` = ''''
       THEN VALUES(`email`) ELSE `oc_agmedia_review_reminder_delivery`.`email` END,
     `language_id` = CASE WHEN `oc_agmedia_review_reminder_delivery`.`language_id` = 0
       THEN VALUES(`language_id`) ELSE `oc_agmedia_review_reminder_delivery`.`language_id` END,
     `status` = ''sent'',
     `attempts` = GREATEST(`oc_agmedia_review_reminder_delivery`.`attempts`, 1),
     `next_attempt_at` = NULL,
     `claimed_at` = NULL,
     `sent_at` = COALESCE(`oc_agmedia_review_reminder_delivery`.`sent_at`, VALUES(`sent_at`)),
     `last_error` = CASE
       WHEN `oc_agmedia_review_reminder_delivery`.`last_error` IS NULL
         OR `oc_agmedia_review_reminder_delivery`.`last_error` = '''' THEN VALUES(`last_error`)
       ELSE `oc_agmedia_review_reminder_delivery`.`last_error`
     END,
     `date_modified` = NOW()'
);
PREPARE arr_import_mail_log_stmt FROM @arr_import_mail_log_sql;
EXECUTE arr_import_mail_log_stmt;
DEALLOCATE PREPARE arr_import_mail_log_stmt;

-- Legacy suppression was global. Copy each address to the default store and every
-- configured OpenCart store because the independent table is deliberately scoped
-- by store_id. Full 254-character addresses are retained without truncation.
SET @arr_import_suppression_sql := IF(
  @arr_has_legacy_suppression = 0,
  'SELECT 1',
  'INSERT INTO `oc_agmedia_review_reminder_suppression`
    (`store_id`, `email`, `reason`, `date_added`)
   SELECT stores.`store_id`, LOWER(TRIM(ls.`email`)),
     LEFT(COALESCE(NULLIF(ls.`reason`, ''''), ''Migrated legacy suppression''), 191),
     CASE WHEN ls.`created_at` IS NOT NULL AND YEAR(ls.`created_at`) > 0
       THEN ls.`created_at` ELSE NOW() END
   FROM `oc_orderreviews_suppression` ls
   CROSS JOIN (
     SELECT 0 AS `store_id`
     UNION SELECT `store_id` FROM `oc_store`
   ) stores
   WHERE TRIM(ls.`email`) <> '''' AND CHAR_LENGTH(TRIM(ls.`email`)) <= 254
   ON DUPLICATE KEY UPDATE
     `reason` = CASE WHEN `oc_agmedia_review_reminder_suppression`.`reason` = ''''
       THEN VALUES(`reason`) ELSE `oc_agmedia_review_reminder_suppression`.`reason` END'
);
PREPARE arr_import_suppression_stmt FROM @arr_import_suppression_sql;
EXECUTE arr_import_suppression_stmt;
DEALLOCATE PREPARE arr_import_suppression_stmt;

-- Backfill suppression from legacy delivery rows too, in case an address was
-- terminalised before the legacy suppression insert completed.
SET @arr_import_suppressed_delivery_sql := IF(
  @arr_has_legacy_delivery = 0,
  'SELECT 1',
  'INSERT INTO `oc_agmedia_review_reminder_suppression`
    (`store_id`, `email`, `reason`, `date_added`)
   SELECT ld.`store_id`, LOWER(TRIM(ld.`email`)),
     LEFT(COALESCE(NULLIF(ld.`last_error`, ''''), ''Migrated suppressed legacy delivery''), 191),
     CASE WHEN ld.`updated_at` IS NOT NULL AND YEAR(ld.`updated_at`) > 0
       THEN ld.`updated_at` ELSE NOW() END
   FROM `oc_orderreviews_delivery` ld
   WHERE LOWER(ld.`status`) = ''suppressed''
     AND TRIM(ld.`email`) <> '''' AND CHAR_LENGTH(TRIM(ld.`email`)) <= 254
   ON DUPLICATE KEY UPDATE
     `reason` = CASE WHEN `oc_agmedia_review_reminder_suppression`.`reason` = ''''
       THEN VALUES(`reason`) ELSE `oc_agmedia_review_reminder_suppression`.`reason` END'
);
PREPARE arr_import_suppressed_delivery_stmt FROM @arr_import_suppressed_delivery_sql;
EXECUTE arr_import_suppressed_delivery_stmt;
DEALLOCATE PREPARE arr_import_suppressed_delivery_stmt;

-- Migrate operational settings once, without copying iSenseLabs subjects, bodies,
-- licence fields, coupon rules, or proprietary form content. The independent module
-- remains disabled. Multiple enabled legacy templates become one set of status IDs;
-- MAX(delay) is used so the new reminder is never earlier merely due to consolidation.
-- The independent worker uses immutable order.date_added (documented separately).
SET @arr_settings_marker_exists := (
  SELECT COUNT(*) FROM `oc_setting`
  WHERE `store_id` = 0
    AND `code` = 'agmedia_review_reminder_migration'
    AND `key` = '20260827_legacy_settings'
);
SET @arr_legacy_status_ids := NULL;
SET @arr_legacy_delay_days := NULL;
SET @arr_legacy_batch_size := NULL;
SET @arr_legacy_hourly_limit := NULL;
SET @arr_legacy_max_age_days := NULL;
SET @arr_legacy_max_attempts := NULL;

SET @arr_read_template_settings_sql := IF(
  @arr_settings_marker_exists > 0 OR @arr_has_legacy_mail_settings = 0,
  'SELECT NULL, NULL INTO @arr_legacy_status_ids, @arr_legacy_delay_days',
  'SELECT
     CONCAT(''['', GROUP_CONCAT(DISTINCT CAST(JSON_UNQUOTE(JSON_EXTRACT(`value`, ''$.OrderStatusID'')) AS UNSIGNED)
       ORDER BY CAST(JSON_UNQUOTE(JSON_EXTRACT(`value`, ''$.OrderStatusID'')) AS UNSIGNED) SEPARATOR '',''), '']''),
     MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(`value`, ''$.Delay'')) AS UNSIGNED))
   INTO @arr_legacy_status_ids, @arr_legacy_delay_days
   FROM `oc_orderreviews_mail`
   WHERE `code` = ''orderreviewsMailTemplate''
     AND `key` = ''ReviewMail''
     AND JSON_VALID(`value`)
     AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(`value`, ''$.Enabled''))) = ''yes''
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT(`value`, ''$.OrderStatusID'')) AS UNSIGNED) > 0
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT(`value`, ''$.Delay'')) AS UNSIGNED) BETWEEN 0 AND 90'
);
PREPARE arr_read_template_settings_stmt FROM @arr_read_template_settings_sql;
EXECUTE arr_read_template_settings_stmt;
DEALLOCATE PREPARE arr_read_template_settings_stmt;

SET @arr_read_global_settings_sql := IF(
  @arr_settings_marker_exists > 0 OR @arr_has_legacy_global_settings = 0,
  'SELECT NULL, NULL, NULL, NULL INTO @arr_legacy_batch_size, @arr_legacy_hourly_limit, @arr_legacy_max_age_days, @arr_legacy_max_attempts',
  'SELECT
     MAX(CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.BatchSize'')) AS UNSIGNED) BETWEEN 1 AND 10
       THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.BatchSize'')) AS UNSIGNED) END),
     MAX(CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.HourlyLimit'')) AS UNSIGNED) BETWEEN 1 AND 4
       THEN LEAST(CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.HourlyLimit'')) AS UNSIGNED), 2) END),
     MAX(CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.MaxAgeDays'')) AS UNSIGNED) BETWEEN 1 AND 365
       THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.MaxAgeDays'')) AS UNSIGNED) END),
     MAX(CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.MaxAttempts'')) AS UNSIGNED) BETWEEN 1 AND 5
       THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(src.`value`, ''$.MaxAttempts'')) AS UNSIGNED) END)
   INTO @arr_legacy_batch_size, @arr_legacy_hourly_limit, @arr_legacy_max_age_days, @arr_legacy_max_attempts
   FROM (
     SELECT `value`
     FROM `oc_orderreviews_setting`
     WHERE `store_id` = 0 AND `code` = ''orderreviews'' AND `key` = ''orderreviews'' AND JSON_VALID(`value`)
     ORDER BY `setting_id` DESC
     LIMIT 1
   ) src'
);
PREPARE arr_read_global_settings_stmt FROM @arr_read_global_settings_sql;
EXECUTE arr_read_global_settings_stmt;
DEALLOCATE PREPARE arr_read_global_settings_stmt;

SET @arr_legacy_max_age_days := CASE
  WHEN @arr_settings_marker_exists > 0 THEN NULL
  ELSE GREATEST(COALESCE(@arr_legacy_max_age_days, 45), COALESCE(@arr_legacy_delay_days, 0))
END;

-- The legacy module was already uninstalled on this production site, so its sent
-- history may no longer exist. This permanent floor is intentionally independent
-- of max_age_days: no order before 2026-08-27 can ever enter the new queue.
UPDATE `oc_setting`
SET `value` = CASE
      WHEN `value` REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])-([0-2][0-9]|3[01])$'
        AND `value` >= '2026-08-27' THEN `value`
      ELSE '2026-08-27'
    END,
    `serialized` = 0
WHERE `store_id` = 0
  AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_min_order_date';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_min_order_date', '2026-08-27', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `oc_setting`
  WHERE `store_id` = 0
    AND `code` = 'module_agmedia_review_reminder'
    AND `key` = 'module_agmedia_review_reminder_min_order_date'
);

-- Quarantine any active row that may already have been created before this rule
-- was deployed. A pre-cutoff `sending` row remains ambiguous and therefore becomes
-- unknown; every other pre-DATA active state is safely cancelled without sending.
UPDATE `oc_agmedia_review_reminder_attempt` a
INNER JOIN `oc_agmedia_review_reminder_delivery` d ON d.`delivery_id` = a.`delivery_id`
INNER JOIN `oc_order` o ON o.`store_id` = d.`store_id` AND o.`order_id` = d.`order_id`
SET a.`outcome` = 'failed',
    a.`error` = 'Order predates permanent 2026-08-27 delivery cutoff.',
    a.`date_finished` = NOW()
WHERE o.`date_added` < '2026-08-27 00:00:00'
  AND d.`status` IN ('pending', 'retry', 'processing', 'sending')
  AND a.`outcome` = 'started';

UPDATE `oc_agmedia_review_reminder_delivery` d
INNER JOIN `oc_order` o ON o.`store_id` = d.`store_id` AND o.`order_id` = d.`order_id`
SET d.`next_attempt_at` = NULL,
    d.`claimed_at` = NULL,
    d.`last_error` = CASE
      WHEN d.`status` = 'sending'
        THEN 'Pre-cutoff SMTP outcome is unknown; automatic retry disabled.'
      ELSE 'Order predates permanent 2026-08-27 delivery cutoff; sending disabled.'
    END,
    d.`status` = CASE WHEN d.`status` = 'sending' THEN 'unknown' ELSE 'cancelled' END,
    d.`date_modified` = NOW()
WHERE o.`date_added` < '2026-08-27 00:00:00'
  AND d.`status` IN ('pending', 'retry', 'processing', 'sending');

-- Always keep the independent module disabled during its first migration.
UPDATE `oc_setting`
SET `value` = '0', `serialized` = 0
WHERE @arr_settings_marker_exists = 0
  AND `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_status';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_status', '0', 0
WHERE @arr_settings_marker_exists = 0
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_status'
  );

UPDATE `oc_setting`
SET `value` = @arr_legacy_status_ids, `serialized` = 1
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_status_ids IS NOT NULL
  AND `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_order_status_id';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_order_status_id', @arr_legacy_status_ids, 1
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_status_ids IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_order_status_id'
  );

UPDATE `oc_setting`
SET `value` = CAST(@arr_legacy_delay_days AS CHAR), `serialized` = 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_delay_days IS NOT NULL
  AND `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_delay_days';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_delay_days', CAST(@arr_legacy_delay_days AS CHAR), 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_delay_days IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_delay_days'
  );

UPDATE `oc_setting`
SET `value` = CAST(@arr_legacy_batch_size AS CHAR), `serialized` = 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_batch_size IS NOT NULL
  AND `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_batch_size';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_batch_size', CAST(@arr_legacy_batch_size AS CHAR), 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_batch_size IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_batch_size'
  );

UPDATE `oc_setting`
SET `value` = CAST(@arr_legacy_hourly_limit AS CHAR), `serialized` = 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_hourly_limit IS NOT NULL
  AND `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_hourly_limit';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_hourly_limit', CAST(@arr_legacy_hourly_limit AS CHAR), 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_hourly_limit IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_hourly_limit'
  );

UPDATE `oc_setting`
SET `value` = CAST(@arr_legacy_max_age_days AS CHAR), `serialized` = 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_max_age_days IS NOT NULL
  AND `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_max_age_days';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_max_age_days', CAST(@arr_legacy_max_age_days AS CHAR), 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_max_age_days IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_max_age_days'
  );

UPDATE `oc_setting`
SET `value` = CAST(@arr_legacy_max_attempts AS CHAR), `serialized` = 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_max_attempts IS NOT NULL
  AND `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_max_attempts';
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_agmedia_review_reminder', 'module_agmedia_review_reminder_max_attempts', CAST(@arr_legacy_max_attempts AS CHAR), 0
WHERE @arr_settings_marker_exists = 0 AND @arr_legacy_max_attempts IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_max_attempts'
  );

-- Write a separate one-time marker only when at least one legacy configuration
-- source existed. New default subject/body and cron token remain installer-owned.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'agmedia_review_reminder_migration', '20260827_legacy_settings', NOW(), 0
WHERE @arr_settings_marker_exists = 0
  AND (@arr_has_legacy_mail_settings > 0 OR @arr_has_legacy_global_settings > 0)
  AND NOT EXISTS (
    SELECT 1 FROM `oc_setting`
    WHERE `store_id` = 0
      AND `code` = 'agmedia_review_reminder_migration'
      AND `key` = '20260827_legacy_settings'
  );

-- Verification output. No recipient addresses or message contents are exposed.
SELECT `status`, COUNT(*) AS `records`
FROM `oc_agmedia_review_reminder_delivery`
GROUP BY `status`
ORDER BY `status`;

SELECT
  SUM(`status` IN ('pending', 'retry')) AS `eligible_queue_records`,
  SUM(`status` = 'unknown') AS `manual_review_unknown_records`,
  SUM(`status` = 'sent') AS `confirmed_sent_records`,
  SUM(`status` IN ('failed', 'cancelled', 'suppressed')) AS `other_terminal_records`
FROM `oc_agmedia_review_reminder_delivery`;

SELECT COUNT(*) AS `suppressed_addresses_by_store`
FROM `oc_agmedia_review_reminder_suppression`;

SELECT COUNT(*) AS `active_records_before_cutoff_must_be_zero`
FROM `oc_agmedia_review_reminder_delivery` d
INNER JOIN `oc_order` o ON o.`store_id` = d.`store_id` AND o.`order_id` = d.`order_id`
WHERE o.`date_added` < '2026-08-27 00:00:00'
  AND d.`status` IN ('pending', 'retry', 'processing', 'sending');

SELECT
  COALESCE((
    SELECT `value` FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_min_order_date'
    ORDER BY `setting_id` DESC LIMIT 1
  ), '2026-08-27') AS `configured_min_order_date`,
  COALESCE((
    SELECT `value` FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_order_status_id'
    ORDER BY `setting_id` DESC LIMIT 1
  ), '(installer default pending)') AS `configured_order_status_ids`,
  COALESCE((
    SELECT `value` FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_delay_days'
    ORDER BY `setting_id` DESC LIMIT 1
  ), '(installer default pending)') AS `configured_delay_days`,
  COALESCE((
    SELECT `value` FROM `oc_setting`
    WHERE `store_id` = 0 AND `code` = 'module_agmedia_review_reminder'
      AND `key` = 'module_agmedia_review_reminder_status'
    ORDER BY `setting_id` DESC LIMIT 1
  ), '0') AS `module_status`;
