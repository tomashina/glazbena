<?php

/**
 * Independent SMTP transport for AG Media Review Reminder.
 *
 * PHPMailer is bundled with this OpenCart installation under its LGPL license.
 * This wrapper deliberately enables only authenticated SMTP and keeps TLS peer
 * verification enabled.
 */

include_once(DIR_SYSTEM . 'library/shared/phpmailer/src/Exception.php');
include_once(DIR_SYSTEM . 'library/shared/phpmailer/src/PHPMailer.php');
include_once(DIR_SYSTEM . 'library/shared/phpmailer/src/SMTP.php');

class AgmediaReviewReminderMailException extends \RuntimeException {
	private $smtpCode;
	private $invalidRecipient;
	private $ambiguousOutcome;

	public function __construct($message, $smtpCode = '', $invalidRecipient = false, $ambiguousOutcome = false, $previous = null) {
		$this->smtpCode = (string)$smtpCode;
		$this->invalidRecipient = (bool)$invalidRecipient;
		$this->ambiguousOutcome = (bool)$ambiguousOutcome;

		parent::__construct($message, is_numeric($smtpCode) ? (int)$smtpCode : 0, $previous);
	}

	public function getSmtpCode() {
		return $this->smtpCode;
	}

	public function isInvalidRecipient() {
		return $this->invalidRecipient;
	}

	public function isAmbiguousOutcome() {
		return $this->ambiguousOutcome;
	}
}

/**
 * Tracks whether the SMTP DATA terminator was handed to the socket. A lost
 * connection after that point has an ambiguous delivery result and must never
 * be retried automatically.
 */
class AgmediaReviewReminderSmtp extends \PHPMailer\PHPMailer\SMTP {
	private $dataTerminatorStarted = false;
	private $beforeDataTerminator;
	private $lastCommandFailure = array();

	public function setBeforeDataTerminator($callback) {
		if ($callback !== null && !is_callable($callback)) {
			throw new \InvalidArgumentException('DATA phase callback must be callable.');
		}

		$this->beforeDataTerminator = $callback;
	}

	public function client_send($data, $command = '') {
		if ($command === 'DATA END' && !$this->dataTerminatorStarted) {
			if (is_callable($this->beforeDataTerminator)) {
				call_user_func($this->beforeDataTerminator);
			}

			// Set this only after the durable database phase callback succeeds.
			$this->dataTerminatorStarted = true;
		}

		return parent::client_send($data, $command);
	}

	public function hasStartedDataTerminator() {
		return $this->dataTerminatorStarted;
	}

	public function getLastCommandFailure() {
		return $this->lastCommandFailure;
	}

	public function isLastFailureRecipientCommand() {
		return isset($this->lastCommandFailure['command']) && $this->lastCommandFailure['command'] === 'RCPT TO';
	}

	protected function sendCommand($command, $commandstring, $expect) {
		$result = parent::sendCommand($command, $commandstring, $expect);

		if (!$result) {
			$this->lastCommandFailure = $this->getError();
			$this->lastCommandFailure['command'] = $command;
		} elseif ($command !== 'QUIT') {
			// A successful fallback HELO, recipient or DATA phase supersedes an
			// earlier command failure. QUIT must not erase the RCPT rejection
			// PHPMailer reports after closing the connection.
			$this->lastCommandFailure = array();
		}

		return $result;
	}
}

class AgmediaReviewReminderMailer {
	private $config;

	public function __construct(array $config) {
		$this->config = $config;
	}

