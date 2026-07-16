<?php 
class ControllerExtensionPaymentCorvusPay extends Controller {
	private $error = array(); 

	public function index() {
		$this->load->language('extension/payment/corvuspay');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_corvuspay', $this->request->post);				

			$this->session->data['success'] = $this->language->get('text_success');

          $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}


		if (isset($this->error['merchant'])) {
			$data['error_merchant'] = $this->error['merchant'];
		} else {
			$data['error_merchant'] = '';
		}

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
        $data['text_successful'] = $this->language->get('text_successful');
        $data['text_declined'] = $this->language->get('text_declined');
        $data['text_off'] = $this->language->get('text_off');

        $data['text_edit'] = $this->language->get('text_edit');
        $data['help_entry_callback'] = $this->language->get('help_entry_callback');
        $data['help_entry_total'] = $this->language->get('help_entry_total');

		$data['entry_merchant'] = $this->language->get('entry_merchant');
		$data['entry_password'] = $this->language->get('entry_password');
		$data['entry_callback'] = $this->language->get('entry_callback');
		$data['entry_authorisationtype'] = $this->language->get('entry_authorisationtype');
		$data['entry_authorisationtype0'] = $this->language->get('entry_authorisationtype0');
		$data['entry_authorisationtype1'] = $this->language->get('entry_authorisationtype1');
		$data['entry_test'] = $this->language->get('entry_test');
		$data['entry_total'] = $this->language->get('entry_total');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_pending_status'] = $this->language->get('entry_pending_status');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['help_entry_pending_status'] = $this->language->get('help_entry_pending_status');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}


		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/corvuspay', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/corvuspay', 'user_token=' . $this->session->data['user_token'], true);

		
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		if (isset($this->request->post['payment_corvuspay_merchant'])) {
			$data['payment_corvuspay_merchant'] = $this->request->post['payment_corvuspay_merchant'];
		} else {
			$data['payment_corvuspay_merchant'] = $this->config->get('payment_corvuspay_merchant');
		}

		if (isset($this->request->post['payment_corvuspay_password'])) {
			$data['payment_corvuspay_password'] = $this->request->post['payment_corvuspay_password'];
		} else {
			$data['payment_corvuspay_password'] = $this->config->get('payment_corvuspay_password');
		}

        
        if (isset($this->request->post['payment_corvuspay_authorisationtype'])) {
			$data['payment_corvuspay_authorisationtype'] = $this->request->post['payment_corvuspay_authorisationtype'];
		} else {
			$data['payment_corvuspay_authorisationtype'] = $this->config->get('payment_corvuspay_authorisationtype');
		}


		$data['callback'] = HTTPS_CATALOG . 'index.php?route=extension/payment/corvuspay/callback';

		if (isset($this->request->post['payment_corvuspay_test'])) {
			$data['payment_corvuspay_test'] = $this->request->post['payment_corvuspay_test'];
		} else {
			$data['payment_corvuspay_test'] = $this->config->get('payment_corvuspay_test');
		}


		if (isset($this->request->post['payment_corvuspay_total'])) {
			$data['payment_corvuspay_total'] = $this->request->post['payment_corvuspay_total'];
		} else {
			$data['payment_corvuspay_total'] = $this->config->get('payment_corvuspay_total');
		} 

		if (isset($this->request->post['payment_corvuspay_order_status_id'])) {
			$data['payment_corvuspay_order_status_id'] = $this->request->post['payment_corvuspay_order_status_id'];
		} else {
			$data['payment_corvuspay_order_status_id'] = $this->config->get('payment_corvuspay_order_status_id');
		} 

		if (isset($this->request->post['payment_corvuspay_pending_status_id'])) {
			$data['payment_corvuspay_pending_status_id'] = $this->request->post['payment_corvuspay_pending_status_id'];
		} else {
			$data['payment_corvuspay_pending_status_id'] = $this->config->get('payment_corvuspay_pending_status_id');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$processing_statuses = array_map('intval', (array)$this->config->get('config_processing_status'));
		$complete_statuses = array_map('intval', (array)$this->config->get('config_complete_status'));
		$blocked_pending_statuses = array_unique(array_merge($processing_statuses, $complete_statuses));
		$data['pending_order_statuses'] = array();

		foreach ($data['order_statuses'] as $order_status) {
			if (!in_array((int)$order_status['order_status_id'], $blocked_pending_statuses, true)) {
				$data['pending_order_statuses'][] = $order_status;
			}
		}

		if (isset($this->request->post['payment_corvuspay_geo_zone_id'])) {
			$data['payment_corvuspay_geo_zone_id'] = $this->request->post['payment_corvuspay_geo_zone_id'];
		} else {
			$data['payment_corvuspay_geo_zone_id'] = $this->config->get('payment_corvuspay_geo_zone_id');
		} 

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_corvuspay_status'])) {
			$data['payment_corvuspay_status'] = $this->request->post['payment_corvuspay_status'];
		} else {
			$data['payment_corvuspay_status'] = $this->config->get('payment_corvuspay_status');
		}

		if (isset($this->request->post['payment_corvuspay_sort_order'])) {
			$data['payment_corvuspay_sort_order'] = $this->request->post['payment_corvuspay_sort_order'];
		} else {
			$data['payment_corvuspay_sort_order'] = $this->config->get('payment_corvuspay_sort_order');
		}

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/corvuspay', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/corvuspay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_corvuspay_merchant'])) {
			$this->error['merchant'] = $this->language->get('error_merchant');
		}

		if (empty($this->request->post['payment_corvuspay_password'])) {
			$this->error['password'] = $this->language->get('error_password');
		}

		if (empty($this->request->post['payment_corvuspay_order_status_id'])) {
			$this->error['warning'] = $this->language->get('error_order_status');
		}

		$pending_status_id = isset($this->request->post['payment_corvuspay_pending_status_id'])
			? (int)$this->request->post['payment_corvuspay_pending_status_id']
			: 0;

		if ($pending_status_id > 0) {
			$processing_statuses = array_map('intval', (array)$this->config->get('config_processing_status'));
			$complete_statuses = array_map('intval', (array)$this->config->get('config_complete_status'));

			if (in_array($pending_status_id, array_unique(array_merge($processing_statuses, $complete_statuses)), true)) {
				$this->error['warning'] = $this->language->get('error_pending_status');
			}
		}

        return !$this->error;

		/*if (!$this->error) {
			return true;
		} else {
			return false;
		}*/
	}
}
?>
