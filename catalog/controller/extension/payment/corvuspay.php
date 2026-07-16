<?php
class ControllerExtensionPaymentCorvusPay extends Controller {
	private const LOG_PREFIX = '[CorvusPay] ';

	public function index() {
		$this->load->language('extension/payment/corvuspay');
		$this->load->model('checkout/order');

		if (empty($this->session->data['order_id'])) {
			$this->log->write(self::LOG_PREFIX . 'Payment form requested without an order ID.');

			return '';
		}

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		if (!$order_info || $order_info['payment_code'] !== 'corvuspay') {
			$this->log->write(self::LOG_PREFIX . 'Payment form requested for an invalid order or payment method.');

			return '';
		}

		$data['action'] = $this->config->get('payment_corvuspay_test')
			? 'https://wallet.test.corvuspay.com/checkout/'
			: 'https://wallet.corvuspay.com/checkout/';
		$data['confirm_url'] = $this->url->link('extension/payment/corvuspay/confirm', '', true);
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['text_loading'] = $this->language->get('text_loading');
		$pending_status_id = (int)$this->config->get('payment_corvuspay_pending_status_id');
		$data['use_confirm'] = $pending_status_id > 0 && $this->isSafePendingStatus($pending_status_id);

		$parameters = array(
			'amount' => number_format($order_info['total'], 2, '.', ''),
			'cardholder_address' => $order_info['payment_address_1'],
			'cardholder_city' => $order_info['payment_city'],
			'cardholder_country' => $order_info['payment_iso_code_2'],
			'cardholder_email' => $order_info['email'],
			'cardholder_name' => $order_info['payment_firstname'],
			'cardholder_phone' => $order_info['telephone'],
			'cardholder_surname' => $order_info['payment_lastname'],
			'cardholder_zip_code' => $order_info['payment_postcode'],
			'cart' => 'Web shop kupnja ' . $order_info['order_id'],
			'currency' => $order_info['currency_code'],
			'language' => substr($order_info['language_code'], 0, 2),
			'order_number' => (string)$order_info['order_id'],
			'payment_all' => 'Y0299',
			'require_complete' => 'false',
			'store_id' => (string)$this->config->get('payment_corvuspay_merchant'),
			'version' => '1.3'
		);

		$parameters['signature'] = $this->calculateSignature(
			$parameters,
			(string)$this->config->get('payment_corvuspay_password')
		);

		$data['parameters'] = $parameters;

		return $this->load->view('extension/payment/corvuspay', $data);
	}