	/**
	 * @param array $message to, from, sender, subject, html, text, message_id
	 * @return array SMTP acceptance metadata
	 * @throws AgmediaReviewReminderMailException
	 */
	public function send(array $message) {
		$this->validateMessage($message);

		$hostConfig = $this->normaliseHost(
			isset($this->config['hostname']) ? $this->config['hostname'] : '',
			isset($this->config['port']) ? $this->config['port'] : 0
		);

		$username = html_entity_decode(trim(isset($this->config['username']) ? $this->config['username'] : ''), ENT_QUOTES, 'UTF-8');
		$password = html_entity_decode(isset($this->config['password']) ? $this->config['password'] : '', ENT_QUOTES, 'UTF-8');

		if ($username === '' || $password === '') {
			throw new AgmediaReviewReminderMailException('SMTP authentication credentials are incomplete.');
		}

		$timeout = isset($this->config['timeout']) ? (int)$this->config['timeout'] : 10;
		$timeout = max(2, min(30, $timeout));

		$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
		$smtp = new AgmediaReviewReminderSmtp();

		if (isset($message['before_data_terminator'])) {
			$smtp->setBeforeDataTerminator($message['before_data_terminator']);
		}

		$mail->setSMTPInstance($smtp);

		try {
			$mail->isSMTP();
			$mail->Host = $hostConfig['host'];
			$mail->Port = $hostConfig['port'];
			$mail->SMTPAuth = true;
			$mail->Username = $username;
			$mail->Password = $password;
			$mail->Timeout = $timeout;
			$mail->Timelimit = $timeout;
			$mail->SMTPDebug = 0;
			$mail->SMTPAutoTLS = true;
			$mail->SMTPSecure = $hostConfig['secure'];
			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => true,
					'verify_peer_name' => true,
					'allow_self_signed' => false,
					'SNI_enabled' => true,
					'peer_name' => $hostConfig['host']
				)
			);

			$mail->CharSet = 'UTF-8';
			$mail->Encoding = 'quoted-printable';
			$mail->XMailer = '';
			$mail->setFrom($message['from'], $this->singleLine($message['sender']));
			$mail->addAddress($message['to']);

			if (!empty($message['reply_to']) && filter_var($message['reply_to'], FILTER_VALIDATE_EMAIL)) {
				$mail->addReplyTo($message['reply_to'], $this->singleLine($message['sender']));
			}

			$mail->Subject = $this->singleLine($message['subject']);
			$mail->isHTML(true);
			$mail->Body = $message['html'];
			$mail->AltBody = $message['text'];
			$mail->MessageID = $this->normaliseMessageId($message['message_id']);
			$mail->addCustomHeader('Auto-Submitted', 'auto-generated');
			$mail->addCustomHeader('X-Auto-Response-Suppress', 'All');

			if (!$mail->send()) {
				throw new \RuntimeException($mail->ErrorInfo ?: 'SMTP transport rejected the message.');
			}

			$transactionId = $smtp->getLastTransactionID();

