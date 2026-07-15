<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script can only be run from the command line.\n");
	exit(1);
}

$root = dirname(__DIR__, 2);
require $root . '/config.php';

if (!chdir($root)) {
	fwrite(STDERR, "Could not enter project root: " . $root . "\n");
	exit(1);
}

$options = getopt('', array('base-url:', 'backup-dir:', 'confirm:'));

if (($options['confirm'] ?? '') !== 'UPGRADE-3.0.5.0') {
	fwrite(STDERR, "Refusing to run without --confirm=UPGRADE-3.0.5.0\n");
	exit(1);
}

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
	fwrite(STDERR, "PHP 8.1 or newer is required. Current: " . PHP_VERSION . "\n");
	exit(1);
}

$base_url = rtrim($options['base-url'] ?? HTTP_SERVER, '/') . '/';
$backup_dir = $options['backup-dir'] ?? ($root . '/upgrade-backups');
$unlock_file = $root . '/system/storage/upgrade-unlocked';
$timestamp = gmdate('Ymd-His');
$backup_file = rtrim($backup_dir, '/') . '/glazbena-pre-3050-' . $timestamp . '.sql';
$status_file = rtrim($backup_dir, '/') . '/modification-status-' . $timestamp . '.json';
$cookie_file = null;
$session_id = null;

if (!is_dir($backup_dir) && !mkdir($backup_dir, 0700, true)) {
	fwrite(STDERR, "Could not create backup directory: " . $backup_dir . "\n");
	exit(1);
}

$database = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);

if ($database->connect_errno) {
	fwrite(STDERR, $database->connect_error . "\n");
	exit(1);
}

$run = function (array $command, array $environment = array(), $stdout_file = null) {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => $stdout_file ? array('file', $stdout_file, 'w') : array('pipe', 'w'),
		2 => array('pipe', 'w')
	);

	$process = proc_open($command, $descriptors, $pipes, null, $environment ?: null);

	if (!is_resource($process)) {
		throw new RuntimeException('Could not start: ' . implode(' ', $command));
	}

	fclose($pipes[0]);
	$output = $stdout_file ? '' : stream_get_contents($pipes[1]);

	if (!$stdout_file) {
		fclose($pipes[1]);
	}

	$error = stream_get_contents($pipes[2]);
	fclose($pipes[2]);
	$code = proc_close($process);

	if ($code !== 0) {
		throw new RuntimeException(trim($error) ?: ('Command failed with exit code ' . $code));
	}

	return $output;
};

$findExecutable = function ($name) use ($run) {
	$output = trim($run(array('/bin/sh', '-c', 'command -v ' . escapeshellarg($name))));

	if ($output === '') {
		throw new RuntimeException('Required executable not found: ' . $name);
	}

	return $output;
};

$httpPost = function ($url, $cookie_file) {
	$curl = curl_init($url);
	curl_setopt_array($curl, array(
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_COOKIEJAR => $cookie_file,
		CURLOPT_COOKIEFILE => $cookie_file,
		CURLOPT_TIMEOUT => 300
	));
	$body = curl_exec($curl);
	$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	$error = curl_error($curl);
	curl_close($curl);

	if ($body === false || $code !== 200) {
		throw new RuntimeException('Upgrade HTTP request failed (' . $code . '): ' . $error);
	}

	return $body;
};

$httpGet = function ($url, $cookie_file) {
	$curl = curl_init($url);
	curl_setopt_array($curl, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_COOKIEJAR => $cookie_file,
		CURLOPT_COOKIEFILE => $cookie_file,
		CURLOPT_TIMEOUT => 300
	));
	$body = curl_exec($curl);
	$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	$error = curl_error($curl);
	curl_close($curl);

	if ($body === false || $code !== 200) {
		throw new RuntimeException('Upgrade initialization failed (' . $code . '): ' . $error);
	}

	return $body;
};

$maintenance_query = $database->query(
	"SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' " .
	"AND `code` = 'config' AND `key` = 'config_maintenance' LIMIT 1"
);
$original_maintenance = $maintenance_query && $maintenance_query->num_rows
	? (int)$maintenance_query->fetch_assoc()['value']
	: 0;

