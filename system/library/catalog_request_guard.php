<?php
/**
 * Bound expensive public catalogue variants before OpenCart opens the database.
 * Ordinary visits and shopping routes do not enter this guard.
 */
class CatalogRequestGuard {
	const BURST = 30;
	const TOKENS_PER_SECOND = 1;
	const SHARDS = 256;
	const MAX_SHARD_ENTRIES = 128;
	const MAX_SHARD_BYTES = 32768;
	const IDLE_SECONDS = 60;
	const CONCURRENT_REQUESTS = 3;

	private $directory;
	private $active_handle;
	private $shutdown_registered = false;

	public function __construct($directory) {
		$this->directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
	}

	/** Return zero to continue, or the Retry-After seconds for a 429 response. */
	public function evaluate(array &$query, array $server, $now = null) {
		if (!$this->isCatalogVariantRequest($query, $server)) {
			return 0;
		}

		$this->normalizeParameters($query);
		$address = isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : '';
		if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_IP)) {
			return 0;
		}

		if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
			return 0;
		}

		$now = $now === null ? microtime(true) : (float)$now;
		$retry_after = $this->consumeToken($address, $now);
		if ($retry_after > 0) {
			return $retry_after;
		}

		return $this->acquireRequestSlot();
	}

	public function isCatalogVariantRequest(array $query, array $server) {
		$method = isset($server['REQUEST_METHOD']) ? strtoupper((string)$server['REQUEST_METHOD']) : '';
		if ($method !== 'GET' && $method !== 'HEAD') {
			return false;
		}

		$has_variant = false;
		foreach (array('sort', 'order', 'limit', 'page') as $parameter) {
			if (array_key_exists($parameter, $query)) {
				$has_variant = true;
				break;
			}
		}
		if (!$has_variant) {
			return false;
		}

		// SEO URLs acquire their actual route later, after database startup.
		if (!array_key_exists('route', $query) || $query['route'] === '') {
			return true;
		}
		if (!is_string($query['route'])) {
			return false;
		}

		$route = trim($query['route'], '/');
		$routes = array(
			'product/product', 'product/category', 'product/search', 'product/manufacturer',
			'product/manufacturer/info', 'product/manufacturer_info', 'product/special',
			'extension/module/digitalElephantFilter',
			'extension/module/digital_elephant_filter/show_more'
		);
		return in_array($route, $routes, true)
			|| (substr($route, -6) === '/index' && in_array(substr($route, 0, -6), $routes, true));
	}

	public function normalizeParameters(array &$query) {
		foreach (array('limit' => 100, 'page' => 10000) as $parameter => $maximum) {
			if (!array_key_exists($parameter, $query)) {
				continue;
			}
			$value = $query[$parameter];
			if ((!is_string($value) && !is_int($value)) || !preg_match('/^[+-]?[0-9]+$/D', (string)$value)) {
				unset($query[$parameter]);
			} else {
				$query[$parameter] = max(1, min($maximum, (int)$value));
			}
		}

		if (array_key_exists('sort', $query)) {
			$sorts = array('pd.name', 'p.model', 'p.quantity', 'p.price', 'ps.price', 'rating', 'p.sort_order', 'p.date_added');
			if (!is_string($query['sort']) || !in_array($query['sort'], $sorts, true)) {
				unset($query['sort']);
			}
		}
		if (array_key_exists('order', $query)) {
			$order = is_string($query['order']) ? strtoupper($query['order']) : '';
			if ($order === 'ASC' || $order === 'DESC') {
				$query['order'] = $order;
			} else {
				unset($query['order']);
			}
		}
	}

	private function consumeToken($address, $now) {
		$key = hash('sha256', inet_pton($address));
		$path = $this->directory . 'bucket-' . substr($key, 0, 2) . '.json';
		$handle = @fopen($path, 'c+b');
		if (!$handle) {
			return 0;
		}
		if (!@flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);
			return 0;
		}

		$bytes = stream_get_contents($handle, self::MAX_SHARD_BYTES + 1);
		$records = is_string($bytes) && strlen($bytes) <= self::MAX_SHARD_BYTES ? json_decode($bytes, true) : null;
		$records = is_array($records) ? $records : array();
		foreach ($records as $record_key => $record) {
			if (!is_array($record) || !isset($record['time'], $record['tokens'])
				|| !is_numeric($record['time']) || !is_numeric($record['tokens'])
				|| $record['time'] < $now - self::IDLE_SECONDS) {
				unset($records[$record_key]);
			}
		}

		$record = isset($records[$key]) ? $records[$key] : array('time' => $now, 'tokens' => self::BURST);
		$tokens = min(self::BURST, max(0, (float)$record['tokens']) + max(0, $now - (float)$record['time']) * self::TOKENS_PER_SECOND);
		if ($tokens < 1) {
			flock($handle, LOCK_UN);
			fclose($handle);
			return max(1, (int)ceil((1 - $tokens) / self::TOKENS_PER_SECOND));
		}

		unset($records[$key]);
		$records[$key] = array('time' => $now, 'tokens' => $tokens - 1);
		// Insertion order is last use order, so eviction keeps recent addresses.
		while (count($records) > self::MAX_SHARD_ENTRIES) {
			array_shift($records);
		}
		$payload = json_encode($records);
		if ($payload !== false && strlen($payload) <= self::MAX_SHARD_BYTES) {
			rewind($handle);
			if (@ftruncate($handle, 0)) {
				@fwrite($handle, $payload);
				@fflush($handle);
			}
		}
		flock($handle, LOCK_UN);
		fclose($handle);
		return 0;
	}

	private function acquireRequestSlot() {
		if (is_resource($this->active_handle)) {
			return 0;
		}
		for ($slot = 0; $slot < self::CONCURRENT_REQUESTS; $slot++) {
			$handle = @fopen($this->directory . 'active-' . $slot . '.lock', 'c+b');
			if (!$handle) {
				return 0;
			}
			if (@flock($handle, LOCK_EX | LOCK_NB)) {
				$this->active_handle = $handle;
				if (!$this->shutdown_registered) {
					register_shutdown_function(array($this, 'release'));
					$this->shutdown_registered = true;
				}
				return 0;
			}
			fclose($handle);
		}
		return 2;
	}

	public function release() {
		if (is_resource($this->active_handle)) {
			flock($this->active_handle, LOCK_UN);
			fclose($this->active_handle);
		}
		$this->active_handle = null;
	}
}
