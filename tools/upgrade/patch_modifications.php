<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script can only be run from the command line.\n");
	exit(1);
}

require dirname(__DIR__, 2) . '/config.php';

$refresh = in_array('--refresh', $argv, true);
$session_id = null;
$database = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);

if ($database->connect_errno) {
	fwrite(STDERR, $database->connect_error . "\n");
	exit(1);
}

$table = DB_PREFIX . 'modification';

$patchModification = function ($code, $name, array $replacements) use ($database, $table) {
	$statement = $database->prepare(
		"SELECT `modification_id`, `xml` FROM `" . $table . "` WHERE `code` = ? AND `name` = ? LIMIT 1"
	);
	$statement->bind_param('ss', $code, $name);
	$statement->execute();
	$query = $statement->get_result();

	if (!$query || !$query->num_rows) {
		throw new RuntimeException('Modification not found: ' . $name . ' (' . $code . ')');
	}

	$modification = $query->fetch_assoc();
	$modification_id = (int)$modification['modification_id'];
	$xml = $modification['xml'];

	foreach ($replacements as $replacement) {
		if (strpos($xml, $replacement['replace']) !== false) {
			continue;
		}

		$count = 0;
		$xml = str_replace($replacement['search'], $replacement['replace'], $xml, $count);

		if ($count !== $replacement['expected']) {
			throw new RuntimeException(
				'Modification ' . $modification_id . ' pattern count mismatch: expected ' .
				$replacement['expected'] . ', found ' . $count
			);
		}
	}

	$statement = $database->prepare("UPDATE `" . $table . "` SET `xml` = ? WHERE `modification_id` = ?");
	$statement->bind_param('si', $xml, $modification_id);
	$statement->execute();
};

$removeDuplicateFileModification = function ($code) use ($database, $table) {
	$statement = $database->prepare("DELETE FROM `" . $table . "` WHERE `code` = ?");
	$statement->bind_param('s', $code);
	$statement->execute();
};

