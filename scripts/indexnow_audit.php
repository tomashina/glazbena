<?php

$base_url = isset($argv[1]) ? rtrim($argv[1], '/') : 'https://www.glazbena-kutija.hr';
$root = dirname(__DIR__);
$key = findIndexNowKey($root);

if (!$key) {
	fwrite(STDERR, "No IndexNow key file found in project root.\n");
	exit(1);
}

$key_url = $base_url . '/' . $key['file'];
$response = fetchUrl($key_url);

if ($response['status'] !== 200) {
	fwrite(STDERR, 'IndexNow key file returned HTTP ' . $response['status'] . ': ' . $key_url . "\n");
	exit(1);
}

if (trim($response['body']) !== $key['key']) {
	fwrite(STDERR, 'IndexNow key file body does not match expected key: ' . $key_url . "\n");
	exit(1);
}

echo 'OK: IndexNow key file is reachable: ' . $key_url . "\n";

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

function fetchUrl($url) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_USERAGENT => 'GlazbenaKutija-IndexNowAudit/1.0'
	));
	$body = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	return array('status' => $status, 'body' => $body === false ? '' : $body);
}
