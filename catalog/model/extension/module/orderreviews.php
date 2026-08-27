<?php
class ModelExtensionModuleOrderReviews extends Model {

	public function sendMail($data = array()) {
		$store_data = $this->getCurrentStore($data['store_id']);
		$this->load->config('isenselabs/orderreviews');

		$mailToUser = new Vendor\iSenseLabs\OrderReviews\Mail($this->config->get('config_mail_engine'));
        $mailToUser->parameter = $this->config->get('config_mail_parameter');
        $mailToUser->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mailToUser->smtp_username = $this->config->get('config_mail_smtp_username');
        $mailToUser->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
        $mailToUser->smtp_port = $this->config->get('config_mail_smtp_port');
        $mailToUser->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$fromAddress = filter_var($this->config->get('config_mail_smtp_username'), FILTER_VALIDATE_EMAIL)
			? $this->config->get('config_mail_smtp_username')
			: $store_data['store_email'];

		$mailToUser->setTo($data['email']);
		$mailToUser->setFrom($fromAddress);

		if (filter_var($store_data['store_email'], FILTER_VALIDATE_EMAIL)) {
			$mailToUser->setReplyTo($store_data['store_email']);
		}

		$mailToUser->setSender(html_entity_decode($store_data['name'], ENT_QUOTES, 'UTF-8'));
		$mailToUser->setSubject(html_entity_decode($data['subject'], ENT_QUOTES, 'UTF-8'));
		$mailToUser->setHtml($data['message']);

		if (isset($data['before_data_terminator']) && is_callable($data['before_data_terminator'])) {
			$mailToUser->setBeforeDataTerminator($data['before_data_terminator']);
		}

		// BCC is intentionally disabled for automated review delivery. It adds
		// another SMTP recipient and can turn an otherwise valid delivery into a failure.

		return (bool)$mailToUser->send();
	}

	public function getOrders($orderID, $dayLimit, $dateType = 'date_added', $store_id = 0, $reviewmail_id = 0, $limit = 2, $max_age_days = 45, $max_attempts = 3, $customer_group_id = 'send_all') {
		$dateType = in_array($dateType, array('date_added', 'date_modified'), true) ? $dateType : 'date_added';
		$dayLimit = max(0, (int)$dayLimit);
		$limit = max(1, min(20, (int)$limit));
		$max_age_days = max(1, min(365, (int)$max_age_days));
		$max_attempts = max(1, min(5, (int)$max_attempts));

		$customerGroupSql = $customer_group_id === 'send_all'
			? ''
			: ' AND o.customer_group_id = ' . (int)$customer_group_id;
		$newOrderWindowSql = $dayLimit > $max_age_days
			? ' AND 1 = 0'
			: ' AND o.`' . $dateType . '` <= DATE_SUB(NOW(), INTERVAL ' . $dayLimit . ' DAY)'
				. ' AND o.`' . $dateType . '` >= DATE_SUB(NOW(), INTERVAL ' . $max_age_days . ' DAY)';

		$sql = "SELECT o.*, d.delivery_id AS orderreviews_delivery_id
			FROM `" . DB_PREFIX . "order` o
			LEFT JOIN `" . DB_PREFIX . "orderreviews_delivery` d
				ON d.store_id = o.store_id AND d.order_id = o.order_id
			LEFT JOIN `" . DB_PREFIX . "orderreviews_suppression` s
				ON s.email = LOWER(TRIM(o.email))
			WHERE o.store_id = " . (int)$store_id . "
				AND NOT EXISTS (
					SELECT 1 FROM `" . DB_PREFIX . "orderreviews_mail_log` ml
					WHERE ml.store_id = o.store_id AND ml.order_id = o.order_id
				)
				AND NOT EXISTS (
					SELECT 1 FROM `" . DB_PREFIX . "orderreviews_log` rl
					WHERE rl.order_id = o.order_id
				)
				AND s.suppression_id IS NULL
				AND (
					(
						d.reviewmail_id = " . (int)$reviewmail_id . "
						AND d.status IN ('pending', 'retry')
						AND d.available_at <= NOW()
						AND d.attempts < " . $max_attempts . "
					)
					OR (
						d.delivery_id IS NULL
						AND o.order_status_id = " . (int)$orderID . "
						" . $customerGroupSql . "
						" . $newOrderWindowSql . "
					)
				)
			ORDER BY o.`" . $dateType . "` ASC, o.order_id ASC
			LIMIT " . $limit;

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function ensureDeliverySchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "orderreviews_delivery` (
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
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "orderreviews_suppression` (
			`suppression_id` INT(11) NOT NULL AUTO_INCREMENT,
			`email` VARCHAR(254) NOT NULL,
			`reason` VARCHAR(1000) NULL DEFAULT NULL,
			`source` VARCHAR(32) NOT NULL DEFAULT 'worker',
			`created_at` DATETIME NOT NULL,
			`updated_at` DATETIME NOT NULL,
			PRIMARY KEY (`suppression_id`),
			UNIQUE KEY `uniq_orderreviews_suppression_email` (`email`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		if ($this->checkDbTable('orderreviews_mail_log')) {
			$this->ensureDeliveryIndex('orderreviews_mail_log', 'idx_orderreviews_mail_log_order', '`store_id`, `order_id`');
		}

		if ($this->checkDbTable('orderreviews_log')) {
			$this->ensureDeliveryIndex('orderreviews_log', 'idx_orderreviews_log_order', '`order_id`');
		}
	}

	public function acquireDeliveryLock() {
		$lockName = 'orderreviews:' . sha1(DB_PREFIX . DB_DATABASE);
		$query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($lockName) . "', 0) AS acquired");

		return !empty($query->row['acquired']);
	}

