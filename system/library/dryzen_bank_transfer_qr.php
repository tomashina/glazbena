<?php
class DryzenBankTransferQr {
	private $registry;
	private $config;
	private $log;
	private $defaults = array(
		'qr_status'      => '1',
		'qr_company'     => 'GORDOM USLUGE d.o.o.',
		'qr_address'     => 'Wickerhauserova ulica 52',
		'qr_city'        => '10000 Zagreb',
		'qr_iban'        => 'HR6824020061100462543',
		'qr_model'       => 'HR00',
		'qr_reference'   => '{order_id}{year2}',
		'qr_purpose'     => 'SUPP',
		'qr_description' => 'Web narudzba Dryzen'
	);

	public function __construct($registry) {
		$this->registry = $registry;
		$this->config = $registry->get('config');
		$this->log = $registry->get('log');
	}

	public function getSetting($key) {
		$value = $this->config->get('payment_bank_transfer_' . $key);

		if ($value === null || $value === '') {
			return isset($this->defaults[$key]) ? $this->defaults[$key] : '';
		}

		return $value;
	}

	public function getReference($order_info) {
		return $this->cleanField($this->replaceTokens($this->getSetting('qr_reference'), $order_info), 22);
	}

	public function getModel($with_prefix = true) {
		$model = strtoupper(trim($this->getSetting('qr_model')));
		$model = preg_replace('/[^A-Z0-9]/', '', $model);

		if (substr($model, 0, 2) == 'HR') {
			$model = substr($model, 2);
		}

		if ($model === '') {
			$model = '00';
		}

		$model = str_pad(substr($model, 0, 2), 2, '0', STR_PAD_LEFT);

		return $with_prefix ? 'HR' . $model : $model;
	}

	public function getDescription($order_info) {
		return $this->cleanField($this->replaceTokens($this->getSetting('qr_description'), $order_info), 35);
	}

	public function generateForOrder($order_info) {
		if (empty($order_info) || empty($order_info['order_id'])) {
			return false;
		}

		if (isset($order_info['payment_code']) && $order_info['payment_code'] != 'bank_transfer') {
			return false;
		}

		if (!$this->getSetting('qr_status')) {
			return false;
		}

		try {
			$payload = $this->buildPayload($order_info);

			if (!$payload) {
				return false;
			}

			$dir = DIR_IMAGE . 'tmp/';

			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}

			$file = $dir . (int)$order_info['order_id'] . '.png';

			if ($this->renderBarcodePng($payload, $file)) {
				return $file;
			}
		} catch (\Exception $e) {
			if ($this->log) {
				$this->log->write('DryZen bank transfer QR error: ' . $e->getMessage());
			}
		}

		return false;
	}

	public function getImageUrl($order_info, $file = '') {
		if (empty($order_info['store_url']) || empty($order_info['order_id'])) {
			return '';
		}

		$url = rtrim($order_info['store_url'], '/') . '/image/tmp/' . (int)$order_info['order_id'] . '.png';

		if ($file && is_file($file)) {
			$url .= '?v=' . filemtime($file);
		}

		return $url;
	}

	private function buildPayload($order_info) {
		$iban = strtoupper(preg_replace('/\s+/', '', $this->getSetting('qr_iban')));

		if (!$iban) {
			return false;
		}

		$amount = isset($order_info['total']) ? (float)$order_info['total'] : 0.0;
		$amount_cents = (string)round($amount * 100);
		$amount_cents = str_pad(substr($amount_cents, 0, 15), 15, '0', STR_PAD_LEFT);

		$payer_name = trim((isset($order_info['payment_firstname']) ? $order_info['payment_firstname'] : '') . ' ' . (isset($order_info['payment_lastname']) ? $order_info['payment_lastname'] : ''));

		if (!empty($order_info['payment_company'])) {
			$payer_name = $order_info['payment_company'];
		}

		$payer_street = isset($order_info['payment_address_1']) ? $order_info['payment_address_1'] : '';
		$payer_place = trim((isset($order_info['payment_postcode']) ? $order_info['payment_postcode'] : '') . ' ' . (isset($order_info['payment_city']) ? $order_info['payment_city'] : ''));

		$fields = array(
			'HRVHUB30',
			'EUR',
			$amount_cents,
			$this->cleanField($payer_name, 30),
			$this->cleanField($payer_street, 27),
			$this->cleanField($payer_place, 27),
			$this->cleanField($this->getSetting('qr_company'), 25),
			$this->cleanField($this->getSetting('qr_address'), 25),
			$this->cleanField($this->getSetting('qr_city'), 27),
			$this->cleanField($iban, 21),
			$this->getModel(true),
			$this->getReference($order_info),
			$this->cleanField(strtoupper($this->getSetting('qr_purpose')), 4),
			$this->getDescription($order_info)
		);

		return implode("\n", $fields);
	}

	private function renderBarcodePng($payload, $file) {
		require_once(DIR_SYSTEM . 'library/shared/tcpdf/tcpdf_barcodes_2d.php');

		$barcode = new TCPDF2DBarcode($payload, 'PDF417,3,4');
		$barcode_array = $barcode->getBarcodeArray();

		if (!$barcode_array || empty($barcode_array['num_cols']) || empty($barcode_array['num_rows']) || empty($barcode_array['bcode'])) {
			return false;
		}

		if (!function_exists('imagecreate')) {
			return false;
		}

		$scale_x = 2;
		$scale_y = 2;
		$margin = 4;
		$width = ($barcode_array['num_cols'] + ($margin * 2)) * $scale_x;
		$height = ($barcode_array['num_rows'] + ($margin * 2)) * $scale_y;
		$image = imagecreate($width, $height);
		$white = imagecolorallocate($image, 255, 255, 255);
		$black = imagecolorallocate($image, 0, 0, 0);

		imagefilledrectangle($image, 0, 0, $width, $height, $white);

		for ($row = 0; $row < $barcode_array['num_rows']; $row++) {
			for ($col = 0; $col < $barcode_array['num_cols']; $col++) {
				if (!empty($barcode_array['bcode'][$row][$col])) {
					$x1 = ($col + $margin) * $scale_x;
					$y1 = ($row + $margin) * $scale_y;
					$x2 = $x1 + $scale_x - 1;
					$y2 = $y1 + $scale_y - 1;
					imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);
				}
			}
		}

		$result = imagepng($image, $file);
		imagedestroy($image);

		return $result;
	}

	private function replaceTokens($value, $order_info) {
		$timestamp = !empty($order_info['date_added']) ? strtotime($order_info['date_added']) : time();

		$tokens = array(
			'{order_id}'  => isset($order_info['order_id']) ? $order_info['order_id'] : '',
			'{year2}'     => date('y', $timestamp),
			'{year4}'     => date('Y', $timestamp),
			'{invoice_no}' => isset($order_info['invoice_no']) ? $order_info['invoice_no'] : '',
			'{firstname}' => isset($order_info['payment_firstname']) ? $order_info['payment_firstname'] : '',
			'{lastname}'  => isset($order_info['payment_lastname']) ? $order_info['payment_lastname'] : '',
			'{store_name}' => isset($order_info['store_name']) ? $order_info['store_name'] : ''
		);

		return strtr($value, $tokens);
	}

	private function cleanField($value, $max_length) {
		$value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
		$value = str_replace(array("\r", "\n", "\t"), ' ', $value);
		$value = preg_replace('/\s+/u', ' ', $value);
		$value = trim($value);

		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, $max_length, 'UTF-8');
		}

		return substr($value, 0, $max_length);
	}
}
