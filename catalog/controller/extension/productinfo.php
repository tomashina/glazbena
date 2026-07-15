<?php
class ControllerExtensionProductInfo extends Controller {
	public function index() {
		
		$this->load->model('extension/accessory');
		$this->load->model('catalog/product');
		$this->load->language('product/product');
		$this->load->language('extension/tmdaccessories');
		
		$data['user_token'] = $this->session->data['user_token'];
		$url = '';
		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}
		
		
		$data['text_tax'] = $this->language->get('text_tax');
		$data['text_option'] = $this->language->get('text_option');
		$data['button_upload'] = $this->language->get('button_upload');
		$data['text_select'] = $this->language->get('text_select');
		$data['button_cart'] = $this->language->get('button_cart');
		$data['entry_qty'] = $this->language->get('entry_qty');
		$data['text_loading'] = $this->language->get('text_loading');
		
		$this->load->model('tool/image');
		
			$product_info = $this->model_catalog_product->getProduct($product_id);
			
			$data['description'] = utf8_substr(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8')), 0, 170) . '..';
			
			$data['name'] = $product_info['name'];
			$data['heading_title'] = $product_info['name'];
			if(isset($this->request->get['product_id'])){
			$data['product_id'] = (int)$this->request->get['product_id'];
			} else{
				$data['product_id'] =0;
			}
			if (isset($this->request->post['minimum'])) {
			$data['minimum'] = $this->request->post['minimum'];
			} elseif (!empty($product_info)) {
				$data['minimum'] = $product_info['minimum'];
			} else {
				$data['minimum'] = 1;
			}
			
				if ($product_info['image']) {
					$data['thumb'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_height'));
				
			} else {
				$data['thumb'] = '';
			}
			
			
					$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				

				if ((float)$product_info['special']) {
					$data['special'] = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$data['special'] = false;
				}

				if ($this->config->get('config_tax')) {
					$data['tax'] = $this->currency->format((float)$product_info['special'] ? $product_info['special'] : $product_info['price'], $this->config->get('config_currency'));
				} else {
					$data['tax'] = false;
				}
				
				

			
			
			$discounts = $this->model_extension_accessory->getProductDiscounts($product_id);

			$data['discounts'] = array();

			foreach ($discounts as $discount) {
				$data['discounts'][] = array(
					'quantity' => $discount['quantity'],
					'price'    => $this->currency->format($discount['price'], $this->config->get('config_currency'))
				);
			}
			
			
			$data['options'] = array();

			foreach ($this->model_extension_accessory->getProductOptions($product_id) as $option) {
				$product_option_value_data = array();

				foreach ($option['product_option_value'] as $option_value) {
					if (!$option_value['subtract'] || ($option_value['quantity'] > 0)) {
						if ((($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) && (float)$option_value['price']) {
							$price = $this->currency->format($option_value['price'], $this->config->get('config_currency'));
						} else {
							$price = false;
						}

						$product_option_value_data[] = array(
							'product_option_value_id' => $option_value['product_option_value_id'],
							'option_value_id'         => $option_value['option_value_id'],
							'name'                    => $option_value['name'],
							'image'                   => $this->model_tool_image->resize($option_value['image'], 50, 50),
							'price'                   => $price,
							'price_prefix'            => $option_value['price_prefix']
						);
					}
				}

				$data['options'][] = array(
					'product_option_id'    => $option['product_option_id'],
					'product_option_value' => $product_option_value_data,
					'option_id'            => $option['option_id'],
					'name'                 => $option['name'],
					'type'                 => $option['type'],
					'value'                => $option['value'],
					'required'             => $option['required']
				);
			}
			
			
		
		$this->response->setOutput($this->load->view('extension/productinfo', $data));
		
	}
	
	
	
}
