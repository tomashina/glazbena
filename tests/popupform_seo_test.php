<?php

$baseUrl = rtrim(getenv('POPUPFORM_TEST_BASE_URL') ?: 'https://glazbena.test', '/');

if (!function_exists('curl_init')) {
	echo "SKIP: cURL extension is unavailable.\n";
	exit(0);
}

function popupformRequest($url, $ajax = false) {
	$headers = array();
	$curl = curl_init($url);

	curl_setopt_array($curl, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 20,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_HTTPHEADER => $ajax ? array('X-Requested-With: XMLHttpRequest') : array(),
		CURLOPT_HEADERFUNCTION => function($curl, $line) use (&$headers) {
			$length = strlen($line);
			$line = trim($line);

			if ($line === '' || stripos($line, 'HTTP/') === 0) {
				return $length;
			}

			$separator = strpos($line, ':');

			if ($separator !== false) {
				$name = strtolower(trim(substr($line, 0, $separator)));
				$headers[$name] = trim(substr($line, $separator + 1));
			}

			return $length;
		}
	));

	$body = curl_exec($curl);
	$error = curl_error($curl);
	$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);

	return array(
		'status' => $status,
		'headers' => $headers,
		'body' => $body === false ? '' : $body,
		'error' => $error
	);
}

function popupformAssert($condition, $message) {
	global $failures;

	if (!$condition) {
		$failures[] = $message;
	}
}

function popupformHeader(array $response, $name) {
	$name = strtolower($name);
	return isset($response['headers'][$name]) ? $response['headers'][$name] : '';
}

$probe = popupformRequest($baseUrl . '/zoom-h1essential-rucni-snimac');

if ($probe['status'] === 0 || $probe['error'] !== '') {
	echo "SKIP: storefront is unavailable at " . $baseUrl . ".\n";
	exit(0);
}

$failures = array();
$products = array(
	array(
		'id' => 15627,
		'form_id' => 2,
		'name' => 'Zoom H1essential ručni snimač',
		'slug' => 'zoom-h1essential-rucni-snimac'
	),
	array(
		'id' => 15628,
		'form_id' => 1,
		'name' => 'Zoom H4essential ručni snimač',
		'slug' => 'zoom-h4essential-rucni-snimac'
	)
);

foreach ($products as $product) {
	$popupUrl = $baseUrl . '/index.php?route=extension/popupform/popupformpage&form_id=' .
		$product['form_id'] . '&product_id=' . $product['id'];
	$direct = popupformRequest($popupUrl);
	$location = popupformHeader($direct, 'location');

	popupformAssert($direct['status'] === 301, $product['name'] . ' direct popup request must return 301.');
	popupformAssert(
		parse_url($location, PHP_URL_PATH) === '/' . $product['slug'],
		$product['name'] . ' direct popup request must redirect to the product URL.'
	);
	popupformAssert(
		strpos($direct['body'], 'modal-dialog') === false,
		$product['name'] . ' direct popup response must not expose the modal fragment.'
	);

	$ajax = popupformRequest($popupUrl, true);

	popupformAssert($ajax['status'] === 200, $product['name'] . ' AJAX popup request must return 200.');
	popupformAssert(
		strtolower(popupformHeader($ajax, 'x-robots-tag')) === 'noindex, nofollow',
		$product['name'] . ' AJAX popup response must return X-Robots-Tag: noindex, nofollow.'
	);
	popupformAssert(
		strpos($ajax['body'], '<h4 class="modal-title">' . $product['name'] . '</h4>') !== false,
		$product['name'] . ' AJAX popup response must contain the correct modal title.'
	);
	popupformAssert(
		preg_match('/name="product_id"\s+value="' . $product['id'] . '"/', $ajax['body']) === 1,
		$product['name'] . ' AJAX popup response must retain the product ID.'
	);
	popupformAssert(
		preg_match('/name="form_id"\s+value="' . $product['form_id'] . '"/', $ajax['body']) === 1,
		$product['name'] . ' AJAX popup response must retain the form ID.'
	);

	$page = popupformRequest($baseUrl . '/' . $product['slug']);

	popupformAssert($page['status'] === 200, $product['name'] . ' product page must return 200.');
	popupformAssert(
		preg_match('/<a\b[^>]*href=["\'][^"\']*popupformpage/i', $page['body']) === 0,
		$product['name'] . ' product page must not expose the popup endpoint in an anchor href.'
	);
	popupformAssert(
		preg_match('/<button\b[^>]*data-popup-url=["\'][^"\']*popupformpage/i', $page['body']) === 1,
		$product['name'] . ' product page must expose the popup endpoint only as button data.'
	);
}

$invalidProduct = popupformRequest(
	$baseUrl . '/index.php?route=extension/popupform/popupformpage&form_id=2&product_id=0',
	true
);
$invalidForm = popupformRequest(
	$baseUrl . '/index.php?route=extension/popupform/popupformpage&form_id=999999&product_id=15627',
	true
);

foreach (array('invalid product' => $invalidProduct, 'invalid form' => $invalidForm) as $label => $response) {
	popupformAssert($response['status'] === 404, 'AJAX request with ' . $label . ' must return 404.');
	popupformAssert(
		strpos(strtolower(popupformHeader($response, 'x-robots-tag')), 'noindex') !== false,
		'AJAX request with ' . $label . ' must return a noindex header.'
	);
}

if ($failures) {
	foreach ($failures as $failure) {
		fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
	}

	exit(1);
}

echo "Popup form SEO tests passed.\n";
