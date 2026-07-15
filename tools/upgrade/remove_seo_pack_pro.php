<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script can only be run from the command line.\n");
	exit(1);
}

require dirname(__DIR__, 2) . '/config.php';

$database = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);

if ($database->connect_errno) {
	fwrite(STDERR, $database->connect_error . "\n");
	exit(1);
}

$routes = array(
	'extension/extension/autolinks',
	'extension/extension/bot_report',
	'extension/extension/canonicals',
	'extension/extension/clickfix',
	'extension/extension/custom_alt_generator',
	'extension/extension/custom_h1_generator',
	'extension/extension/custom_h2_generator',
	'extension/extension/custom_imgtitle_generator',
	'extension/extension/custom_title_generator',
	'extension/extension/extendedseo',
	'extension/extension/keywords_generator',
	'extension/extension/l',
	'extension/extension/meta_description_generator',
	'extension/extension/mlseo',
	'extension/extension/not_found_report',
	'extension/extension/redirect',
	'extension/extension/rename_files',
	'extension/extension/richsnippets',
	'extension/extension/rp_generator',
	'extension/extension/seoedit',
	'extension/extension/seoeditor',
	'extension/extension/seoimages',
	'extension/extension/seopack',
	'extension/extension/seopagination',
	'extension/extension/seoreplacer',
	'extension/extension/seoreport',
	'extension/extension/table_edit_ajax',
	'extension/extension/tag_generator'
);

$setting_codes = array(
	'autolinks', 'canonicals', 'clickfix', 'extendedseo', 'l', 'mlseo',
	'richsnippets', 'seoimages', 'seopack', 'seopagination', 'seoreplacer'
);

try {
	$database->begin_transaction();

	$statement = $database->prepare(
		"DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = ? OR `name` = ?"
	);
	$identity = 'Opencart SEO Pack PRO';
	$statement->bind_param('ss', $identity, $identity);
	$statement->execute();
	$removed_modifications = $statement->affected_rows;

	$placeholders = implode(',', array_fill(0, count($setting_codes), '?'));
	$types = str_repeat('s', count($setting_codes));
	$statement = $database->prepare(
		"DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` IN (" . $placeholders . ")"
	);
	$statement->bind_param($types, ...$setting_codes);
	$statement->execute();
	$removed_settings = $statement->affected_rows;

	$query = $database->query("SELECT `user_group_id`, `permission` FROM `" . DB_PREFIX . "user_group`");

	while ($group = $query->fetch_assoc()) {
		$permissions = json_decode($group['permission'], true);

		if (!is_array($permissions)) {
			continue;
		}

		$changed = false;

		foreach (array('access', 'modify') as $type) {
			if (!isset($permissions[$type]) || !is_array($permissions[$type])) {
				continue;
			}

			$filtered = array_values(array_diff($permissions[$type], $routes));
			$changed = $changed || count($filtered) !== count($permissions[$type]);
			$permissions[$type] = $filtered;
		}

		if ($changed) {
			$permission = json_encode($permissions);
			$user_group_id = (int)$group['user_group_id'];
			$statement = $database->prepare(
				"UPDATE `" . DB_PREFIX . "user_group` SET `permission` = ? WHERE `user_group_id` = ?"
			);
			$statement->bind_param('si', $permission, $user_group_id);
			$statement->execute();
		}
	}

	$database->commit();

	echo "SEO Pack PRO removed: " . $removed_modifications . " modification(s), " .
		$removed_settings . " setting(s).\n";
} catch (Throwable $exception) {
	$database->rollback();
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}
