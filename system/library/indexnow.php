<?php
class Indexnow {
	private $registry;
	private $queue_table_ready = false;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	public function queueEntity($entity_type, $entity_id, $action = 'updated') {
		try {
			$entries = $this->resolveEntityEntries((string)$entity_type, (int)$entity_id, (string)$action);

			if (!$entries) {
				return;
			}

			$this->ensureQueueTable();

			foreach ($entries as $entry) {
				$this->enqueueUrl($entry['url'], $entry['entity_type'], $entry['entity_id'], $entry['action']);
			}
		} catch (Exception $e) {
			$this->writeLog('IndexNow queue error: ' . $e->getMessage());
		}
	}

	private function resolveEntityEntries($entity_type, $entity_id, $action) {
		$action = $this->normalizeAction($action);
		$entries = array();

		if ($entity_type === 'product') {
			foreach ($this->resolveSeoUrls('product_id=' . $entity_id, 'product/product', 'product_id=' . $entity_id) as $url) {
				$entries[] = $this->entry($url, 'product', $entity_id, $action);
			}

			$query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . $entity_id . "'");
			foreach ($query->rows as $row) {
				foreach ($this->resolveSeoUrls('category_id=' . (int)$row['category_id'], 'product/category', 'path=' . (int)$row['category_id']) as $url) {
					$entries[] = $this->entry($url, 'category', (int)$row['category_id'], 'updated');
				}
			}

			$query = $this->db->query("SELECT manufacturer_id FROM " . DB_PREFIX . "product WHERE product_id = '" . $entity_id . "' AND manufacturer_id > 0");
			if ($query->num_rows) {
				foreach ($this->resolveSeoUrls('manufacturer_id=' . (int)$query->row['manufacturer_id'], 'product/manufacturer/info', 'manufacturer_id=' . (int)$query->row['manufacturer_id']) as $url) {
					$entries[] = $this->entry($url, 'manufacturer', (int)$query->row['manufacturer_id'], 'updated');
				}
			}
		} elseif ($entity_type === 'category') {
			foreach ($this->resolveSeoUrls('category_id=' . $entity_id, 'product/category', 'path=' . $entity_id) as $url) {
				$entries[] = $this->entry($url, 'category', $entity_id, $action);
			}
		} elseif ($entity_type === 'manufacturer') {
			foreach ($this->resolveSeoUrls('manufacturer_id=' . $entity_id, 'product/manufacturer/info', 'manufacturer_id=' . $entity_id) as $url) {
				$entries[] = $this->entry($url, 'manufacturer', $entity_id, $action);
			}
		} elseif ($entity_type === 'information') {
			foreach ($this->resolveSeoUrls('information_id=' . $entity_id, 'information/information', 'information_id=' . $entity_id) as $url) {
				$entries[] = $this->entry($url, 'information', $entity_id, $action);
			}
		}

		return $this->dedupeEntries($entries);
	}

	private function entry($url, $entity_type, $entity_id, $action) {
		return array(
			'url' => $url,
			'entity_type' => $entity_type,
			'entity_id' => (int)$entity_id,
			'action' => $this->normalizeAction($action)
		);
	}

	private function dedupeEntries(array $entries) {
		$seen = array();
		$unique = array();

		foreach ($entries as $entry) {
			$key = $entry['url'] . '|' . $entry['action'];
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$unique[] = $entry;
		}

		return $unique;
	}

	private function resolveSeoUrls($query_string, $fallback_route, $fallback_query) {
		$urls = array();
		$query = $this->db->query("SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE query = '" . $this->db->escape($query_string) . "' AND keyword <> '' ORDER BY store_id ASC, language_id ASC");

		foreach ($query->rows as $row) {
			$urls[] = $this->absoluteUrl($row['keyword']);
		}

		if (!$urls) {
			$urls[] = $this->absoluteUrl('index.php?route=' . $fallback_route . '&' . $fallback_query);
		}

		return array_values(array_unique($urls));
	}

	private function absoluteUrl($path) {
		$path = trim((string)$path);

		if (preg_match('#^https?://#i', $path)) {
			return $path;
		}

		return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
	}

	private function getBaseUrl() {
		if (defined('HTTPS_CATALOG')) {
			$base_url = HTTPS_CATALOG;
		} elseif (defined('HTTP_CATALOG')) {
			$base_url = HTTP_CATALOG;
		} elseif (defined('HTTPS_SERVER')) {
			$base_url = HTTPS_SERVER;
		} else {
			$base_url = HTTP_SERVER;
		}

		return rtrim($base_url, '/') . '/';
	}

	private function enqueueUrl($url, $entity_type, $entity_id, $action) {
		$url = trim((string)$url);

		if ($url === '') {
			return;
		}

		$action = $this->normalizeAction($action);
		$existing = $this->db->query("SELECT indexnow_queue_id FROM " . DB_PREFIX . "indexnow_queue WHERE url = '" . $this->db->escape($url) . "' AND status = 'pending' LIMIT 1");

		if ($existing->num_rows) {
			$this->db->query("UPDATE " . DB_PREFIX . "indexnow_queue SET entity_type = '" . $this->db->escape($entity_type) . "', entity_id = '" . (int)$entity_id . "', action = '" . $this->db->escape($action) . "', available_at = NOW(), updated_at = NOW() WHERE indexnow_queue_id = '" . (int)$existing->row['indexnow_queue_id'] . "'");
		} else {
			$this->db->query("INSERT INTO " . DB_PREFIX . "indexnow_queue SET url = '" . $this->db->escape($url) . "', entity_type = '" . $this->db->escape($entity_type) . "', entity_id = '" . (int)$entity_id . "', action = '" . $this->db->escape($action) . "', status = 'pending', attempts = '0', available_at = NOW(), created_at = NOW(), updated_at = NOW()");
		}
	}

	private function ensureQueueTable() {
		if ($this->queue_table_ready) {
			return;
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "indexnow_queue` (
			`indexnow_queue_id` int(11) NOT NULL AUTO_INCREMENT,
			`url` varchar(2048) NOT NULL,
			`entity_type` varchar(32) NOT NULL DEFAULT '',
			`entity_id` int(11) NOT NULL DEFAULT 0,
			`action` varchar(16) NOT NULL DEFAULT 'updated',
			`status` varchar(16) NOT NULL DEFAULT 'pending',
			`attempts` int(11) NOT NULL DEFAULT 0,
			`last_status_code` int(11) DEFAULT NULL,
			`last_error` text,
			`available_at` datetime NOT NULL,
			`submitted_at` datetime DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`updated_at` datetime NOT NULL,
			PRIMARY KEY (`indexnow_queue_id`),
			KEY `indexnow_status_available` (`status`, `available_at`),
			KEY `indexnow_entity` (`entity_type`, `entity_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->queue_table_ready = true;
	}

	private function normalizeAction($action) {
		return $action === 'deleted' ? 'deleted' : 'updated';
	}

	private function writeLog($message) {
		if ($this->registry && $this->registry->has('log')) {
			$this->log->write($message);
		}
	}
}