try {
	$mysqldump = $findExecutable('mysqldump');
	$composer = $findExecutable('composer');

	echo "Backing up current database to " . $backup_file . "\n";
	$run(array(
		$mysqldump,
		'--host=' . DB_HOSTNAME,
		'--port=' . DB_PORT,
		'--user=' . DB_USERNAME,
		'--single-transaction',
		'--quick',
		'--default-character-set=utf8mb4',
		DB_DATABASE
	), array('MYSQL_PWD' => DB_PASSWORD), $backup_file);

	$statuses = array();
	$status_query = $database->query("SELECT `modification_id`, `code`, `status` FROM `" . DB_PREFIX . "modification`");

	while ($row = $status_query->fetch_assoc()) {
		$statuses[] = $row;
	}

	if (file_put_contents($status_file, json_encode($statuses, JSON_PRETTY_PRINT)) === false) {
		throw new RuntimeException('Could not write modification status backup.');
	}

	$database->query(
		"UPDATE `" . DB_PREFIX . "setting` SET `value` = '1' WHERE `store_id` = '0' " .
		"AND `code` = 'config' AND `key` = 'config_maintenance'"
	);

	if (file_put_contents($unlock_file, $timestamp) === false) {
		throw new RuntimeException('Could not unlock the web upgrader.');
	}

	$cookie_file = tempnam(sys_get_temp_dir(), 'oc-upgrade-cookie-');
	$httpGet($base_url . 'install/', $cookie_file);

	for ($step = 1; $step <= 11; $step++) {
		$body = $httpPost($base_url . 'install/index.php?route=upgrade/upgrade/next&step=' . $step, $cookie_file);
		$json = json_decode($body, true);

		if (!is_array($json) || isset($json['error'])) {
			throw new RuntimeException('Upgrade step ' . $step . ' failed: ' . ($json['error'] ?? $body));
		}

		echo ($json['success'] ?? ('Upgrade step ' . $step . ' completed')) . "\n";
	}

	echo "Restoring Composer dependencies removed by the final stock patch.\n";
	$run(array($composer, 'install', '--no-dev', '--no-interaction', '--prefer-dist'));

	$body = $httpPost($base_url . 'install/index.php?route=upgrade/upgrade/next&step=12', $cookie_file);
	$json = json_decode($body, true);

	if (!is_array($json) || isset($json['error'])) {
		throw new RuntimeException('Upgrade completion check failed: ' . ($json['error'] ?? $body));
	}

	$run(array(PHP_BINARY, $root . '/tools/upgrade/patch_modifications.php'));
	$run(array(PHP_BINARY, $root . '/tools/upgrade/remove_seo_pack_pro.php'));

	foreach ($statuses as $status) {
		$enabled = (int)$status['status'];
		$statement = $database->prepare(
			"UPDATE `" . DB_PREFIX . "modification` SET `status` = ? WHERE `modification_id` = ?"
		);
		$modification_id = (int)$status['modification_id'];
		$statement->bind_param('ii', $enabled, $modification_id);
		$statement->execute();
	}

	// Create a short-lived super-admin session solely to invoke OpenCart's own
	// modification refresh routine, then remove it immediately.
	$user_query = $database->query(
		"SELECT `user_id` FROM `" . DB_PREFIX . "user` WHERE `status` = '1' " .
		"AND `user_group_id` = '1' ORDER BY `user_id` LIMIT 1"
	);

	if (!$user_query || !$user_query->num_rows) {
		throw new RuntimeException('No active super-admin user available for OCMOD refresh.');
	}

	$session_id = bin2hex(random_bytes(16));
	$user_token = bin2hex(random_bytes(16));
	$user_id = (int)$user_query->fetch_assoc()['user_id'];
	$session_data = json_encode(array('user_id' => $user_id, 'user_token' => $user_token));
	$statement = $database->prepare(
		"REPLACE INTO `" . DB_PREFIX . "session` SET `session_id` = ?, `data` = ?, " .
		"`expire` = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)"
	);
	$statement->bind_param('ss', $session_id, $session_data);
	$statement->execute();

	$admin_url = $base_url . 'admin/index.php?route=marketplace/modification/refresh&user_token=' . $user_token;
	$curl = curl_init($admin_url);
	curl_setopt_array($curl, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_COOKIE => 'OCSESSID=' . $session_id,
		CURLOPT_TIMEOUT => 300
	));
	$refresh_body = curl_exec($curl);
	$refresh_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);
	$database->query("DELETE FROM `" . DB_PREFIX . "session` WHERE `session_id` = '" . $database->real_escape_string($session_id) . "'");
	$session_id = null;

	if ($refresh_body === false || $refresh_code !== 200) {
		throw new RuntimeException('OCMOD refresh failed with HTTP ' . $refresh_code);
	}

	if (stripos($refresh_body, 'name="username"') !== false || stripos($refresh_body, 'route=common/login') !== false) {
		throw new RuntimeException('OCMOD refresh was redirected to the admin login page.');
	}

	foreach (glob(DIR_CACHE . '*') as $cache_file) {
		if (is_file($cache_file)) {
			unlink($cache_file);
		}
	}

	$database->query(
		"UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $original_maintenance . "' " .
		"WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_maintenance'"
	);

	@unlink($unlock_file);
	@unlink($cookie_file);

	echo "OpenCart 3.0.5.0 deployment completed.\n";
	echo "Database backup: " . $backup_file . "\n";
	echo "Modification status backup: " . $status_file . "\n";
} catch (Throwable $exception) {
	@unlink($unlock_file);

	if ($session_id !== null) {
		$database->query("DELETE FROM `" . DB_PREFIX . "session` WHERE `session_id` = '" . $database->real_escape_string($session_id) . "'");
	}

	if ($cookie_file !== null) {
		@unlink($cookie_file);
	}

	fwrite(STDERR, "DEPLOYMENT FAILED: " . $exception->getMessage() . "\n");
	fwrite(STDERR, "Maintenance mode remains enabled. Backup: " . $backup_file . "\n");
	exit(1);
}
