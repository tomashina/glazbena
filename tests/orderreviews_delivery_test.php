<?php

class Model {}

require_once dirname(__DIR__) . '/catalog/model/extension/module/orderreviews.php';

$model = new ModelExtensionModuleOrderReviews();
$failures = array();

$assert = function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert($model->isValidDeliveryEmail('customer@example.com'), 'Valid email should be accepted.');
$assert(!$model->isValidDeliveryEmail("customer@example.com\r\nBcc: attacker@example.com"), 'Header injection must be rejected.');
$assert(!$model->isValidDeliveryEmail('not-an-email'), 'Malformed email must be rejected.');

$hardBounce = new Exception('Error: RCPT TO not accepted from server! 550 5.1.1 User unknown', 550);
$temporaryBounce = new Exception('Error: RCPT TO not accepted from server! 450 4.2.0 Mailbox busy', 450);
$policyRejection = new Exception('Error: RCPT TO not accepted from server! 550 5.7.1 Rejected by policy', 550);
$systemFailure = new Exception('Error: Password not accepted from server!', 535);
$unknownOutcome = new Exception('Error: SMTP outcome unknown after message DATA.', 0);

$assert($model->isPermanentRecipientFailure($hardBounce), 'SMTP RCPT 550 must be permanent.');
$assert(!$model->isPermanentRecipientFailure($temporaryBounce), 'SMTP RCPT 450 must be retryable.');
$assert(!$model->isPermanentRecipientFailure($policyRejection), 'SMTP policy rejection must not poison the recipient suppression list.');
$assert(!$model->isPermanentRecipientFailure($systemFailure), 'SMTP auth failure must not suppress the recipient.');
$assert($model->isUnknownDeliveryFailure($unknownOutcome), 'Post-DATA unknown outcome must not be retried automatically.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }

    exit(1);
}

echo "OrderReviews delivery tests passed.\n";