			return array(
				'accepted' => true,
				'transaction_id' => is_scalar($transactionId) ? (string)$transactionId : ''
			);
		} catch (\Throwable $exception) {
			$error = $this->collectError($mail, $smtp, $exception);
			$smtpCode = self::extractSmtpCode($error);
			// Never suppress from generic text alone: the same 550/5.1.1 text
			// during MAIL FROM, AUTH or DATA does not prove the recipient is bad.
			$invalidRecipient = $smtp->isLastFailureRecipientCommand() && self::isConfirmedInvalidRecipient($error);
			$explicitReply = preg_match('/(?:^|\D)[45][0-9]{2}(?:\D|$)/', $error) === 1;
			$ambiguous = $smtp->hasStartedDataTerminator() && !$explicitReply;

			try {
				$mail->smtpClose();
			} catch (\Throwable $ignored) {
				// The original transport error is the useful one.
			}

			throw new AgmediaReviewReminderMailException(
				$this->safeErrorMessage($error, $ambiguous),
				$smtpCode,
				$invalidRecipient,
				$ambiguous,
				$exception
			);
		}
	}

	public static function extractSmtpCode($error) {
		if (preg_match('/(?:^|\D)([45][0-9]{2})(?:[ .-]|$)/', (string)$error, $matches)) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Suppression is intentionally conservative: only an enhanced 5.1.1 reply,
	 * or an explicit unknown/non-existent recipient reply, is considered proof
	 * that the address itself is invalid. Policy, spam and quota rejections are
	 * never added to the suppression table.
	 */
	public static function isConfirmedInvalidRecipient($error) {
		$error = strtolower((string)$error);

		if (preg_match('/\b5\.1\.1\b/', $error)) {
			return true;
		}

		if (!preg_match('/\b(?:550|551|553)\b/', $error)) {
			return false;
		}

		return preg_match('/(?:user unknown|unknown user|unknown recipient|no such (?:user|mailbox|recipient)|recipient (?:not found|does not exist|unknown)|mailbox (?:not found|does not exist)|invalid recipient|address rejected.*does not exist)/', $error) === 1;
	}

	private function validateMessage(array $message) {
		foreach (array('to', 'from') as $field) {
			if (empty($message[$field]) || !filter_var($message[$field], FILTER_VALIDATE_EMAIL)) {
				throw new AgmediaReviewReminderMailException('A valid ' . $field . ' address is required.');
			}
		}

		foreach (array('sender', 'subject', 'html', 'text', 'message_id') as $field) {
			if (!isset($message[$field]) || $message[$field] === '') {
				throw new AgmediaReviewReminderMailException('Message field ' . $field . ' is required.');
			}
		}
	}

	private function normaliseHost($hostname, $port) {
		$hostname = html_entity_decode(trim((string)$hostname), ENT_QUOTES, 'UTF-8');
		$secure = '';

		if (stripos($hostname, 'ssl://') === 0) {
			$secure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
			$hostname = substr($hostname, 6);
		} elseif (stripos($hostname, 'tls://') === 0) {
			$secure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			$hostname = substr($hostname, 6);
		}

		$hostname = trim($hostname);
		$port = (int)$port;

		if ($port < 1 || $port > 65535) {
			throw new AgmediaReviewReminderMailException('SMTP port is invalid.');
		}

		if ($hostname === '' || preg_match('/[\x00-\x20;\/\\\\]/', $hostname)) {
			throw new AgmediaReviewReminderMailException('SMTP hostname is invalid.');
		}

		if (!filter_var($hostname, FILTER_VALIDATE_IP) && !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $hostname)) {
			throw new AgmediaReviewReminderMailException('SMTP hostname is invalid.');
		}

		if ($secure === '') {
			if ($port === 465) {
				$secure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
			} elseif ($port === 587) {
				$secure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			}
		}

		if ($secure === '') {
			throw new AgmediaReviewReminderMailException('Encrypted SMTP (TLS/SMTPS) is required.');
		}

		return array('host' => $hostname, 'port' => $port, 'secure' => $secure);
	}

	private function normaliseMessageId($messageId) {
		$messageId = trim((string)$messageId);

		if (!preg_match('/^<[a-z0-9._+-]+@[a-z0-9.-]+>$/i', $messageId)) {
			throw new AgmediaReviewReminderMailException('Message-ID is invalid.');
		}

		return $messageId;
	}

	private function singleLine($value) {
		return trim(str_replace(array("\r", "\n"), ' ', (string)$value));
	}

	private function collectError($mail, $smtp, $exception) {
		$parts = array();

		if ($mail->ErrorInfo !== '') {
			$parts[] = $mail->ErrorInfo;
		}

		if ($exception->getMessage() !== '') {
			$parts[] = $exception->getMessage();
		}

		$smtpError = $smtp->getError();
		$lastCommandFailure = $smtp->getLastCommandFailure();

		if (is_array($lastCommandFailure) && $lastCommandFailure) {
			$smtpError = array_merge($smtpError, array_filter($lastCommandFailure));
		}

		if (is_array($smtpError)) {
			foreach (array('smtp_code', 'smtp_code_ex', 'error', 'detail') as $key) {
				if (!empty($smtpError[$key])) {
					$parts[] = (string)$smtpError[$key];
				}
			}
		}

		return trim(implode(' | ', array_unique($parts)));
	}

	private function safeErrorMessage($error, $ambiguous) {
		if ($ambiguous) {
			return 'SMTP outcome is unknown after the DATA terminator; automatic retry is disabled.';
		}

		$error = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$error);
		$error = trim($error);

		if (function_exists('utf8_strlen') && function_exists('utf8_substr')) {
			return utf8_strlen($error) > 1000 ? utf8_substr($error, 0, 1000) : $error;
		}

		return strlen($error) > 1000 ? substr($error, 0, 1000) : $error;
	}
}
