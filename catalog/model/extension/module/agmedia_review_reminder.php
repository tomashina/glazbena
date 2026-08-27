<?php

class ModelExtensionModuleAgmediaReviewReminder extends Model {
	private $tableExistsCache = array();
	const STATUS_PENDING = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_SENDING = 'sending';
	const STATUS_RETRY = 'retry';
	const STATUS_SENT = 'sent';
	const STATUS_FAILED = 'failed';
	const STATUS_SUPPRESSED = 'suppressed';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_UNKNOWN = 'unknown';

	public function acquireLock($storeId) {
		// The hosting failure quota applies to the SMTP domain, not one store.
		$name = $this->getLockName();
		$query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($name) . "', 0) AS acquired");

		return isset($query->row['acquired']) && (int)$query->row['acquired'] === 1;
	}

	public function releaseLock($storeId) {
		$name = $this->getLockName();
		$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($name) . "')");
	}

	/**
	 * A stale processing row is safe to retry because the SMTP DATA terminator
	 * was never started. A stale sending row is terminal: the remote server may
	 * already have accepted it, so an automatic retry could duplicate mail.
	 */
	public function recoverStale($storeId, $maxAttempts = 3) {
		$storeId = (int)$storeId;
		$maxAttempts = max(1, (int)$maxAttempts);

		$processing = $this->db->query("SELECT delivery_id, attempts FROM `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			WHERE store_id = '" . $storeId . "'
			  AND status = 'processing'
			  AND claimed_at IS NOT NULL
			  AND claimed_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");

		foreach ($processing->rows as $row) {
			$deliveryId = (int)$row['delivery_id'];
			$atCeiling = (int)$row['attempts'] >= $maxAttempts;
			$outcome = $atCeiling ? 'failed' : 'retry';
			$status = $atCeiling ? self::STATUS_FAILED : self::STATUS_RETRY;
			$error = $atCeiling
				? 'Worker stopped before SMTP DATA and maximum attempt count was reached.'
				: 'Worker stopped before SMTP DATA; safely requeued.';

			$this->db->query('START TRANSACTION');

			try {
				$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_attempt`
					SET outcome = '" . $outcome . "', error = '" . $this->db->escape($error) . "', date_finished = NOW()
					WHERE delivery_id = '" . $deliveryId . "' AND outcome = 'started'");
				$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
					SET status = '" . $status . "', next_attempt_at = " . ($atCeiling ? 'NULL' : 'NOW()') . ",
						claimed_at = NULL, last_error = '" . $this->db->escape($error) . "', date_modified = NOW()
					WHERE delivery_id = '" . $deliveryId . "' AND status = 'processing'");
				$this->db->query('COMMIT');
			} catch (\Throwable $exception) {
				$this->db->query('ROLLBACK');
				throw $exception;
			}
		}

		$sending = $this->db->query("SELECT delivery_id FROM `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			WHERE store_id = '" . $storeId . "'
			  AND status = 'sending'
			  AND sending_at IS NOT NULL
			  AND sending_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");

		foreach ($sending->rows as $row) {
			$deliveryId = (int)$row['delivery_id'];
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_attempt`
				SET outcome = 'failed', error = 'Ambiguous SMTP outcome after DATA; manual review required.', date_finished = NOW()
				WHERE delivery_id = '" . $deliveryId . "' AND outcome = 'started'");
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			SET status = 'unknown', next_attempt_at = NULL,
				last_error = 'Ambiguous SMTP outcome after DATA; automatic retry disabled.', date_modified = NOW()
			WHERE store_id = '" . $storeId . "'
			  AND status = 'sending'
			  AND sending_at IS NOT NULL
			  AND sending_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
	}

	public function enqueueEligible($storeId, array $orderStatusIds, $delayDays, $maxAgeDays, $limit = 250) {
		$storeId = (int)$storeId;
		$orderStatusIds = $this->normaliseIds($orderStatusIds);

		if (!$orderStatusIds) {
			return 0;
		}

		$delayDays = max(0, (int)$delayDays);
		$maxAgeDays = max($delayDays, (int)$maxAgeDays);
		$limit = max(1, min(1000, (int)$limit));
		$legacyGuards = $this->getLegacyDuplicateGuards();

		$sql = "SELECT o.order_id, o.store_id, o.email, o.language_id
			FROM `" . DB_PREFIX . "order` o
			LEFT JOIN `" . DB_PREFIX . "agmedia_review_reminder_delivery` d
				ON d.store_id = o.store_id AND d.order_id = o.order_id
			WHERE o.store_id = '" . $storeId . "'
			  AND d.delivery_id IS NULL
			  AND o.order_status_id IN (" . implode(',', $orderStatusIds) . ")
			  AND o.date_added <= DATE_SUB(NOW(), INTERVAL " . $delayDays . " DAY)
			  AND o.date_added >= DATE_SUB(NOW(), INTERVAL " . $maxAgeDays . " DAY)" .
			  $legacyGuards . "
			ORDER BY o.date_added ASC, o.order_id ASC
			LIMIT " . $limit;

		$query = $this->db->query($sql);
		$queued = 0;

		foreach ($query->rows as $order) {
			$email = $this->normaliseEmail($order['email']);
			$status = self::STATUS_PENDING;
			$error = '';

			if (!$this->isValidEmail($email)) {
				$status = self::STATUS_CANCELLED;
				$error = 'Order has an invalid recipient email address.';
			} elseif ($this->isSuppressed($storeId, $email)) {
				$status = self::STATUS_SUPPRESSED;
				$error = 'Recipient is on the suppression list.';
			}

			$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				SET store_id = '" . $storeId . "',
					order_id = '" . (int)$order['order_id'] . "',
					email = '" . $this->db->escape($this->limitText($email, 254)) . "',
					language_id = '" . (int)$order['language_id'] . "',
					status = '" . $this->db->escape($status) . "',
					attempts = '0', next_attempt_at = NOW(),
					last_error = " . ($error === '' ? 'NULL' : "'" . $this->db->escape($error) . "'") . ",
					date_added = NOW(), date_modified = NOW()");

			if ($this->db->countAffected()) {
				$queued++;
			}
		}

		return $queued;
	}

	public function getHourlyAttemptCount($storeId) {
		$query = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "agmedia_review_reminder_attempt`
			WHERE date_added >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");

		return isset($query->row['total']) ? (int)$query->row['total'] : 0;
	}

	public function claimNext($storeId, $maxAttempts = 3) {
		$maxAttempts = max(1, (int)$maxAttempts);
		$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			SET status = 'failed', next_attempt_at = NULL,
				last_error = 'Maximum SMTP attempt count reached.', date_modified = NOW()
			WHERE store_id = '" . (int)$storeId . "'
			  AND status IN ('pending', 'retry') AND attempts >= '" . $maxAttempts . "'");

		$query = $this->db->query("SELECT *
			FROM `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			WHERE store_id = '" . (int)$storeId . "'
			  AND status IN ('pending', 'retry')
			  AND attempts < '" . $maxAttempts . "'
			  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
			ORDER BY COALESCE(next_attempt_at, date_added) ASC, delivery_id ASC
			LIMIT 1");

		if (!$query->num_rows) {
			return array();
		}

		$deliveryId = (int)$query->row['delivery_id'];
		$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			SET status = 'processing', claimed_at = NOW(), sending_at = NULL,
				last_error = NULL, date_modified = NOW()
			WHERE delivery_id = '" . $deliveryId . "'
			  AND status IN ('pending', 'retry')");

		if (!$this->db->countAffected()) {
			return array();
		}

		$query->row['status'] = self::STATUS_PROCESSING;

		return $query->row;
	}

	public function getEligibleOrder($orderId, $storeId, array $orderStatusIds, $delayDays, $maxAgeDays) {
		$orderStatusIds = $this->normaliseIds($orderStatusIds);

		if (!$orderStatusIds) {
			return array();
		}

		$delayDays = max(0, (int)$delayDays);
		$maxAgeDays = max($delayDays, (int)$maxAgeDays);
		$query = $this->db->query("SELECT o.order_id, o.store_id, o.store_name, o.store_url,
				o.firstname, o.lastname, o.email, o.language_id, o.order_status_id, o.date_added
			FROM `" . DB_PREFIX . "order` o
			WHERE o.order_id = '" . (int)$orderId . "'
			  AND o.store_id = '" . (int)$storeId . "'
			  AND o.order_status_id IN (" . implode(',', $orderStatusIds) . ")
			  AND o.date_added <= DATE_SUB(NOW(), INTERVAL " . $delayDays . " DAY)
			  AND o.date_added >= DATE_SUB(NOW(), INTERVAL " . $maxAgeDays . " DAY)
			LIMIT 1");

		return $query->num_rows ? $query->row : array();
	}

	public function getOrderProducts($orderId) {
		$query = $this->db->query("SELECT op.order_product_id, op.product_id, op.name, op.model, op.quantity
			FROM `" . DB_PREFIX . "order_product` op
			INNER JOIN `" . DB_PREFIX . "product` p ON p.product_id = op.product_id AND p.status = '1'
			WHERE op.order_id = '" . (int)$orderId . "'
			ORDER BY op.order_product_id ASC");

		return $query->rows;
	}

	public function getLanguageCode($languageId) {
		$query = $this->db->query("SELECT code FROM `" . DB_PREFIX . "language`
			WHERE language_id = '" . (int)$languageId . "' AND status = '1' LIMIT 1");

		return $query->num_rows ? $query->row['code'] : 'hr-hr';
	}

	public function isSuppressed($storeId, $email) {
		$email = $this->normaliseEmail($email);
		$query = $this->db->query("SELECT suppression_id
			FROM `" . DB_PREFIX . "agmedia_review_reminder_suppression`
			WHERE store_id = '" . (int)$storeId . "'
			  AND email = '" . $this->db->escape($email) . "'
			LIMIT 1");

		if ($query->num_rows) {
			return true;
		}

		if ($this->tableExists('orderreviews_suppression')) {
			$legacy = $this->db->query("SELECT suppression_id
				FROM `" . DB_PREFIX . "orderreviews_suppression`
				WHERE LOWER(TRIM(email)) = '" . $this->db->escape($email) . "'
				LIMIT 1");

			return (bool)$legacy->num_rows;
		}

		return false;
	}

	public function markWithoutAttempt($deliveryId, $status, $error) {
		$allowed = array(self::STATUS_FAILED, self::STATUS_SUPPRESSED, self::STATUS_CANCELLED);

		if (!in_array($status, $allowed, true)) {
			throw new \InvalidArgumentException('Invalid terminal delivery status.');
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			SET status = '" . $this->db->escape($status) . "', next_attempt_at = NULL,
				last_error = '" . $this->db->escape($this->limitText($error, 2000)) . "', date_modified = NOW()
			WHERE delivery_id = '" . (int)$deliveryId . "' AND status = 'processing'");
	}

	public function updateProcessingRecipient($deliveryId, $email) {
		$email = $this->normaliseEmail($email);

		if (!$this->isValidEmail($email)) {
			throw new \InvalidArgumentException('Invalid current order recipient.');
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
			SET email = '" . $this->db->escape($email) . "', date_modified = NOW()
			WHERE delivery_id = '" . (int)$deliveryId . "' AND status = 'processing'");

		if (!$this->db->countAffected()) {
			throw new \RuntimeException('Queued recipient could not be refreshed.');
		}
	}

	/**
	 * Reserve a provider attempt before opening SMTP. This makes the hourly cap
	 * conservative even if PHP is killed mid-connection. The delivery remains
	 * processing until the DATA terminator callback durably marks it sending.
	 */
	public function reserveAttempt($deliveryId, $storeId, $orderId, $email) {
		$this->db->query('START TRANSACTION');

		try {
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				SET attempts = attempts + 1, date_modified = NOW()
				WHERE delivery_id = '" . (int)$deliveryId . "' AND status = 'processing'");

			if (!$this->db->countAffected()) {
				throw new \RuntimeException('Delivery is no longer claimable.');
			}

			$attempts = $this->db->query("SELECT attempts FROM `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				WHERE delivery_id = '" . (int)$deliveryId . "' LIMIT 1");

			$this->db->query("INSERT INTO `" . DB_PREFIX . "agmedia_review_reminder_attempt`
				SET delivery_id = '" . (int)$deliveryId . "', store_id = '" . (int)$storeId . "',
					order_id = '" . (int)$orderId . "', email = '" . $this->db->escape($this->normaliseEmail($email)) . "',
					outcome = 'started', date_added = NOW(), date_finished = NULL");
			$attemptId = (int)$this->db->getLastId();
			$this->db->query('COMMIT');

			return array(
				'attempt_id' => $attemptId,
				'attempts' => (int)$attempts->row['attempts']
			);
		} catch (\Throwable $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
	}

	public function markSending($deliveryId, $attemptId, $messageId) {
		$this->db->query('START TRANSACTION');

		try {
			$query = $this->db->query("SELECT attempt_id FROM `" . DB_PREFIX . "agmedia_review_reminder_attempt`
				WHERE attempt_id = '" . (int)$attemptId . "' AND delivery_id = '" . (int)$deliveryId . "' AND outcome = 'started'
				FOR UPDATE");

			if (!$query->num_rows) {
				throw new \RuntimeException('SMTP attempt reservation is missing.');
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery` d
				INNER JOIN `" . DB_PREFIX . "order` o ON o.store_id = d.store_id AND o.order_id = d.order_id
				SET d.status = 'sending', d.sending_at = NOW(), d.message_id = '" . $this->db->escape($messageId) . "', d.date_modified = NOW()
				WHERE d.delivery_id = '" . (int)$deliveryId . "' AND d.status = 'processing'
				  AND LOWER(TRIM(o.email)) = LOWER(TRIM(d.email))");

			if (!$this->db->countAffected()) {
				throw new \RuntimeException('Could not persist the SMTP DATA phase; the order recipient may have changed.');
			}

			$this->db->query('COMMIT');
		} catch (\Throwable $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
	}

	public function markSent($deliveryId, $attemptId) {
		$this->finishAttempt($deliveryId, $attemptId, self::STATUS_SENT, 'accepted', '', '', null);
	}

	public function markTransportFailure($deliveryId, $attemptId, $attempts, $maxAttempts, $smtpCode, $error, $ambiguous) {
		if ($ambiguous) {
			$this->finishAttempt($deliveryId, $attemptId, self::STATUS_UNKNOWN, 'failed', $smtpCode, $error, null);

			return self::STATUS_UNKNOWN;
		}

		if ((int)$attempts >= (int)$maxAttempts) {
			$this->finishAttempt($deliveryId, $attemptId, self::STATUS_FAILED, 'failed', $smtpCode, $error, null);

			return self::STATUS_FAILED;
		}

		$delayMinutes = $this->retryDelayMinutes($attempts);
		$this->finishAttempt($deliveryId, $attemptId, self::STATUS_RETRY, 'retry', $smtpCode, $error, $delayMinutes);

		return self::STATUS_RETRY;
	}

	public function suppressDelivery($deliveryId, $attemptId, $storeId, $email, $reason, $smtpCode = '') {
		$this->db->query('START TRANSACTION');

		try {
			$this->insertSuppression($storeId, $email, $reason);
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_attempt`
				SET outcome = 'suppressed', smtp_code = " . ($smtpCode === '' ? 'NULL' : "'" . $this->db->escape($smtpCode) . "'") . ",
					error = '" . $this->db->escape($this->limitText($reason, 2000)) . "', date_finished = NOW()
				WHERE attempt_id = '" . (int)$attemptId . "' AND delivery_id = '" . (int)$deliveryId . "' AND outcome = 'started'");
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				SET status = 'suppressed', next_attempt_at = NULL,
					last_error = '" . $this->db->escape($this->limitText($reason, 2000)) . "', date_modified = NOW()
				WHERE delivery_id = '" . (int)$deliveryId . "' AND status IN ('processing', 'sending')");
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				SET status = 'suppressed', next_attempt_at = NULL,
					last_error = 'Recipient is on the suppression list.', date_modified = NOW()
				WHERE store_id = '" . (int)$storeId . "'
				  AND email = '" . $this->db->escape($this->normaliseEmail($email)) . "'
				  AND status IN ('pending', 'retry')");
			$this->db->query('COMMIT');
		} catch (\Throwable $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
	}

	public function suppressEmail($storeId, $email, $reason, $includeProcessing = false) {
		$email = $this->normaliseEmail($email);

		if (!$this->isValidEmail($email)) {
			throw new \InvalidArgumentException('Invalid suppression email.');
		}

		$this->db->query('START TRANSACTION');

		try {
			$this->insertSuppression($storeId, $email, $reason);

			if ($includeProcessing) {
				$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_attempt` a
					INNER JOIN `" . DB_PREFIX . "agmedia_review_reminder_delivery` d ON d.delivery_id = a.delivery_id
					SET a.outcome = 'suppressed', a.error = 'Recipient unsubscribed before SMTP DATA.', a.date_finished = NOW()
					WHERE d.store_id = '" . (int)$storeId . "'
					  AND d.email = '" . $this->db->escape($email) . "'
					  AND d.status = 'processing' AND a.outcome = 'started'");
			}

			$statuses = $includeProcessing ? "'pending', 'retry', 'processing'" : "'pending', 'retry'";
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				SET status = 'suppressed', next_attempt_at = NULL,
					last_error = 'Recipient unsubscribed from review reminders.', date_modified = NOW()
				WHERE store_id = '" . (int)$storeId . "'
				  AND email = '" . $this->db->escape($email) . "'
				  AND status IN (" . $statuses . ")");
			$this->db->query('COMMIT');
		} catch (\Throwable $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
	}

	public function normaliseEmail($email) {
		return strtolower(trim((string)$email));
	}

	public function isValidEmail($email) {
		return strlen((string)$email) <= 254 &&
			strpos((string)$email, "\r") === false &&
			strpos((string)$email, "\n") === false &&
			(bool)filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	private function finishAttempt($deliveryId, $attemptId, $deliveryStatus, $outcome, $smtpCode, $error, $retryMinutes) {
		$this->db->query('START TRANSACTION');

		try {
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_attempt`
				SET outcome = '" . $this->db->escape($outcome) . "',
					smtp_code = " . ($smtpCode === '' ? 'NULL' : "'" . $this->db->escape($smtpCode) . "'") . ",
					error = " . ($error === '' ? 'NULL' : "'" . $this->db->escape($this->limitText($error, 2000)) . "'") . ",
					date_finished = NOW()
				WHERE attempt_id = '" . (int)$attemptId . "'
				  AND delivery_id = '" . (int)$deliveryId . "'
				  AND outcome = 'started'");

			if (!$this->db->countAffected()) {
				throw new \RuntimeException('SMTP attempt could not be finalized.');
			}

			$sentAt = $deliveryStatus === self::STATUS_SENT ? 'NOW()' : 'NULL';
			$nextAttempt = $retryMinutes === null ? 'NULL' : 'DATE_ADD(NOW(), INTERVAL ' . (int)$retryMinutes . ' MINUTE)';
			$this->db->query("UPDATE `" . DB_PREFIX . "agmedia_review_reminder_delivery`
				SET status = '" . $this->db->escape($deliveryStatus) . "',
					next_attempt_at = " . $nextAttempt . ", sent_at = " . $sentAt . ",
					last_error = " . ($error === '' ? 'NULL' : "'" . $this->db->escape($this->limitText($error, 2000)) . "'") . ",
					date_modified = NOW()
				WHERE delivery_id = '" . (int)$deliveryId . "'
				  AND status IN ('processing', 'sending')");

			if (!$this->db->countAffected()) {
				throw new \RuntimeException('Delivery state could not be finalized.');
			}

			$this->db->query('COMMIT');
		} catch (\Throwable $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
	}

	private function insertSuppression($storeId, $email, $reason) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "agmedia_review_reminder_suppression`
			SET store_id = '" . (int)$storeId . "', email = '" . $this->db->escape($this->normaliseEmail($email)) . "',
				reason = '" . $this->db->escape($this->limitText($reason, 191)) . "', date_added = NOW()
			ON DUPLICATE KEY UPDATE reason = VALUES(reason)");
	}

	private function retryDelayMinutes($attempts) {
		$attempts = (int)$attempts;

		if ($attempts <= 1) {
			return 75;
		}

		if ($attempts === 2) {
			return 360;
		}

		return 1440;
	}

	private function normaliseIds(array $ids) {
		$result = array();

		foreach ($ids as $id) {
			$id = (int)$id;

			if ($id > 0) {
				$result[$id] = $id;
			}
		}

		return array_values($result);
	}

	private function getLegacyDuplicateGuards() {
		$sql = '';

		if ($this->tableExists('orderreviews_mail_log')) {
			$sql .= "\n AND NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "orderreviews_mail_log` oml
				WHERE oml.store_id = o.store_id AND oml.order_id = o.order_id)";
		}

		if ($this->tableExists('orderreviews_log')) {
			$sql .= "\n AND NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "orderreviews_log` orl
				WHERE orl.store_id = o.store_id AND orl.order_id = o.order_id)";
		}

		if ($this->tableExists('orderreviews_delivery')) {
			$sql .= "\n AND NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "orderreviews_delivery` ord
				WHERE ord.store_id = o.store_id AND ord.order_id = o.order_id
				  AND LOWER(ord.status) IN ('sent', 'failed', 'suppressed', 'sending', 'processing', 'unknown'))";
		}

		return $sql;
	}

	private function tableExists($table) {
		if (array_key_exists($table, $this->tableExistsCache)) {
			return $this->tableExistsCache[$table];
		}

		$tableName = DB_PREFIX . $table;
		$query = $this->db->query("SELECT 1 FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = '" . $this->db->escape($tableName) . "'
			LIMIT 1");

		$this->tableExistsCache[$table] = (bool)$query->num_rows;

		return $this->tableExistsCache[$table];
	}

	private function getLockName() {
		$database = defined('DB_DATABASE') ? DB_DATABASE : '';

		return 'agmedia_rr:' . substr(sha1($database . '|' . DB_PREFIX), 0, 40);
	}

	private function limitText($value, $length) {
		$value = (string)$value;

		if (function_exists('utf8_strlen') && function_exists('utf8_substr')) {
			return utf8_strlen($value) > $length ? utf8_substr($value, 0, $length) : $value;
		}

		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}
}
