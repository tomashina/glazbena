<?php
class ModelExtensionShippingGls extends Model {
	public function installSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "gls_shipment` (
			`gls_shipment_id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL,
			`order_number` varchar(64) NOT NULL,
			`parcel_id` varchar(64) NOT NULL DEFAULT '',
			`parcel_number` varchar(64) NOT NULL DEFAULT '',
			`parcel_number_with_checkdigit` varchar(64) NOT NULL DEFAULT '',
			`point_id` varchar(128) NOT NULL DEFAULT '',
			`status` varchar(64) NOT NULL DEFAULT '',
			`payload` mediumtext,
			`response` mediumtext,
			`label` mediumblob,
			`date_added` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`gls_shipment_id`),
			KEY `order_id` (`order_id`),
			KEY `parcel_id` (`parcel_id`),
			KEY `parcel_number` (`parcel_number`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'gls'");

		if (!$query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `gls` TEXT NULL AFTER `shipping_code`");
		}
	}

	public function getShipmentByOrderId($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "gls_shipment` WHERE order_id = '" . (int)$order_id . "' ORDER BY gls_shipment_id DESC LIMIT 1");

		return $query->num_rows ? $query->row : array();
	}

	public function createShipment($order_id) {
		$this->installSchema();

		$existing = $this->getShipmentByOrderId($order_id);

		if (!empty($existing['parcel_id']) || !empty($existing['parcel_number'])) {
			$existing['existing'] = true;
			return $existing;
		}

		if (trim($this->getConfig('username')) === '' || trim($this->getConfig('password')) === '' || trim($this->getConfig('client_number')) === '') {
			throw new Exception($this->language->get('error_missing_credentials'));
		}

		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info || $order_info['shipping_code'] !== 'gls.gls') {
			throw new Exception($this->language->get('error_not_gls_order'));
		}

		$point_id = $this->extractPointId(isset($order_info['gls']) ? $order_info['gls'] : '');

		if ($point_id === '') {
			throw new Exception($this->language->get('error_missing_point'));
		}

		$order_number = $this->getOrderNumber($order_info);
		$is_cod = $this->isCashOnDelivery($order_info);
		$total = number_format((float)$order_info['total'], 2, '.', '');

		$service_list = array(
			array(
				'Code' => 'PSD',
				'PSDParameter' => array(
					'StringValue' => $point_id
				)
			)
		);

		$parcel = array(
			'ClientNumber'    => (int)$this->getConfig('client_number'),
			'ClientReference' => $order_number,
			'CODAmount'       => $is_cod ? (float)$total : 0,
			'CODReference'    => $is_cod ? $order_number : '',
			'Content'         => $this->getConfig('content', 'Dryzen narudzba'),
			'Count'           => 1,
			'DeliveryAddress' => $this->getOrderAddress($order_info),
			'PickupAddress'   => $this->getPickupAddress(),
			'PickupDate'      => $this->getPickupDate(),
			'ServiceList'     => $service_list
		);

		if ($is_cod && !empty($order_info['currency_code'])) {
			$parcel['CODCurrency'] = $order_info['currency_code'];
		}

		$payload = $this->getBasePayload();
		$payload['ParcelList'] = array($parcel);

		$response = $this->apiRequest('ParcelService', 'PrepareLabels', $payload);

		$this->throwErrors(isset($response['PrepareLabelsError']) ? $response['PrepareLabelsError'] : array(), 'GLS PrepareLabels');
		$info = array();

		if (!empty($response['ParcelInfoList']) && is_array($response['ParcelInfoList'])) {
			$info = $response['ParcelInfoList'][0];
		}

		$parcel_id = isset($info['ParcelId']) ? (string)$info['ParcelId'] : '';

		if ($parcel_id === '') {
			throw new Exception('GLS API: ParcelId is missing from PrepareLabels response.');
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "gls_shipment` SET order_id = '" . (int)$order_id . "', order_number = '" . $this->db->escape($order_number) . "', parcel_id = '" . $this->db->escape($parcel_id) . "', parcel_number = '', parcel_number_with_checkdigit = '', point_id = '" . $this->db->escape($point_id) . "', status = 'prepared', payload = '" . $this->db->escape(json_encode($payload)) . "', response = '" . $this->db->escape(json_encode($response)) . "', label = '', date_added = NOW(), date_modified = NOW()");

		return $this->getShipmentByOrderId($order_id);
	}

	public function getLabel($order_id) {
		$this->installSchema();

		$shipment = $this->getShipmentByOrderId($order_id);

		if (!empty($shipment['label'])) {
			return $shipment['label'];
		}

		if (empty($shipment['parcel_id'])) {
			throw new Exception($this->language->get('error_missing_label'));
		}

		$payload = $this->getBasePayload();
		$payload['ParcelIdList'] = array((int)$shipment['parcel_id']);
		$payload['PrintPosition'] = max(1, min(4, (int)$this->getConfig('print_position', 1)));
		$payload['ShowPrintDialog'] = 0;
		$payload['TypeOfPrinter'] = $this->getConfig('printer_type', 'A4_2x2');
		$payload['HidePhoneNumberOnLabels'] = (bool)$this->getConfig('hide_phone', 0);

		$response = $this->apiRequest('ParcelService', 'GetPrintedLabels', $payload);

		$this->throwErrors(isset($response['GetPrintedLabelsErrorList']) ? $response['GetPrintedLabelsErrorList'] : array(), 'GLS GetPrintedLabels');

		if (empty($response['Labels'])) {
			throw new Exception('GLS API: label PDF is missing from GetPrintedLabels response.');
		}

		$label = $this->bytesToString($response['Labels']);

		$this->db->query("UPDATE `" . DB_PREFIX . "gls_shipment` SET label = '" . $this->db->escape($label) . "', date_modified = NOW() WHERE gls_shipment_id = '" . (int)$shipment['gls_shipment_id'] . "'");

		return $label;
	}

	private function apiRequest($service, $method, $payload) {
		if (!function_exists('curl_init')) {
			throw new Exception('GLS API: cURL is not available.');
		}

		$url = rtrim($this->getConfig('api_url', 'https://api.mygls.hr'), '/') . '/' . $service . '.svc/json/' . $method;
		$body = json_encode($payload);

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Content-Length: ' . strlen($body)
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 90);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($error) {
			throw new Exception('GLS API: ' . $error);
		}

		if ($http_code < 200 || $http_code >= 300) {
			throw new Exception('GLS API HTTP ' . $http_code . ': ' . $response);
		}

		$json = json_decode($response, true);

		if (!is_array($json)) {
			throw new Exception('GLS API returned invalid JSON: ' . $response);
		}

		return $json;
	}

	private function getBasePayload() {
		return array(
			'Username'      => $this->getConfig('username'),
			'Password'      => $this->hashPassword($this->getConfig('password')),
			'WebshopEngine' => 'OpenCart 3 Dryzen GLS OCMOD'
		);
	}

	private function hashPassword($password) {
		return array_values(unpack('C*', hash('sha512', (string)$password, true)));
	}

	private function throwErrors($errors, $prefix) {
		if (empty($errors) || !is_array($errors)) {
			return;
		}

		$messages = array();

		foreach ($errors as $error) {
			if (!is_array($error)) {
				continue;
			}

			$code = isset($error['ErrorCode']) ? $error['ErrorCode'] : '';
			$description = isset($error['ErrorDescription']) ? $error['ErrorDescription'] : '';

			if ((string)$code === '0' && trim((string)$description) === '') {
				continue;
			}

			$messages[] = trim($code . ' ' . $description);
		}

		if ($messages) {
			throw new Exception($prefix . ': ' . implode('; ', array_filter($messages)));
		}
	}

	private function bytesToString($bytes) {
		if (is_string($bytes)) {
			$decoded = base64_decode($bytes, true);

			return $decoded !== false ? $decoded : $bytes;
		}

		$pdf = '';

		foreach ((array)$bytes as $byte) {
			$pdf .= chr((int)$byte & 255);
		}

		return $pdf;
	}

	private function getPickupDate() {
		$days = max(0, (int)$this->getConfig('pickup_days', 1));
		$timestamp = strtotime('today +' . $days . ' day');

		return '/Date(' . ($timestamp * 1000) . ')/';
	}

	private function getPickupAddress() {
		return array(
			'Name'            => $this->getConfig('pickup_name', $this->config->get('config_name')),
			'Street'          => $this->getConfig('pickup_street'),
			'HouseNumber'     => $this->getConfig('pickup_house_number', '1'),
			'HouseNumberInfo' => $this->getConfig('pickup_house_number_info'),
			'City'            => $this->getConfig('pickup_city'),
			'ZipCode'         => $this->getConfig('pickup_postcode'),
			'CountryIsoCode'  => strtoupper($this->getConfig('pickup_country', 'HR')),
			'ContactName'     => $this->getConfig('pickup_name', $this->config->get('config_name')),
			'ContactPhone'    => $this->normalizePhone($this->getConfig('pickup_phone', $this->config->get('config_telephone'))),
			'ContactEmail'    => $this->getConfig('pickup_email', $this->config->get('config_email'))
		);
	}

	private function getOrderAddress($order_info) {
		$street = $this->splitStreet(isset($order_info['shipping_address_1']) ? $order_info['shipping_address_1'] : '');
		$name = trim((isset($order_info['shipping_firstname']) ? $order_info['shipping_firstname'] : $order_info['firstname']) . ' ' . (isset($order_info['shipping_lastname']) ? $order_info['shipping_lastname'] : $order_info['lastname']));
		$house_info = trim($street['info'] . ' ' . (isset($order_info['shipping_address_2']) ? $order_info['shipping_address_2'] : ''));

		return array(
			'Name'            => $name,
			'Street'          => $street['street'],
			'HouseNumber'     => $street['number'],
			'HouseNumberInfo' => $house_info,
			'City'            => isset($order_info['shipping_city']) ? $order_info['shipping_city'] : '',
			'ZipCode'         => isset($order_info['shipping_postcode']) ? $order_info['shipping_postcode'] : '',
			'CountryIsoCode'  => isset($order_info['shipping_iso_code_2']) ? strtoupper($order_info['shipping_iso_code_2']) : 'HR',
			'ContactName'     => $name,
			'ContactPhone'    => $this->normalizePhone(isset($order_info['telephone']) ? $order_info['telephone'] : ''),
			'ContactEmail'    => isset($order_info['email']) ? $order_info['email'] : ''
		);
	}

	private function splitStreet($address) {
		$address = trim((string)$address);

		if ($address === '') {
			return array('street' => '-', 'number' => '1', 'info' => '');
		}

		if (preg_match('/^(.+?)[, ]+([0-9]+)(.*)$/', $address, $matches)) {
			return array(
				'street' => trim($matches[1]),
				'number' => trim($matches[2]),
				'info' => trim($matches[3])
			);
		}

		return array('street' => $address, 'number' => '1', 'info' => '');
	}

	private function normalizePhone($phone) {
		$phone = preg_replace('/[^0-9+]/', '', (string)$phone);

		if (strpos($phone, '00') === 0) {
			$phone = '+' . substr($phone, 2);
		}

		if (strpos($phone, '0') === 0) {
			$phone = '+385' . substr($phone, 1);
		}

		if ($phone !== '' && strpos($phone, '+') !== 0) {
			$phone = '+' . $phone;
		}

		return $phone;
	}

	private function extractPointId($gls) {
		$gls = trim((string)$gls);

		if ($gls === '' || stripos($gls, 'undefined') !== false) {
			return '';
		}

		if (strpos($gls, ';') !== false) {
			$parts = explode(';', $gls);
			return trim(end($parts));
		}

		return $gls;
	}

	private function getOrderNumber($order_info) {
		$number = !empty($order_info['number_order']) ? $order_info['number_order'] : $order_info['order_id'];

		return $this->getConfig('order_prefix', 'DRYZEN-') . $number;
	}

	private function getConfig($key, $default = '') {
		$value = $this->config->get('shipping_gls_' . $key);

		return ($value !== null && $value !== '') ? $value : $default;
	}

	private function isCashOnDelivery($order_info) {
		$payment_code = strtolower((string)$order_info['payment_code']);
		$payment_method = strtolower((string)$order_info['payment_method']);

		return $payment_code === 'cod' || strpos($payment_code, 'cod') !== false || strpos($payment_code, 'cash') !== false || strpos($payment_method, 'pouze') !== false || strpos($payment_method, 'cash') !== false || strpos($payment_method, 'gotovina') !== false;
	}
}
