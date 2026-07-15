<?php
class ControllerCheckoutPaymentMethod extends Controller {
	public function index() {
		$this->load->language('checkout/checkout');

		if (isset($this->session->data['payment_address'])) {
			// Totals
			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			// Because __call can not keep var references so we put them into an array.
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);
			
			$this->load->model('setting/extension');

			$sort_order = array();

			$results = $this->model_setting_extension->getExtensions('total');

			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
			}

			array_multisort($sort_order, SORT_ASC, $results);

			foreach ($results as $result) {
				if ($this->config->get('total_' . $result['code'] . '_status')) {
					$this->load->model('extension/total/' . $result['code']);
					
					// We have to put the totals in an array so that they pass by reference.
					$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
				}
			}

			// Payment Methods
			$method_data = array();

			$this->load->model('setting/extension');

			$results = $this->model_setting_extension->getExtensions('payment');

			$recurring = $this->cart->hasRecurringProducts();

			foreach ($results as $result) {
				if ($this->config->get('payment_' . $result['code'] . '_status')) {
					$this->load->model('extension/payment/' . $result['code']);

					$method = $this->{'model_extension_payment_' . $result['code']}->getMethod($this->session->data['payment_address'], $total);

					

					if ($this->config->get('total_paycharge_status')) {
			$paycharge_total = $this->cart->getTotal();

			if (isset($this->session->data['shipping_method'])) {
				$paycharge_total += $this->tax->calculate($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id'], $this->config->get('config_tax'));
			}

			foreach ($this->config->get('total_paycharge') as $paycharge) {
				$status = true;

				if (!isset($paycharge['step5vals']) && !isset($paycharge['step5calc'])) {
					$status = false;
				} elseif ($paycharge['payment_method'] != $method['code']) {
					$status = false;
				} elseif ($paycharge_total < $paycharge['cart_min'] || $paycharge_total > $paycharge['cart_max']) {
					$status = false;
				} elseif ($paycharge['customer_group_id'] && $paycharge['customer_group_id'] != $this->customer->getGroupId()) {
					$status = false;
				//} elseif (!$paycharge['customer_group_id'] && $this->customer->isLogged()) {
					//$status = false;
				} elseif ($paycharge['geo_zone_id'] && isset($this->session->data['payment_address'])) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$paycharge['geo_zone_id'] . "' AND country_id = '" . (int)$this->session->data['payment_address']['country_id'] . "' AND (zone_id = '" . (int)$this->session->data['payment_address']['zone_id'] . "' OR zone_id = '0')");

					if (!$query->num_rows) {
						$status = false;
					}
				}

				if ($status) {
					$text = '';
					$value = 0;
					$charge_p = $paycharge['valuep'];
					$charge_f = $paycharge['valuef'];

					if ($charge_p) {
						if (isset($paycharge['formula'])) {
							$text .= round((($charge_p / 100) +1) * ($charge_p / 100) * 100, 2) . '%';
							$value += (($charge_p / 100) * $paycharge_total) / (1- ($charge_p / 100));
						} else {
							$text .= $charge_p . '%';
							$value += ($paycharge_total / 100 * $charge_p);
						}
					}

					if ($charge_p && $charge_f && substr($charge_f, 0, 1) != '-') {
						$text .= ' + '; // Add separator
					}

					if ($charge_f) {
						$text .= $charge_f; // Or: $this->currency->format($charge_f, $this->session->data['currency']);
						$value += $charge_f;
					}

					$method['title'] .= ' (';
					if (isset($paycharge['step5vals'])) {
						$method['title'] .= $text;
					}
					if (isset($paycharge['step5vals']) && isset($paycharge['step5calc'])) {
						$method['title'] .= ' = ';
					}
					if (isset($paycharge['step5calc'])) {
						$method['title'] .= $this->currency->format($value, $this->session->data['currency']);
					}
					$method['title'] .= ')';
				}
			}
		}
		
						if ($recurring) {
							if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
								$method_data[$result['code']] = $method;
							}
						} else {
							$method_data[$result['code']] = $method;
						}
					}
				}
			}

			$sort_order = array();

			foreach ($method_data as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $method_data);

			$this->session->data['payment_methods'] = $method_data;
		}

		if (empty($this->session->data['payment_methods'])) {
			$data['error_warning'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact'));
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['payment_methods'])) {
			$data['payment_methods'] = $this->session->data['payment_methods'];
		} else {
			$data['payment_methods'] = array();
		}

		if (isset($this->session->data['payment_method']['code'])) {
			$data['code'] = $this->session->data['payment_method']['code'];
		} else {
			$data['code'] = '';
		}

		if (isset($this->session->data['comment'])) {
			$data['comment'] = $this->session->data['comment'];
		} else {
			$data['comment'] = '';
		}

		$data['scripts'] = $this->document->getScripts();

		if ($this->config->get('config_checkout_id')) {
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

			if ($information_info) {
				$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_checkout_id'), true), $information_info['title'], $information_info['title']);
			} else {
				$data['text_agree'] = '';
			}
		} else {
			$data['text_agree'] = '';
		}

		if (isset($this->session->data['agree'])) {
			$data['agree'] = $this->session->data['agree'];
		} else {
			$data['agree'] = '';
		}

		$this->response->setOutput($this->load->view('checkout/payment_method', $data));
	}

	public function save() {
		$this->load->language('checkout/checkout');

		$json = array();

		// Validate if payment address has been set.
		if (!isset($this->session->data['payment_address'])) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}

		if (!isset($this->request->post['payment_method'])) {
			$json['error']['warning'] = $this->language->get('error_payment');
		} elseif (!isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
			$json['error']['warning'] = $this->language->get('error_payment');
		}

		if ($this->config->get('config_checkout_id')) {
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

			if ($information_info && !isset($this->request->post['agree'])) {
				$json['error']['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
			}
		}

		if (!$json) {
			$this->session->data['payment_method'] = $this->session->data['payment_methods'][$this->request->post['payment_method']];

			$this->session->data['comment'] = strip_tags($this->request->post['comment']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
