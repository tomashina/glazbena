<?php

if ($argc < 3) {
	fwrite(STDERR, "Usage: php scripts/jsonld_audit.php <url> <Product|ItemList|OnlineStore>\n");
	exit(2);
}

$url = $argv[1];
$required_type = $argv[2];
$html = fetchHtml($url);
if ($html === false) {
	fwrite(STDERR, "Unable to fetch URL: " . $url . "\n");
	exit(1);
}

preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#si', $html, $matches);
$nodes = array();
$errors = array();

foreach ($matches[1] as $index => $json) {
	$data = json_decode(html_entity_decode($json, ENT_QUOTES, 'UTF-8'), true);
	if (!is_array($data)) {
		$errors[] = 'JSON-LD block ' . ($index + 1) . ' is invalid: ' . json_last_error_msg();
		continue;
	}
	foreach (isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : array($data) as $node) {
		if (is_array($node)) {
			$nodes[] = $node;
		}
	}
}

$found = null;
foreach ($nodes as $node) {
	$type = isset($node['@type']) ? $node['@type'] : null;
	if ($type === $required_type || (is_array($type) && in_array($required_type, $type, true))) {
		$found = $node;
		break;
	}
}

if (!$found) {
	$errors[] = 'Missing JSON-LD type ' . $required_type;
} elseif ($required_type === 'Product') {
	foreach (array('name', 'url', 'image', 'offers') as $field) {
		if (empty($found[$field])) {
			$errors[] = 'Product is missing ' . $field;
		}
	}
	foreach (array('price', 'priceCurrency', 'availability', 'url') as $field) {
		if (empty($found['offers'][$field])) {
			$errors[] = 'Product Offer is missing ' . $field;
		}
	}
} elseif ($required_type === 'ItemList') {
	if (empty($found['itemListElement']) || !is_array($found['itemListElement'])) {
		$errors[] = 'ItemList has no itemListElement entries';
	}
} elseif ($required_type === 'OnlineStore') {
	foreach (array('name', 'url', 'logo', 'contactPoint', 'hasMerchantReturnPolicy') as $field) {
		if (empty($found[$field])) {
			$errors[] = 'OnlineStore is missing ' . $field;
		}
	}
}

if ($errors) {
	echo "FAIL\n";
	foreach ($errors as $error) {
		echo '- ' . $error . "\n";
	}
	exit(1);
}

echo 'OK: Valid ' . $required_type . ' JSON-LD found at ' . $url . "\n";

function fetchHtml($url) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_USERAGENT => 'GlazbenaKutija-JsonLdAudit/1.0',
		CURLOPT_SSL_VERIFYPEER => strpos($url, '.test') === false
	));
	$body = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $body === false || $status >= 400 ? false : $body;
}
