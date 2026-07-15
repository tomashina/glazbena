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

try {
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
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}

echo "Upgrade modification patches applied.\n";
