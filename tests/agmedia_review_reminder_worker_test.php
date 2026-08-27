<?php

define('DIR_SYSTEM', dirname(__DIR__) . '/system/');

require_once DIR_SYSTEM . 'library/agmedia_review_reminder/mailer.php';
require_once DIR_SYSTEM . 'library/agmedia_review_reminder/renderer.php';

class Controller {
	public $request;
	public $response;
	public $config;
	public $load;
	public $log;
	public $url;
	public $model_extension_module_agmedia_review_reminder;
}

class AgmediaReviewReminderResponseStub {
	public $headers = array();
	public $output = '';

	public function addHeader($header) {
		$this->headers[] = $header;
	}

	public function setOutput($output) {
		$this->output = $output;
	}
}

class AgmediaReviewReminderConfigStub {
	private $values;

	public function __construct(array $values) {
		$this->values = $values;
	}

	public function get($key) {
		return isset($this->values[$key]) ? $this->values[$key] : null;
	}
}

class AgmediaReviewReminderModelStub {
	public $suppressionCalls = 0;

	public function isValidEmail($email) {
		return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	public function acquireLock($storeId) {
		return true;
	}

	public function suppressEmail($storeId, $email, $reason, $includeProcessing) {
		$this->suppressionCalls++;
	}

	public function releaseLock($storeId) {
	}
}

require_once dirname(__DIR__) . '/catalog/controller/extension/module/agmedia_review_reminder.php';

class AgmediaReviewReminderSmtpTestDouble extends AgmediaReviewReminderSmtp {
	public function command($command, $commandString, $expect) {
		return $this->sendCommand($command, $commandString, $expect);
	}
}

$failures = array();
$assert = function($condition, $message) use (&$failures) {
	if (!$condition) {
		$failures[] = $message;
	}
};

$assert(
	AgmediaReviewReminderMailer::isConfirmedInvalidRecipient('SMTP Error: 550 5.1.1 User unknown'),
	'Enhanced 5.1.1 must be treated as a confirmed invalid recipient.'
);
$assert(
	AgmediaReviewReminderMailer::isConfirmedInvalidRecipient('550 No such mailbox'),
	'An explicit permanent no-such-mailbox response must be suppressible.'
);
$assert(
	!AgmediaReviewReminderMailer::isConfirmedInvalidRecipient('550 5.7.1 Rejected by policy'),
	'Policy rejection must not poison the suppression list.'
);
$assert(
	!AgmediaReviewReminderMailer::isConfirmedInvalidRecipient('450 4.2.0 Mailbox busy'),
	'Temporary recipient failures must remain retryable.'
);
$assert(AgmediaReviewReminderMailer::extractSmtpCode('server said 451 4.3.0 later') === '451', 'SMTP reply code extraction failed.');

if (function_exists('stream_socket_pair')) {
	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	$smtp = new AgmediaReviewReminderSmtp();
	$property = new ReflectionProperty('PHPMailer\\PHPMailer\\SMTP', 'smtp_conn');
	$property->setAccessible(true);
	$property->setValue($smtp, $pair[0]);
	stream_set_blocking($pair[1], false);
	$callbackSawBytes = null;
	$callbackCalls = 0;
	$smtp->setBeforeDataTerminator(function() use (&$callbackSawBytes, &$callbackCalls, $pair) {
		$callbackCalls++;
		$callbackSawBytes = fread($pair[1], 16);
	});
	$smtp->client_send(".\r\n", 'DATA END');
	$written = fread($pair[1], 16);
	$assert($callbackCalls === 1, 'DATA phase callback must run exactly once.');
	$assert($callbackSawBytes === '' || $callbackSawBytes === false, 'Database phase callback must run before the DATA terminator reaches the socket.');
	$assert($written === ".\r\n", 'DATA terminator was not handed to the SMTP socket.');
	$assert($smtp->hasStartedDataTerminator(), 'SMTP transport did not record its ambiguous-outcome boundary.');
	fclose($pair[0]);
	fclose($pair[1]);

	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	$smtp = new AgmediaReviewReminderSmtp();
	$property->setValue($smtp, $pair[0]);
	stream_set_blocking($pair[1], false);
	$smtp->setBeforeDataTerminator(function() {
		throw new RuntimeException('database phase failed');
	});

	try {
		$smtp->client_send(".\r\n", 'DATA END');
		$failures[] = 'A failed durable phase callback must abort SMTP DATA.';
	} catch (RuntimeException $exception) {
		$assert($exception->getMessage() === 'database phase failed', 'Unexpected DATA callback error.');
	}

	$assert(!$smtp->hasStartedDataTerminator(), 'A failed database phase must not be classified as possibly accepted.');
	$assert(fread($pair[1], 16) === '' || feof($pair[1]), 'SMTP DATA terminator must not be written after a failed database phase.');
	fclose($pair[0]);
	fclose($pair[1]);

	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	$smtp = new AgmediaReviewReminderSmtpTestDouble();
	$property->setValue($smtp, $pair[0]);
	fwrite($pair[1], "550 5.1.1 User unknown\r\n");
	$assert(!$smtp->command('RCPT TO', 'RCPT TO:<missing@example.test>', 250), 'Fake RCPT rejection must fail.');
	fread($pair[1], 4096);
	fwrite($pair[1], "221 2.0.0 Bye\r\n");
	$assert($smtp->command('QUIT', 'QUIT', 221), 'Fake SMTP QUIT must succeed.');
	fread($pair[1], 4096);
	$failure = $smtp->getLastCommandFailure();
	$assert(isset($failure['smtp_code']) && (int)$failure['smtp_code'] === 550, 'RCPT failure must survive PHPMailer QUIT/close for safe classification.');
	$assert(isset($failure['smtp_code_ex']) && $failure['smtp_code_ex'] === '5.1.1', 'Enhanced recipient status must be retained.');
	$assert($smtp->isLastFailureRecipientCommand(), 'Only an RCPT TO rejection may suppress a recipient.');
	fclose($pair[0]);
	fclose($pair[1]);

	$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	$smtp = new AgmediaReviewReminderSmtpTestDouble();
	$property->setValue($smtp, $pair[0]);
	fwrite($pair[1], "550 5.1.1 Sender rejected\r\n");
	$assert(!$smtp->command('MAIL FROM', 'MAIL FROM:<sender@example.test>', 250), 'Fake sender rejection must fail.');
	fread($pair[1], 4096);
	$assert(!$smtp->isLastFailureRecipientCommand(), 'MAIL FROM 550/5.1.1 must never suppress the customer recipient.');
	fclose($pair[0]);
	fclose($pair[1]);
}

$renderer = new AgmediaReviewReminderRenderer();
$rendered = $renderer->render(
	array(
		'order_id' => 321,
		'firstname' => 'Ana',
		'lastname' => 'Anić',
		'store_name' => 'Glazbena kutija',
		'date_added' => '2026-08-20 12:30:00'
	),
	array(
		array(
			'name' => '<script>alert(1)</script> Gitara',
			'model' => 'G-1',
			'review_url' => 'https://example.test/gitara?review=write#tab-review'
		)
	),
	array(
		'locale' => 'hr-hr',
		'store_name' => 'Glazbena kutija',
		'subject' => 'Narudžba #{order_id} — {customer_name}',
		'body' => '<p>Pozdrav {customer_name}, {order_date}</p>{products}<p><a href="{unsubscribe_url}">Odjava</a></p>',
		'unsubscribe_url' => 'https://example.test/unsubscribe?e=abc&t=def'
	)
);

$assert($rendered['subject'] === 'Narudžba #321 — Ana Anić', 'Subject placeholders were not rendered.');
$assert(strpos($rendered['html'], '&lt;script&gt;alert(1)&lt;/script&gt; Gitara') !== false, 'Product data must be HTML-escaped.');
$assert(strpos($rendered['html'], '20.08.2026.') !== false, 'Croatian order date placeholder was not rendered.');
$assert(substr_count($rendered['html'], 'https://example.test/unsubscribe?e=abc&amp;t=def') === 1, 'Unsubscribe link must not be duplicated in HTML.');
$assert(strpos($rendered['text'], 'https://example.test/gitara?review=write#tab-review') !== false, 'Plain-text mail must include the product review URL.');
$assert(substr_count($rendered['text'], 'https://example.test/unsubscribe?e=abc&t=def') === 1, 'Plain-text mail must include one unsubscribe URL.');

$unsubscribeEmail = 'customer@example.test';
$unsubscribeEncoded = rtrim(strtr(base64_encode($unsubscribeEmail), '+/', '-_'), '=');
$unsubscribeSecret = 'test-open-cart-secret';
$unsubscribeSignature = hash_hmac('sha256', "agmedia_review_reminder\n0\n" . $unsubscribeEmail, $unsubscribeSecret);
$controller = new ControllerExtensionModuleAgmediaReviewReminder();
$controller->config = new AgmediaReviewReminderConfigStub(array(
	'config_store_id' => 0,
	'config_encryption' => $unsubscribeSecret,
	'config_name' => 'Glazbena kutija'
));
$controller->load = new class {
	public function model($route) {
	}
};
$controller->log = new class {
	public function write($message) {
	}
};
$controller->url = new class {
	public function link($route, $args = '', $secure = false) {
		return 'https://example.test/index.php?route=' . $route;
	}
};
$controller->model_extension_module_agmedia_review_reminder = new AgmediaReviewReminderModelStub();
$controller->request = (object)array(
	'server' => array('REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1'),
	'get' => array('e' => $unsubscribeEncoded, 't' => $unsubscribeSignature),
	'post' => array()
);
$controller->response = new AgmediaReviewReminderResponseStub();
$controller->unsubscribe();
$assert($controller->model_extension_module_agmedia_review_reminder->suppressionCalls === 0, 'Unsubscribe GET must be read-only for mail link scanners.');
$assert(strpos($controller->response->output, '<form method="post"') !== false, 'Unsubscribe GET must render an explicit POST confirmation form.');

$controller->request = (object)array(
	'server' => array('REQUEST_METHOD' => 'POST', 'SERVER_PROTOCOL' => 'HTTP/1.1'),
	'get' => array(),
	'post' => array('e' => $unsubscribeEncoded, 't' => $unsubscribeSignature)
);
$controller->response = new AgmediaReviewReminderResponseStub();
$controller->unsubscribe();
$assert($controller->model_extension_module_agmedia_review_reminder->suppressionCalls === 1, 'A valid signed unsubscribe POST must persist suppression exactly once.');

if ($failures) {
	foreach ($failures as $failure) {
		fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
	}

	exit(1);
}

echo "AG Media Review Reminder worker tests passed.\n";
