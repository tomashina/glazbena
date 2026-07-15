<?php
class ControllerExtensionShippingGls extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/shipping/gls');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/shipping/gls');

		$this->model_extension_shipping_gls->installSchema();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_gls', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/shipping/gls', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/shipping/gls', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);

		$defaults = array(
			'shipping_gls_api_url'                  => 'https://api.mygls.hr',
			'shipping_gls_username'                 => '',
			'shipping_gls_password'                 => '',
			'shipping_gls_client_number'            => '',
			'shipping_gls_pickup_name'              => $this->config->get('config_name'),
			'shipping_gls_pickup_street'            => '',
			'shipping_gls_pickup_house_number'      => '',
			'shipping_gls_pickup_house_number_info' => '',
			'shipping_gls_pickup_city'              => '',
			'shipping_gls_pickup_postcode'          => '',
			'shipping_gls_pickup_country'           => 'HR',
			'shipping_gls_pickup_email'             => $this->config->get('config_email'),
			'shipping_gls_pickup_phone'             => $this->config->get('config_telephone'),
			'shipping_gls_order_prefix'             => 'DRYZEN-',
			'shipping_gls_content'                  => 'Dryzen narudzba',
			'shipping_gls_printer_type'             => 'A4_2x2',
			'shipping_gls_print_position'           => '1',
			'shipping_gls_hide_phone'               => 0,
			'shipping_gls_pickup_days'              => '1',
			'shipping_gls_filter_type'              => '',
			'shipping_gls_cost'                     => '0.00',
			'shipping_gls_free_total'               => '',
			'shipping_gls_tax_class_id'             => 0,
			'shipping_gls_geo_zone_id'              => 0,
			'shipping_gls_status'                   => 0,
			'shipping_gls_sort_order'               => 0
		);

		foreach ($defaults as $key => $default) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$value = $this->config->get($key);
				$data[$key] = ($value !== null && $value !== '') ? $value : $default;
			}
		}

		$data['printer_types'] = array('A4_2x2', 'A4_4x1', 'Connect', 'Thermo', 'ThermoZPL', 'ThermoZPL_300DPI', 'ShipItThermoPdf', 'ShipItThermoZpl');

		$this->load->model('localisation/tax_class');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['api_help'] = sprintf($this->language->get('help_api_url'), $data['shipping_gls_api_url']);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/gls', $data));
	}

	public function install() {
		$this->load->model('extension/shipping/gls');
		$this->model_extension_shipping_gls->installSchema();
	}

	public function createShipment() {
		$this->load->language('extension/shipping/gls');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/shipping/gls')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (empty($this->request->get['order_id'])) {
			$json['error'] = $this->language->get('error_not_gls_order');
		} else {
			try {
				$this->load->model('extension/shipping/gls');
				$shipment = $this->model_extension_shipping_gls->createShipment((int)$this->request->get['order_id']);

				$json['success'] = !empty($shipment['existing']) ? $this->language->get('text_shipment_exists') : $this->language->get('text_shipment_created');
				$json['parcel_id'] = isset($shipment['parcel_id']) ? $shipment['parcel_id'] : '';
				$json['parcel_number'] = isset($shipment['parcel_number']) ? $shipment['parcel_number'] : '';
				$json['display_id'] = !empty($shipment['parcel_number']) ? $shipment['parcel_number'] : (isset($shipment['parcel_id']) ? $shipment['parcel_id'] : '');
				$json['label'] = str_replace('&amp;', '&', $this->url->link('extension/shipping/gls/label', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int)$this->request->get['order_id'], true));
			} catch (Exception $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function label() {
		$this->load->language('extension/shipping/gls');

		if (!$this->user->hasPermission('access', 'extension/shipping/gls')) {
			$this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token'], true));
		}

		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;

		try {
			$this->load->model('extension/shipping/gls');
			$pdf = $this->model_extension_shipping_gls->getLabel($order_id);

			$this->response->addHeader('Content-Type: application/pdf');
			$this->response->addHeader('Content-Disposition: inline; filename="gls-' . $order_id . '.pdf"');
			$this->response->setOutput($pdf);
		} catch (Exception $e) {
			$this->session->data['error_warning'] = $e->getMessage();
			$this->response->redirect($this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true));
		}
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/gls')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
