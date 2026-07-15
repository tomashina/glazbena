<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script can only be run from the command line.\n");
	exit(1);
}

$root = dirname(__DIR__, 2);
require $root . '/config.php';

$database = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);

if ($database->connect_errno) {
	fwrite(STDERR, $database->connect_error . "\n");
	exit(1);
}

// Legacy OpenCart tables may still contain zero-date defaults. Keep this CLI
// maintenance connection compatible while indexes are added; application SQL
// mode is not changed.
$database->query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

// OpenCart 2 installations could contain prices assigned to the legacy group
// id 0. OpenCart 3 has no such customer group, so those prices would silently
// disappear after the upgrade. Move only orphaned group-0 records to the
// configured default group.
$default_group = $database->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = 'config_customer_group_id' LIMIT 1");
$default_group_id = $default_group && $default_group->num_rows ? (int)$default_group->fetch_assoc()['value'] : 0;
$legacy_group = $database->query("SELECT customer_group_id FROM `" . DB_PREFIX . "customer_group` WHERE customer_group_id = '0' LIMIT 1");

if ($default_group_id > 0 && (!$legacy_group || !$legacy_group->num_rows)) {
	foreach (array('product_special', 'product_discount', 'product_reward', 'product_recurring') as $group_table) {
		$full_group_table = DB_PREFIX . $group_table;
		$exists = $database->query("SHOW TABLES LIKE '" . $database->real_escape_string($full_group_table) . "'");

		if ($exists && $exists->num_rows) {
			$database->query("UPDATE `" . $full_group_table . "` SET customer_group_id = '" . $default_group_id . "' WHERE customer_group_id = '0'");

			if ($database->affected_rows) {
				echo "Migrated " . $database->affected_rows . " orphaned customer-group rows in " . $full_group_table . "\n";
			}
		}
	}
}

$indexes = array(
	'product' => array(
		'idx_catalog_status_date_sort' => array('status', 'date_available', 'sort_order', 'product_id'),
		'idx_catalog_manufacturer' => array('manufacturer_id', 'status', 'date_available', 'product_id')
	),
	'category_path' => array(
		'idx_path_category' => array('path_id', 'category_id')
	),
	'product_special' => array(
		'idx_active_special' => array('product_id', 'customer_group_id', 'date_start', 'date_end', 'priority', 'price')
	),
	'product_discount' => array(
		'idx_active_discount' => array('product_id', 'customer_group_id', 'quantity', 'date_start', 'date_end', 'priority', 'price')
	),
	'product_image' => array(
		'idx_product_sort' => array('product_id', 'sort_order', 'product_image_id')
	),
	'review' => array(
		'idx_product_status_rating' => array('product_id', 'status', 'rating')
	),
	'seo_url' => array(
		'idx_query_store_language' => array('query(191)', 'store_id', 'language_id'),
		'idx_keyword_store_language' => array('keyword(191)', 'store_id', 'language_id')
	)
);

$changed = 0;

foreach ($indexes as $table => $table_indexes) {
	$full_table = DB_PREFIX . $table;
	$exists = $database->query("SHOW TABLES LIKE '" . $database->real_escape_string($full_table) . "'");

	if (!$exists || !$exists->num_rows) {
		echo "Skipping missing table: " . $full_table . "\n";
		continue;
	}

	$existing = array();
	$result = $database->query("SHOW INDEX FROM `" . $full_table . "`");

	while ($row = $result->fetch_assoc()) {
		$existing[$row['Key_name']] = true;
	}

	foreach ($table_indexes as $index_name => $columns) {
		if (isset($existing[$index_name])) {
			echo "Index already present: " . $full_table . '.' . $index_name . "\n";
			continue;
		}

		$quoted_columns = array_map(function ($column) {
			if (!preg_match('/^([a-z0-9_]+)(\([0-9]+\))?$/i', $column, $matches)) {
				throw new RuntimeException('Unsafe index column: ' . $column);
			}

			return '`' . $matches[1] . '`' . (isset($matches[2]) ? $matches[2] : '');
		}, $columns);

		$database->query("ALTER TABLE `" . $full_table . "` ADD INDEX `" . $index_name . "` (" . implode(',', $quoted_columns) . ")");
		echo "Added index: " . $full_table . '.' . $index_name . "\n";
		$changed++;
	}

	$database->query("ANALYZE TABLE `" . $full_table . "`");
}

echo "Database optimization complete; indexes added: " . $changed . "\n";