	public function releaseDeliveryLock() {
		$lockName = 'orderreviews:' . sha1(DB_PREFIX . DB_DATABASE);
		$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($lockName) . "')");
	}

	public function recoverStaleDeliveries() {
		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = 'retry',
				attempts = GREATEST(attempts - 1, 0),
				available_at = NOW(),
				last_error = 'Worker stopped before the SMTP DATA terminator; delivery returned to the queue.',
				locked_at = NULL,
				updated_at = NOW()
			WHERE status = 'processing'
				AND locked_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");

		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = 'unknown',
				last_error = 'Worker stopped after the SMTP attempt; delivery outcome is unknown.',
				locked_at = NULL,
				updated_at = NOW()
			WHERE status = 'sending'
				AND locked_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
	}

	public function getRecentDeliveryAttemptCount() {
		$query = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "orderreviews_delivery`
			WHERE last_attempt_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");

		return (int)$query->row['total'];
	}

	public function finalizeIneligibleDeliveries($store_id, $reviewmail_id, $max_attempts) {
		$store_id = (int)$store_id;
		$reviewmail_id = (int)$reviewmail_id;
		$max_attempts = max(1, min(5, (int)$max_attempts));

		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery` d
			INNER JOIN `" . DB_PREFIX . "order` o
				ON o.store_id = d.store_id AND o.order_id = d.order_id
			INNER JOIN `" . DB_PREFIX . "orderreviews_suppression` s
				ON s.email = LOWER(TRIM(o.email)) AND s.email = d.email
			SET d.status = 'suppressed',
				d.locked_at = NULL,
				d.last_error = 'Recipient is present on the suppression list.',
				d.updated_at = NOW()
			WHERE d.store_id = " . $store_id . "
				AND d.reviewmail_id = " . $reviewmail_id . "
				AND d.status IN ('pending', 'retry')");

		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery` d
			SET d.status = 'cancelled',
				d.locked_at = NULL,
				d.last_error = 'A review already exists for this order.',
				d.updated_at = NOW()
			WHERE d.store_id = " . $store_id . "
				AND d.reviewmail_id = " . $reviewmail_id . "
				AND d.status IN ('pending', 'retry')
				AND EXISTS (
					SELECT 1 FROM `" . DB_PREFIX . "orderreviews_log` rl
					WHERE rl.order_id = d.order_id
				)");

		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery` d
			LEFT JOIN `" . DB_PREFIX . "order` o
				ON o.store_id = d.store_id AND o.order_id = d.order_id
			SET d.status = 'cancelled',
				d.locked_at = NULL,
				d.last_error = 'Order no longer exists.',
				d.updated_at = NOW()
			WHERE d.store_id = " . $store_id . "
				AND d.reviewmail_id = " . $reviewmail_id . "
				AND d.status IN ('pending', 'retry')
				AND o.order_id IS NULL");

		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = 'failed',
				locked_at = NULL,
				last_error = 'Maximum delivery attempts reached.',
				updated_at = NOW()
			WHERE store_id = " . $store_id . "
				AND reviewmail_id = " . $reviewmail_id . "
				AND status IN ('pending', 'retry')
				AND attempts >= " . $max_attempts);
	}

	public function claimDelivery($store_id, $order_id, $reviewmail_id, $email, $max_attempts = 3) {
		$email = $this->normaliseEmail($email);
		$max_attempts = max(1, min(5, (int)$max_attempts));

		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "orderreviews_delivery`
			SET store_id = " . (int)$store_id . ",
				order_id = " . (int)$order_id . ",
				reviewmail_id = " . (int)$reviewmail_id . ",
				email = '" . $this->db->escape($email) . "',
				status = 'pending',
				available_at = NOW(),
				created_at = NOW(),
				updated_at = NOW()");

		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = 'processing',
				email = '" . $this->db->escape($email) . "',
				attempts = attempts + 1,
				locked_at = NOW(),
				last_attempt_at = NOW(),
				last_smtp_code = NULL,
				last_error = NULL,
				updated_at = NOW()
			WHERE store_id = " . (int)$store_id . "
				AND order_id = " . (int)$order_id . "
				AND reviewmail_id = " . (int)$reviewmail_id . "
				AND status IN ('pending', 'retry')
				AND available_at <= NOW()
				AND attempts < " . $max_attempts);

		if (!$this->db->countAffected()) {
			return 0;
		}

		$query = $this->db->query("SELECT delivery_id
			FROM `" . DB_PREFIX . "orderreviews_delivery`
			WHERE store_id = " . (int)$store_id . " AND order_id = " . (int)$order_id . "
			LIMIT 1");

		return !empty($query->row['delivery_id']) ? (int)$query->row['delivery_id'] : 0;
	}

	public function markDeliverySent($delivery_id, $store_id, $order_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = 'sent', sent_at = NOW(), locked_at = NULL, updated_at = NOW()
			WHERE delivery_id = " . (int)$delivery_id . " AND status = 'sending'");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "orderreviews_mail_log` (`date`, `store_id`, `order_id`)
			SELECT NOW(), " . (int)$store_id . ", " . (int)$order_id . "
			FROM DUAL
			WHERE NOT EXISTS (
				SELECT 1 FROM `" . DB_PREFIX . "orderreviews_mail_log`
				WHERE store_id = " . (int)$store_id . " AND order_id = " . (int)$order_id . "
			)");
	}

	public function markDeliveryCancelled($delivery_id, $reason) {
		$this->updateDeliveryStatus($delivery_id, 'cancelled', 0, $reason, null);
	}

	public function cancelQueuedDelivery($store_id, $order_id, $reviewmail_id, $reason) {
		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = 'cancelled',
				locked_at = NULL,
				last_error = '" . $this->db->escape($this->sanitiseDeliveryError($reason)) . "',
				updated_at = NOW()
			WHERE store_id = " . (int)$store_id . "
				AND order_id = " . (int)$order_id . "
				AND reviewmail_id = " . (int)$reviewmail_id . "
				AND status IN ('pending', 'retry')");
	}

	public function markDeliverySending($delivery_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = 'sending', locked_at = NOW(), updated_at = NOW()
			WHERE delivery_id = " . (int)$delivery_id . " AND status = 'processing'");

		if (!$this->db->countAffected()) {
			throw new Exception('Delivery could not enter the SMTP sending state.');
		}
	}

	public function markDeliveryUnknown($delivery_id, Exception $exception) {
		$this->updateDeliveryStatus($delivery_id, 'unknown', (int)$exception->getCode(), $exception->getMessage(), null);
	}

	public function markDeliverySuppressed($delivery_id, $email, $reason, $source = 'worker') {
		$email = $this->normaliseEmail($email);
		$reason = $this->sanitiseDeliveryError($reason);
		$source = substr(preg_replace('/[^a-z0-9_-]/i', '', (string)$source), 0, 32);

		$this->db->query("INSERT INTO `" . DB_PREFIX . "orderreviews_suppression`
			SET email = '" . $this->db->escape($email) . "',
				reason = '" . $this->db->escape($reason) . "',
				source = '" . $this->db->escape($source ?: 'worker') . "',
				created_at = NOW(),
				updated_at = NOW()
			ON DUPLICATE KEY UPDATE
				reason = VALUES(reason), source = VALUES(source), updated_at = NOW()");

		$this->updateDeliveryStatus($delivery_id, 'suppressed', 0, $reason, null);
	}

	public function markDeliveryFailed($delivery_id, Exception $exception, $max_attempts = 3) {
		$code = (int)$exception->getCode();
		$error = $this->sanitiseDeliveryError($exception->getMessage());
		$query = $this->db->query("SELECT attempts FROM `" . DB_PREFIX . "orderreviews_delivery` WHERE delivery_id = " . (int)$delivery_id);
		$attempts = !empty($query->row['attempts']) ? (int)$query->row['attempts'] : $max_attempts;

		if ($attempts >= (int)$max_attempts) {
			$this->updateDeliveryStatus($delivery_id, 'failed', $code, $error, null);
			return 'failed';
		}

		$backoffMinutes = $attempts <= 1 ? 75 : ($attempts === 2 ? 360 : 1440);
		$this->updateDeliveryStatus($delivery_id, 'retry', $code, $error, $backoffMinutes);

		return 'retry';
	}

	public function isPermanentRecipientFailure(Exception $exception) {
		$code = (int)$exception->getCode();
		$message = strtolower($exception->getMessage());

		if (!in_array($code, array(550, 551, 553), true) || strpos($message, 'rcpt to') === false) {
			return false;
		}

		$invalidAddressSignals = array(
			'5.1.1',
			'5.1.2',
			'5.1.3',
			'5.1.6',
			'5.1.10',
			'no such user',
			'user unknown',
			'unknown user',
			'mailbox does not exist',
			'recipient address does not exist'
		);

		foreach ($invalidAddressSignals as $signal) {
			if (strpos($message, $signal) !== false) {
				return true;
			}
		}

		return false;
	}

	public function isUnknownDeliveryFailure(Exception $exception) {
		return stripos($exception->getMessage(), 'outcome unknown after message data') !== false;
	}

	public function isValidDeliveryEmail($email) {
		$email = $this->normaliseEmail($email);

		return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	public function isDeliveryEmailSuppressed($email) {
		$email = $this->normaliseEmail($email);
		$query = $this->db->query("SELECT suppression_id FROM `" . DB_PREFIX . "orderreviews_suppression`
			WHERE email = '" . $this->db->escape($email) . "' LIMIT 1");

		return $query->num_rows > 0;
	}

	private function updateDeliveryStatus($delivery_id, $status, $smtp_code, $error, $backoff_minutes) {
		$sql = "UPDATE `" . DB_PREFIX . "orderreviews_delivery`
			SET status = '" . $this->db->escape($status) . "',
				locked_at = NULL,
				last_smtp_code = " . ((int)$smtp_code > 0 ? (int)$smtp_code : 'NULL') . ",
				last_error = '" . $this->db->escape($this->sanitiseDeliveryError($error)) . "',
				updated_at = NOW()";

		if ($backoff_minutes !== null) {
			$sql .= ", available_at = DATE_ADD(NOW(), INTERVAL " . max(1, (int)$backoff_minutes) . " MINUTE)";
		}

		$sql .= " WHERE delivery_id = " . (int)$delivery_id . " AND status IN ('processing', 'sending')";
		$this->db->query($sql);
	}

	private function normaliseEmail($email) {
		return strtolower(trim((string)$email));
	}

	private function sanitiseDeliveryError($error) {
		$error = trim(preg_replace('/\s+/', ' ', strip_tags((string)$error)));

		return substr($error, 0, 1000);
	}

	private function ensureDeliveryIndex($table, $index, $columns) {
		$query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE Key_name = '" . $this->db->escape($index) . "'");

		if (!$query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD KEY `" . $index . "` (" . $columns . ")");
		}
	}

	public function getOrderProducts($order_id) {
		$query = $this->db->query("SELECT DISTINCTROW product_id, name, order_id FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "' ORDER BY product_id");
		return $query->rows;
	}

	public function getAccountOrders($orderID, $dayLimit, $dateType = 'date_modified', $store_id = 0) {
		$order_data = array();

		$query =  $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order`
			WHERE `order_status_id`=".$orderID." AND DATE(`". $dateType ."`) <= '".date("Y-m-d ",strtotime('-'.$dayLimit.' days'))."' AND store_id =".$store_id);

		foreach ($query->rows as $result) {
			$order_data[] = $result['order_id'];
		}

		return $order_data;
	}

	public function loadLanguage($directory, $filename) {

		$default = 'en-gb/'.$this->config->get('orderreviews_path');

		$data = array();

		$file = DIR_LANGUAGE . $directory . '/'. $filename.'.php';

		if (file_exists($file)) {
			$_ = array();

			require($file);
			 $data = array_merge($data, $_);
			return $data;
		}

		$file = DIR_LANGUAGE . $default .'.php';

		if (file_exists($file)) {
			$_ = array();
			require($file);
			$data = array_merge($data, $_);
			return $data;
		} else {
			trigger_error('Error: Could not load language ' . $filename . '!');
		//	exit();
		}
	}

	// Coupons
	public function generateuniquerandomcouponcode() {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$couponCode = '';
		for ($i = 0; $i < 10; $i++) {
			$couponCode .= $characters[rand(0, strlen($characters) - 1)];
		}
		if($this->isUniqueCode($couponCode)) {
			return $couponCode;
		} else {
			return $this->generateuniquerandomcouponcode();
		}
	}

	public function isUniqueCode($randomCode) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon` WHERE code='".$this->db->escape($randomCode)."'");
				if($query->num_rows == 0) {
					return true;
							} else {
					return false;
				}
	}

	public function addCoupon($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "coupon SET name = '" . $this->db->escape($data['name']) . "', code = '" . $this->db->escape($data['code']) . "', discount = '" . (float)$data['discount'] . "', type = '" . $this->db->escape($data['type']) . "', total = '" . (float)$data['total'] . "', logged = '" . (int)$data['logged'] . "', shipping = '" . (int)$data['shipping'] . "', date_start = '" . $this->db->escape($data['date_start']) . "', date_end = '" . $this->db->escape($data['date_end']) . "', uses_total = '" . (int)$data['uses_total'] . "', uses_customer = '" . (int)$data['uses_customer'] . "', status = '" . (int)$data['status'] . "', date_added = NOW()");

		$coupon_id = $this->db->getLastId();

		if (isset($data['coupon_product'])) {
			foreach ($data['coupon_product'] as $product_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "coupon_product SET coupon_id = '" . (int)$coupon_id . "', product_id = '" . (int)$product_id . "'");
			}
		}

		if (isset($data['coupon_category'])) {
			foreach ($data['coupon_category'] as $category_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "coupon_category SET coupon_id = '" . (int)$coupon_id . "', category_id = '" . (int)$category_id . "'");
			}
		}
	}

	private function getCatalogURL() {
        if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
			$storeURL = HTTPS_SERVER;
        } else {
			$storeURL = HTTP_SERVER;
        }
        return $storeURL;
    }

	public function getStore($store_id) {
        if($store_id && $store_id != 0) {
            $store = $this->getStoreData($store_id);
        } else {
            $store['store_id'] = 0;
            $store['name'] = $this->config->get('config_name');
            $store['url'] = $this->getCatalogURL();
        }
        return $store;
    }

	private function getStoreData($store_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "store WHERE store_id = '" . (int)$store_id . "'");

		return $query->row;
	}

	public function fetchForm($filename) {
		$data = array();
		$file = DIR_APPLICATION.'view/theme/'. $filename;

		if (file_exists($file)) {
			extract($data);
			ob_start();
			include($file);
			$content = ob_get_clean();
			return $content;
		} else {
			trigger_error('Error: Could not load template ' . $file . '!');
			exit();
		}
	}

	private $moduleName = 'orderreviews';
	private $moduleModel = 'model_module_orderreviews';

	public function sendReviewMail($order_id, $order_status_id) {

		$this->config->load('isenselabs/orderreviews');
		$this->load->model('setting/setting');
		$this->load->model('setting/store');
		$this->load->model('checkout/order');
		$this->load->model('localisation/language');

		$order = $this->model_checkout_order->getOrder($order_id);
		$stores = array_merge(array(0 => $this->getStore(0)), $this->model_setting_store->getStores());

		foreach ($stores as $store) {
			if ($order['store_id'] != $store['store_id']) {
				continue;
			}

			if(!$this->checkDbTable('orderreviews_setting')){
				$setting = $this->model_setting_setting->getSetting($this->moduleName, $store['store_id']);
			} else {
				$setting = $this->getSetting($this->moduleName, $store['store_id']);
			}
			$moduleData = isset($setting[$this->moduleName]) ? $setting[$this->moduleName] : array();

			if (!empty($moduleData['Enabled']) && $moduleData['Enabled'] == 'yes' && isset($moduleData['ReviewMail'])) {
				foreach ($moduleData['ReviewMail'] as $reviewmail) {
					if ($reviewmail['Enabled']=='yes' && $reviewmail['Delay']=='0' && $reviewmail['OrderStatusID']==$order_status_id) {

						if (!(($reviewmail['CustomerGroupID'] == 'send_all') || ($reviewmail['CustomerGroupID'] != 'send_all' && $reviewmail['CustomerGroupID']==$order['customer_group_id']))) {
							break;
						}

						$OrderLanguage = $this->model_localisation_language->getLanguage($order['language_id']);

						$LangVars = $this->loadLanguage($OrderLanguage['code'].'/extension/module','orderreviews');
						$OrderProducts = $this->getOrderProducts($order['order_id']);

						$query_reviewed = $this->db->query("SELECT DISTINCT product_id FROM " . DB_PREFIX . "orderreviews_log as ol LEFT JOIN " . DB_PREFIX . "order as o ON ol.order_id = o.order_id LEFT JOIN " . DB_PREFIX . "order_product as op ON ol.order_id = op.order_id WHERE o.email = '" . $order['email'] . "' ORDER BY product_id");

						$reviewedProducts = array();

						foreach ($query_reviewed->rows as $rows){
							$reviewedProducts[]=$rows['product_id'];

						}

						$currentProducts = array();

						foreach($OrderProducts as $products ){
							if(!in_array($products['product_id'],$reviewedProducts)){
								$currentProducts[] = $products;
							}
						}

						if(!empty($currentProducts)) {
							$Products = '';
							$ProductIDs = '';
							if (sizeof($currentProducts)==1) {
								$Products = '<a href="'.$store['url'].'index.php?route=product/product&amp;product_id=' . $currentProducts[0]['product_id'].'">'.$currentProducts[0]['name'].'</a>';

								$ProductIDs = $currentProducts[0]['product_id'];
							} else {
								for ($i=0; $i<sizeof($currentProducts); $i++) {
									if (($i+1) == sizeof($currentProducts)) {
										$Products .= ' '.$LangVars['text_and'].' ';
									}  else if (($i+1) < sizeof($currentProducts) && ($i>0)) {
										$Products .= ', ';
									}
									$Products .= '<a href="'.$store['url'].'index.php?route=product/product&amp;product_id=' . $currentProducts[$i]['product_id'].'">'.$currentProducts[$i]['name'].'</a>';
									$ProductIDs .= $currentProducts[$i]['product_id'];

									if (!(($i+1) == sizeof($currentProducts)))
											$ProductIDs .= '_';
								}
							}

							$subject_original = array('{first_name}','{last_name}', '{order_id}');
							$subject_replace = array($order['firstname'], $order['lastname'], $order_id);
							$Subject = str_replace($subject_original, $subject_replace, $reviewmail['Subject'][$order['language_id']]);
							$Message = html_entity_decode($reviewmail['Message'][$order['language_id']]);
							$FirstName = $order['firstname'];
							$LastName = $order['lastname'];
							$Email = $order['email'];

							$SubmitLink = $store['url'].'index.php?route='.$this->config->get('orderreviews_path').'/sendReview';
							$params = 'order_id='.$order['order_id'].'&reviewmail_id='.$reviewmail['id'].'&store_id='.$store['store_id'];
							$ReveiewMailLink = $store['url'].'index.php?route='.$this->config->get('orderreviews_path').'/sendReview&params='.base64_encode($params);

							//$MainFormData = $this->fetchForm('default/template/module/orderreviews_review_email_form.twig');
							$ProductFormData = $this->fetchForm('default/template/'.$this->config->get('orderreviews_path').'/orderreviews_product_form_include.twig');
							$ProductsViews = "";


							if ($reviewmail['ReviewType'] == 'per_purchase') {
								$tempVar = '';
								$old = array("{number}","{pr_name}","{pr_id}","{image}");
								$new = array('0','','0',NULL);
								$tempVar = str_replace($old, $new, $ProductFormData);
								$ProductsViews .= $tempVar;
							} else if ($reviewmail['ReviewType'] == 'per_product') {
								if (sizeof($currentProducts)>0) {
										for ($i=0; $i<sizeof($currentProducts); $i++) {
										$tempVar = '';
										if($reviewmail['DisplayImages'] == 'yes'){
											$this->load->model('catalog/product');
											$product_info = $this->model_catalog_product->getProduct($OrderProducts[$i]['product_id']);
											$this->load->model('tool/image');
											if ($product_info['image']) { $image = $this->model_tool_image->resize($product_info['image'], 200, 200); } else { $image = false; }
											$old = array("{number}","{pr_name}","{pr_id}","{image}");
											$new = array($OrderProducts[$i]['product_id'],$OrderProducts[$i]['name'].':',$OrderProducts[$i]['product_id'].'<br/>',"<img src='".$image."' />");
										} else {
											$old = array("{number}","{pr_name}","{pr_id}","{image}");
											$new = array($OrderProducts[$i]['product_id'],$OrderProducts[$i]['name'].':',$OrderProducts[$i]['product_id'].'<br/>',NULL);
										}
										$tempVar = str_replace($old, $new, $ProductFormData);
										$ProductsViews .= $tempVar;
									}
								}
							}


							if(!empty($moduleData['EmailType']) && $moduleData['EmailType'] == 'link'){

								$MainFormData = $this->fetchForm('default/template/'.$this->config->get('orderreviews_path').'/orderreviews_review_email_form.twig');

								$form_pattern = array("{submit_link}","{first_name}", "{last_name}", "{customer_id}", "{text_submit}", "{text_review}", "{product_id}", "{order_id}", "{customer_name}", "{reviewmail_id}", "{email}","{product_info}","{reviewmail_link_href}","{link_button_text}","{store_url}","{star_1}","{star_2}","{star_3}","{star_4}","{star_5}");
								$form_replacements = array($SubmitLink, $FirstName, $LastName, base64_encode($order['customer_id']), $LangVars['text_submit'], $LangVars['text_review'], $ProductIDs, base64_encode($order['order_id']), $FirstName.' '.$LastName, $reviewmail['id'], base64_encode($Email), $ProductsViews, $ReveiewMailLink,$LangVars['text_leave_review'],$store['url'],$LangVars['star_1'],$LangVars['star_2'],$LangVars['star_3'],$LangVars['star_4'],$LangVars['star_5']);

							}else{
								$privacyCheckbox = $this->privacyCheckbox($moduleData['PrivacyPolicy']);
								$MainFormData = $this->fetchForm('default/template/'.$this->config->get('orderreviews_path').'/orderreviews_product_form_main.twig');

								$form_pattern = array("{submit_link}","{first_name}", "{last_name}", "{customer_id}", "{text_submit}", "{text_review}", "{privacy_aggreement}", "{product_id}", "{order_id}", "{customer_name}", "{reviewmail_id}", "{email}","{product_info}");
								$form_replacements = array($SubmitLink, $FirstName, $LastName, base64_encode($order['customer_id']), $LangVars['text_submit'], $LangVars['text_review'], $privacyCheckbox, $ProductIDs, base64_encode($order['order_id']), $FirstName.' '.$LastName, $reviewmail['id'], base64_encode($Email), $ProductsViews);

							}

							$ReviewForm = str_replace($form_pattern, $form_replacements, $MainFormData);

							if(!empty($moduleData['EmailType']) && $moduleData['EmailType'] == 'link'){
								$patterns = array('{first_name}', '{last_name}', '{review_form}', '{order_products}', '{order_id}','{reviewmail_link}');
								$replacements = array($FirstName, $LastName, $ReviewForm, $Products, $order['order_id'],$LangVars['link_replacement']);
							}else {
								$patterns = array('{first_name}', '{last_name}', '{review_form}', '{order_products}', '{order_id}', '{reviewmail_link}', '{reviewmail_link_href}');
								$replacements = array($FirstName, $LastName, $ReviewForm, $Products,$order['order_id'], $LangVars['text_reviewmail_link'], $ReveiewMailLink);
							}

							$HTMLMail = str_replace($patterns, $replacements, $Message);

							$newMail = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
								<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
									<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
									<title>OrderReviews</title>
								</head>
								<body>'.$HTMLMail.'</body></html>';

							$MailData = array(
								'email' =>  $Email,
								'message' => $newMail,
								'subject' => $Subject,
								'store_name' => $store['name'],
								'store_id' => $store['store_id']);

							if (sizeof($currentProducts)>0) {
								$emailResult = $this->sendMail($MailData);
								if  (isset($moduleData['LOG']) && $moduleData['LOG'] == 'yes') {
									$OrderDate = date('Y-m-d H:i:s', time());
									$this->db->query("INSERT INTO `" . DB_PREFIX . "orderreviews_mail_log` SET `date`='".$this->db->escape($OrderDate)."', `store_id`='".$this->db->escape($store['store_id'])."', `order_id`='".$this->db->escape($order['order_id'])."'");
								}
							}
						}
					}
				}
			}
		}

	}

	private function getCurrentStore($store_id) {
        if($store_id && $store_id != 0) {
            $store = $this->getStore($store_id);
            $store['store_email'] = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key`= 'config_email' AND `store_id`=".$this->db->escape($store_id))->row['value'];
        } else {
            $store['store_id'] = 0;
            $store['name'] = $this->config->get('config_name');
            $store['url'] = $this->getCatalogURL();
            $store['store_email'] = $this->config->get('config_email');
        }
        return $store;
    }
	public function updateReviewLog($log_id,$discount_code)
	{
		$this->db->query("UPDATE `" . DB_PREFIX . "orderreviews_log` SET `review_coupon` = '".$discount_code."' WHERE log_id = ".(int)$log_id);
	}
	public function addReviewLog($order_id,$data,$store_id,$review_id) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "orderreviews_log SET order_id = '" . $this->db->escape($order_id) . "', review_id = '" . $this->db->escape($review_id) . "', customer_name = '" . $this->db->escape($data['name']) . "', review_product_id = '" . (int)($data['product_id']) . "', review_rating = '" . (int)($data['rating']) . "', store_id = '" . (int)($store_id) . "', privacy_policy = '" . (int)$data['privacy_policy'] . "', date_created = NOW()");

		if ($data['privacy_policy'] && is_file(DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR . 'gdpr.php')) {
        	$order = $this->db->query("SELECT email FROM `" . DB_PREFIX . "order` WHERE order_id = '" . $this->db->escape($order_id) . "'");
            $this->load->library('gdpr');
            $this->gdpr->newOptin($this->config->get('config_account_id'), $order->row['email'], 'OrderReviews');
        }
	}

	public function checkReviewLog($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "orderreviews_log` WHERE order_id='".$this->db->escape($order_id)."'");
			if($query->num_rows > 0) {
				return true;
			} else {
				return false;
			}
	}

	public function getOrderCustomerGroup($order_id)
	{
		if ($order_id) {
			$query = $this->db->query("SELECT customer_group_id FROM `" . DB_PREFIX . "order` WHERE order_id = ".(int)$order_id);
			return $query->num_rows ? $query->row['customer_group_id'] : 0 ;
		}
	}

	public function privacyCheckbox($status = 0) {
		$privacyCheckbox = '';

		if (!empty($status)) {
			$this->load->language('extension/module/orderreviews');
			$this->load->model('catalog/information');
			$information = $this->model_catalog_information->getInformation($this->config->get('config_account_id'));

			if (!empty($information['information_id'])) {
				$privacyCheckbox = '<label id="ORPrivacyPolicy" style="font-family:Verdana;font-size:13px;letter-spacing:0"><input type="checkbox" name="privacy_policy" value="1"> ' . sprintf($this->language->get('text_privacy_agreement'), $this->getCatalogURL() . 'index.php?route=information/information&information_id=' . $information['information_id'], $information['title']) . '</label><br><br>';
			}
		}

		return $privacyCheckbox;
	}

	public function addReview($product_id, $data) {
		if ($data['AutoApproved'] == 'yes' && ($data['rating'] >= $data['AutoApprovedStar'])) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['name']) . "', customer_id = '" . (int)$data['customer_id'] . "', product_id = '" . (int)$product_id . "', text = '" . $this->db->escape($data['text']) . "', rating = '" . (int)$data['rating'] . "', status = '1', date_added = NOW()");
		} else {
			$this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['name']) . "', customer_id = '" . (int)$data['customer_id'] . "', product_id = '" . (int)$product_id . "', text = '" . $this->db->escape($data['text']) . "', rating = '" . (int)$data['rating'] . "', date_added = NOW()");
		}

		$review_id = $this->db->getLastId();

		if (in_array('review', (array)$this->config->get('config_mail_alert'))) {
			$this->load->language('mail/review');
			$this->load->model('catalog/product');
			
			$product_info = $this->model_catalog_product->getProduct($product_id);

			$subject = sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));

			$message  = $this->language->get('text_waiting') . "\n";
			$message .= sprintf($this->language->get('text_product'), html_entity_decode($product_info['name'], ENT_QUOTES, 'UTF-8')) . "\n";
			$message .= sprintf($this->language->get('text_reviewer'), html_entity_decode($data['name'], ENT_QUOTES, 'UTF-8')) . "\n";
			$message .= sprintf($this->language->get('text_rating'), $data['rating']) . "\n";
			$message .= $this->language->get('text_review') . "\n";
			$message .= html_entity_decode($data['text'], ENT_QUOTES, 'UTF-8') . "\n\n";

			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($this->config->get('config_email'));
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject($subject);
			$mail->setText($message);
			$mail->send();

			// Send to additional alert emails
			$emails = explode(',', $this->config->get('config_mail_alert_email'));

			foreach ($emails as $email) {
				if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
					$mail->setTo($email);
					$mail->send();
				}
			}
		}

		return $review_id;
	}

	public function getProductsInIDArray($products = array()) {
		if(!empty($products)) {
			return $this->db->query('SELECT p.product_id, p.image, pd.name  FROM `' . DB_PREFIX .'product` p ' . 
				'LEFT JOIN `'  . DB_PREFIX . 'product_description` AS pd ON (pd.language_id = ' . $this->config->get('config_language_id') . ' AND p.product_id = pd.product_id) ' . 
				((count($products) == 1) ?
					'WHERE p.product_id = ' . $products[0] :
					'WHERE p.product_id IN (' . implode(',', $products) . ')'
				)
			)->rows;
		} else {
			return array();
		}
	}

	public function getCategoriesByID($categories = array()) {
		if(!empty($categories)) {
			return $this->db->query('SELECT c.category_id, name FROM ' . DB_PREFIX . 'category c ' . 
				'JOIN '  . DB_PREFIX . 'category_description AS cd on c.category_id=cd.category_id ' . 
				((count($categories) == 1) ?
					'WHERE c.category_id = ' . $categories[0] :
					'WHERE c.category_id IN (' . implode(',', $categories) . ')'
				) . 
				' AND language_id = ' . $this->config->get('config_language_id')
			)->rows;
		} else {
			return NULL;
		}
	}

	public function checkDbTable($table) {
		// return TRUE if exist, FALSE if table is not exist
		$result = $this->db->query("SHOW TABLES LIKE '". DB_PREFIX . $table . "'");
		return ($result->num_rows != '1') ? FALSE : TRUE;
	}

	public function getSetting($code, $store_id = 0) {
		$setting_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "orderreviews_setting WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");

		foreach ($query->rows as $result) {
			if (!$result['serialized']) {
				$setting_data[$result['key']] = $result['value'];
			} else {
				$setting_data[$result['key']] = json_decode($result['value'], true);
			}
		}
		
		$mailTemplates = array();
		$mailQuery = $this->db->query("SELECT * FROM " . DB_PREFIX . "orderreviews_mail WHERE store_id = '" . (int)$store_id . "' AND `code` = 'orderreviewsMailTemplate' ORDER BY mail_id ASC");

		foreach ($mailQuery->rows as $result) {
			if (!$result['serialized']) {
				$mailTemplates[$result['mail_id']] = $result['value'];
			} else {
				$mailTemplates[$result['mail_id']] = json_decode($result['value'], true);
			}
		}

		$setting_data[$code]['ReviewMail'] = $mailTemplates;

		return $setting_data;
	}
}
