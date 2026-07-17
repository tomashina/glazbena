<?php

$base_url = isset($argv[1]) ? rtrim($argv[1], '/') : 'https://www.glazbena-kutija.hr';
$with_crawlers = in_array('--with-crawlers', $argv, true);
$root = dirname(__DIR__);
$checks = array(
	array('llms.txt', array(PHP_BINARY, $root . '/scripts/llms_txt_audit.php', $base_url . '/llms.txt')),
	array('OpenAI feed', array(PHP_BINARY, $root . '/scripts/openai_product_feed_audit.php', $base_url . '/openai-products.jsonl?limit=100', '100')),
	array('Category ItemList', array(PHP_BINARY, $root . '/scripts/jsonld_audit.php', $base_url . '/gitare', 'ItemList')),
	array('OnlineStore policy', array(PHP_BINARY, $root . '/scripts/jsonld_audit.php', $base_url . '/', 'OnlineStore')),
	array('IndexNow key', array(PHP_BINARY, $root . '/scripts/indexnow_audit.php', $base_url))
);

$product_url = firstProductUrl($base_url . '/openai-products.jsonl?limit=1');
if ($product_url) {
	$checks[] = array('Product JSON-LD', array(PHP_BINARY, $root . '/scripts/jsonld_audit.php', $product_url, 'Product'));
} else {
	echo "FAIL: Could not resolve a product URL from the OpenAI feed.\n";
	exit(1);
}
if ($with_crawlers) {
	$checks[] = array('AI crawler access', array(PHP_BINARY, $root . '/scripts/ai_crawler_audit.php', $base_url));
}

$failures = 0;
foreach ($checks as $check) {
	echo "\n== " . $check[0] . " ==\n";
	$status = runCommand($check[1]);
	if ($status !== 0) {
		$failures++;
	}
}

if ($failures) {
	echo "\nFAIL: " . $failures . " regression check(s) failed.\n";
	exit(1);
}

echo "\nOK: All AI discovery regression checks passed.\n";

function runCommand(array $arguments) {
	$command = implode(' ', array_map('escapeshellarg', $arguments));
	passthru($command, $status);
	return $status;
}

function firstProductUrl($feed_url) {
	$ch = curl_init($feed_url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_USERAGENT => 'GlazbenaKutija-AiMonitor/1.0',
		CURLOPT_SSL_VERIFYPEER => strpos($feed_url, '.test') === false
	));
	$body = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($body === false || $status >= 400) {
		return null;
	}
	$line = strtok($body, "\n");
	$record = json_decode($line, true);
	return is_array($record) && !empty($record['url']) ? $record['url'] : null;
}
