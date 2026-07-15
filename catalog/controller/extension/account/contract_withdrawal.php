<?php
class ControllerExtensionAccountContractWithdrawal extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/account/contract_withdrawal');
		$this->load->model('extension/account/contract_withdrawal');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setDescription($this->language->get('meta_description'));

		if (empty($this->session->data['contract_withdrawal_token'])) {
			$this->session->data['contract_withdrawal_token'] = token(32);
		}

		$order_info = $this->getMatchedOrder();
		$order_products = $order_info ? $this->model_extension_account_contract_withdrawal->getOrderProducts($order_info['order_id']) : array();
		$preview = false;
		$statement = '';
		$selected_products = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate($order_info, $order_products)) {
			$selected_products = $this->getSelectedProducts($order_products);
			$statement = $this->buildStatement($selected_products);
			$preview = empty($this->request->post['confirm']);

			if (!$preview) {
				$token = token(40);
				$withdrawal_data = array(
					'order_id'         => (int)$this->request->post['order_id'],
					'customer_id'      => $order_info ? (int)$order_info['customer_id'] : ($this->customer->isLogged() ? (int)$this->customer->getId() : 0),
					'firstname'        => trim($this->request->post['firstname']),
					'lastname'         => trim($this->request->post['lastname']),
					'email'            => trim($this->request->post['email']),
					'telephone'        => trim($this->request->post['telephone']),
					'address'          => trim($this->request->post['address']),
					'refund_iban'      => $this->request->post['refund_iban'],
					'withdrawal_scope' => $this->request->post['withdrawal_scope'],
					'products'         => $selected_products,
					'statement'        => $statement,
					'comment'          => trim($this->request->post['comment']),
					'token'            => $token,
					'ip'               => isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '',
					'user_agent'       => isset($this->request->server['HTTP_USER_AGENT']) ? utf8_substr($this->request->server['HTTP_USER_AGENT'], 0, 255) : '',
					'date_ordered'     => $order_info ? date('Y-m-d', strtotime($order_info['date_added'])) : ''
				);

				$contract_withdrawal_id = $this->model_extension_account_contract_withdrawal->addWithdrawal($withdrawal_data);
				$withdrawal_info = $this->model_extension_account_contract_withdrawal->getWithdrawal($contract_withdrawal_id);

				$this->sendCustomerMail($withdrawal_info);
				$this->sendAdminMail($withdrawal_info);
				unset($this->session->data['contract_withdrawal_token']);

				$this->response->redirect($this->url->link('extension/account/contract_withdrawal/success', 'contract_withdrawal_id=' . $contract_withdrawal_id . '&token=' . $token, true));
			}
		}

		$data = $this->getCommonData();
		$data['action'] = $this->url->link('extension/account/contract_withdrawal', '', true);
		$data['preview'] = $preview;
		$data['statement'] = $statement;
		$data['selected_products'] = $selected_products;
		$data['order_products'] = $order_products;
		$data['order_matched'] = (bool)$order_info;
		$data['errors'] = $this->error;
		$data['form_token'] = $this->session->data['contract_withdrawal_token'];

		$data['form'] = $this->getFormData($order_info);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/account/contract_withdrawal_form', $data));
	}

	public function success() {
		$this->load->language('extension/account/contract_withdrawal');
		$this->load->model('extension/account/contract_withdrawal');

		$this->document->setTitle($this->language->get('text_success_title'));

		$contract_withdrawal_id = isset($this->request->get['contract_withdrawal_id']) ? (int)$this->request->get['contract_withdrawal_id'] : 0;
		$token = isset($this->request->get['token']) ? $this->request->get['token'] : '';
		$withdrawal_info = $this->model_extension_account_contract_withdrawal->getWithdrawalByToken($contract_withdrawal_id, $token);

		if (!$withdrawal_info) {
			return new Action('error/not_found');
		}

		$data = $this->getCommonData();
		$data['contract_withdrawal_id'] = $withdrawal_info['contract_withdrawal_id'];
		$data['order_id'] = $withdrawal_info['order_id'];
		$data['email'] = $withdrawal_info['email'];
		$data['status'] = $this->language->get('text_status_new');
		$data['statement'] = $withdrawal_info['statement'];
		$data['date_submitted'] = $this->formatDateTime($withdrawal_info['date_submitted']);
		$data['continue'] = $this->url->link('common/home', '', true);
		$data['account'] = $this->url->link('account/account', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/account/contract_withdrawal_success', $data));
	}

	private function getCommonData() {
		$data = array();

		$keys = array(
			'heading_title',
			'text_account',
			'text_intro',
			'text_order',
			'text_customer',
			'text_products',
			'text_full_order',
			'text_selected_items',
			'text_no_products',
			'text_preview',
			'text_preview_help',
			'text_statement',
			'text_success_title',
			'text_success_message',
			'text_reference',
			'text_submitted',
			'text_status',
			'text_back_account',
			'text_continue',
			'text_required_note',
			'entry_order_id',
			'entry_firstname',
			'entry_lastname',
			'entry_email',
			'entry_telephone',
			'entry_address',
			'entry_refund_iban',
			'entry_scope',
			'entry_comment',
			'button_preview',
			'button_confirm',
			'button_edit'
		);

		foreach ($keys as $key) {
			$data[$key] = $this->language->get($key);
		}

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/account/contract_withdrawal', '', true)
			)
		);

		return $data;
	}

	private function getFormData($order_info) {
		$form = array(
			'order_id'         => '',
			'firstname'        => $this->customer->isLogged() ? $this->customer->getFirstName() : '',
			'lastname'         => $this->customer->isLogged() ? $this->customer->getLastName() : '',
			'email'            => $this->customer->isLogged() ? $this->customer->getEmail() : '',
			'telephone'        => $this->customer->isLogged() ? $this->customer->getTelephone() : '',
			'address'          => '',
			'refund_iban'      => '',
			'withdrawal_scope' => 'full',
			'comment'          => '',
			'order_products'   => array()
		);

		if ($order_info) {
			$form['order_id'] = $order_info['order_id'];
			$form['firstname'] = $order_info['firstname'];
			$form['lastname'] = $order_info['lastname'];
			$form['email'] = $order_info['email'];
			$form['telephone'] = $order_info['telephone'];
			$form['address'] = trim($order_info['payment_address_1'] . "\n" . $order_info['payment_postcode'] . ' ' . $order_info['payment_city']);
		} elseif (isset($this->request->get['order_id'])) {
			$form['order_id'] = (int)$this->request->get['order_id'];
		}

		foreach ($form as $key => $value) {
			if (isset($this->request->post[$key])) {
				$form[$key] = is_array($this->request->post[$key]) ? $this->request->post[$key] : trim($this->request->post[$key]);
			}
		}

		return $form;
	}

	private function getMatchedOrder() {
		if (isset($this->request->post['order_id'])) {
			$order_id = (int)$this->request->post['order_id'];
		} elseif (isset($this->request->get['order_id'])) {
			$order_id = (int)$this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$order_id) {
			return false;
		}

		$order_info = $this->model_extension_account_contract_withdrawal->getCustomerOrder($order_id);

		if ($order_info) {
			return $order_info;
		}

		if (isset($this->request->post['email']) && filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			return $this->model_extension_account_contract_withdrawal->getOrderByEmail($order_id, $this->request->post['email']);
		}

		return false;
	}

	private function validate($order_info, $order_products) {
		foreach (array('order_id', 'firstname', 'lastname', 'email', 'telephone', 'address', 'refund_iban', 'withdrawal_scope', 'comment') as $key) {
			if (!isset($this->request->post[$key])) {
				$this->request->post[$key] = '';
			}
		}

		$this->request->post['refund_iban'] = $this->normalizeIban($this->request->post['refund_iban']);

		if (!empty($this->request->post['website'])) {
			$this->error['warning'] = $this->language->get('error_confirm');
		}

		$posted_token = isset($this->request->post['form_token']) ? (string)$this->request->post['form_token'] : '';
		$session_token = isset($this->session->data['contract_withdrawal_token']) ? (string)$this->session->data['contract_withdrawal_token'] : '';

		if (!$posted_token || !$session_token || !hash_equals($session_token, $posted_token)) {
			$this->error['warning'] = $this->language->get('error_session');
		}

		if (empty($this->request->post['order_id'])) {
			$this->error['order_id'] = $this->language->get('error_order_id');
		} elseif (!$order_info) {
			$this->error['order_id'] = $this->language->get('error_order_match');
		}

		if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 64)) {
			$this->error['firstname'] = $this->language->get('error_firstname');
		}

		if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 64)) {
			$this->error['lastname'] = $this->language->get('error_lastname');
		}

		if ((utf8_strlen(trim($this->request->post['email'])) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			$this->error['email'] = $this->language->get('error_email');
		}

		if (utf8_strlen(trim($this->request->post['telephone'])) > 32) {
			$this->error['telephone'] = $this->language->get('error_telephone');
		}

		if (utf8_strlen(trim($this->request->post['address'])) > 1000) {
			$this->error['address'] = $this->language->get('error_address');
		}

		if ($this->request->post['refund_iban'] && !$this->isValidIban($this->request->post['refund_iban'])) {
			$this->error['refund_iban'] = $this->language->get('error_refund_iban');
		}

		if (!isset($this->request->post['withdrawal_scope']) || !in_array($this->request->post['withdrawal_scope'], array('full', 'items'))) {
			$this->request->post['withdrawal_scope'] = 'full';
		}

		if ($this->request->post['withdrawal_scope'] == 'items' && $order_products && empty($this->request->post['order_products'])) {
			$this->error['scope'] = $this->language->get('error_scope');
		}

		if (!empty($this->request->post['confirm']) && empty($this->request->post['confirm_legal'])) {
			$this->error['warning'] = $this->language->get('error_confirm');
		}

		return !$this->error;
	}

	private function getSelectedProducts($order_products) {
		if (!$order_products) {
			return array();
		}

		if (!isset($this->request->post['withdrawal_scope']) || $this->request->post['withdrawal_scope'] == 'full') {
			return $order_products;
		}

		$selected = isset($this->request->post['order_products']) ? (array)$this->request->post['order_products'] : array();
		$products = array();

		foreach ($order_products as $product) {
			if (in_array($product['order_product_id'], $selected)) {
				$products[] = $product;
			}
		}

		return $products;
	}

	private function buildStatement($products) {
		$scope_text = $this->request->post['withdrawal_scope'] == 'items' ? $this->language->get('text_selected_items') : $this->language->get('text_full_order');
		$lines = array();

		$lines[] = 'Ja, ' . trim($this->request->post['firstname']) . ' ' . trim($this->request->post['lastname']) . ', ovim izjavljujem da jednostrano raskidam ugovor za narudžbu #' . (int)$this->request->post['order_id'] . '.';
		$lines[] = 'Opseg: ' . $scope_text . '.';

		if ($products) {
			$lines[] = 'Artikli:';

			foreach ($products as $product) {
				$lines[] = '- ' . $product['name'] . ' (' . $product['model'] . '), količina: ' . $product['quantity'];
			}
		}

		if (trim($this->request->post['comment'])) {
			$lines[] = 'Napomena: ' . trim($this->request->post['comment']);
		}

		if ($this->request->post['refund_iban']) {
			$lines[] = 'IBAN za povrat sredstava: ' . $this->request->post['refund_iban'];
		}

		$lines[] = 'E-mail za potvrdu: ' . trim($this->request->post['email']);
		$lines[] = 'Vrijeme podnošenja: ' . $this->formatDateTime(date('Y-m-d H:i:s'));

		return implode("\n", $lines);
	}

	private function sendCustomerMail($withdrawal_info) {
		$subject = sprintf($this->language->get('email_customer_subject'), $withdrawal_info['contract_withdrawal_id']);
		$message = $this->buildMailMessage($withdrawal_info, true);

		$this->sendMail($withdrawal_info['email'], $subject, $message);
	}

	private function sendAdminMail($withdrawal_info) {
		$subject = sprintf($this->language->get('email_admin_subject'), $withdrawal_info['contract_withdrawal_id']);
		$message = $this->buildMailMessage($withdrawal_info, false);
		$emails = array($this->config->get('config_email'));

		if ($this->config->get('config_mail_alert_email')) {
			foreach (explode(',', $this->config->get('config_mail_alert_email')) as $email) {
				$email = trim($email);

				if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
					$emails[] = $email;
				}
			}
		}

		foreach (array_unique($emails) as $email) {
			if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$this->sendMail($email, $subject, $message);
			}
		}
	}

	private function buildMailMessage($withdrawal_info, $customer_copy) {
		$lines = array();

		if ($customer_copy) {
			$lines[] = 'Potvrđujemo primitak vašeg zahtjeva za jednostrani raskid ugovora.';
		} else {
			$lines[] = 'Zaprimljen je novi zahtjev za jednostrani raskid ugovora.';
		}

		$lines[] = '';
		$lines[] = 'Broj zahtjeva: #' . $withdrawal_info['contract_withdrawal_id'];
		$lines[] = 'Broj narudžbe: #' . $withdrawal_info['order_id'];
		$lines[] = 'Potrošač: ' . $withdrawal_info['firstname'] . ' ' . $withdrawal_info['lastname'];
		$lines[] = 'E-mail: ' . $withdrawal_info['email'];
		if (!empty($withdrawal_info['refund_iban'])) {
			$lines[] = 'IBAN za povrat sredstava: ' . $withdrawal_info['refund_iban'];
		}
		$lines[] = 'Vrijeme zaprimanja: ' . $this->formatDateTime($withdrawal_info['date_submitted']);
		$lines[] = '';
		$lines[] = 'Sadržaj izjave:';
		$lines[] = $withdrawal_info['statement'];

		return implode("\n", $lines);
	}

	private function sendMail($to, $subject, $message) {
		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$mail->setTo($to);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		$mail->setText($message);

		try {
			$mail->send();
		} catch (Exception $e) {
			$this->log->write('Contract withdrawal mail error: ' . $e->getMessage());
		}
	}

	private function normalizeIban($iban) {
		return strtoupper(preg_replace('/\s+/', '', trim($iban)));
	}

	private function isValidIban($iban) {
		return (bool)preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban);
	}

	private function formatDateTime($date) {
		return date('Y-m-d H:i:s', strtotime($date)) . ' (' . date_default_timezone_get() . ')';
	}
}
