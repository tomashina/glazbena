<?php

if ($argc < 2) {
	fwrite(STDERR, "Usage: php scripts/openai_product_feed_audit.php <feed-url-or-file> [limit]\n");
	exit(2);
}

$source = $argv[1];
$limit = isset($argv[2]) ? max(1, (int)$argv[2]) : 100;
$document = readFeed($source);
if ($document === false || trim($document['body']) === '') {
	fwrite(STDERR, "Unable to read feed: " . $source . "\n");
	exit(1);
}

$required_fields = array('is_eligible_search', 'is_eligible_checkout', 'item_id', 'title', 'description', 'url', 'brand', 'image_url', 'price', 'availability', 'seller_name', 'seller_url', 'return_policy', 'target_countries', 'store_country');
$availability_values = array('in_stock', 'out_of_stock', 'pre_order', 'backorder', 'unknown');
$errors = array();
$warnings = array();
$seen_ids = array();
$checked = 0;
$line_number = 0;

if ($document['status'] >= 400) {
	$errors[] = 'Feed returned HTTP ' . $document['status'];
}
if ($document['content_type'] && stripos($document['content_type'], 'application/x-ndjson') !== 0 && stripos($document['content_type'], 'application/jsonl') !== 0) {
	$warnings[] = 'Unexpected Content-Type: ' . $document['content_type'];
}

$normalized_body = str_replace(array("\r\n", "\r"), "\n", $document['body']);
foreach (explode("\n", $normalized_body) as $line) {
	$line_number++;
	$line = trim($line);
	if ($line === '') {
		continue;
	}

	$record = json_decode($line, true);
	if (!is_array($record)) {
		$errors[] = 'Line ' . $line_number . ': invalid JSON: ' . json_last_error_msg();
		continue;
	}

	$checked++;
	$prefix = 'Line ' . $line_number . ' item ' . (isset($record['item_id']) ? $record['item_id'] : '#');

	foreach ($required_fields as $field) {
		if (!array_key_exists($field, $record) || $record[$field] === '' || $record[$field] === array()) {
			$errors[] = $prefix . ': missing ' . $field;
		}
	}
	foreach (array('is_eligible_search', 'is_eligible_checkout', 'accepts_returns', 'is_digital') as $field) {
		if (isset($record[$field]) && !in_array($record[$field], array('true', 'false'), true)) {
			$errors[] = $prefix . ': ' . $field . ' must be a lower-case true/false string';
		}
	}
	foreach (array('url', 'image_url', 'seller_url', 'return_policy', 'seller_privacy_policy', 'seller_tos') as $field) {
		if (!empty($record[$field]) && !preg_match('#^https://#i', (string)$record[$field])) {
			$errors[] = $prefix . ': ' . $field . ' must be an absolute HTTPS URL';
		}
	}
	foreach (array('price', 'sale_price') as $field) {
		if (!empty($record[$field]) && !preg_match('/^\d+(?:\.\d{2}) [A-Z]{3}$/', (string)$record[$field])) {
			$errors[] = $prefix . ': ' . $field . ' must look like 79.99 EUR';
		}
	}
	if (!empty($record['sale_price']) && priceNumber($record['sale_price']) > priceNumber($record['price'])) {
		$errors[] = $prefix . ': sale_price exceeds price';
	}
	if (!empty($record['availability']) && !in_array($record['availability'], $availability_values, true)) {
		$errors[] = $prefix . ': unsupported availability ' . $record['availability'];
	}
	if (isset($record['availability']) && $record['availability'] === 'pre_order' && empty($record['availability_date'])) {
		$errors[] = $prefix . ': availability_date is required for pre_order';
	}
	if (!empty($record['description']) && (preg_match('/<[^>]+>/', $record['description']) || preg_match('/&(?:nbsp|lt|gt|amp);/i', $record['description']))) {
		$errors[] = $prefix . ': description contains HTML or encoded HTML entities';
	}
	if (isset($record['title']) && utf8Length($record['title']) > 150) {
		$errors[] = $prefix . ': title exceeds 150 characters';
	}
	if (isset($record['description']) && utf8Length($record['description']) > 5000) {
		$errors[] = $prefix . ': description exceeds 5000 characters';
	}
	if (isset($record['brand']) && utf8Length($record['brand']) > 70) {
		$errors[] = $prefix . ': brand exceeds 70 characters';
	}
	if (!empty($record['image_url']) && !preg_match('/\.(jpe?g|png)(?:\?.*)?$/i', $record['image_url'])) {
		$errors[] = $prefix . ': image_url must use JPEG or PNG';
	}
	if (!empty($record['gtin']) && !isValidGtin((string)$record['gtin'])) {
		$errors[] = $prefix . ': gtin has an invalid length or check digit';
	}
	if (!empty($record['target_countries']) && (!is_array($record['target_countries']) || !preg_match('/^[A-Z]{2}$/', (string)$record['target_countries'][0]))) {
		$errors[] = $prefix . ': target_countries must be a list of ISO alpha-2 codes';
	}

	$item_id = isset($record['item_id']) ? (string)$record['item_id'] : '';
	if ($item_id !== '') {
		if (isset($seen_ids[$item_id])) {
			$errors[] = $prefix . ': duplicate item_id';
		}
		$seen_ids[$item_id] = true;
	}

	if ($checked >= $limit) {
		break;
	}
}

if ($checked === 0) {
	$errors[] = 'No JSONL records found.';
}
if ($warnings) {
	foreach ($warnings as $warning) {
		echo 'WARN: ' . $warning . "\n";
	}
}
if ($errors) {
	echo "FAIL\n";
	foreach ($errors as $error) {
		echo '- ' . $error . "\n";
	}
	exit(1);
}

echo 'OK: OpenAI Stable product feed fields are valid for ' . $checked . " records.\n";

function readFeed($source) {
	if (is_readable($source) && !preg_match('#^https?://#i', $source)) {
		return array('body' => file_get_contents($source), 'status' => 0, 'content_type' => '');
	}
	$ch = curl_init($source);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 120,
		CURLOPT_USERAGENT => 'GlazbenaKutija-OpenAIFeedAudit/1.0',
		CURLOPT_SSL_VERIFYPEER => strpos($source, '.test') === false
	));
	$body = curl_exec($ch);
	$result = array('body' => $body === false ? '' : $body, 'status' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), 'content_type' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
	curl_close($ch);
	return $body === false ? false : $result;
}

function priceNumber($price) {
	return (float)preg_replace('/\s+[A-Z]{3}$/', '', (string)$price);
}

function utf8Length($value) {
	return function_exists('mb_strlen') ? mb_strlen((string)$value, 'UTF-8') : strlen((string)$value);
}

function isValidGtin($gtin) {
	if (!preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $gtin)) {
		return false;
	}
	$sum = 0;
	for ($i = strlen($gtin) - 2, $weight = 3; $i >= 0; $i--, $weight = $weight === 3 ? 1 : 3) {
		$sum += (int)$gtin[$i] * $weight;
	}
	return (10 - ($sum % 10)) % 10 === (int)$gtin[strlen($gtin) - 1];
}
