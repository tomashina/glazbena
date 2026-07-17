<?php

$source = isset($argv[1]) ? $argv[1] : 'https://www.glazbena-kutija.hr/openai-products.jsonl?limit=1000';
$content = readSource($source);
if ($content === false || trim($content) === '') {
	fwrite(STDERR, "Unable to read feed.\n");
	exit(1);
}

$required = array('is_eligible_search', 'is_eligible_checkout', 'item_id', 'title', 'description', 'url', 'brand', 'image_url', 'price', 'availability', 'seller_name', 'seller_url', 'return_policy', 'target_countries', 'store_country');
$optional = array('gtin', 'mpn', 'product_category', 'additional_image_urls', 'sale_price', 'pricing_trend', 'review_count', 'star_rating', 'weight', 'dimensions');
$coverage = array_fill_keys(array_merge($required, $optional), 0);
$records = 0;
$ids = array();
$invalid = 0;

$normalized = str_replace(array("\r\n", "\r"), "\n", $content);
foreach (explode("\n", $normalized) as $line) {
	if (trim($line) === '') {
		continue;
	}
	$record = json_decode($line, true);
	if (!is_array($record)) {
		$invalid++;
		continue;
	}
	$records++;
	if (isset($record['item_id'])) {
		$ids[(string)$record['item_id']] = true;
	}
	foreach ($coverage as $field => $unused) {
		if (isset($record[$field]) && $record[$field] !== '' && $record[$field] !== array()) {
			$coverage[$field]++;
		}
	}
}

echo "OpenAI merchant readiness\n";
echo "Source: " . $source . "\n";
echo "Records: " . $records . "\n";
echo "Unique item_id: " . count($ids) . "\n";
echo "Feed size bucket: 0-1M\n";
echo "Invalid JSON lines: " . $invalid . "\n\n";

echo "Required field coverage\n";
foreach ($required as $field) {
	echo str_pad($field, 28) . coverage($coverage[$field], $records) . "\n";
}
echo "\nOptional field coverage\n";
foreach ($optional as $field) {
	echo str_pad($field, 28) . coverage($coverage[$field], $records) . "\n";
}

$ready = $records > 0 && count($ids) === $records && $invalid === 0;
foreach ($required as $field) {
	if ($coverage[$field] !== $records) {
		$ready = false;
	}
}

echo "\n" . ($ready ? 'OK: Feed sample is ready for merchant onboarding review.' : 'FAIL: Required coverage or uniqueness is incomplete.') . "\n";
exit($ready ? 0 : 1);

function coverage($count, $total) {
	$percent = $total > 0 ? ($count / $total) * 100 : 0;
	return $count . '/' . $total . ' (' . number_format($percent, 1) . '%)';
}

function readSource($source) {
	if (is_readable($source) && !preg_match('#^https?://#i', $source)) {
		return file_get_contents($source);
	}
	$ch = curl_init($source);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 300,
		CURLOPT_USERAGENT => 'GlazbenaKutija-MerchantReadiness/1.0',
		CURLOPT_SSL_VERIFYPEER => strpos($source, '.test') === false
	));
	$body = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $body === false || $status >= 400 ? false : $body;
}
