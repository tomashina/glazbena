<?php
require_once(DIR_SYSTEM . 'library/agmedia_review_reminder/policy.php');

class ControllerExtensionModuleAgmediaReviewReminder extends Controller {
	public function index() {
		// This extension is a worker, not a storefront layout module.
		return '';
	}

	public function cron() {
		$this->addSecurityHeaders(true);

		if ($this->requestMethod() !== 'GET') {
			$this->respondJson(array('status' => 'error', 'message' => 'GET is required.'), 405);
			return;
		}

		$expectedToken = trim((string)$this->config->get('module_agmedia_review_reminder_cron_token'));
		$providedToken = isset($this->request->get['token']) && is_string($this->request->get['token'])
			? trim($this->request->get['token'])
			: '';

		if ($expectedToken === '') {
			$this->respondJson(array('status' => 'error', 'message' => 'Cron token is not configured.'), 503);
			return;
		}

		if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
			$this->respondJson(array('status' => 'error', 'message' => 'Forbidden.'), 403);
			return;
		}

		if (!(bool)$this->config->get('module_agmedia_review_reminder_status')) {
			$this->respondJson(array('status' => 'disabled', 'message' => 'AG Media Review Reminder is disabled.'), 503);
			return;
		}

		if (strtolower(trim((string)$this->config->get('config_mail_engine'))) !== 'smtp') {
			$this->respondJson(array('status' => 'error', 'message' => 'Authenticated SMTP is required.'), 503);
			return;
		}

		if (!(bool)$this->config->get('config_review_status')) {
			$this->respondJson(array('status' => 'error', 'message' => 'OpenCart product reviews are disabled.'), 503);
			return;
		}

		$settings = $this->getWorkerSettings();
		$mailConfig = $this->getMailConfig();

		if (!$mailConfig['ready']) {
			$this->respondJson(array('status' => 'error', 'message' => 'SMTP configuration is incomplete.'), 503);
			return;
		}

		$summary = array(
			'status' => 'ok',
			'queued' => 0,
			'attempted' => 0,
			'sent' => 0,
			'retry' => 0,
			'failed' => 0,
			'unknown' => 0,
			'suppressed' => 0,
			'cancelled' => 0,
			'skipped' => 0,
			'stopped_on_error' => false,
			'message' => 'AG Media Review Reminder worker completed.'
		);
		$responseStatus = 200;

		$storeId = (int)$this->config->get('config_store_id');
		$locked = false;

		$this->load->model('extension/module/agmedia_review_reminder');

