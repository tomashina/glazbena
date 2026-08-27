<?php
class ModelExtensionModuleAgmediaReviewReminder extends Model {
	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "agmedia_review_reminder_delivery` (
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
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "agmedia_review_reminder_suppression` (
			`suppression_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`store_id` INT UNSIGNED NOT NULL DEFAULT '0',
			`email` VARCHAR(254) NOT NULL DEFAULT '',
			`reason` VARCHAR(191) NOT NULL DEFAULT '',
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`suppression_id`),
			UNIQUE KEY `store_email` (`store_id`, `email`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "agmedia_review_reminder_attempt` (
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
			KEY `store_date_added` (`store_id`, `date_added`),
			KEY `date_added` (`date_added`),
			KEY `delivery_id` (`delivery_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->importLegacyState();
	}

	public function uninstall() {
		// Delivery history and suppressions are deliberately retained for audit
		// and to prevent duplicate reminders after a later reinstall.
	}

	public function hasDeliveryTable() {
		return $this->tableExists('agmedia_review_reminder_delivery');
	}

	public function getRecentDeliveries($limit = 20) {
		$limit = max(1, min(100, (int)$limit));
		$query = $this->db->query("SELECT `delivery_id`, `order_id`, `email`, `status`, `attempts`, `last_error`, `sent_at`, `date_modified` FROM `" . DB_PREFIX . "agmedia_review_reminder_delivery` ORDER BY `date_modified` DESC, `delivery_id` DESC LIMIT " . $limit);

		return $query->rows;
	}

	public function getDeliverySummary() {
		$summary = array();
		$query = $this->db->query("SELECT `status`, COUNT(*) AS `total` FROM `" . DB_PREFIX . "agmedia_review_reminder_delivery` GROUP BY `status`");

		foreach ($query->rows as $row) {
			$summary[$row['status']] = (int)$row['total'];
		}

		return $summary;
	}

	private function importLegacyState() {
		if ($this->tableExists('orderreviews_mail_log')) {
			$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				(`store_id`, `order_id`, `email`, `language_id`, `status`, `attempts`, `next_attempt_at`, `claimed_at`, `sending_at`, `sent_at`, `message_id`, `last_error`, `date_added`, `date_modified`)
				SELECT ml.`store_id`, ml.`order_id`, LEFT(LOWER(TRIM(o.`email`)), 254), o.`language_id`, 'sent', 0, NULL, NULL, NULL,
					CASE WHEN ml.`date` IS NOT NULL AND YEAR(ml.`date`) > 0 THEN ml.`date` ELSE COALESCE(o.`date_added`, NOW()) END,
					'', 'Imported from legacy OrderReviews sent log.',
					CASE WHEN ml.`date` IS NOT NULL AND YEAR(ml.`date`) > 0 THEN ml.`date` ELSE COALESCE(o.`date_added`, NOW()) END,
					CASE WHEN ml.`date` IS NOT NULL AND YEAR(ml.`date`) > 0 THEN ml.`date` ELSE COALESCE(o.`date_added`, NOW()) END
				FROM `" . DB_PREFIX . "orderreviews_mail_log` ml
				INNER JOIN `" . DB_PREFIX . "order` o ON (o.`order_id` = ml.`order_id`)
				WHERE o.`email` <> ''");
		}

		if ($this->tableExists('orderreviews_log')) {
			$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				(`store_id`, `order_id`, `email`, `language_id`, `status`, `attempts`, `next_attempt_at`, `claimed_at`, `sending_at`, `sent_at`, `message_id`, `last_error`, `date_added`, `date_modified`)
				SELECT rl.`store_id`, rl.`order_id`, LEFT(LOWER(TRIM(o.`email`)), 254), o.`language_id`, 'suppressed', 0, NULL, NULL, NULL,
					NULL, '', 'Imported because a legacy review already exists.',
					COALESCE(o.`date_added`, NOW()), COALESCE(o.`date_added`, NOW())
				FROM (SELECT DISTINCT `store_id`, `order_id` FROM `" . DB_PREFIX . "orderreviews_log`) rl
				INNER JOIN `" . DB_PREFIX . "order` o ON (o.`order_id` = rl.`order_id`)
				WHERE o.`email` <> ''");
		}

		if ($this->tableExists('orderreviews_suppression')) {
			$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "agmedia_review_reminder_suppression`
				(`store_id`, `email`, `reason`, `date_added`)
				SELECT stores.`store_id`, LEFT(LOWER(TRIM(s.`email`)), 254), LEFT(COALESCE(s.`reason`, 'legacy_suppression'), 191),
					CASE WHEN s.`created_at` IS NULL OR YEAR(s.`created_at`) < 1000 THEN NOW() ELSE s.`created_at` END
				FROM `" . DB_PREFIX . "orderreviews_suppression` s
				CROSS JOIN (SELECT 0 AS `store_id` UNION SELECT `store_id` FROM `" . DB_PREFIX . "store`) stores
				WHERE s.`email` <> ''");
		}
	}

	private function tableExists($table) {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . $table) . "'");

		return !empty($query->row['total']);
	}
}
