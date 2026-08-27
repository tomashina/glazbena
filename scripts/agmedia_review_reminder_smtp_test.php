<?php

/**
 * Safe SMTP smoke test for the independent AG Media Review Reminder module.
 *
 * This script performs read-only database queries and never touches orders,
 * the delivery queue, attempts, suppressions or the cron token. Delivery is
 * opt-in and consists of exactly one synthetic message.
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This test can only be run from the command line.\n");
	exit(1);
}

$options = getopt('', array('to:', 'send', 'host:'));

if (!is_array($options)) {
	fwrite(STDERR, "Could not parse command-line arguments.\n");
	exit(2);
}

$recipient = isset($options['to']) && is_string($options['to']) ? strtolower(trim($options['to'])) : '';
$shouldSend = array_key_exists('send', $options);
$smtpHostOverride = isset($options['host']) && is_string($options['host']) ? trim($options['host']) : '';

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
	fwrite(STDERR, "Usage: php scripts/agmedia_review_reminder_smtp_test.php --to=name@example.com [--host=ssl://mail.example.com] [--send]\n");
	exit(2);
}

if (isset($options['host']) && $smtpHostOverride === '') {
	fwrite(STDERR, "The --host override cannot be empty.\n");
	exit(2);
}

$projectRoot = dirname(__DIR__);
$configPath = $projectRoot . '/config.php';

if (!is_file($configPath)) {
	fwrite(STDERR, "OpenCart config.php was not found.\n");
	exit(3);
}

require_once($configPath);

if (!defined('DIR_SYSTEM') || !defined('DB_HOSTNAME') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_DATABASE') || !defined('DB_PREFIX')) {
	fwrite(STDERR, "OpenCart database configuration is incomplete.\n");
	exit(3);
}

$mailerPath = DIR_SYSTEM . 'library/agmedia_review_reminder/mailer.php';
$rendererPath = DIR_SYSTEM . 'library/agmedia_review_reminder/renderer.php';

if (!is_file($mailerPath) || !is_file($rendererPath)) {
	fwrite(STDERR, "AG Media Review Reminder transport files are missing.\n");
	exit(3);
}

require_once($mailerPath);
require_once($rendererPath);

if (!class_exists('mysqli')) {
	fwrite(STDERR, "The mysqli PHP extension required by OpenCart is unavailable.\n");
	exit(3);
}

// Keep connection errors deterministic and, most importantly, do not echo
// credentials from mysqli diagnostics.
try {
	if (function_exists('mysqli_report')) {
		mysqli_report(MYSQLI_REPORT_OFF);
	}

	$database = @new mysqli(
		DB_HOSTNAME,
		DB_USERNAME,
		DB_PASSWORD,
		DB_DATABASE,
		defined('DB_PORT') ? (int)DB_PORT : 3306
	);
} catch (Throwable $exception) {
	fwrite(STDERR, "Could not read the OpenCart SMTP settings.\n");
	exit(3);
}

if ($database->connect_errno) {
	fwrite(STDERR, "Could not read the OpenCart SMTP settings.\n");
	exit(3);
}

if (!$database->set_charset('utf8mb4')) {
	$database->close();
	fwrite(STDERR, "Could not initialise the OpenCart database connection.\n");
	exit(3);
}

$settingKeys = array(
	'config_name',
	'config_email',
	'config_language_id',
	'config_mail_engine',
	'config_mail_smtp_hostname',
	'config_mail_smtp_username',
	'config_mail_smtp_password',
	'config_mail_smtp_port',
	'config_mail_smtp_timeout',
	'module_agmedia_review_reminder_status',
	'module_agmedia_review_reminder_subject',
	'module_agmedia_review_reminder_body'
);
$escapedKeys = array();

foreach ($settingKeys as $settingKey) {
	$escapedKeys[] = "'" . $database->real_escape_string($settingKey) . "'";
}

$settingsResult = $database->query(
	"SELECT `key`, `value`, `serialized` FROM `" . DB_PREFIX . "setting` " .
	"WHERE `store_id` = 0 AND `key` IN (" . implode(',', $escapedKeys) . ")"
);

if (!$settingsResult) {
	$database->close();
	fwrite(STDERR, "Could not read the OpenCart SMTP settings.\n");
	exit(3);
}

$settings = array();

while ($row = $settingsResult->fetch_assoc()) {
	$value = $row['value'];

	if ((int)$row['serialized'] === 1) {
		$decoded = json_decode($value, true);
		$value = is_array($decoded) ? $decoded : array();
	}

	$settings[$row['key']] = $value;
}

$settingsResult->free();

// Select only the Croatian language ID. If it is unavailable, the script uses
// its own Croatian defaults instead of accidentally previewing another locale.
$croatianLanguageId = 0;
$languageResult = $database->query(
	"SELECT `language_id` FROM `" . DB_PREFIX . "language` " .
	"WHERE LOWER(`code`) = 'hr-hr' OR LOWER(`code`) LIKE 'hr%' " .
	"ORDER BY CASE WHEN LOWER(`code`) = 'hr-hr' THEN 0 ELSE 1 END, `language_id` ASC LIMIT 1"
);

if ($languageResult && $languageRow = $languageResult->fetch_assoc()) {
	$croatianLanguageId = (int)$languageRow['language_id'];
}

if ($languageResult) {
	$languageResult->free();
}

$database->close();

$requiredSmtpSettings = array(
	'config_mail_smtp_hostname',
	'config_mail_smtp_username',
	'config_mail_smtp_password',
	'config_mail_smtp_port'
);

foreach ($requiredSmtpSettings as $requiredSetting) {
	if (!isset($settings[$requiredSetting]) || trim((string)$settings[$requiredSetting]) === '') {
		fwrite(STDERR, "Missing SMTP setting: " . $requiredSetting . "\n");
		exit(4);
	}
}

$smtpUsername = html_entity_decode(trim((string)$settings['config_mail_smtp_username']), ENT_QUOTES, 'UTF-8');
$storeEmail = isset($settings['config_email']) ? trim((string)$settings['config_email']) : '';
$from = filter_var($smtpUsername, FILTER_VALIDATE_EMAIL) ? $smtpUsername : $storeEmail;

if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
	fwrite(STDERR, "The configured SMTP/store sender address is invalid.\n");
	exit(4);
}

$storeName = isset($settings['config_name']) ? trim((string)$settings['config_name']) : '';

if ($storeName === '') {
	$storeName = 'Glazbena kutija';
}

$subjectTemplate = '';
$bodyTemplate = '';

if ($croatianLanguageId > 0 && isset($settings['module_agmedia_review_reminder_subject']) && is_array($settings['module_agmedia_review_reminder_subject'])) {
	if (isset($settings['module_agmedia_review_reminder_subject'][$croatianLanguageId])) {
		$subjectTemplate = trim((string)$settings['module_agmedia_review_reminder_subject'][$croatianLanguageId]);
	}
}

if ($croatianLanguageId > 0 && isset($settings['module_agmedia_review_reminder_body']) && is_array($settings['module_agmedia_review_reminder_body'])) {
	if (isset($settings['module_agmedia_review_reminder_body'][$croatianLanguageId])) {
		$bodyTemplate = trim((string)$settings['module_agmedia_review_reminder_body'][$croatianLanguageId]);
	}
}

if ($subjectTemplate === '') {
	$subjectTemplate = 'Kako ste zadovoljni narudžbom #{order_id}?';
}

if ($bodyTemplate === '') {
	$bodyTemplate = '<p>Pozdrav {customer_name},</p><p>hvala na narudžbi <strong>#{order_id}</strong>. Vaše iskustvo pomaže drugim kupcima, pa vas pozivamo da ostavite recenziju kupljenih proizvoda.</p>{products}<p>Hvala,<br>{store_name}</p><p style="font-size:12px;color:#777;"><a href="{unsubscribe_url}">Ne želim više primati podsjetnike za recenziju</a></p>';
}

$storeUrl = defined('HTTPS_SERVER') && HTTPS_SERVER ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : 'https://www.glazbena-kutija.hr/');
$storeUrl = rtrim((string)$storeUrl, '/') . '/';
$renderer = new AgmediaReviewReminderRenderer();
$rendered = $renderer->render(
	array(
		'order_id' => 999999,
		'firstname' => 'Tomislav',
		'lastname' => 'Test',
		'store_name' => $storeName,
		'date_added' => date('Y-m-d H:i:s')
	),
	array(
		array(
			'name' => 'Testni proizvod (primjer)',
			'model' => 'TEST-001',
			'review_url' => $storeUrl . '?agmedia_review_reminder_test=product#tab-review'
		)
	),
	array(
		'locale' => 'hr-hr',
		'store_name' => $storeName,
		'subject' => '[TEST] ' . $subjectTemplate,
		'body' => $bodyTemplate,
		'unsubscribe_url' => $storeUrl . '?agmedia_review_reminder_test=unsubscribe'
	)
);

$configuredEngine = isset($settings['config_mail_engine']) ? strtolower(trim((string)$settings['config_mail_engine'])) : '';
$configuredHost = trim((string)$settings['config_mail_smtp_hostname']);
$effectiveHost = $smtpHostOverride !== '' ? $smtpHostOverride : $configuredHost;
$moduleInstalled = array_key_exists('module_agmedia_review_reminder_status', $settings);
$moduleEnabled = $moduleInstalled && (bool)$settings['module_agmedia_review_reminder_status'];

echo "AG Media Review Reminder SMTP smoke test\n";
echo "Mode: " . ($shouldSend ? 'SEND ONE SYNTHETIC MESSAGE' : 'dry run (nothing sent)') . PHP_EOL;
echo "Recipient: " . $recipient . PHP_EOL;
echo "Subject: " . $rendered['subject'] . PHP_EOL;
echo "Mail engine: " . ($configuredEngine !== '' ? $configuredEngine : 'not configured') . PHP_EOL;
echo "SMTP endpoint: " . $effectiveHost . ':' . (int)$settings['config_mail_smtp_port'] . ($smtpHostOverride !== '' ? ' (host override)' : ' (database setting)') . PHP_EOL;
echo "Module settings: " . ($moduleInstalled ? ($moduleEnabled ? 'installed, enabled' : 'installed, disabled') : 'not installed; Croatian preview defaults used') . PHP_EOL;
echo "Rendered Croatian preview: " . strlen($rendered['html']) . " HTML bytes and " . strlen($rendered['text']) . " text bytes.\n";

if (!$shouldSend) {
	echo "Dry run complete. Add --send to deliver exactly one synthetic test email.\n";
	exit(0);
}

if ($configuredEngine !== 'smtp') {
	fwrite(STDERR, "SMTP test blocked: OpenCart Mail Engine is not set to SMTP.\n");
	exit(5);
}

$smtpPort = (int)$settings['config_mail_smtp_port'];

if ($smtpPort < 1 || $smtpPort > 65535) {
	fwrite(STDERR, "SMTP test blocked: the configured SMTP port is invalid.\n");
	exit(5);
}

$messageDomain = '';
$atPosition = strrpos($from, '@');

if ($atPosition !== false) {
	$messageDomain = strtolower(substr($from, $atPosition + 1));
}

if ($messageDomain === '' || !preg_match('/^[a-z0-9.-]+$/i', $messageDomain)) {
	$messageDomain = 'glazbena-kutija.hr';
}

$mailConfig = array(
	'hostname' => $effectiveHost,
	'username' => $smtpUsername,
	'password' => (string)$settings['config_mail_smtp_password'],
	'port' => $smtpPort,
	'timeout' => !empty($settings['config_mail_smtp_timeout']) ? (int)$settings['config_mail_smtp_timeout'] : 10
);
$messageIdSeed = hash('sha256', microtime(true) . '|' . getmypid() . '|' . $recipient . '|' . mt_rand());
$message = array(
	'to' => $recipient,
	'from' => $from,
	'reply_to' => filter_var($storeEmail, FILTER_VALIDATE_EMAIL) ? $storeEmail : '',
	'sender' => $storeName,
	'subject' => $rendered['subject'],
	'html' => $rendered['html'],
	'text' => $rendered['text'],
	'message_id' => '<agmedia-review-test-' . substr($messageIdSeed, 0, 24) . '@' . $messageDomain . '>'
);

try {
	$mailer = new AgmediaReviewReminderMailer($mailConfig);
	$result = $mailer->send($message);

	if (empty($result['accepted'])) {
		throw new RuntimeException('SMTP transport did not accept the test message.');
	}

	echo "SMTP accepted exactly one synthetic test message for " . $recipient . ".\n";
} catch (Throwable $exception) {
	$error = trim(preg_replace('/\s+/', ' ', $exception->getMessage()));
	$rawPassword = (string)$settings['config_mail_smtp_password'];
	$decodedPassword = html_entity_decode($rawPassword, ENT_QUOTES, 'UTF-8');

	// PHPMailer does not normally include credentials in its errors. Redact both
	// stored and decoded forms anyway so a non-standard SMTP reply cannot echo it.
	foreach (array_unique(array($rawPassword, $decodedPassword)) as $secret) {
		if ($secret !== '') {
			$error = str_replace($secret, '[redacted]', $error);
		}
	}

	if ($error === '') {
		$error = 'Unknown SMTP transport error.';
	}

	fwrite(STDERR, "SMTP test failed: " . $error . "\n");
	exit(6);
}
