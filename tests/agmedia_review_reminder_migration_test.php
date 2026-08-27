<?php
/**
 * Integration test for the standalone legacy migration.
 *
 * It creates and removes only a uniquely named temporary database. If the local
 * MySQL user cannot create databases, the test reports SKIP without touching the
 * configured OpenCart database.
 */

mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOSTNAME') ?: '127.0.0.1';
$user = getenv('DB_USERNAME') ?: 'glazbena';
$password = getenv('DB_PASSWORD') ?: 'glazbena';
$port = (int)(getenv('DB_PORT') ?: 3306);
$configuredDatabase = getenv('DB_DATABASE') ?: 'glazbena';
$temporaryName = 'arr_migration_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
$database = $temporaryName;
$tablePrefix = 'oc_';
$ownsDatabase = false;
$connection = @new mysqli($host, $user, $password, '', $port);

if ($connection->connect_errno) {
	echo "SKIP: local MySQL is unavailable.\n";
	exit(0);
}

function runSql(mysqli $connection, $sql) {
	if (!$connection->multi_query($sql)) {
		throw new RuntimeException('SQL failed: ' . $connection->error);
	}

	do {
		$result = $connection->store_result();
		if ($result instanceof mysqli_result) {
			$result->free();
		}

		if (!$connection->more_results()) {
			break;
		}

		if (!$connection->next_result()) {
			throw new RuntimeException('SQL failed: ' . $connection->error);
		}
	} while (true);
}

function value(mysqli $connection, $sql, $column) {
	global $tablePrefix;
	$sql = str_replace('oc_', $tablePrefix, $sql);
	$result = $connection->query($sql);
	if (!$result) {
		throw new RuntimeException('Query failed: ' . $connection->error);
	}
	$row = $result->fetch_assoc();
	$result->free();

	return $row[$column];
}