try {
	// The pre-upgrade shop already used language-specific manufacturer
	// descriptions. Keep the migration idempotent for installations where
	// the legacy table is missing or only partially present.
	$manufacturer_description_table = DB_PREFIX . 'manufacturer_description';
	$database->query(
		"CREATE TABLE IF NOT EXISTS `" . $manufacturer_description_table . "` (" .
		"`manufacturer_id` int NOT NULL, " .
		"`language_id` int NOT NULL, " .
		"`description` text NOT NULL, " .
		"`meta_description` varchar(255) NOT NULL DEFAULT '', " .
		"`meta_keyword` varchar(255) NOT NULL DEFAULT '', " .
		"`custom_title` varchar(255) NOT NULL DEFAULT '', " .
		"PRIMARY KEY (`manufacturer_id`, `language_id`)" .
		") ENGINE=MyISAM DEFAULT CHARSET=utf8"
	);

	$manufacturer_description_columns = array();
	$column_query = $database->query("SHOW COLUMNS FROM `" . $manufacturer_description_table . "`");

	while ($column = $column_query->fetch_assoc()) {
		$manufacturer_description_columns[$column['Field']] = true;
	}

	$missing_manufacturer_columns = array(
		'description'      => "text NOT NULL",
		'meta_description' => "varchar(255) NOT NULL DEFAULT ''",
		'meta_keyword'     => "varchar(255) NOT NULL DEFAULT ''",
		'custom_title'     => "varchar(255) NOT NULL DEFAULT ''"
	);

	foreach ($missing_manufacturer_columns as $column_name => $definition) {
		if (!isset($manufacturer_description_columns[$column_name])) {
			$database->query(
				"ALTER TABLE `" . $manufacturer_description_table . "` " .
				"ADD `" . $column_name . "` " . $definition
			);
		}
	}

	// OpenCart automatically loads system/*.ocmod.xml files. A database copy
	// would apply the same operations twice on every OCMOD refresh.
	$removeDuplicateFileModification('custom_fields');

	// Attribute Preset accessed a missing preset_id key while saving products
	// that do not use presets. PHP 8 turns that into output that breaks AJAX.
	// OpenCart 3.0.5.0 also added an inline style to the attribute-name field;
	// make the extension's exact OCMOD search match the upgraded template.
	$patchModification('StvAttrPreset', 'Attributes Preset Values', array(
		array(
			'search' => "count(\$product_attribute['preset_id'])",
			'replace' => "count(\$product_attribute['preset_id'] ?? array())",
			'expected' => 4
		),
		array(
			'search' => '<td class="text-left" style="width: 40%;"><input type="text" name="product_attribute[{{ attribute_row }}][name]" value="{{ product_attribute.name }}" placeholder="{{ entry_attribute }}" class="form-control"/> <input type="hidden" name="product_attribute[{{ attribute_row }}][attribute_id]" value="{{ product_attribute.attribute_id }}"/></td>',
			'replace' => '<td class="text-left" style="width: 40%;"><input type="text" name="product_attribute[{{ attribute_row }}][name]" value="{{ product_attribute.name }}" placeholder="{{ entry_attribute }}" class="form-control" style="position:relative;"/> <input type="hidden" name="product_attribute[{{ attribute_row }}][attribute_id]" value="{{ product_attribute.attribute_id }}"/></td>',
			'expected' => 1
		)
	));

	// Products Image Manager uses jQuery's removed event.props collection.
	// The JS file contains the compatibility fix; this version bump forces
	// browsers and reverse proxies to fetch it after every deployment.
	$patchModification('sf_products_image_manager', '|<b>SF</b>| Products Image Manager', array(
		array(
			'search' => 'pim.js?v=3.0.7.1',
			'replace' => 'pim.js?v=3.0.7.2',
			'expected' => 2
		)
	));

	// TMD Bundle Product runs after other SEO modifications which split the
	// original condition across lines. Match the stable end of the condition.
	$patchModification('#TOOC-2.2.x_2.3.x', 'TMD Bundle Product', array(
		array(
			'search' => "<![CDATA[&& \$url[0] != 'product_id']]>",
			'replace' => "<![CDATA[ != 'product_id') {]]>",
			'expected' => 1
		),
		array(
			'search' => "&& \$url[0] != 'product_id' && \$url[0] != 'bundle_id' && \$url[0] != 'bundle_category_id'",
			'replace' => " != 'product_id' && \$url[0] != 'bundle_id' && \$url[0] != 'bundle_category_id') {",
			'expected' => 1
		)
	));

	// Basel assumes every product has a special end-date record. PHP 8 no
	// longer permits indexing the false value returned for regular products.
	$patchModification('basel_theme', 'Basel Theme', array(
		array(
			'search' => "\$date_end['date_end']",
			'replace' => "(\$date_end ? \$date_end['date_end'] : '')",
			'expected' => 1
		)
	));

	// Product variants are grouped by an exact identifier (MPN, SKU, EAN,
	// etc.). Do not send that identifier through the full catalogue search,
	// which uses multiple leading-wildcard LIKE conditions.
	$patchModification('advanced_product_variants', 'Advanced Product Variants', array(
		array(
			'search' => "\$products = \$this->model_catalog_product->getProducts(\$filters);",
			'replace' => "\$products = \$this->model_catalog_product->getProductsByConfiguredVariant(\$filters['filter_name']);",
			'expected' => 3
		)
	));

	// The legacy Meta Pixel OCMOD printed tracking code directly in <head>.
	// Keep its e-commerce events, but expose the markup inertly until the
	// visitor grants marketing consent through the custom cookie manager.
	$patchModification('javv_meta_pixel_events', 'JAVV Meta Pixel Events', array(
		array(
			'search' => "\$data['javv_meta_pixel'] = \$this->load->controller('extension/module/javv_meta_pixel');",
			'replace' => "\$data['javv_meta_pixel_encoded'] = base64_encode(\$this->load->controller('extension/module/javv_meta_pixel'));",
			'expected' => 1
		),
		array(
			'search' => "{% if javv_meta_pixel %}\n{{ javv_meta_pixel }}\n{% endif %}",
			'replace' => "{% if javv_meta_pixel_encoded %}\n<script>window.gkMetaPixelEncoded='{{ javv_meta_pixel_encoded }}';</script>\n{% endif %}",
			'expected' => 1
		)
	));

	if ($refresh) {
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

		$admin_url = rtrim(HTTP_SERVER, '/') .
			'/admin/index.php?route=marketplace/modification/refresh&user_token=' . $user_token;
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
		$database->query(
			"DELETE FROM `" . DB_PREFIX . "session` WHERE `session_id` = '" .
			$database->real_escape_string($session_id) . "'"
		);
		$session_id = null;

		if ($refresh_body === false || $refresh_code !== 200) {
			throw new RuntimeException('OCMOD refresh failed with HTTP ' . $refresh_code);
		}

		if (
			stripos($refresh_body, 'name="username"') !== false ||
			stripos($refresh_body, 'route=common/login') !== false
		) {
			throw new RuntimeException('OCMOD refresh was redirected to the admin login page.');
		}

		foreach (glob(DIR_CACHE . '*') as $cache_file) {
			if (is_file($cache_file)) {
				unlink($cache_file);
			}
		}
	}
} catch (Throwable $exception) {
	if ($session_id !== null) {
		$database->query(
			"DELETE FROM `" . DB_PREFIX . "session` WHERE `session_id` = '" .
			$database->real_escape_string($session_id) . "'"
		);
	}

	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}

echo "Upgrade modification patches applied.\n";

if ($refresh) {
	echo "OCMOD and application caches refreshed.\n";
}
