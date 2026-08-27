<?php
require_once(DIR_SYSTEM . 'library/agmedia_review_reminder/policy.php');

class ControllerExtensionModuleAgmediaReviewReminder extends Controller {

	private $error = array();
	private $route = 'extension/module/agmedia_review_reminder';
	private $setting_code = 'module_agmedia_review_reminder';

	public function index() {
		$this->load->language($this->route);
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('localisation/order_status');
		$this->load->model($this->route);

		$defaults = $this->getDefaults();
		$current_settings = $this->model_setting_setting->getSetting($this->setting_code);
		$settings = array_replace($defaults, $current_settings);

		$current_token = isset($current_settings['module_agmedia_review_reminder_cron_token']) ? (string)$current_settings['module_agmedia_review_reminder_cron_token'] : '';

		foreach (array('module_agmedia_review_reminder_order_status_id', 'module_agmedia_review_reminder_subject', 'module_agmedia_review_reminder_body') as $array_key) {
			if (!isset($settings[$array_key]) || !is_array($settings[$array_key])) {
				$settings[$array_key] = $defaults[$array_key];
			}
		}

		$settings['module_agmedia_review_reminder_body'] = $this->normaliseBodies($settings['module_agmedia_review_reminder_body']);

		$settings['module_agmedia_review_reminder_min_order_date'] = AgmediaReviewReminderPolicy::normaliseMinOrderDate(
			isset($settings['module_agmedia_review_reminder_min_order_date']) ? $settings['module_agmedia_review_reminder_min_order_date'] : ''
		);

		if (!preg_match('/^[a-f0-9]{64}$/D', $current_token) && $this->user->hasPermission('modify', $this->route)) {
			$settings['module_agmedia_review_reminder_cron_token'] = $this->generateToken();
			$this->model_setting_setting->editSetting($this->setting_code, $settings);
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['regenerate_token'])) {
			if ($this->validate()) {
				$settings = $this->normaliseSettings($this->request->post, $this->generateToken());
				$this->model_setting_setting->editSetting($this->setting_code, $settings);
				$this->session->data['success'] = $this->language->get('text_token_success');

				$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true));
				return;
			}
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && !isset($this->request->post['regenerate_token']) && $this->validate()) {
			$settings = $this->normaliseSettings($this->request->post, $settings['module_agmedia_review_reminder_cron_token']);
			$this->model_setting_setting->editSetting($this->setting_code, $settings);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$settings = array_replace($settings, $this->request->post);

			if (!isset($this->request->post['module_agmedia_review_reminder_order_status_id'])) {
				$settings['module_agmedia_review_reminder_order_status_id'] = array();
			}

			if (isset($settings['module_agmedia_review_reminder_body']) && is_array($settings['module_agmedia_review_reminder_body'])) {
				$settings['module_agmedia_review_reminder_body'] = $this->normaliseBodies($settings['module_agmedia_review_reminder_body']);
			}
		}

		$data = $this->language->all();
		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_order_status'] = isset($this->error['order_status']) ? $this->error['order_status'] : '';
		$data['error_min_order_date'] = isset($this->error['min_order_date']) ? $this->error['min_order_date'] : '';
		$data['error_delay_days'] = isset($this->error['delay_days']) ? $this->error['delay_days'] : '';
		$data['error_batch_size'] = isset($this->error['batch_size']) ? $this->error['batch_size'] : '';
		$data['error_hourly_limit'] = isset($this->error['hourly_limit']) ? $this->error['hourly_limit'] : '';
		$data['error_max_age_days'] = isset($this->error['max_age_days']) ? $this->error['max_age_days'] : '';
		$data['error_max_attempts'] = isset($this->error['max_attempts']) ? $this->error['max_attempts'] : '';
		$data['error_subject'] = isset($this->error['subject']) ? $this->error['subject'] : array();
		$data['error_body'] = isset($this->error['body']) ? $this->error['body'] : array();

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['action'] = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['min_order_date_floor'] = AgmediaReviewReminderPolicy::MIN_ORDER_DATE;
		$data['sender_name'] = (string)$this->config->get('config_name');
		$data['sender_email'] = (string)($this->config->get('config_mail_smtp_username') ?: $this->config->get('config_email'));
		$data['mail_engine'] = (string)$this->config->get('config_mail_engine');
		$data['smtp_host'] = (string)$this->config->get('config_mail_smtp_hostname');
		$data['smtp_port'] = (int)$this->config->get('config_mail_smtp_port');
		$data['smtp_ready'] = strtolower($data['mail_engine']) === 'smtp'
			&& $data['smtp_host'] !== ''
			&& (string)$this->config->get('config_mail_smtp_username') !== ''
			&& (string)$this->config->get('config_mail_smtp_password') !== '';
		$data['review_status'] = (bool)$this->config->get('config_review_status');
		$data['review_guest'] = (bool)$this->config->get('config_review_guest');
		$data['recent_deliveries'] = array();
		$data['delivery_summary'] = array();

		if ($this->model_extension_module_agmedia_review_reminder->hasDeliveryTable()) {
			$data['recent_deliveries'] = $this->model_extension_module_agmedia_review_reminder->getRecentDeliveries(20);
			$data['delivery_summary'] = $this->model_extension_module_agmedia_review_reminder->getDeliverySummary();
		}

		$data['delivery_statuses'] = array(
			'pending' => $this->language->get('status_pending'),
			'processing' => $this->language->get('status_processing'),
			'sending' => $this->language->get('status_sending'),
			'retry' => $this->language->get('status_retry'),
			'sent' => $this->language->get('status_sent'),
			'failed' => $this->language->get('status_failed'),
			'cancelled' => $this->language->get('status_cancelled'),
			'suppressed' => $this->language->get('status_suppressed'),
			'unknown' => $this->language->get('status_unknown')
		);

		foreach ($settings as $key => $value) {
			$data[$key] = $value;
		}

		$data['module_agmedia_review_reminder_body'] = $this->prepareBodiesForTextarea(
			(array)$data['module_agmedia_review_reminder_body']
		);

		$data['module_agmedia_review_reminder_order_status_id'] = array_map('intval', (array)$data['module_agmedia_review_reminder_order_status_id']);

		if (empty($data['module_agmedia_review_reminder_cron_token'])) {
			$data['module_agmedia_review_reminder_cron_token'] = $this->generateToken();
		}

		$catalog_url = defined('HTTPS_CATALOG') && HTTPS_CATALOG ? HTTPS_CATALOG : HTTP_CATALOG;
		$data['cron_url'] = rtrim($catalog_url, '/') . '/index.php?route=extension/module/agmedia_review_reminder/cron&token=' . rawurlencode($data['module_agmedia_review_reminder_cron_token']);

		$this->document->addScript('view/javascript/summernote/summernote.js');
		$this->document->addScript('view/javascript/summernote/opencart.js');
		$this->document->addStyle('view/javascript/summernote/summernote.css');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->route, $data));
	}

	public function install() {
		$this->load->model('setting/setting');
		$current = $this->model_setting_setting->getSetting($this->setting_code);
		$defaults = $this->getDefaults();
		$settings = array_replace($defaults, $current);

		foreach (array('module_agmedia_review_reminder_order_status_id', 'module_agmedia_review_reminder_subject', 'module_agmedia_review_reminder_body') as $array_key) {
			if (!isset($settings[$array_key]) || !is_array($settings[$array_key])) {
				$settings[$array_key] = $defaults[$array_key];
			}
		}

		$settings['module_agmedia_review_reminder_body'] = $this->normaliseBodies($settings['module_agmedia_review_reminder_body']);

		$settings['module_agmedia_review_reminder_min_order_date'] = AgmediaReviewReminderPolicy::normaliseMinOrderDate(
			isset($settings['module_agmedia_review_reminder_min_order_date']) ? $settings['module_agmedia_review_reminder_min_order_date'] : ''
		);

		if (empty($settings['module_agmedia_review_reminder_cron_token']) || !preg_match('/^[a-f0-9]{64}$/D', (string)$settings['module_agmedia_review_reminder_cron_token'])) {
			$settings['module_agmedia_review_reminder_cron_token'] = $defaults['module_agmedia_review_reminder_cron_token'];
		}

		// Retire the legacy endpoint before importing any legacy state so there is
		// no installation window in which both workers can mutate the mail queue.
		$this->model_setting_setting->editSetting($this->setting_code, $settings);

		$this->load->model($this->route);
		$this->model_extension_module_agmedia_review_reminder->install();
	}

	public function uninstall() {
		$this->load->model($this->route);
		$this->model_extension_module_agmedia_review_reminder->uninstall();

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting($this->setting_code);
		$this->model_setting_setting->editSetting($this->setting_code, array(
			'module_agmedia_review_reminder_status' => 0,
			'module_agmedia_review_reminder_legacy_endpoint_disabled' => 1
		));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$order_statuses = isset($this->request->post['module_agmedia_review_reminder_order_status_id']) ? $this->request->post['module_agmedia_review_reminder_order_status_id'] : array();

		if (!is_array($order_statuses) || !$this->normaliseOrderStatusIds($order_statuses)) {
			$this->error['order_status'] = $this->language->get('error_order_status');
		}

		$min_order_date = isset($this->request->post['module_agmedia_review_reminder_min_order_date'])
			? trim((string)$this->request->post['module_agmedia_review_reminder_min_order_date'])
			: '';

		if (!AgmediaReviewReminderPolicy::isValidOrderDate($min_order_date) || $min_order_date < AgmediaReviewReminderPolicy::MIN_ORDER_DATE) {
			$this->error['min_order_date'] = sprintf($this->language->get('error_min_order_date'), AgmediaReviewReminderPolicy::MIN_ORDER_DATE);
		}

		$this->validateInteger('delay_days', 0, 90);
		$this->validateInteger('batch_size', 1, 10);
		$this->validateInteger('hourly_limit', 1, 2);
		$this->validateInteger('max_age_days', 1, 365);
		$this->validateInteger('max_attempts', 1, 5);

		$delay_days = isset($this->request->post['module_agmedia_review_reminder_delay_days']) ? (int)$this->request->post['module_agmedia_review_reminder_delay_days'] : 0;
		$max_age_days = isset($this->request->post['module_agmedia_review_reminder_max_age_days']) ? (int)$this->request->post['module_agmedia_review_reminder_max_age_days'] : 0;

		if ($max_age_days < $delay_days) {
			$this->error['max_age_days'] = $this->language->get('error_max_age_delay');
		}

		$languages = $this->model_localisation_language->getLanguages();
		$subjects = isset($this->request->post['module_agmedia_review_reminder_subject']) && is_array($this->request->post['module_agmedia_review_reminder_subject']) ? $this->request->post['module_agmedia_review_reminder_subject'] : array();
		$bodies = isset($this->request->post['module_agmedia_review_reminder_body']) && is_array($this->request->post['module_agmedia_review_reminder_body']) ? $this->normaliseBodies($this->request->post['module_agmedia_review_reminder_body']) : array();

		foreach ($languages as $language) {
			$language_id = (int)$language['language_id'];
			$subject = isset($subjects[$language_id]) ? trim($subjects[$language_id]) : '';
			$body = isset($bodies[$language_id]) ? trim($bodies[$language_id]) : '';

			if (utf8_strlen($subject) < 3 || utf8_strlen($subject) > 180) {
				$this->error['subject'][$language_id] = $this->language->get('error_subject');
			}

			if (utf8_strlen(strip_tags(html_entity_decode($body, ENT_QUOTES, 'UTF-8'))) < 10 || utf8_strlen($body) > 65535) {
				$this->error['body'][$language_id] = $this->language->get('error_body');
			} elseif (strpos($body, '{products}') === false || strpos($body, '{unsubscribe_url}') === false) {
				$this->error['body'][$language_id] = $this->language->get('error_body_placeholders');
			}
		}

		return !$this->error;
	}

	private function validateInteger($name, $minimum, $maximum) {
		$key = 'module_agmedia_review_reminder_' . $name;
		$value = isset($this->request->post[$key]) ? filter_var($this->request->post[$key], FILTER_VALIDATE_INT) : false;

		if ($value === false || $value < $minimum || $value > $maximum) {
			$this->error[$name] = sprintf($this->language->get('error_number_range'), $minimum, $maximum);
		}
	}

	private function normaliseSettings(array $post, $cron_token) {
		$order_statuses = isset($post['module_agmedia_review_reminder_order_status_id']) && is_array($post['module_agmedia_review_reminder_order_status_id']) ? $post['module_agmedia_review_reminder_order_status_id'] : array();
		$order_statuses = $this->normaliseOrderStatusIds($order_statuses);

		return array(
			'module_agmedia_review_reminder_status' => !empty($post['module_agmedia_review_reminder_status']) ? 1 : 0,
			'module_agmedia_review_reminder_order_status_id' => $order_statuses,
			'module_agmedia_review_reminder_min_order_date' => AgmediaReviewReminderPolicy::normaliseMinOrderDate(
				isset($post['module_agmedia_review_reminder_min_order_date']) ? $post['module_agmedia_review_reminder_min_order_date'] : ''
			),
			'module_agmedia_review_reminder_delay_days' => (int)$post['module_agmedia_review_reminder_delay_days'],
			'module_agmedia_review_reminder_batch_size' => (int)$post['module_agmedia_review_reminder_batch_size'],
			'module_agmedia_review_reminder_hourly_limit' => (int)$post['module_agmedia_review_reminder_hourly_limit'],
			'module_agmedia_review_reminder_max_age_days' => (int)$post['module_agmedia_review_reminder_max_age_days'],
			'module_agmedia_review_reminder_max_attempts' => (int)$post['module_agmedia_review_reminder_max_attempts'],
			'module_agmedia_review_reminder_subject' => isset($post['module_agmedia_review_reminder_subject']) && is_array($post['module_agmedia_review_reminder_subject']) ? $post['module_agmedia_review_reminder_subject'] : array(),
			'module_agmedia_review_reminder_body' => isset($post['module_agmedia_review_reminder_body']) && is_array($post['module_agmedia_review_reminder_body']) ? $this->normaliseBodies($post['module_agmedia_review_reminder_body']) : array(),
			'module_agmedia_review_reminder_cron_token' => $cron_token ?: $this->generateToken(),
			'module_agmedia_review_reminder_legacy_endpoint_disabled' => 1
		);
	}

	private function normaliseOrderStatusIds(array $order_statuses) {
		$valid = array();

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $order_status) {
			$valid[(int)$order_status['order_status_id']] = true;
		}

		$result = array();

		foreach ($order_statuses as $order_status_id) {
			$order_status_id = (int)$order_status_id;

			if ($order_status_id > 0 && isset($valid[$order_status_id])) {
				$result[$order_status_id] = $order_status_id;
			}
		}

		return array_values($result);
	}

	private function normaliseBodies(array $bodies) {
		foreach ($bodies as $language_id => $body) {
			$bodies[$language_id] = AgmediaReviewReminderPolicy::normaliseBodyHtml($body);
		}

		return $bodies;
	}

	private function prepareBodiesForTextarea(array $bodies) {
		foreach ($bodies as $language_id => $body) {
			$bodies[$language_id] = AgmediaReviewReminderPolicy::prepareBodyForTextarea($body);
		}

		return $bodies;
	}

	private function getDefaults() {
		$subjects = array();
		$bodies = array();

		$this->load->model('localisation/language');

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$language_id = (int)$language['language_id'];
			$is_croatian = strtolower($language['code']) === 'hr-hr' || strpos(strtolower($language['code']), 'hr') === 0;

			if ($is_croatian) {
				$subjects[$language_id] = 'Kako ste zadovoljni narudžbom #{order_id}?';
				$bodies[$language_id] = '<p>Pozdrav {customer_name},</p><p>hvala na narudžbi <strong>#{order_id}</strong>. Vaše iskustvo pomaže drugim kupcima, pa vas pozivamo da ostavite recenziju kupljenih proizvoda.</p>{products}<p>Hvala,<br>{store_name}</p><p style="font-size:12px;color:#777;"><a href="{unsubscribe_url}">Ne želim više primati podsjetnike za recenziju</a></p>';
			} else {
				$subjects[$language_id] = 'How was your order #{order_id}?';
				$bodies[$language_id] = '<p>Hello {customer_name},</p><p>Thank you for order <strong>#{order_id}</strong>. Your experience helps other customers, so we invite you to review the products you purchased.</p>{products}<p>Thank you,<br>{store_name}</p><p style="font-size:12px;color:#777;"><a href="{unsubscribe_url}">Stop receiving review reminders</a></p>';
			}
		}

		$complete_statuses = array_values(array_filter(array_map('intval', (array)$this->config->get('config_complete_status'))));

		return array(
			'module_agmedia_review_reminder_status' => 0,
			'module_agmedia_review_reminder_order_status_id' => $complete_statuses,
			'module_agmedia_review_reminder_min_order_date' => AgmediaReviewReminderPolicy::MIN_ORDER_DATE,
			'module_agmedia_review_reminder_delay_days' => 7,
			'module_agmedia_review_reminder_batch_size' => 1,
			'module_agmedia_review_reminder_hourly_limit' => 1,
			'module_agmedia_review_reminder_max_age_days' => 45,
			'module_agmedia_review_reminder_max_attempts' => 3,
			'module_agmedia_review_reminder_subject' => $subjects,
			'module_agmedia_review_reminder_body' => $bodies,
			'module_agmedia_review_reminder_cron_token' => $this->generateToken(),
			'module_agmedia_review_reminder_legacy_endpoint_disabled' => 1
		);
	}

	private function generateToken() {
		try {
			return bin2hex(random_bytes(32));
		} catch (Exception $exception) {
			return hash('sha256', uniqid((string)mt_rand(), true) . (string)$this->config->get('config_encryption'));
		}
	}
}