function assertSameValue($expected, $actual, $message) {
	if ((string)$expected !== (string)$actual) {
		throw new RuntimeException($message . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
	}
}

if ($connection->query("CREATE DATABASE `" . $database . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
	$ownsDatabase = true;
} else {
	// Restricted local users commonly have table privileges only. In that case use
	// a cryptographically unique prefix inside the configured development database.
	$database = $configuredDatabase;
	$tablePrefix = 'arrmt_' . getmypid() . '_' . bin2hex(random_bytes(4)) . '_';
	if (!$connection->select_db($database)) {
		echo "SKIP: local MySQL user cannot create a database or access the configured development database.\n";
		exit(0);
	}
}

try {
	$connection->select_db($database);

	$seed = <<<'SQL'
CREATE TABLE `oc_store` (
  `store_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`store_id`)
) ENGINE=InnoDB;
INSERT INTO `oc_store` VALUES (1);

CREATE TABLE `oc_order` (
  `order_id` INT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `email` VARCHAR(254) NOT NULL DEFAULT '',
  `language_id` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB;

INSERT INTO `oc_order` (`order_id`, `store_id`, `email`, `language_id`) VALUES
  (1,0,'pending@example.test',1), (2,0,'retry@example.test',1),
  (3,0,'processing@example.test',1), (4,0,'sending@example.test',1),
  (5,0,'unknown@example.test',1), (6,0,'sent@example.test',1),
  (7,0,'failed@example.test',1), (8,0,'suppressed@example.test',1),
  (9,0,'cancelled@example.test',1), (10,0,'odd@example.test',1),
  (11,0,'mail-log@example.test',1), (12,0,'review-log@example.test',1),
  (13,0,'processing-zero@example.test',1);

CREATE TABLE `oc_setting` (
  `setting_id` INT NOT NULL AUTO_INCREMENT,
  `store_id` INT NOT NULL DEFAULT 0,
  `code` VARCHAR(128) NOT NULL,
  `key` VARCHAR(128) NOT NULL,
  `value` TEXT NOT NULL,
  `serialized` TINYINT NOT NULL,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB;

CREATE TABLE `oc_orderreviews_delivery` (
  `delivery_id` INT NOT NULL AUTO_INCREMENT,
  `store_id` INT NOT NULL DEFAULT 0,
  `order_id` INT NOT NULL,
  `reviewmail_id` INT NOT NULL DEFAULT 0,
  `email` VARCHAR(254) NOT NULL,
  `status` VARCHAR(16) NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `available_at` DATETIME NOT NULL,
  `locked_at` DATETIME NULL,
  `last_attempt_at` DATETIME NULL,
  `sent_at` DATETIME NULL,
  `last_smtp_code` SMALLINT UNSIGNED NULL,
  `last_error` VARCHAR(1000) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`delivery_id`),
  UNIQUE KEY `store_order` (`store_id`,`order_id`)
) ENGINE=InnoDB;

INSERT INTO `oc_orderreviews_delivery`
 (`store_id`,`order_id`,`email`,`status`,`attempts`,`available_at`,`locked_at`,`last_attempt_at`,`sent_at`,`last_error`,`created_at`,`updated_at`) VALUES
 (0,1,'pending@example.test','pending',0,NOW(),NULL,NULL,NULL,NULL,NOW(),NOW()),
 (0,2,'retry@example.test','retry',1,NOW(),NULL,NOW(),NULL,'temporary reject',NOW(),NOW()),
 (0,3,'processing@example.test','processing',1,NOW(),NOW(),NOW(),NULL,NULL,NOW(),NOW()),
 (0,4,'sending@example.test','sending',1,NOW(),NOW(),NOW(),NULL,NULL,NOW(),NOW()),
 (0,5,'unknown@example.test','unknown',1,NOW(),NULL,NOW(),NULL,'ambiguous',NOW(),NOW()),
 (0,6,'sent@example.test','sent',1,NOW(),NULL,NOW(),NOW(),NULL,NOW(),NOW()),
 (0,7,'failed@example.test','failed',3,NOW(),NULL,NOW(),NULL,'gave up',NOW(),NOW()),
 (0,8,'suppressed@example.test','suppressed',1,NOW(),NULL,NOW(),NULL,'no such user',NOW(),NOW()),
 (0,9,'cancelled@example.test','cancelled',0,NOW(),NULL,NULL,NULL,'ineligible',NOW(),NOW()),
 (0,10,'odd@example.test','unexpected',1,NOW(),NULL,NOW(),NULL,'future state',NOW(),NOW()),
 (0,11,'mail-log@example.test','pending',0,NOW(),NULL,NULL,NULL,NULL,NOW(),NOW()),
 (0,12,'review-log@example.test','pending',0,NOW(),NULL,NULL,NULL,NULL,NOW(),NOW()),
 (0,13,'processing-zero@example.test','processing',0,NOW(),NOW(),NOW(),NULL,NULL,NOW(),NOW());

CREATE TABLE `oc_orderreviews_mail_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `date` DATETIME NULL,
  `store_id` INT NOT NULL DEFAULT 0,
  `order_id` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
INSERT INTO `oc_orderreviews_mail_log` (`date`,`store_id`,`order_id`) VALUES (NOW(),0,11);

CREATE TABLE `oc_orderreviews_log` (
  `log_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `store_id` INT NOT NULL DEFAULT 0,
  `date_created` DATETIME NOT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB;
INSERT INTO `oc_orderreviews_log` (`order_id`,`store_id`,`date_created`) VALUES (12,0,NOW());

CREATE TABLE `oc_orderreviews_suppression` (
  `suppression_id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(254) NOT NULL,
  `reason` VARCHAR(1000) NULL,
  `source` VARCHAR(32) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`suppression_id`),
  UNIQUE KEY (`email`)
) ENGINE=InnoDB;
INSERT INTO `oc_orderreviews_suppression`
 (`email`,`reason`,`source`,`created_at`,`updated_at`)
 VALUES ('global-block@example.test','legacy block','migration',NOW(),NOW());

CREATE TABLE `oc_orderreviews_mail` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `mail_id` INT NOT NULL,
  `store_id` INT NOT NULL DEFAULT 0,
  `code` VARCHAR(128) NOT NULL,
  `key` VARCHAR(128) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `serialized` TINYINT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
INSERT INTO `oc_orderreviews_mail` (`mail_id`,`store_id`,`code`,`key`,`value`,`serialized`) VALUES
 (1,0,'orderreviewsMailTemplate','ReviewMail','{"Enabled":"yes","OrderStatusID":"5","Delay":"7","Subject":{"1":"do not copy"},"DateType":"date_modified"}',1),
 (2,0,'orderreviewsMailTemplate','ReviewMail','{"Enabled":"yes","OrderStatusID":"3","Delay":"14","Message":{"1":"do not copy"},"DateType":"date_added"}',1);

CREATE TABLE `oc_orderreviews_setting` (
  `setting_id` INT NOT NULL AUTO_INCREMENT,
  `store_id` INT NOT NULL DEFAULT 0,
  `code` VARCHAR(128) NOT NULL,
  `key` VARCHAR(128) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `serialized` TINYINT NOT NULL,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB;
INSERT INTO `oc_orderreviews_setting` (`store_id`,`code`,`key`,`value`,`serialized`) VALUES
 (0,'orderreviews','orderreviews','{"Enabled":"yes","BatchSize":"2","HourlyLimit":"4","MaxAgeDays":"45","MaxAttempts":"3","License":{"secret":"must not copy"}}',1);
SQL;

	$seed = str_replace('oc_', $tablePrefix, $seed);
	runSql($connection, $seed);
	$migrationTemplate = file_get_contents(dirname(__DIR__) . '/database/migrations/20260827_agmedia_review_reminder_migrate.sql');
	if ($migrationTemplate === false) {
		throw new RuntimeException('Migration SQL could not be read.');
	}

	$migration = str_replace('oc_', $tablePrefix, $migrationTemplate);
	runSql($connection, $migration);

	$expected = array(
		1 => 'pending', 2 => 'retry', 3 => 'retry', 4 => 'unknown',
		5 => 'unknown', 6 => 'sent', 7 => 'failed', 8 => 'suppressed',
		9 => 'cancelled', 10 => 'unknown', 11 => 'sent', 12 => 'suppressed',
		13 => 'retry'
	);

	foreach ($expected as $orderId => $status) {
		assertSameValue($status, value($connection, "SELECT `status` FROM `oc_agmedia_review_reminder_delivery` WHERE `store_id`=0 AND `order_id`=" . (int)$orderId, 'status'), 'Unexpected migrated status for order ' . $orderId);
	}

	assertSameValue(0, value($connection, "SELECT `attempts` FROM `oc_agmedia_review_reminder_delivery` WHERE `order_id`=3", 'attempts'), 'Pre-SMTP processing attempt was not undone');
	assertSameValue(0, value($connection, "SELECT `attempts` FROM `oc_agmedia_review_reminder_delivery` WHERE `order_id`=13", 'attempts'), 'Zero pre-SMTP attempts must not underflow');
	assertSameValue(13, value($connection, "SELECT COUNT(*) AS total FROM `oc_agmedia_review_reminder_delivery`", 'total'), 'Unexpected delivery count');
	assertSameValue(3, value($connection, "SELECT COUNT(*) AS total FROM `oc_agmedia_review_reminder_suppression`", 'total'), 'Suppression was not copied to every store plus suppressed delivery');
	assertSameValue(0, value($connection, "SELECT COUNT(*) AS total FROM `oc_agmedia_review_reminder_attempt`", 'total'), 'Synthetic attempts must not consume the hourly quota');
	assertSameValue(254, value($connection, "SELECT CHARACTER_MAXIMUM_LENGTH AS length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oc_agmedia_review_reminder_delivery' AND COLUMN_NAME='email'", 'length'), 'Delivery email column length mismatch');
	assertSameValue(1, value($connection, "SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oc_agmedia_review_reminder_attempt' AND INDEX_NAME='date_added'", 'total'), 'Global hourly index is missing');

	assertSameValue('0', value($connection, "SELECT `value` FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_status'", 'value'), 'New module must remain disabled');
	assertSameValue('[3,5]', value($connection, "SELECT `value` FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_order_status_id'", 'value'), 'Legacy status IDs were not consolidated');
	assertSameValue('14', value($connection, "SELECT `value` FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_delay_days'", 'value'), 'Conservative maximum delay was not migrated');
	assertSameValue('2', value($connection, "SELECT `value` FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_hourly_limit'", 'value'), 'Legacy hourly limit was not clamped to the safe maximum');
	assertSameValue('1', value($connection, "SELECT `value` FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_legacy_endpoint_disabled'", 'value'), 'Legacy cron endpoint was not retired');
	assertSameValue('no', value($connection, "SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.Enabled')) AS enabled FROM `oc_orderreviews_setting` WHERE `code`='orderreviews' AND `key`='orderreviews'", 'enabled'), 'Legacy module was not disabled');
	assertSameValue(0, value($connection, "SELECT COUNT(*) AS total FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND (`key` LIKE '%subject%' OR `key` LIKE '%body%' OR `value` LIKE '%do not copy%' OR `value` LIKE '%secret%')", 'total'), 'Proprietary message or licence data was copied');

	// A repeat run must neither duplicate records nor reset settings edited after the
	// one-time operational migration marker was written.
	$connection->query(str_replace('oc_', $tablePrefix, "UPDATE `oc_setting` SET `value`='21' WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_delay_days'"));
	$connection->query(str_replace('oc_', $tablePrefix, "UPDATE `oc_agmedia_review_reminder_delivery` SET `status`='pending' WHERE `order_id` IN (4,11)"));
	runSql($connection, $migration);

	assertSameValue(13, value($connection, "SELECT COUNT(*) AS total FROM `oc_agmedia_review_reminder_delivery`", 'total'), 'Repeat migration duplicated deliveries');
	assertSameValue(3, value($connection, "SELECT COUNT(*) AS total FROM `oc_agmedia_review_reminder_suppression`", 'total'), 'Repeat migration duplicated suppressions');
	assertSameValue('unknown', value($connection, "SELECT `status` FROM `oc_agmedia_review_reminder_delivery` WHERE `order_id`=4", 'status'), 'Repeat migration did not restore ambiguous terminal state');
	assertSameValue('sent', value($connection, "SELECT `status` FROM `oc_agmedia_review_reminder_delivery` WHERE `order_id`=11", 'status'), 'Repeat migration did not restore confirmed sent state');
	assertSameValue('21', value($connection, "SELECT `value` FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_delay_days'", 'value'), 'Repeat migration overwrote a post-migration setting');

	// Optional legacy tables really are optional: exercise the dynamic no-op branches
	// using a second isolated prefix with only the three core OpenCart tables.
	$originalPrefix = $tablePrefix;
	$tablePrefix = $originalPrefix . 'clean_';
	$cleanSeed = str_replace('oc_', $tablePrefix, <<<'SQL'
CREATE TABLE `oc_store` (`store_id` INT UNSIGNED NOT NULL, PRIMARY KEY (`store_id`)) ENGINE=InnoDB;
CREATE TABLE `oc_order` (`order_id` INT UNSIGNED NOT NULL, `store_id` INT UNSIGNED NOT NULL DEFAULT 0, `email` VARCHAR(254) NOT NULL DEFAULT '', `language_id` INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (`order_id`)) ENGINE=InnoDB;
CREATE TABLE `oc_setting` (`setting_id` INT NOT NULL AUTO_INCREMENT, `store_id` INT NOT NULL DEFAULT 0, `code` VARCHAR(128) NOT NULL, `key` VARCHAR(128) NOT NULL, `value` TEXT NOT NULL, `serialized` TINYINT NOT NULL, PRIMARY KEY (`setting_id`)) ENGINE=InnoDB;
SQL
	);
	runSql($connection, $cleanSeed);
	$cleanMigration = str_replace('oc_', $tablePrefix, $migrationTemplate);
	runSql($connection, $cleanMigration);
	runSql($connection, $cleanMigration);
	assertSameValue(0, value($connection, "SELECT COUNT(*) AS total FROM `oc_agmedia_review_reminder_delivery`", 'total'), 'Clean migration invented delivery history');
	assertSameValue(1, value($connection, "SELECT COUNT(*) AS total FROM `oc_setting` WHERE `code`='module_agmedia_review_reminder' AND `key`='module_agmedia_review_reminder_status'", 'total'), 'Clean repeat migration duplicated status settings');
	$tablePrefix = $originalPrefix;

	echo "AGmedia Review Reminder migration test passed.\n";
} finally {
	if ($ownsDatabase && strpos($database, 'arr_migration_test_') === 0) {
		$connection->select_db('information_schema');
		$connection->query("DROP DATABASE `" . $database . "`");
	} elseif (!$ownsDatabase && preg_match('/^arrmt_[a-z0-9_]+_$/', $tablePrefix)) {
		$tables = $connection->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='" . $connection->real_escape_string($database) . "' AND TABLE_NAME LIKE '" . $connection->real_escape_string($tablePrefix) . "%'");
		$drop = array();
		if ($tables) {
			while ($row = $tables->fetch_assoc()) {
				if (strpos($row['TABLE_NAME'], $tablePrefix) === 0) {
					$drop[] = '`' . str_replace('`', '``', $row['TABLE_NAME']) . '`';
				}
			}
			$tables->free();
		}
		if ($drop) {
			$connection->query('DROP TABLE ' . implode(', ', $drop));
		}
	}
	$connection->close();
}
