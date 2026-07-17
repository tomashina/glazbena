<?php

$options = parseOptions($argv);
$root = dirname(__DIR__);
require $root . '/config.php';

$key = findIndexNowKey($root);
if (!$key) {
	fwrite(STDERR, "No IndexNow key file found in project root.\n");
	exit(1);
}

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? DB_PORT : 3306);
if ($db->connect_errno) {
	fwrite(STDERR, "Database connection failed.\n");
	exit(1);
}
$db->set_charset('utf8mb4');
ensureQueueTable($db);

$rows = getPendingRows($db, $options['limit'], $options['retry_failed']);
if (!$rows) {
	echo "OK: No pending IndexNow URLs.\n";
	exit(0);
}

$urls = array_values(array_unique(array_column($rows, 'url')));
$host = parse_url($options['base_url'], PHP_URL_HOST);
$payload = array(
	'host' => $host,
	'key' => $key['key'],
	'keyLocation' => $options['base_url'] . '/' . $key['file'],
	'urlList' => $urls
);

if ($options['dry_run']) {
	echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
	echo 'DRY RUN: ' . count($urls) . " URL(s) would be submitted.\n";
	exit(0);
}

$response = submitIndexNow($options['endpoint'], $payload);
$ids = array_map('intval', array_column($rows, 'indexnow_queue_id'));

if (in_array($response['status'], array(200, 202), true)) {
	markSubmitted($db, $ids, $response['status']);
	echo 'OK: Submitted ' . count($urls) . ' unique URLs to IndexNow. HTTP ' . $response['status'] . ".\n";
	exit(0);
}

markFailed($db, $rows, $response);
fwrite(STDERR, 'FAIL: IndexNow returned HTTP ' . $response['status'] . '. ' . trim($response['error'] ?: $response['body']) . "\n");
exit(1);

function parseOptions($argv) {
	$options = array(
		'base_url' => 'https://www.glazbena-kutija.hr',
		'limit' => 1000,
		'dry_run' => false,
		'retry_failed' => false,
		'endpoint' => 'https://api.indexnow.org/indexnow'
	);

	foreach (array_slice($argv, 1) as $argument) {
		if (strpos($argument, '--limit=') === 0) {
			$options['limit'] = max(1, min(10000, (int)substr($argument, 8)));
		} elseif ($argument === '--dry-run') {
			$options['dry_run'] = true;
		} elseif ($argument === '--retry-failed') {
			$options['retry_failed'] = true;
		} elseif (strpos($argument, '--endpoint=') === 0) {
			$options['endpoint'] = substr($argument, 11);
		} elseif (preg_match('#^https?://#i', $argument)) {
			$options['base_url'] = rtrim($argument, '/');
		} else {
			fwrite(STDERR, "Usage: php scripts/indexnow_submit.php [base-url] [--limit=1000] [--dry-run] [--retry-failed] [--endpoint=https://api.indexnow.org/indexnow]\n");
			exit(2);
		}
	}

	return $options;
}

function findIndexNowKey($root) {
	foreach (glob($root . '/*.txt') as $file) {
		$name = basename($file, '.txt');
		if (!preg_match('/^[a-f0-9]{32}$/i', $name)) {
			continue;
		}
		$key = trim((string)file_get_contents($file));
		if (hash_equals(strtolower($name), strtolower($key))) {
			return array('file' => basename($file), 'key' => $key);
		}
	}

	return null;
}

function ensureQueueTable($db) {
	$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "indexnow_queue` (
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
}

function getPendingRows($db, $limit, $retry_failed) {
	$status_sql = $retry_failed ? "status IN ('pending','failed')" : "status = 'pending'";
	$result = $db->query("SELECT * FROM `" . DB_PREFIX . "indexnow_queue` WHERE " . $status_sql . " AND available_at <= NOW() ORDER BY created_at ASC LIMIT " . (int)$limit);

	return $result ? $result->fetch_all(MYSQLI_ASSOC) : array();
}

function submitIndexNow($endpoint, array $payload) {
	$ch = curl_init($endpoint);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_HTTPHEADER => array('Content-Type: application/json; charset=utf-8'),
		CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_USERAGENT => 'GlazbenaKutija-IndexNowSubmit/1.0'
	));
	$body = curl_exec($ch);
	$error = curl_error($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	return array('status' => $status, 'body' => $body === false ? '' : $body, 'error' => $error);
}

function markSubmitted($db, array $ids, $status_code) {
	if (!$ids) {
		return;
	}
	$db->query("UPDATE `" . DB_PREFIX . "indexnow_queue` SET status='submitted', attempts=attempts+1, last_status_code='" . (int)$status_code . "', last_error='', submitted_at=NOW(), updated_at=NOW() WHERE indexnow_queue_id IN (" . implode(',', $ids) . ")");
}

function markFailed($db, array $rows, array $response) {
	$error = trim($response['error'] ?: $response['body']);
	if ($error === '') {
		$error = 'IndexNow request failed';
	}

	foreach ($rows as $row) {
		$attempts = (int)$row['attempts'] + 1;
		$status = $attempts >= 5 ? 'failed' : 'pending';
		$delay = min(1440, (int)pow(2, min($attempts, 8)) * 5);
		$db->query("UPDATE `" . DB_PREFIX . "indexnow_queue` SET status='" . $status . "', attempts='" . $attempts . "', last_status_code='" . (int)$response['status'] . "', last_error='" . $db->real_escape_string(substr($error, 0, 2000)) . "', available_at=DATE_ADD(NOW(), INTERVAL " . $delay . " MINUTE), updated_at=NOW() WHERE indexnow_queue_id='" . (int)$row['indexnow_queue_id'] . "'");
	}
}