	public function confirm() {
		$this->load->language('extension/payment/corvuspay');

		$json = array();

		if (empty($this->session->data['order_id']) ||
			empty($this->session->data['payment_method']['code']) ||
			$this->session->data['payment_method']['code'] !== 'corvuspay') {
			$json['error'] = $this->language->get('error_confirm_order');
		} else {
			$this->load->model('checkout/order');

			$order_id = (int)$this->session->data['order_id'];
			$order_info = $this->model_checkout_order->getOrder($order_id);

			if (!$order_info || $order_info['payment_code'] !== 'corvuspay') {
				$json['error'] = $this->language->get('error_confirm_order');
			} else {
				$pending_status_id = (int)$this->config->get('payment_corvuspay_pending_status_id');

				if ((int)$order_info['order_status_id'] === 0 && $pending_status_id > 0) {
					if ($this->isSafePendingStatus($pending_status_id)) {
						$this->model_checkout_order->addOrderHistory(
							$order_id,
							$pending_status_id,
							$this->language->get('text_pending_comment'),
							false
						);
					} else {
						$this->log->write(
							self::LOG_PREFIX . 'Pending status ' . $pending_status_id .
							' is a processing/complete status; order ' . $order_id . ' was left at status 0.'
						);
					}
				}

				$this->log->write(self::LOG_PREFIX . 'Payment initiated for order ' . $order_id . '.');
				$json['success'] = true;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function callback() {
		$this->load->language('extension/payment/corvuspay');
		$this->load->model('checkout/order');

		$callback_data = $this->getCallbackData();

		if (empty($callback_data['order_number']) || !ctype_digit($callback_data['order_number'])) {
			$this->failCallback('Callback received without a valid numeric order number.');
		}

		$order_id = (int)$callback_data['order_number'];
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info || $order_info['payment_code'] !== 'corvuspay') {
			$this->failCallback('Callback received for an unknown or non-CorvusPay order ' . $order_id . '.');
		}

		if (empty($callback_data['signature']) || !$this->verifySignature($callback_data)) {
			$this->failCallback('Invalid callback signature for order ' . $order_id . '.');
		}

		if (!isset($callback_data['approval_code']) || $callback_data['approval_code'] === '') {
			$this->failCallback('Successful callback for order ' . $order_id . ' has no approval code.');
		}

		$approval_code = preg_replace('/[^A-Za-z0-9_-]/', '', $callback_data['approval_code']);

		if ($approval_code === '') {
			$this->failCallback('Successful callback for order ' . $order_id . ' has an invalid approval code.');
		}

		$success_status_id = (int)$this->config->get('payment_corvuspay_order_status_id');

		if ($success_status_id < 1) {
			$this->failCallback('No successful order status is configured for order ' . $order_id . '.');
		}

		$current_status_id = (int)$order_info['order_status_id'];
		$pending_status_id = (int)$this->config->get('payment_corvuspay_pending_status_id');
		$default_status_id = (int)$this->config->get('config_order_status_id');
		$updatable_statuses = array(0, $default_status_id);

		if ($pending_status_id > 0) {
			$updatable_statuses[] = $pending_status_id;
		}

		$updatable_statuses = array_values(array_unique($updatable_statuses));

		if ($current_status_id === $success_status_id) {
			$this->log->write(self::LOG_PREFIX . 'Duplicate successful callback for order ' . $order_id . ' ignored.');
		} elseif (in_array($current_status_id, $updatable_statuses, true)) {
			$comment = sprintf(
				$this->language->get('text_success_comment'),
				$approval_code
			);

			$this->model_checkout_order->addOrderHistory($order_id, $success_status_id, $comment, true);
			$this->log->write(self::LOG_PREFIX . 'Order ' . $order_id . ' marked as paid.');
		} else {
			$this->log->write(
				self::LOG_PREFIX . 'Valid successful callback for order ' . $order_id .
				' did not overwrite existing status ' . $current_status_id . '.'
			);
		}

		$this->response->redirect($this->url->link('checkout/success', '', true));
	}

	private function calculateSignature(array $parameters, string $secret_key): string {
		unset($parameters['signature']);
		ksort($parameters, SORT_STRING);

		$message = '';

		foreach ($parameters as $key => $value) {
			if (!is_scalar($value)) {
				continue;
			}

			$message .= $key . (string)$value;
		}

		return hash_hmac('sha256', $message, $secret_key);
	}

	private function verifySignature(array $callback_data): bool {
		$provided_signature = strtolower((string)$callback_data['signature']);

		if (!preg_match('/^[a-f0-9]{64}$/', $provided_signature)) {
			return false;
		}

		$expected_signature = $this->calculateSignature(
			$callback_data,
			(string)$this->config->get('payment_corvuspay_password')
		);

		return hash_equals($expected_signature, $provided_signature);
	}

	private function getCallbackData(): array {
		$callback_data = array();

		foreach (array($_GET, $_POST) as $source) {
			foreach ($source as $key => $value) {
				if (is_scalar($value)) {
					$callback_data[(string)$key] = (string)$value;
				}
			}
		}

		// The OpenCart route is part of our Success URL, not CorvusPay's signed response.
		unset($callback_data['route']);

		return $callback_data;
	}

	private function isSafePendingStatus(int $status_id): bool {
		$processing_statuses = array_map('intval', (array)$this->config->get('config_processing_status'));
		$complete_statuses = array_map('intval', (array)$this->config->get('config_complete_status'));

		return !in_array($status_id, array_unique(array_merge($processing_statuses, $complete_statuses)), true);
	}

	private function failCallback(string $log_message): void {
		$this->log->write(self::LOG_PREFIX . $log_message);
		$this->session->data['error'] = $this->language->get('error_invalid_response');
		$this->response->redirect($this->url->link('checkout/checkout', '', true));
	}
}
