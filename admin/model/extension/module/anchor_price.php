<?php
class ModelExtensionModuleAnchorPrice extends Model {
	const STORE_ID = 0;
	const CURRENCY_CODE = 'EUR';
	const CUTOVER_DATE = '2026-09-10';
	const PUBLICATION_LOCATION_CODE = 'WEB';

	private $catalog_language_id;

	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "anchor_price` (
			`anchor_price_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`product_id` INT(11) UNSIGNED NOT NULL,
			`store_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`price` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`gross_price` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`currency_code` CHAR(3) NOT NULL DEFAULT 'EUR',
			`tax_class_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`tax_context` TEXT NOT NULL,
			`reference_date` DATE NOT NULL,
			`rule_code` VARCHAR(32) NOT NULL,
			`source` VARCHAR(32) NOT NULL,
			`verification_status` VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			`created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`anchor_price_id`),
			UNIQUE KEY `product_store` (`product_id`, `store_id`),
			KEY `reference_date` (`reference_date`),
			KEY `verification_status` (`verification_status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "anchor_price_audit` (
			`audit_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`anchor_price_id` INT(11) UNSIGNED NOT NULL,
			`product_id` INT(11) UNSIGNED NOT NULL,
			`store_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`user_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`action` VARCHAR(32) NOT NULL,
			`old_data` MEDIUMTEXT NOT NULL,
			`new_data` MEDIUMTEXT NOT NULL,
			`reason` VARCHAR(255) NOT NULL,
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`audit_id`),
			KEY `anchor_price_id` (`anchor_price_id`),
			KEY `product_store` (`product_id`, `store_id`),
			KEY `date_added` (`date_added`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "anchor_price_publication` (
			`publication_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`store_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`batch_key` CHAR(32) NOT NULL DEFAULT '',
			`location_code` VARCHAR(16) NOT NULL,
			`sequence_no` INT(11) UNSIGNED NOT NULL,
			`filename` VARCHAR(255) NOT NULL,
			`relative_path` VARCHAR(255) NOT NULL,
			`status` VARCHAR(20) NOT NULL,
			`product_count` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`checksum_sha256` CHAR(64) NOT NULL DEFAULT '',
			`error_message` TEXT,
			`created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0',
			`published_at` DATETIME DEFAULT NULL,
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`publication_id`),
			UNIQUE KEY `store_location_sequence` (`store_id`, `location_code`, `sequence_no`),
			KEY `batch_key` (`batch_key`),
			KEY `status_published` (`status`, `published_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		$batch_column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "anchor_price_publication` LIKE 'batch_key'");
		if (!$batch_column->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` ADD `batch_key` CHAR(32) NOT NULL DEFAULT '' AFTER `store_id`");
		} elseif (strtolower($batch_column->row['Type']) !== 'char(32)' || $batch_column->row['Null'] !== 'NO' || (string)$batch_column->row['Default'] !== '') {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` MODIFY `batch_key` CHAR(32) NOT NULL DEFAULT ''");
		}

		$batch_index = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "anchor_price_publication` WHERE Key_name = 'batch_key'");
		$valid_batch_index = $batch_index->num_rows === 1 && $batch_index->row['Column_name'] === 'batch_key' && (int)$batch_index->row['Seq_in_index'] === 1 && (int)$batch_index->row['Non_unique'] === 1;
		if ($batch_index->num_rows && !$valid_batch_index) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` DROP INDEX `batch_key`");
		}
		if (!$valid_batch_index) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` ADD KEY `batch_key` (`batch_key`)");
		}

		$sequence_index = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "anchor_price_publication` WHERE Key_name = 'store_location_sequence'");
		$sequence_columns = array();
		$valid_sequence_index = $sequence_index->num_rows === 3;
		foreach ($sequence_index->rows as $index_row) {
			$sequence_columns[(int)$index_row['Seq_in_index']] = $index_row['Column_name'];
			$valid_sequence_index = $valid_sequence_index && (int)$index_row['Non_unique'] === 0;
		}
		ksort($sequence_columns);
		$valid_sequence_index = $valid_sequence_index && array_values($sequence_columns) === array('store_id', 'location_code', 'sequence_no');
		if ($sequence_index->num_rows && !$valid_sequence_index) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` DROP INDEX `store_location_sequence`");
		}
		if (!$valid_sequence_index) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` ADD UNIQUE KEY `store_location_sequence` (`store_id`, `location_code`, `sequence_no`)");
		}

		$status_index = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "anchor_price_publication` WHERE Key_name = 'status_published'");
		$status_columns = array();
		$valid_status_index = $status_index->num_rows === 2;
		foreach ($status_index->rows as $index_row) {
			$status_columns[(int)$index_row['Seq_in_index']] = $index_row['Column_name'];
			$valid_status_index = $valid_status_index && (int)$index_row['Non_unique'] === 1;
		}
		ksort($status_columns);
		$valid_status_index = $valid_status_index && array_values($status_columns) === array('status', 'published_at');
		if ($status_index->num_rows && !$valid_status_index) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` DROP INDEX `status_published`");
		}
		if (!$valid_status_index) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "anchor_price_publication` ADD KEY `status_published` (`status`, `published_at`)");
		}

		foreach (array('anchor_price', 'anchor_price_audit', 'anchor_price_publication') as $table_name) {
			$full_table_name = DB_PREFIX . $table_name;
			$table = $this->db->query("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $this->db->escape(DB_DATABASE) . "' AND TABLE_NAME = '" . $this->db->escape($full_table_name) . "' LIMIT 1");

			if ($table->num_rows && strtoupper($table->row['ENGINE']) !== 'INNODB') {
				$this->db->query("ALTER TABLE `" . $full_table_name . "` ENGINE=InnoDB");
			}
		}
	}

	public function uninstall() {
		// Deliberately keep all snapshots, audit entries and publication records.
	}

	public function tablesExist() {
		$query = $this->db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $this->db->escape(DB_DATABASE) . "' AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "anchor_price") . "' LIMIT 1");
		return (bool)$query->num_rows;
	}

	public function seedExistingProducts($created_by = 0) {
		return $this->syncProducts((int)$created_by, 'install', false);
	}

	public function syncMissingProducts($created_by = 0) {
		// Drafts are intentionally skipped. Their snapshot is created on first publication.
		return $this->syncProducts((int)$created_by, 'sync', true);
	}

	private function syncProducts($created_by, $source, $active_only) {
		$created = 0;
		$last_product_id = 0;
		$limit = 250;
		$now = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
		$today = $now->format('Y-m-d');

		do {
			$sql = "SELECT p.product_id, p.price, p.tax_class_id, p.status, p.date_added, p.date_available FROM `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . self::STORE_ID . "') LEFT JOIN `" . DB_PREFIX . "anchor_price` ap ON (ap.product_id = p.product_id AND ap.store_id = p2s.store_id) WHERE p.product_id > '" . (int)$last_product_id . "' AND ap.anchor_price_id IS NULL";

			if ($active_only || $source === 'install') {
				$sql .= " AND p.status = '1' AND p.date_available <= '" . $this->db->escape($today) . "'";
			} elseif ($source === 'install') {
				// Preserve legacy products from the reference day, but never assign a
				// draft created after the cutover a false first-publication date.
				$sql .= " AND (p.date_added < '2026-09-11 00:00:00' OR (p.status = '1' AND p.date_available <= '" . $this->db->escape($today) . "'))";
			}

			$sql .= " ORDER BY p.product_id ASC LIMIT " . (int)$limit;
			$query = $this->db->query($sql);

			foreach ($query->rows as $product) {
				$last_product_id = (int)$product['product_id'];
				$product_date = substr($product['date_added'], 0, 10);

				if ($source === 'install') {
					$reference_date = $product_date <= self::CUTOVER_DATE ? self::CUTOVER_DATE : $today;
					$rule_code = $product_date <= self::CUTOVER_DATE ? 'baseline_2026_09_10' : 'first_listing';
				} elseif ($source === 'sync') {
					// A missing active row is first observed as public now (typically a former draft).
					$reference_date = $now->format('Y-m-d');
					$rule_code = 'first_listing';
				} elseif ($product_date > self::CUTOVER_DATE) {
					$reference_date = $product_date;
					$rule_code = 'first_listing';
				} else {
					$reference_date = self::CUTOVER_DATE;
					$rule_code = 'baseline_2026_09_10';
				}

				if ($this->insertSnapshot($product, $reference_date, $rule_code, $source, $created_by)) {
					$created++;
				}
			}
		} while ($query->num_rows === $limit);

		return $created;
	}

	private function markAutomaticFirstListingsPending($created_by) {
		$today = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
		$query = $this->db->query("SELECT ap.* FROM `" . DB_PREFIX . "anchor_price` ap LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = ap.product_id) WHERE ap.verification_status = 'confirmed' AND ap.source IN ('migration_backfill_2026_09_10', 'install', 'sync', 'price_list_sync', 'cron_sync', 'product_event', 'product_edit_event') AND (ap.rule_code = 'first_listing' OR p.product_id IS NULL OR p.status <> '1' OR p.date_available > '" . $this->db->escape($today->format('Y-m-d')) . "') ORDER BY ap.anchor_price_id ASC");
		if (!$query->num_rows) {
			return 0;
		}

		$this->db->query('START TRANSACTION');
		try {
			foreach ($query->rows as $row) {
				$before = $this->snapshotFromRow($row);
				$after = $before;
				$after['verification_status'] = 'pending';
				$this->addAudit((int)$row['anchor_price_id'], (int)$row['product_id'], (int)$row['store_id'], 'status_review_required', $before, $after, 'Automatic snapshot date/status requires manual verification', (int)$created_by);
			}

			$anchor_price_ids = array();
			foreach ($query->rows as $row) {
				$anchor_price_ids[] = (int)$row['anchor_price_id'];
			}
			$this->db->query("UPDATE `" . DB_PREFIX . "anchor_price` SET verification_status = 'pending', date_modified = NOW() WHERE anchor_price_id IN (" . implode(',', $anchor_price_ids) . ")");
			$this->db->query('COMMIT');
		} catch (Exception $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}

		return $query->num_rows;
	}

	public function capturePublishedProduct($product_id, $source, $created_by = 0) {
		$now = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
		$query = $this->db->query("SELECT p.product_id, p.price, p.tax_class_id, p.status, p.date_added, p.date_available FROM `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . self::STORE_ID . "') WHERE p.product_id = '" . (int)$product_id . "' LIMIT 1");

		if (!$query->num_rows || !(int)$query->row['status'] || $query->row['date_available'] > $now->format('Y-m-d')) {
			return false;
		}

		$existing = $this->db->query("SELECT anchor_price_id FROM `" . DB_PREFIX . "anchor_price` WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . self::STORE_ID . "' LIMIT 1");

		if ($existing->num_rows) {
			return false;
		}

		return $this->insertSnapshot($query->row, $now->format('Y-m-d'), 'first_listing', $source, (int)$created_by);
	}

	private function insertSnapshot(array $product, $reference_date, $rule_code, $source, $created_by) {
		$price = round($this->currentSnapshotPrice($product), 4);
		$tax_class_id = (int)$product['tax_class_id'];
		$gross_price = round((float)$this->tax->calculate($price, $tax_class_id, true), 4);
		$tax_context = $this->buildTaxContext($price, $tax_class_id);
		$today = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
		// The merchant chose current effective amounts as the initial September baseline.
		// Products created later still require verification of their first publication date.
		$verification_status = (($source === 'install' && $rule_code === 'baseline_2026_09_10') || in_array($source, array('product_add_event', 'import_add_event'), true)) ? 'confirmed' : 'pending';

		$this->db->query('START TRANSACTION');
		try {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "anchor_price` SET product_id = '" . (int)$product['product_id'] . "', store_id = '" . self::STORE_ID . "', price = '" . (float)$price . "', gross_price = '" . (float)$gross_price . "', currency_code = '" . self::CURRENCY_CODE . "', tax_class_id = '" . $tax_class_id . "', tax_context = '" . $this->db->escape($tax_context) . "', reference_date = '" . $this->db->escape($reference_date) . "', rule_code = '" . $this->db->escape($rule_code) . "', source = '" . $this->db->escape($source) . "', verification_status = '" . $this->db->escape($verification_status) . "', created_by = '" . (int)$created_by . "', date_added = NOW(), date_modified = NOW() ON DUPLICATE KEY UPDATE anchor_price_id = anchor_price_id");

			if ($this->db->countAffected() !== 1) {
				$this->db->query('COMMIT');
				return false;
			}

			$anchor_price_id = (int)$this->db->getLastId();
			$after = array(
				'price' => number_format($price, 4, '.', ''),
				'gross_price' => number_format($gross_price, 4, '.', ''),
				'currency_code' => self::CURRENCY_CODE,
				'tax_class_id' => $tax_class_id,
				'tax_context' => $tax_context,
				'reference_date' => $reference_date,
				'rule_code' => $rule_code,
				'source' => $source,
				'verification_status' => $verification_status
			);

			$this->addAudit($anchor_price_id, (int)$product['product_id'], self::STORE_ID, 'create', null, $after, 'Automatic snapshot: ' . $source, (int)$created_by);
			$this->db->query('COMMIT');
		} catch (Exception $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}

		return true;
	}


	private function currentSnapshotPrice(array $product) {
		$group_id = (int)$this->config->get('config_customer_group_id');
		foreach (array('product_special' => '', 'product_discount' => " AND quantity = '1'") as $table => $condition) {
			$query = $this->db->query("SELECT price FROM `" . DB_PREFIX . $table . "` WHERE product_id = '" . (int)$product['product_id'] . "' AND customer_group_id = '" . $group_id . "'" . $condition . " AND ((date_start = '0000-00-00' OR date_start <= NOW()) AND (date_end = '0000-00-00' OR date_end >= NOW())) ORDER BY priority ASC, price ASC LIMIT 1");
			if ($query->num_rows) {
				return (float)$query->row['price'];
			}
		}
		return (float)$product['price'];
	}

	private function buildTaxContext($price, $tax_class_id) {
		$rates = $this->tax->getRates((float)$price, (int)$tax_class_id);
		$context = array(
			'calculation' => 'tax.calculate(value, tax_class_id, true)',
			'config_tax' => (bool)$this->config->get('config_tax'),
			'country_id' => (int)$this->config->get('config_country_id'),
			'zone_id' => (int)$this->config->get('config_zone_id'),
			'customer_group_id' => (int)$this->config->get('config_customer_group_id'),
			'tax_class_id' => (int)$tax_class_id,
			'rates' => $rates
		);

		$json = json_encode($context);
		return ($json === false) ? '{}' : $json;
	}

	public function getAnchorPrice($anchor_price_id, $lock = false) {
		$language_id = $this->getCatalogLanguageId();
		$query = $this->db->query("SELECT ap.*, p.model, p.sku, p.status AS product_status, p.date_added AS product_date_added, pd.name AS product_name, m.name AS manufacturer FROM `" . DB_PREFIX . "anchor_price` ap LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = ap.product_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = ap.product_id AND pd.language_id = '" . (int)$language_id . "') LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (m.manufacturer_id = p.manufacturer_id) WHERE ap.anchor_price_id = '" . (int)$anchor_price_id . "' LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
		return $query->row;
	}

	public function getAnchorPrices($data = array()) {
		$language_id = $this->getCatalogLanguageId();
		$sql = "SELECT ap.*, p.model, p.sku, p.status AS product_status, p.price AS current_price, pd.name AS product_name, m.name AS manufacturer FROM `" . DB_PREFIX . "anchor_price` ap LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = ap.product_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = ap.product_id AND pd.language_id = '" . (int)$language_id . "') LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (m.manufacturer_id = p.manufacturer_id) WHERE ap.store_id = '" . self::STORE_ID . "'";
		$sql .= $this->buildFilterSql($data);

		$sorts = array(
			'product_name' => 'pd.name',
			'model' => 'p.model',
			'price' => 'ap.price',
			'gross_price' => 'ap.gross_price',
			'reference_date' => 'ap.reference_date',
			'verification_status' => 'ap.verification_status'
		);
		$sort = isset($data['sort']) && isset($sorts[$data['sort']]) ? $sorts[$data['sort']] : 'pd.name';
		$order = isset($data['order']) && strtoupper($data['order']) === 'DESC' ? 'DESC' : 'ASC';
		$sql .= " ORDER BY " . $sort . " " . $order . ", ap.anchor_price_id ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? max(0, (int)$data['start']) : 0;
			$limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 20;
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalAnchorPrices($data = array()) {
		$language_id = $this->getCatalogLanguageId();
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "anchor_price` ap LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = ap.product_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = ap.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE ap.store_id = '" . self::STORE_ID . "'";
		$sql .= $this->buildFilterSql($data);
		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	private function buildFilterSql($data) {
		$sql = '';

		if (!empty($data['filter_name'])) {
			$sql .= " AND pd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_model'])) {
			$value = $this->db->escape($data['filter_model']);
			$sql .= " AND (p.model LIKE '%" . $value . "%' OR p.sku LIKE '%" . $value . "%')";
		}

		if (!empty($data['filter_status']) && in_array($data['filter_status'], array('confirmed', 'pending', 'disabled'), true)) {
			$sql .= " AND ap.verification_status = '" . $this->db->escape($data['filter_status']) . "'";
		}

		if (!empty($data['filter_date_from'])) {
			$sql .= " AND ap.reference_date >= '" . $this->db->escape($data['filter_date_from']) . "'";
		}

		if (!empty($data['filter_date_to'])) {
			$sql .= " AND ap.reference_date <= '" . $this->db->escape($data['filter_date_to']) . "'";
		}

		return $sql;
	}

	public function getMissingProductCount($active_only = true) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . self::STORE_ID . "') LEFT JOIN `" . DB_PREFIX . "anchor_price` ap ON (ap.product_id = p.product_id AND ap.store_id = p2s.store_id) WHERE ap.anchor_price_id IS NULL";
		if ($active_only || $source === 'install') {
			$now = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
			$sql .= " AND p.status = '1' AND p.date_available <= '" . $this->db->escape($now->format('Y-m-d')) . "'";
		}
		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function updateAnchorPrice($anchor_price_id, array $data, $reason, $created_by) {
		$this->db->query('START TRANSACTION');
		try {
			$before = $this->getAnchorPrice((int)$anchor_price_id, true);
			if (!$before) {
				throw new Exception('Sidrena cijena više ne postoji.');
			}
			$after = $this->prepareAnchorUpdate($before, $data, $reason);
			$this->writeAnchorUpdate($before, $after, trim($reason), $created_by);
			$this->db->query('COMMIT');
		} catch (Exception $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
		return true;
	}

	private function prepareAnchorUpdate(array $before, array $data, $reason) {
		$status = isset($data['verification_status']) ? $data['verification_status'] : '';
		if (!in_array($status, array('confirmed', 'pending', 'disabled'), true)) {
			throw new Exception('Invalid anchor price status.');
		}
		if ((int)$before['product_status'] === 1 && $before['verification_status'] === 'confirmed' && $status !== 'confirmed') {
			throw new Exception('An active product must keep a confirmed anchor price. Disable the product before changing this status.');
		}

		foreach (array('price', 'gross_price') as $field) {
			$value = isset($data[$field]) ? str_replace(',', '.', trim((string)$data[$field])) : '';
			if (!preg_match('/^[0-9]{1,11}(?:\.[0-9]{1,4})?$/D', $value)) {
				throw new Exception('Unesite nenegativan iznos s najviše četiri decimale: ' . $field . '.');
			}
			$data[$field] = $value;
		}
		$reference_date = isset($data['reference_date']) ? trim((string)$data['reference_date']) : '';
		$date = DateTime::createFromFormat('!Y-m-d', $reference_date);
		if (!$date || $date->format('Y-m-d') !== $reference_date) {
			throw new Exception('Referentni datum mora biti ispravan datum GGGG-MM-DD.');
		}
		$today = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
		if ($reference_date < self::CUTOVER_DATE || $reference_date > $today->format('Y-m-d')) {
			throw new Exception('Referentni datum mora biti od 2026-09-10 do danas.');
		}
		$product_date_added = isset($before['product_date_added']) ? substr($before['product_date_added'], 0, 10) : '';
		if ($product_date_added > self::CUTOVER_DATE && $reference_date <= self::CUTOVER_DATE) {
			throw new Exception('Artikl dodan nakon 2026-09-10 ne može imati cijenu na raniji referentni dan.');
		}
		$rule_code = $before['rule_code'];
		if ($before['verification_status'] === 'pending') {
			if (($before['rule_code'] === 'first_listing' || $product_date_added > self::CUTOVER_DATE) && $reference_date <= self::CUTOVER_DATE) {
				throw new Exception('A product first listed after 2026-09-10 cannot use the 2026-09-10 baseline date.');
			}
			$rule_code = $reference_date === self::CUTOVER_DATE ? 'baseline_2026_09_10' : 'first_listing';
		} else {
			if ($rule_code === 'baseline_2026_09_10' && $reference_date !== self::CUTOVER_DATE) {
				throw new Exception('A baseline anchor must keep the 2026-09-10 reference date.');
			}
			if ($rule_code === 'first_listing' && ($reference_date <= self::CUTOVER_DATE || $reference_date > $today->format('Y-m-d'))) {
				throw new Exception('A first-listing date must be after 2026-09-10 and cannot be in the future.');
			}
		}

		$reason = trim($reason);
		if (utf8_strlen($reason) < 3 || utf8_strlen($reason) > 255) {
			throw new Exception('An audit reason is required.');
		}

		$tax_context = $this->buildTaxContext((float)$data['price'], (int)$before['tax_class_id']);
		$tax_context_data = json_decode($tax_context, true);
		if (is_array($tax_context_data)) {
			$tax_context_data['manual_edit'] = true;
			$tax_context_data['entered_gross_price'] = number_format((float)$data['gross_price'], 4, '.', '');
			$encoded_context = json_encode($tax_context_data);
			if ($encoded_context !== false) {
				$tax_context = $encoded_context;
			}
		}

		$after = array(
			'price' => number_format((float)$data['price'], 4, '.', ''),
			'gross_price' => number_format((float)$data['gross_price'], 4, '.', ''),
			'currency_code' => $before['currency_code'],
			'tax_class_id' => (int)$before['tax_class_id'],
			'tax_context' => $tax_context,
			'reference_date' => $reference_date,
			'rule_code' => $rule_code,
			'source' => 'admin',
			'verification_status' => $status
		);
		return $after;
	}

	private function writeAnchorUpdate(array $before, array $after, $reason, $created_by) {
		$anchor_price_id = (int)$before['anchor_price_id'];
		$this->db->query("UPDATE `" . DB_PREFIX . "anchor_price` SET price = '" . (float)$after['price'] . "', gross_price = '" . (float)$after['gross_price'] . "', tax_context = '" . $this->db->escape($after['tax_context']) . "', reference_date = '" . $this->db->escape($after['reference_date']) . "', rule_code = '" . $this->db->escape($after['rule_code']) . "', source = '" . $this->db->escape($after['source']) . "', verification_status = '" . $this->db->escape($after['verification_status']) . "', date_modified = NOW() WHERE anchor_price_id = '" . $anchor_price_id . "'");
		$this->addAudit($anchor_price_id, (int)$before['product_id'], (int)$before['store_id'], 'update', $this->snapshotFromRow($before), $after, $reason, (int)$created_by);
	}

	public function importCsv($path, $created_by) {
		if (!is_file($path) || !is_readable($path) || filesize($path) > 8388608) {
			throw new Exception('CSV datoteka mora biti čitljiva i manja od 8 MB.');
		}
		$content = file_get_contents($path);
		if ($content === false || strpos($content, "\0") !== false || !preg_match('//u', $content)) {
			throw new Exception('CSV datoteka mora biti u UTF-8 kodiranju.');
		}
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		$first_line = strtok($content, "\r\n");
		$delimiter = substr_count((string)$first_line, ';') >= substr_count((string)$first_line, ',') ? ';' : ',';
		$handle = fopen('php://temp', 'w+b');
		fwrite($handle, $content);
		rewind($handle);
		$rows = array();
		$seen = array();
		try {
			$headers = fgetcsv($handle, 0, $delimiter, '"', '');
			if (!$headers) {
				throw new Exception('CSV datoteka nema zaglavlje.');
			}
			$headers = array_map('trim', $headers);
			$allowed = array('product_id', 'model', 'price', 'gross_price', 'reference_date', 'verification_status', 'reason');
			$required = array('price', 'gross_price', 'reference_date', 'verification_status', 'reason');
			if (count($headers) !== count(array_unique($headers)) || array_diff($headers, $allowed) || array_diff($required, $headers) || (!in_array('product_id', $headers, true) && !in_array('model', $headers, true))) {
				throw new Exception('Neispravno CSV zaglavlje. Koristite predložak i product_id ili jedinstveni model.');
			}
			$line = 1;
			while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
				$line++;
				if (count($values) === 1 && trim((string)$values[0]) === '') {
					continue;
				}
				try {
					if (count($values) !== count($headers)) {
						throw new Exception('Broj stupaca ne odgovara zaglavlju.');
					}
					$data = array_combine($headers, array_map('trim', $values));
					$before = $this->importProductState($data);
					$product_id = (int)$before['product_id'];
					if (isset($seen[$product_id])) {
						throw new Exception('Artikl se ponavlja u istoj datoteci.');
					}
					$after = $this->prepareAnchorUpdate($before, $data, $data['reason']);
					$after['source'] = 'csv_import';
					$rows[$product_id] = array('data' => $data, 'before' => $before, 'after' => $after, 'line' => $line);
					$seen[$product_id] = true;
					if (count($rows) > 10000) {
						throw new Exception('Najviše 10.000 artikala po uvozu.');
					}
				} catch (Exception $exception) {
					throw new Exception('CSV redak ' . $line . ': ' . $exception->getMessage());
				}
			}
		} finally {
			fclose($handle);
		}
		if (!$rows) {
			throw new Exception('CSV datoteka nema artikala za uvoz.');
		}

		// No database writes occur until the complete file has passed validation.
		ksort($rows, SORT_NUMERIC);
		$this->db->query('START TRANSACTION');
		try {
			foreach ($rows as $row) {
				$locked = $this->importProductState($row['data'], true);
				if ($locked !== $row['before']) {
					throw new Exception('CSV redak ' . $row['line'] . ': Artikl je izmijenjen tijekom provjere. Ponovite uvoz.');
				}
				$before = $row['before'];
				$after = $row['after'];
				$reason = $row['data']['reason'];
				if (!empty($before['anchor_price_id'])) {
					$this->writeAnchorUpdate($before, $after, $reason, $created_by);
				} else {
					$fields = array_merge($after, array('product_id' => (int)$before['product_id'], 'store_id' => self::STORE_ID, 'created_by' => (int)$created_by));
					$assignments = array();
					foreach ($fields as $field => $value) {
						$assignments[] = '`' . $field . "` = '" . $this->db->escape((string)$value) . "'";
					}
					$this->db->query("INSERT INTO `" . DB_PREFIX . "anchor_price` SET " . implode(', ', $assignments) . ', date_added = NOW(), date_modified = NOW()');
					$this->addAudit((int)$this->db->getLastId(), (int)$before['product_id'], self::STORE_ID, 'create', null, $after, $reason, (int)$created_by);
				}
			}
			$this->db->query('COMMIT');
		} catch (Exception $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}
		return count($rows);
	}

	private function importProductState(array $data, $lock = false) {
		$product_id = isset($data['product_id']) ? $data['product_id'] : '';
		$model = isset($data['model']) ? $data['model'] : '';
		if (($product_id !== '' && (!ctype_digit($product_id) || (int)$product_id < 1 || (int)$product_id > 2147483647)) || ($product_id === '' && $model === '')) {
			throw new Exception('Unesite ispravan product_id ili jedinstveni model.');
		}
		$where = $product_id !== '' ? "p.product_id = '" . (int)$product_id . "'" : "BINARY p.model = BINARY '" . $this->db->escape($model) . "'";
		$query = $this->db->query("SELECT p.product_id, p.model, p.tax_class_id, p.status AS product_status, p.date_added AS product_date_added FROM `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . self::STORE_ID . "') WHERE " . $where . " ORDER BY p.product_id LIMIT 2" . ($lock ? ' FOR UPDATE' : ''));
		if ($query->num_rows !== 1 || ($model !== '' && $query->row['model'] !== $model)) {
			throw new Exception('Artikl ne postoji, model nije jedinstven ili product_id i model ne odgovaraju.');
		}
		$product = $query->row;
		$anchor = $this->db->query("SELECT * FROM `" . DB_PREFIX . "anchor_price` WHERE product_id = '" . (int)$product['product_id'] . "' AND store_id = '" . self::STORE_ID . "' LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
		$before = $anchor->num_rows ? $anchor->row : array(
			'anchor_price_id' => 0, 'store_id' => self::STORE_ID, 'price' => '0.0000', 'gross_price' => '0.0000',
			'currency_code' => self::CURRENCY_CODE, 'tax_context' => '{}', 'reference_date' => self::CUTOVER_DATE,
			'rule_code' => 'baseline_2026_09_10', 'source' => '', 'verification_status' => 'pending'
		);
		if ($anchor->num_rows) {
			// Historical snapshots retain their original tax class even if the product changes.
			unset($product['tax_class_id']);
		}
		return array_merge($before, $product);
	}

	private function snapshotFromRow(array $row) {
		return array(
			'price' => $row['price'],
			'gross_price' => $row['gross_price'],
			'currency_code' => $row['currency_code'],
			'tax_class_id' => (int)$row['tax_class_id'],
			'tax_context' => $row['tax_context'],
			'reference_date' => $row['reference_date'],
			'rule_code' => $row['rule_code'],
			'source' => $row['source'],
			'verification_status' => $row['verification_status']
		);
	}

	private function addAudit($anchor_price_id, $product_id, $store_id, $action, $before, array $after, $reason, $created_by) {
		$before_json = $before === null ? '{}' : json_encode($before);
		$after_json = json_encode($after);
		$this->db->query("INSERT INTO `" . DB_PREFIX . "anchor_price_audit` SET anchor_price_id = '" . (int)$anchor_price_id . "', product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', user_id = '" . (int)$created_by . "', action = '" . $this->db->escape($action) . "', old_data = '" . $this->db->escape($before_json === false ? '{}' : $before_json) . "', new_data = '" . $this->db->escape($after_json === false ? '{}' : $after_json) . "', reason = '" . $this->db->escape($reason) . "', date_added = NOW()");
	}

	public function getAuditTrail($anchor_price_id, $limit = 25) {
		$limit = max(1, min(100, (int)$limit));
		$query = $this->db->query("SELECT a.*, u.username FROM `" . DB_PREFIX . "anchor_price_audit` a LEFT JOIN `" . DB_PREFIX . "user` u ON (u.user_id = a.user_id) WHERE a.anchor_price_id = '" . (int)$anchor_price_id . "' ORDER BY a.audit_id DESC LIMIT " . $limit);
		return $query->rows;
	}

	public function generateDailyPublications($store_id = 0, $created_by = 0, $force = false) {
		$store_id = (int)$store_id;
		if ($store_id !== self::STORE_ID) {
			throw new Exception('Only store 0 is supported by this publication.');
		}

		$lock_name = 'anchor_price_publication_' . $store_id;
		$this->acquirePublicationLock($lock_name);

		try {
			$this->discardUnpublishedPublications($store_id);
			$this->syncMissingProducts((int)$created_by);
			$products = $this->getPublicationProducts($store_id);
			$this->assertPublicationProducts($products);
			if (!$products) {
				throw new Exception('The price list has no confirmed active products.');
			}
			$now = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
			$location_code = self::PUBLICATION_LOCATION_CODE;

			if (!$force) {
				$existing = $this->getTodayPublication($store_id, $location_code, $now);
				if ($existing && $this->publishedBatchIsValid($store_id, $existing['batch_key'])) {
					$this->archiveExpiredPublications();
					$this->releasePublicationLock($lock_name);
					return array($this->publicationResult($existing, true));
				}
			}

			$batch_key = $this->createBatchKey();

			try {
				$publication = $this->generateLocationPublication($store_id, (int)$created_by, $location_code, $products, $now, $batch_key);
				$publication = $this->publishPublication($store_id, $publication, $now, $batch_key);
			} catch (Exception $publication_exception) {
				if (!empty($publication['publication_id'])) {
					$this->invalidatePublication($publication['publication_id'], 'Daily price-list publication was not completed.');
				}
				throw $publication_exception;
			}

			$this->archiveExpiredPublications();
			$this->releasePublicationLock($lock_name);
			return array($publication);
		} catch (Exception $exception) {
			$this->releasePublicationLock($lock_name);
			throw $exception;
		}
	}

	public function generatePublicationCsv($store_id = 0, $created_by = 0, $location_code = 'WEB', $force = false) {
		$store_id = (int)$store_id;
		$location_code = strtoupper(trim($location_code));
		if ($store_id !== self::STORE_ID || $location_code !== self::PUBLICATION_LOCATION_CODE) {
			throw new Exception('Unsupported store or sales location.');
		}

		$publications = $this->generateDailyPublications($store_id, (int)$created_by, (bool)$force);
		foreach ($publications as $publication) {
			if ($publication['location_code'] === $location_code) {
				return $publication;
			}
		}

		throw new Exception('Requested sales-location publication was not generated.');
	}

	private function generateLocationPublication($store_id, $created_by, $location_code, array $products, DateTime $now, $batch_key) {
		$reservation = $this->reservePublication($store_id, $location_code, (int)$created_by, $now, $batch_key);
		$publication_id = $reservation['publication_id'];
		$filename = $reservation['filename'];
		$relative_path = 'anchor_price/' . $filename;
		$directory = rtrim(DIR_DOWNLOAD, '/\\') . DIRECTORY_SEPARATOR . 'anchor_price';
		$final_path = $directory . DIRECTORY_SEPARATOR . $filename;
		$temp_path = '';
		$handle = null;
		$published_file = false;

		try {
			if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
				throw new Exception('Unable to create the anchor-price publication directory.');
			}

			$temp_path = tempnam($directory, '.anchor-price-');
			if ($temp_path === false) {
				throw new Exception('Unable to create a temporary publication file.');
			}

			$handle = fopen($temp_path, 'wb');
			if ($handle === false) {
				throw new Exception('Unable to open the temporary publication file.');
			}

			if (fwrite($handle, "\xEF\xBB\xBF") === false) {
				fclose($handle);
				throw new Exception('Unable to write the CSV byte-order mark.');
			}

			$this->writeCsvRow($handle, array(
				'Prodajni kanal',
				'ID proizvoda',
				'Naziv proizvoda',
				'Šifra/model',
				'SKU',
				'Marka/proizvođač',
				'Jedinica mjere',
				'Cijena po jedinici (EUR)',
				'Redovna maloprodajna cijena (EUR)',
				'Aktualna maloprodajna cijena (EUR)',
				'Poseban oblik prodaje',
				'Naziv posebnog oblika prodaje',
				'Aktualna akcijska cijena (EUR)',
				'Sidrena cijena (EUR)',
				'Datum sidrene cijene',
				'Barkod',
				'Dostupnost',
				'Količina',
				'Status zalihe',
				'Valuta'
			));

			foreach ($products as $product) {
				$regular_net = $product['discount'] !== null && $product['discount'] !== '' ? (float)$product['discount'] : (float)$product['regular_price'];
				$regular_gross = round((float)$this->tax->calculate($regular_net, (int)$product['tax_class_id'], true), 4);
				$has_special = $product['special_price'] !== null && $product['special_price'] !== '';
				$special_gross = $has_special ? round((float)$this->tax->calculate((float)$product['special_price'], (int)$product['tax_class_id'], true), 4) : null;
				$selling_gross = $has_special ? $special_gross : $regular_gross;
				$unit = trim((string)$this->config->get('module_anchor_price_default_unit'));
				if ($unit === '') {
					$unit = 'kom';
				}
				$unit_price = $this->csvMoney($selling_gross);

				$barcode = $this->validPublicationBarcode($product);

				$availability = (int)$product['quantity'] > 0 ? 'Dostupno' : 'Nije dostupno';
				$this->writeCsvRow($handle, array(
					'Web trgovina',
					(int)$product['product_id'],
					$this->csvText($product['product_name']),
					$this->csvText($product['model']),
					$this->csvText($product['sku']),
					$this->csvText($product['manufacturer']),
					$unit,
					$unit_price,
					$this->csvMoney($regular_gross),
					$this->csvMoney($selling_gross),
					$has_special ? 'DA' : 'NE',
					$has_special ? 'Akcija' : '',
					$has_special ? $this->csvMoney($special_gross) : '',
					$this->csvMoney($product['anchor_gross_price']),
					$product['reference_date'],
					$barcode,
					$availability,
					(int)$product['quantity'],
					$this->csvText($product['stock_status']),
					$product['currency_code'] ? $product['currency_code'] : self::CURRENCY_CODE
				));
			}

			if (!fflush($handle)) {
				fclose($handle);
				throw new Exception('Unable to flush the publication file.');
			}
			if (function_exists('fsync')) {
				@fsync($handle);
			}
			fclose($handle);

			if (!rename($temp_path, $final_path)) {
				throw new Exception('Unable to atomically publish the CSV file.');
			}
			$temp_path = '';
			$published_file = true;
			@chmod($final_path, 0640);
			$checksum = hash_file('sha256', $final_path);
			if ($checksum === false) {
				throw new Exception('Unable to calculate the publication checksum.');
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "anchor_price_publication` SET relative_path = '" . $this->db->escape($relative_path) . "', status = 'staged', product_count = '" . (int)count($products) . "', checksum_sha256 = '" . $this->db->escape($checksum) . "', error_message = NULL, published_at = NULL WHERE publication_id = '" . (int)$publication_id . "' AND batch_key = '" . $this->db->escape($batch_key) . "'");
			return array(
				'publication_id' => $publication_id,
				'batch_key' => $batch_key,
				'location_code' => $location_code,
				'filename' => $filename,
				'relative_path' => $relative_path,
				'product_count' => count($products),
				'checksum_sha256' => $checksum
			);
		} catch (Exception $exception) {
			if (is_resource($handle)) {
				@fclose($handle);
			}
			if ($temp_path && is_file($temp_path)) {
				@unlink($temp_path);
			}
			if ($published_file && is_file($final_path)) {
				@unlink($final_path);
			}
			$this->db->query("UPDATE `" . DB_PREFIX . "anchor_price_publication` SET status = 'failed', error_message = '" . $this->db->escape(substr($exception->getMessage(), 0, 2000)) . "' WHERE publication_id = '" . (int)$publication_id . "'");
			throw $exception;
		}
	}

	private function publishPublication($store_id, array $publication, DateTime $now, $batch_key) {
		if (empty($publication['publication_id'])) {
			throw new Exception('Daily price list is not ready for publication.');
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "anchor_price_publication` WHERE store_id = '" . (int)$store_id . "' AND batch_key = '" . $this->db->escape($batch_key) . "' AND location_code = '" . self::PUBLICATION_LOCATION_CODE . "' AND status = 'staged'");
		if ($query->num_rows !== 1) {
			throw new Exception('Daily price list is incomplete.');
		}

		$publication = $query->row;
		$path = $this->getPublicationPath($publication);
		if (!$this->publicationFileIsValid($publication, $path)) {
			throw new Exception('Prepared price-list checksum is invalid.');
		}
		$publication_id = (int)$publication['publication_id'];

		$this->db->query('START TRANSACTION');
		try {
			$this->db->query("UPDATE `" . DB_PREFIX . "anchor_price_publication` SET status = 'published', published_at = '" . $this->db->escape($now->format('Y-m-d H:i:s')) . "' WHERE publication_id = '" . $publication_id . "' AND batch_key = '" . $this->db->escape($batch_key) . "' AND status = 'staged'");
			if ($this->db->countAffected() !== 1) {
				throw new Exception('Atomic daily price-list publication failed.');
			}
			$this->db->query('COMMIT');
		} catch (Exception $exception) {
			$this->db->query('ROLLBACK');
			throw $exception;
		}

		return $this->publicationResult($this->getPublication($publication_id), false);
	}

	private function discardUnpublishedPublications($store_id) {
		$query = $this->db->query("SELECT publication_id FROM `" . DB_PREFIX . "anchor_price_publication` WHERE store_id = '" . (int)$store_id . "' AND status IN ('generating', 'staged')");
		foreach ($query->rows as $publication) {
			$this->invalidatePublication((int)$publication['publication_id'], 'Incomplete publication preparation removed before retry.');
		}
	}

	private function createBatchKey() {
		return md5(uniqid((string)mt_rand(), true));
	}

	private function reservePublication($store_id, $location_code, $created_by, DateTime $now, $batch_key) {
		$query = $this->db->query("SELECT COALESCE(MAX(sequence_no), 0) + 1 AS sequence_no FROM `" . DB_PREFIX . "anchor_price_publication` WHERE store_id = '" . (int)$store_id . "' AND location_code = '" . $this->db->escape($location_code) . "'");
		$sequence_no = (int)$query->row['sequence_no'];
		$location = $this->publicationLocation($location_code);
		$filename = 'cjenik_' . $location['address'] . '_' . str_pad($sequence_no, 6, '0', STR_PAD_LEFT) . '_' . $now->format('Ymd_His') . '.csv';
		$relative_path = 'anchor_price/' . $filename;
		$this->db->query("INSERT INTO `" . DB_PREFIX . "anchor_price_publication` SET store_id = '" . (int)$store_id . "', batch_key = '" . $this->db->escape($batch_key) . "', location_code = '" . $this->db->escape($location_code) . "', sequence_no = '" . $sequence_no . "', filename = '" . $this->db->escape($filename) . "', relative_path = '" . $this->db->escape($relative_path) . "', status = 'generating', product_count = '0', checksum_sha256 = '', created_by = '" . (int)$created_by . "', date_added = '" . $this->db->escape($now->format('Y-m-d H:i:s')) . "'");
		$publication_id = (int)$this->db->getLastId();
		return array('publication_id' => $publication_id, 'filename' => $filename);
	}

	private function acquirePublicationLock($lock_name) {
		$query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($lock_name) . "', 10) AS acquired");
		if (empty($query->row['acquired'])) {
			throw new Exception('Another price-list publication is already in progress.');
		}
	}

	private function releasePublicationLock($lock_name) {
		$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($lock_name) . "')");
	}

	private function getTodayPublication($store_id, $location_code, DateTime $now) {
		$start = clone $now;
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->modify('+1 day');
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "anchor_price_publication` WHERE store_id = '" . (int)$store_id . "' AND location_code = '" . $this->db->escape($location_code) . "' AND status = 'published' AND published_at >= '" . $this->db->escape($start->format('Y-m-d H:i:s')) . "' AND published_at < '" . $this->db->escape($end->format('Y-m-d H:i:s')) . "' ORDER BY publication_id DESC LIMIT 1");

		if (!$query->num_rows) {
			return array();
		}

		$publication = $query->row;
		$path = $this->getPublicationPath($publication);
		$expected_checksum = strtolower(trim((string)$publication['checksum_sha256']));
		$actual_checksum = ($path && is_readable($path)) ? hash_file('sha256', $path) : false;
		$checksum_matches = $actual_checksum !== false && preg_match('/^[a-f0-9]{64}$/', $expected_checksum);

		if ($checksum_matches) {
			$checksum_matches = function_exists('hash_equals') ? hash_equals($expected_checksum, strtolower($actual_checksum)) : $expected_checksum === strtolower($actual_checksum);
		}

		if (!$checksum_matches) {
			$this->invalidatePublication((int)$publication['publication_id'], 'Existing daily publication file is missing or its SHA-256 checksum does not match.');
			return array();
		}

		return $publication;
	}

	private function publishedBatchIsValid($store_id, $batch_key) {
		$batch_key = strtolower(trim((string)$batch_key));
		if (!preg_match('/^[a-f0-9]{32}$/', $batch_key)) {
			return false;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "anchor_price_publication` WHERE store_id = '" . (int)$store_id . "' AND batch_key = '" . $this->db->escape($batch_key) . "' AND status = 'published'");
		if ($query->num_rows !== 1) {
			return false;
		}

		$publication = $query->row;
		return $publication['location_code'] === self::PUBLICATION_LOCATION_CODE
			&& !empty($publication['published_at'])
			&& (int)$publication['product_count'] > 0
			&& $this->publicationFileIsValid($publication, $this->getPublicationPath($publication));
	}

	private function publicationResult(array $publication, $existing) {
		return array(
			'publication_id' => (int)$publication['publication_id'],
			'batch_key' => isset($publication['batch_key']) ? $publication['batch_key'] : '',
			'location_code' => $publication['location_code'],
			'filename' => $publication['filename'],
			'relative_path' => $publication['relative_path'],
			'product_count' => (int)$publication['product_count'],
			'checksum_sha256' => $publication['checksum_sha256'],
			'existing' => (bool)$existing
		);
	}

	private function invalidatePublication($publication_id, $reason) {
		$publication = $this->getPublication((int)$publication_id);
		if ($publication) {
			$path = $this->getPublicationPath($publication);
			if ($path) {
				@unlink($path);
			}
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "anchor_price_publication` SET status = 'failed', error_message = '" . $this->db->escape(substr($reason, 0, 2000)) . "', published_at = NULL WHERE publication_id = '" . (int)$publication_id . "' AND store_id = '" . self::STORE_ID . "'");
	}

	private function getPublicationProducts($store_id) {
		$language_id = $this->getCatalogLanguageId();
		$customer_group_id = (int)$this->config->get('config_customer_group_id');
		$sql = "SELECT p.product_id, p.model, p.sku, p.ean, p.jan, p.isbn, p.quantity, p.stock_status_id, p.tax_class_id, p.manufacturer_id, p.price AS regular_price, pd.name AS product_name, COALESCE(m.name, '') AS manufacturer, COALESCE(ss.name, '') AS stock_status, ap.anchor_price_id, ap.verification_status, ap.gross_price AS anchor_gross_price, ap.reference_date, ap.currency_code, (SELECT pdq.price FROM `" . DB_PREFIX . "product_discount` pdq WHERE pdq.product_id = p.product_id AND pdq.customer_group_id = '" . $customer_group_id . "' AND pdq.quantity = '1' AND ((pdq.date_start = '0000-00-00' OR pdq.date_start <= NOW()) AND (pdq.date_end = '0000-00-00' OR pdq.date_end >= NOW())) ORDER BY pdq.priority ASC, pdq.price ASC LIMIT 1) AS discount, (SELECT ps.price FROM `" . DB_PREFIX . "product_special` ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . $customer_group_id . "' AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end >= NOW()) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special_price FROM `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . (int)$store_id . "') LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = p.product_id AND pd.language_id = '" . (int)$language_id . "') LEFT JOIN `" . DB_PREFIX . "anchor_price` ap ON (ap.product_id = p.product_id AND ap.store_id = '" . (int)$store_id . "') LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (m.manufacturer_id = p.manufacturer_id) LEFT JOIN `" . DB_PREFIX . "stock_status` ss ON (ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$language_id . "') WHERE p.status = '1' AND p.date_available <= NOW() ORDER BY p.product_id ASC";
		return $this->db->query($sql)->rows;
	}

	private function assertPublicationProducts(array $products) {
		$total = 0;
		foreach ($products as $product) {
			if (empty($product['anchor_price_id'])
				|| $product['verification_status'] !== 'confirmed'
				|| trim((string)$product['product_name']) === ''
				|| trim((string)$product['model']) === ''
				|| empty($product['manufacturer_id'])
				|| trim((string)$product['manufacturer']) === ''
				|| $this->hasInvalidPublicationBarcode($product)) {
				$total++;
			}
		}
		if ($total > 0) {
			throw new Exception($total . ' aktivnih proizvoda nema potvrđenu sidrenu cijenu, naziv, šifru ili marku, ili sadrži neispravan GTIN barkod. Objava je zaustavljena.');
		}
	}

	private function hasInvalidPublicationBarcode(array $product) {
		foreach (array('ean') as $field) {
			$value = isset($product[$field]) ? trim((string)$product[$field]) : '';
			if ($value === '') {
				continue;
			}

			if (!$this->isValidGtin($value)) {
				return true;
			}
		}

		return false;
	}

	private function validPublicationBarcode(array $product) {
		foreach (array('ean') as $field) {
			$value = isset($product[$field]) ? trim((string)$product[$field]) : '';
			if ($this->isValidGtin($value)) {
				return $value;
			}
		}

		return '';
	}

	private function isValidGtin($value) {
		$length = strlen($value);
		if (!in_array($length, array(8, 12, 13, 14), true) || !preg_match('/^[0-9]+$/', $value)) {
			return false;
		}

		$sum = 0;
		$weight = 3;
		for ($index = $length - 2; $index >= 0; $index--) {
			$sum += ((int)$value[$index]) * $weight;
			$weight = ($weight === 3) ? 1 : 3;
		}

		return ((10 - ($sum % 10)) % 10) === (int)$value[$length - 1];
	}

	private function writeCsvRow($handle, array $row) {
		$written = fputcsv($handle, $row, ';', '"', '');

		if ($written === false) {
			throw new Exception('Unable to write a CSV row.');
		}
	}

	private function csvMoney($value) {
		if ($value === null || $value === '') {
			return '';
		}
		return number_format((float)$value, 2, ',', '');
	}

	private function csvText($value) {
		return trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
	}

	private function publicationSlug($value) {
		$value = trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
			if ($converted !== false) {
				$value = $converted;
			}
		}
		$value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
		$value = trim($value, '-');
		return $value !== '' ? $value : 'nepoznata-adresa';
	}

	private function publicationLocation($location_code) {
		$catalog_url = defined('HTTPS_CATALOG') && HTTPS_CATALOG ? HTTPS_CATALOG : (defined('HTTP_CATALOG') ? HTTP_CATALOG : 'webshop');
		$host = parse_url($catalog_url, PHP_URL_HOST);
		$address = $this->publicationSlug($host ? $host : $catalog_url);
		return array(
			'type' => 'cjenik',
			'address' => substr($address, 0, 120)
		);
	}

	private function publicationAddressLine($address) {
		$address = preg_replace('~<br\s*/?>~i', "\n", (string)$address);
		$address = html_entity_decode(strip_tags(str_replace(array("\r\n", "\r"), "\n", $address)), ENT_QUOTES, 'UTF-8');
		$fallback = '';

		foreach (explode("\n", $address) as $line) {
			$line = trim($line);

			if ($line === '' || preg_match('/\b(oib|iban|mbs|mati[cč]ni|ra[cč]un|banka|swift|vat)\b/iu', $line)) {
				continue;
			}

			if ($fallback === '') {
				$fallback = $line;
			}

			if (preg_match('/\p{L}/u', $line) && preg_match('/\d/', $line)) {
				return $line;
			}
		}

		return $fallback;
	}

	public function archiveExpiredPublications() {
		$cutoff = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
		$cutoff->modify('-30 days');
		$query = $this->db->query("SELECT publication_id, relative_path FROM `" . DB_PREFIX . "anchor_price_publication` WHERE status = 'published' AND published_at < '" . $this->db->escape($cutoff->format('Y-m-d H:i:s')) . "'");

		foreach ($query->rows as $publication) {
			$relative_path = str_replace('\\', '/', $publication['relative_path']);
			if (strpos($relative_path, 'anchor_price/') === 0 && strpos($relative_path, '..') === false && substr($relative_path, -4) === '.csv') {
				$path = rtrim(DIR_DOWNLOAD, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
				if (is_file($path)) {
					@unlink($path);
				}
			}
			$this->db->query("UPDATE `" . DB_PREFIX . "anchor_price_publication` SET status = 'expired' WHERE publication_id = '" . (int)$publication['publication_id'] . "'");
		}
	}

	public function getPublications($limit = 10) {
		$limit = max(1, min(100, (int)$limit));
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "anchor_price_publication` WHERE store_id = '" . self::STORE_ID . "' AND location_code = '" . self::PUBLICATION_LOCATION_CODE . "' ORDER BY publication_id DESC LIMIT " . $limit);
		return $query->rows;
	}

	public function getDailyPublicationState() {
		$now = new DateTime('now', new DateTimeZone('Europe/Zagreb'));
		$start = clone $now;
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->modify('+1 day');
		$query = $this->db->query("SELECT DISTINCT location_code FROM `" . DB_PREFIX . "anchor_price_publication` WHERE store_id = '" . self::STORE_ID . "' AND status = 'published' AND published_at >= '" . $this->db->escape($start->format('Y-m-d H:i:s')) . "' AND published_at < '" . $this->db->escape($end->format('Y-m-d H:i:s')) . "'");
		$published = array();
		foreach ($query->rows as $row) {
			$published[] = $row['location_code'];
		}
		return array(
			'due' => (int)$now->format('Hi') >= 800,
			'published' => $published,
			'missing' => array_values(array_diff(array(self::PUBLICATION_LOCATION_CODE), $published))
		);
	}

	public function getPublication($publication_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "anchor_price_publication` WHERE publication_id = '" . (int)$publication_id . "' AND store_id = '" . self::STORE_ID . "' LIMIT 1");
		return $query->row;
	}

	public function getPublicationPath(array $publication) {
		$relative_path = isset($publication['relative_path']) ? str_replace('\\', '/', $publication['relative_path']) : '';
		if (!preg_match('~^anchor_price/[a-zA-Z0-9._-]+\.csv$~D', $relative_path)) {
			return false;
		}
		$base = realpath(DIR_DOWNLOAD);
		$path = realpath(rtrim(DIR_DOWNLOAD, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path));
		if (!$base || !$path || !is_file($path) || realpath(dirname($path)) !== $base . DIRECTORY_SEPARATOR . 'anchor_price') {
			return false;
		}
		return $path;
	}

	public function publicationFileIsValid(array $publication, $path = false) {
		$validated_path = $this->getPublicationPath($publication);
		if (!$validated_path || ($path !== false && realpath($path) !== $validated_path)) {
			return false;
		}
		$path = $validated_path;

		$expected_checksum = isset($publication['checksum_sha256']) ? strtolower(trim((string)$publication['checksum_sha256'])) : '';
		if (!$path || !is_readable($path) || !preg_match('/^[a-f0-9]{64}$/', $expected_checksum)) {
			return false;
		}

		$actual_checksum = hash_file('sha256', $path);
		if ($actual_checksum === false) {
			return false;
		}

		return function_exists('hash_equals') ? hash_equals($expected_checksum, strtolower($actual_checksum)) : $expected_checksum === strtolower($actual_checksum);
	}

	private function getCatalogLanguageId() {
		if ($this->catalog_language_id !== null) {
			return $this->catalog_language_id;
		}

		$code = $this->config->get('config_language');
		$query = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape($code) . "' LIMIT 1");
		$this->catalog_language_id = $query->num_rows ? (int)$query->row['language_id'] : (int)$this->config->get('config_language_id');
		return $this->catalog_language_id;
	}
}
