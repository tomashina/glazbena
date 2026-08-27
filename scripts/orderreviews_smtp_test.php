<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This test can only be run from the command line.\n");
    exit(1);
}

$options = getopt('', array('to:', 'send', 'host:'));
$recipient = isset($options['to']) ? strtolower(trim($options['to'])) : '';
$shouldSend = array_key_exists('send', $options);
$smtpHostOverride = isset($options['host']) ? trim($options['host']) : '';

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/orderreviews_smtp_test.php --to=name@example.com [--send]\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
require_once($projectRoot . '/config.php');
require_once(DIR_SYSTEM . 'library/vendor/isenselabs/orderreviews/mail/smtp.php');
require_once(DIR_SYSTEM . 'library/vendor/isenselabs/orderreviews/mail.php');

$database = @new mysqli(
    DB_HOSTNAME,
    DB_USERNAME,
    DB_PASSWORD,
    DB_DATABASE,
    defined('DB_PORT') ? (int)DB_PORT : 3306
);

if ($database->connect_errno) {
    fwrite(STDERR, "Could not read the OpenCart mail settings.\n");
    exit(3);
}

$keys = array(
    'config_name',
    'config_email',
    'config_mail_engine',
    'config_mail_parameter',
    'config_mail_smtp_hostname',
    'config_mail_smtp_username',
    'config_mail_smtp_password',
    'config_mail_smtp_port',
    'config_mail_smtp_timeout'
);
$escapedKeys = array();

foreach ($keys as $key) {
    $escapedKeys[] = "'" . $database->real_escape_string($key) . "'";
}

$query = $database->query(
    "SELECT `key`, `value` FROM `" . DB_PREFIX . "setting` " .
    "WHERE store_id = 0 AND `key` IN (" . implode(',', $escapedKeys) . ")"
);
$settings = array();

while ($row = $query->fetch_assoc()) {
    $settings[$row['key']] = $row['value'];
}

$required = array(
    'config_mail_smtp_hostname',
    'config_mail_smtp_username',
    'config_mail_smtp_password',
    'config_mail_smtp_port'
);

foreach ($required as $key) {
    if (empty($settings[$key])) {
        fwrite(STDERR, "Missing SMTP setting: " . $key . "\n");
        exit(4);
    }
}

$previewPath = $projectRoot . '/docs/orderreviews-test-email.html';
$html = file_get_contents($previewPath);

echo "Preview: " . $previewPath . PHP_EOL;
echo "Configured mail engine: " . (!empty($settings['config_mail_engine']) ? $settings['config_mail_engine'] : 'unknown') . PHP_EOL;

if (!$shouldSend) {
    echo "Dry run only. Add --send to deliver the preview through SMTP." . PHP_EOL;
    exit(0);
}

if (empty($settings['config_mail_engine']) || strtolower($settings['config_mail_engine']) !== 'smtp') {
    fwrite(STDERR, "SMTP test blocked: OpenCart Mail Engine is not set to SMTP.\n");
    exit(5);
}

$smtpUsernameParts = explode('@', $settings['config_mail_smtp_username']);
$domain = count($smtpUsernameParts) === 2 ? $smtpUsernameParts[1] : parse_url(HTTP_SERVER, PHP_URL_HOST);

if ($domain) {
    putenv('SERVER_NAME=' . $domain);
}

try {
    $mail = new Vendor\iSenseLabs\OrderReviews\Mail('smtp');
    $mail->parameter = isset($settings['config_mail_parameter']) ? $settings['config_mail_parameter'] : '';
    $mail->smtp_hostname = $smtpHostOverride !== '' ? $smtpHostOverride : $settings['config_mail_smtp_hostname'];
    $mail->smtp_username = $settings['config_mail_smtp_username'];
    $mail->smtp_password = html_entity_decode($settings['config_mail_smtp_password'], ENT_QUOTES, 'UTF-8');
    $mail->smtp_port = (int)$settings['config_mail_smtp_port'];
    $mail->smtp_timeout = !empty($settings['config_mail_smtp_timeout']) ? (int)$settings['config_mail_smtp_timeout'] : 10;
    $mail->setTo($recipient);
    $mail->setFrom($settings['config_mail_smtp_username']);
    $mail->setSender(!empty($settings['config_name']) ? $settings['config_name'] : 'Glazbena kutija');

    if (!empty($settings['config_email']) && filter_var($settings['config_email'], FILTER_VALIDATE_EMAIL)) {
        $mail->setReplyTo($settings['config_email']);
    }

    $mail->setSubject('[TEST] Kako vam se sviđa kupljeni proizvod?');
    $mail->setHtml($html);

    if (!$mail->send()) {
        throw new RuntimeException('SMTP transport did not accept the test message.');
    }

    echo "SMTP accepted one test message for " . $recipient . "." . PHP_EOL;
} catch (Exception $exception) {
    fwrite(STDERR, "SMTP test failed: " . trim(preg_replace('/\s+/', ' ', $exception->getMessage())) . PHP_EOL);
    exit(6);
}