		try {
			$locked = $this->model_extension_module_agmedia_review_reminder->acquireLock($storeId);

			if (!$locked) {
				$summary['status'] = 'busy';
				$summary['message'] = 'Another review reminder worker is already running.';
				$this->response->addHeader('Retry-After: 300');
				$this->respondJson($summary, 409);
				return;
			}

			$this->model_extension_module_agmedia_review_reminder->recoverStale($storeId, $settings['max_attempts']);
			$summary['queued'] = $this->model_extension_module_agmedia_review_reminder->enqueueEligible(
				$storeId,
				$settings['order_status_ids'],
				$settings['min_order_date'],
				$settings['delay_days'],
				$settings['max_age_days']
			);

			$attemptsThisHour = $this->model_extension_module_agmedia_review_reminder->getHourlyAttemptCount($storeId);
			$remainingThisHour = max(0, $settings['hourly_limit'] - $attemptsThisHour);
			$runLimit = min($settings['batch_size'], $remainingThisHour);

			if ($runLimit < 1) {
				$summary['message'] = 'Hourly SMTP attempt limit reached; no connection opened.';
				$this->response->addHeader('Retry-After: 3600');
				$this->respondJson($summary);
				return;
			}

			require_once(DIR_SYSTEM . 'library/agmedia_review_reminder/mailer.php');
			require_once(DIR_SYSTEM . 'library/agmedia_review_reminder/renderer.php');

			$mailer = new AgmediaReviewReminderMailer($mailConfig);
			$renderer = new AgmediaReviewReminderRenderer();

			for ($processed = 0; $processed < $runLimit; $processed++) {
				$delivery = $this->model_extension_module_agmedia_review_reminder->claimNext($storeId, $settings['max_attempts']);

				if (!$delivery) {
					break;
				}

				$deliveryId = (int)$delivery['delivery_id'];

				$order = $this->model_extension_module_agmedia_review_reminder->getEligibleOrder(
					(int)$delivery['order_id'],
					$storeId,
					$settings['order_status_ids'],
					$settings['min_order_date'],
					$settings['delay_days'],
					$settings['max_age_days']
				);

				if (!$order) {
					$this->model_extension_module_agmedia_review_reminder->markWithoutAttempt(
						$deliveryId,
						ModelExtensionModuleAgmediaReviewReminder::STATUS_CANCELLED,
						'Order is missing or no longer eligible.'
					);
					$summary['cancelled']++;
					$summary['skipped']++;
					continue;
				}

				// The address may have been corrected after this delivery entered
				// the queue. Always use the current order recipient, never a stale
				// queued snapshot, and retain prior attempts as immutable audit rows.
				$email = $this->model_extension_module_agmedia_review_reminder->normaliseEmail($order['email']);

				if (!$this->model_extension_module_agmedia_review_reminder->isValidEmail($email)) {
					$this->model_extension_module_agmedia_review_reminder->markWithoutAttempt(
						$deliveryId,
						ModelExtensionModuleAgmediaReviewReminder::STATUS_CANCELLED,
						'Current order recipient email is invalid.'
					);
					$summary['cancelled']++;
					$summary['skipped']++;
					continue;
				}

				$queuedEmail = $this->model_extension_module_agmedia_review_reminder->normaliseEmail($delivery['email']);

				if ($queuedEmail !== $email) {
					$this->model_extension_module_agmedia_review_reminder->updateProcessingRecipient($deliveryId, $email);
				}

				if ($this->model_extension_module_agmedia_review_reminder->isSuppressed($storeId, $email)) {
					$this->model_extension_module_agmedia_review_reminder->markWithoutAttempt(
						$deliveryId,
						ModelExtensionModuleAgmediaReviewReminder::STATUS_SUPPRESSED,
						'Current order recipient is on the suppression list.'
					);
					$summary['suppressed']++;
					$summary['skipped']++;
					continue;
				}

				$products = $this->buildProductLinks(
					$this->model_extension_module_agmedia_review_reminder->getOrderProducts($order['order_id'])
				);

				if (!$products) {
					$this->model_extension_module_agmedia_review_reminder->markWithoutAttempt(
						$deliveryId,
						ModelExtensionModuleAgmediaReviewReminder::STATUS_CANCELLED,
						'Order has no reviewable products.'
					);
					$summary['cancelled']++;
					$summary['skipped']++;
					continue;
				}

				$languageId = (int)$order['language_id'];
				$unsubscribeUrl = $this->buildUnsubscribeUrl($storeId, $email);
				$content = $renderer->render($order, $products, array(
					'locale' => $this->model_extension_module_agmedia_review_reminder->getLanguageCode($languageId),
					'store_name' => (string)$this->config->get('config_name'),
					'subject' => $this->getLanguageSetting('module_agmedia_review_reminder_subject', $languageId),
					'body' => $this->getLanguageSetting('module_agmedia_review_reminder_body', $languageId),
					'unsubscribe_url' => $unsubscribeUrl
				));

				$attempt = $this->model_extension_module_agmedia_review_reminder->reserveAttempt(
					$deliveryId,
					$storeId,
					(int)$order['order_id'],
					$email
				);
				$attemptId = (int)$attempt['attempt_id'];
				$messageId = $this->buildMessageId($deliveryId, $attemptId, $email, $mailConfig['from']);
				$summary['attempted']++;

				try {
					$model = $this->model_extension_module_agmedia_review_reminder;
					$mailer->send(array(
						'to' => $email,
						'from' => $mailConfig['from'],
						'reply_to' => $mailConfig['reply_to'],
						'sender' => $mailConfig['sender'],
						'subject' => $content['subject'],
						'html' => $content['html'],
						'text' => $content['text'],
						'message_id' => $messageId,
						'before_data_terminator' => function() use ($model, $deliveryId, $attemptId, $messageId) {
							$model->markSending($deliveryId, $attemptId, $messageId);
						}
					));
					$this->model_extension_module_agmedia_review_reminder->markSent($deliveryId, $attemptId);
					$summary['sent']++;
				} catch (AgmediaReviewReminderMailException $exception) {
					if ($exception->isInvalidRecipient()) {
						$this->model_extension_module_agmedia_review_reminder->suppressDelivery(
							$deliveryId,
							$attemptId,
							$storeId,
							$email,
							$exception->getMessage(),
							$exception->getSmtpCode()
						);
						$summary['suppressed']++;
						$summary['message'] = 'A permanently invalid recipient was suppressed; the worker stopped safely.';
					} else {
						$status = $this->model_extension_module_agmedia_review_reminder->markTransportFailure(
							$deliveryId,
							$attemptId,
							(int)$attempt['attempts'],
							$settings['max_attempts'],
							$exception->getSmtpCode(),
							$exception->getMessage(),
							$exception->isAmbiguousOutcome()
						);

						if (isset($summary[$status])) {
							$summary[$status]++;
						} else {
							$summary['failed']++;
						}

						$summary['status'] = 'error';
						$responseStatus = 502;
						$this->response->addHeader('Retry-After: 3600');
						$summary['message'] = $exception->isAmbiguousOutcome()
							? 'SMTP outcome is unknown; automatic retry is disabled and the worker stopped.'
							: 'SMTP transport failed; the worker stopped after the first failure.';
					}

					$summary['stopped_on_error'] = true;
					$this->log->write('[AG Media Review Reminder] delivery_id=' . $deliveryId . ' SMTP failure: ' . $exception->getMessage());
					break;
				}
			}

			$this->respondJson($summary, $responseStatus);
		} catch (\Throwable $exception) {
			$this->log->write('[AG Media Review Reminder] worker error: ' . $exception->getMessage());
			$this->respondJson(array(
				'status' => 'error',
				'attempted' => isset($summary['attempted']) ? $summary['attempted'] : 0,
				'sent' => isset($summary['sent']) ? $summary['sent'] : 0,
				'message' => 'Review reminder worker failed safely; see the OpenCart error log.'
			), 500);
		} finally {
			if ($locked) {
				try {
					$this->model_extension_module_agmedia_review_reminder->releaseLock($storeId);
				} catch (\Throwable $releaseException) {
					$this->log->write('[AG Media Review Reminder] advisory lock release failed: ' . $releaseException->getMessage());
				}
			}
		}
	}

	public function unsubscribe() {
		$this->addSecurityHeaders(false);
		$method = $this->requestMethod();

		if ($method !== 'GET' && $method !== 'POST') {
			$this->response->addHeader('Allow: GET, POST');
			$this->respondHtml('Neispravan zahtjev.', 405);
			return;
		}

		$input = $method === 'POST' ? $this->request->post : $this->request->get;
		$encodedEmail = isset($input['e']) && is_string($input['e']) ? $input['e'] : '';
		$providedSignature = isset($input['t']) && is_string($input['t']) ? strtolower($input['t']) : '';
		$email = $this->base64UrlDecode($encodedEmail);
		$storeId = (int)$this->config->get('config_store_id');
		$secret = (string)$this->config->get('config_encryption');

		if ($email === false || $secret === '' || !preg_match('/^[a-f0-9]{64}$/', $providedSignature)) {
			$this->respondHtml('Poveznica za odjavu nije valjana.', 400);
			return;
		}

		$email = strtolower(trim($email));
		$expectedSignature = $this->unsubscribeSignature($storeId, $email, $secret);

		$this->load->model('extension/module/agmedia_review_reminder');

		if (!$this->model_extension_module_agmedia_review_reminder->isValidEmail($email) || !hash_equals($expectedSignature, $providedSignature)) {
			$this->respondHtml('Poveznica za odjavu nije valjana.', 400);
			return;
		}

		if ($method === 'GET') {
			// Read-only confirmation prevents antivirus/link-preview crawlers from
			// unsubscribing a customer simply by opening the signed mail link.
			$this->respondUnsubscribeConfirmation($encodedEmail, $providedSignature);
			return;
		}

		$locked = false;

		try {
			$locked = $this->model_extension_module_agmedia_review_reminder->acquireLock($storeId);
			$this->model_extension_module_agmedia_review_reminder->suppressEmail(
				$storeId,
				$email,
				'customer_unsubscribe',
				$locked
			);
			$this->respondHtml('Odjava je spremljena. Više nećete primati podsjetnike za recenziju.');
		} catch (\Throwable $exception) {
			$this->log->write('[AG Media Review Reminder] unsubscribe error: ' . $exception->getMessage());
			$this->respondHtml('Odjavu trenutno nije moguće spremiti. Pokušajte ponovno kasnije.', 500);
		} finally {
			if ($locked) {
				try {
					$this->model_extension_module_agmedia_review_reminder->releaseLock($storeId);
				} catch (\Throwable $ignored) {
				}
			}
		}
	}

	private function getWorkerSettings() {
		$orderStatusIds = array_values(array_unique(array_filter(array_map('intval', (array)$this->config->get('module_agmedia_review_reminder_order_status_id')))));
		$minOrderDate = AgmediaReviewReminderPolicy::normaliseMinOrderDate(
			$this->config->get('module_agmedia_review_reminder_min_order_date')
		);

		$delayDays = max(0, min(90, (int)$this->config->get('module_agmedia_review_reminder_delay_days')));
		$maxAgeDays = (int)$this->config->get('module_agmedia_review_reminder_max_age_days');
		$maxAgeDays = max($delayDays, min(365, $maxAgeDays > 0 ? $maxAgeDays : 45));

		return array(
			'order_status_ids' => $orderStatusIds,
			'min_order_date' => $minOrderDate,
			'delay_days' => $delayDays,
			'batch_size' => max(1, min(10, (int)$this->config->get('module_agmedia_review_reminder_batch_size') ?: 1)),
			'hourly_limit' => max(1, min(2, (int)$this->config->get('module_agmedia_review_reminder_hourly_limit') ?: 1)),
			'max_age_days' => $maxAgeDays,
			'max_attempts' => max(1, min(5, (int)$this->config->get('module_agmedia_review_reminder_max_attempts') ?: 3))
		);
	}

	private function getMailConfig() {
		$username = html_entity_decode(trim((string)$this->config->get('config_mail_smtp_username')), ENT_QUOTES, 'UTF-8');
		$password = html_entity_decode((string)$this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$storeEmail = trim((string)$this->config->get('config_email'));
		$from = filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : $storeEmail;
		$hostname = trim((string)$this->config->get('config_mail_smtp_hostname'));
		$port = (int)$this->config->get('config_mail_smtp_port');

		return array(
			'ready' => $hostname !== '' && $username !== '' && $password !== '' && $port > 0 && (bool)filter_var($from, FILTER_VALIDATE_EMAIL),
			'hostname' => $hostname,
			'username' => $username,
			'password' => $password,
			'port' => $port,
			'timeout' => (int)$this->config->get('config_mail_smtp_timeout') ?: 10,
			'from' => $from,
			'reply_to' => filter_var($storeEmail, FILTER_VALIDATE_EMAIL) ? $storeEmail : '',
			'sender' => trim((string)$this->config->get('config_name')) ?: 'Glazbena kutija'
		);
	}

	private function getLanguageSetting($key, $languageId) {
		$value = $this->config->get($key);

		if (!is_array($value)) {
			return is_string($value) ? $value : '';
		}

		if (isset($value[$languageId]) && trim((string)$value[$languageId]) !== '') {
			return $value[$languageId];
		}

		$currentLanguageId = (int)$this->config->get('config_language_id');

		if (isset($value[$currentLanguageId]) && trim((string)$value[$currentLanguageId]) !== '') {
			return $value[$currentLanguageId];
		}

		foreach ($value as $candidate) {
			if (trim((string)$candidate) !== '') {
				return $candidate;
			}
		}

		return '';
	}

	private function buildProductLinks(array $products) {
		$result = array();

		foreach ($products as $product) {
			if (empty($product['product_id'])) {
				continue;
			}

			$url = html_entity_decode($this->url->link('product/product', 'product_id=' . (int)$product['product_id'], true), ENT_QUOTES, 'UTF-8');
			$url .= (strpos($url, '?') === false ? '?' : '&') . 'review=write#tab-review';
			$product['review_url'] = $url;
			$result[] = $product;
		}

		return $result;
	}

	private function buildMessageId($deliveryId, $attemptId, $email, $from) {
		$domain = substr(strrchr($from, '@'), 1);
		$secret = (string)$this->config->get('config_encryption');
		$digest = substr(hash_hmac('sha256', $deliveryId . '|' . $attemptId . '|' . $email, $secret), 0, 24);

		return '<arr-' . (int)$deliveryId . '-' . (int)$attemptId . '-' . $digest . '@' . strtolower($domain) . '>';
	}

	private function buildUnsubscribeUrl($storeId, $email) {
		$secret = (string)$this->config->get('config_encryption');

		if ($secret === '') {
			throw new \RuntimeException('OpenCart encryption secret is not configured.');
		}

		$encodedEmail = $this->base64UrlEncode(strtolower(trim($email)));
		$signature = $this->unsubscribeSignature($storeId, strtolower(trim($email)), $secret);
		$url = $this->url->link(
			'extension/module/agmedia_review_reminder/unsubscribe',
			'e=' . rawurlencode($encodedEmail) . '&t=' . $signature,
			true
		);

		return html_entity_decode($url, ENT_QUOTES, 'UTF-8');
	}

	private function unsubscribeSignature($storeId, $email, $secret) {
		return hash_hmac('sha256', "agmedia_review_reminder\n" . (int)$storeId . "\n" . strtolower(trim($email)), $secret);
	}

	private function base64UrlEncode($value) {
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}

	private function base64UrlDecode($value) {
		if (!is_string($value) || $value === '' || strlen($value) > 512 || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
			return false;
		}

		$padding = strlen($value) % 4;

		if ($padding) {
			$value .= str_repeat('=', 4 - $padding);
		}

		return base64_decode(strtr($value, '-_', '+/'), true);
	}

	private function requestMethod() {
		return isset($this->request->server['REQUEST_METHOD']) ? strtoupper($this->request->server['REQUEST_METHOD']) : 'GET';
	}

	private function addSecurityHeaders($json) {
		$this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		$this->response->addHeader('Pragma: no-cache');
		$this->response->addHeader('Expires: 0');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->addHeader('Referrer-Policy: no-referrer');

		if (!$json) {
			$this->response->addHeader("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
		}

		$this->response->addHeader($json ? 'Content-Type: application/json; charset=utf-8' : 'Content-Type: text/html; charset=utf-8');
	}

	private function respondJson(array $data, $statusCode = 200) {
		$this->addStatusHeader($statusCode);
		$this->response->setOutput(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	private function respondHtml($message, $statusCode = 200) {
		$this->addStatusHeader($statusCode);
		$storeName = htmlspecialchars((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
		$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
		$this->response->setOutput('<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Podsjetnik za recenziju</title></head><body style="margin:0;background:#f4f4f4;font-family:Arial,sans-serif;color:#222"><main style="max-width:620px;margin:60px auto;padding:30px;background:#fff;border-radius:8px"><h1 style="font-size:24px">' . $storeName . '</h1><p style="font-size:17px;line-height:1.6">' . $message . '</p></main></body></html>');
	}

	private function respondUnsubscribeConfirmation($encodedEmail, $signature) {
		$this->addStatusHeader(200);
		$storeName = htmlspecialchars((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
		$action = htmlspecialchars(
			html_entity_decode($this->url->link('extension/module/agmedia_review_reminder/unsubscribe', '', true), ENT_QUOTES, 'UTF-8'),
			ENT_QUOTES,
			'UTF-8'
		);
		$encodedEmail = htmlspecialchars($encodedEmail, ENT_QUOTES, 'UTF-8');
		$signature = htmlspecialchars($signature, ENT_QUOTES, 'UTF-8');
		$this->response->setOutput('<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Potvrda odjave</title></head><body style="margin:0;background:#f4f4f4;font-family:Arial,sans-serif;color:#222"><main style="max-width:620px;margin:60px auto;padding:30px;background:#fff;border-radius:8px"><h1 style="font-size:24px">' . $storeName . '</h1><p style="font-size:17px;line-height:1.6">Želite li prestati primati podsjetnike za recenziju?</p><form method="post" action="' . $action . '"><input type="hidden" name="e" value="' . $encodedEmail . '"><input type="hidden" name="t" value="' . $signature . '"><button type="submit" style="border:0;border-radius:5px;background:#2563eb;color:#fff;padding:12px 18px;font-size:16px;cursor:pointer">Potvrdi odjavu</button></form></main></body></html>');
	}

	private function addStatusHeader($statusCode) {
		$phrases = array(
			200 => 'OK',
			400 => 'Bad Request',
			403 => 'Forbidden',
			405 => 'Method Not Allowed',
			409 => 'Conflict',
			500 => 'Internal Server Error',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable'
		);
		$protocol = isset($this->request->server['SERVER_PROTOCOL']) && preg_match('#^HTTP/\d(?:\.\d)?$#', $this->request->server['SERVER_PROTOCOL'])
			? $this->request->server['SERVER_PROTOCOL']
			: 'HTTP/1.1';
		$phrase = isset($phrases[$statusCode]) ? $phrases[$statusCode] : 'OK';
		$this->response->addHeader($protocol . ' ' . (int)$statusCode . ' ' . $phrase);
	}
}
