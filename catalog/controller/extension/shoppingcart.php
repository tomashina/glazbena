<?php
class ControllerExtensionShoppingcart extends Controller {
	public function index() {
		$this->load->language('extension/shoppingcart');
		$this->load->model('extension/bundleproduct');
		$this->load->model('catalog/product');

		$this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
		
		$bundle_infos = $this->config->get('module_bundle_languageseting');

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['bundleheading'])){
			$data['heading_title']	= $bundle_infos[$this->config->get('config_language_id')]['bundleheading'];
		} else {
			$data['heading_title'] = $this->language->get('heading_title');
		}
		$this->document->setTitle($data['heading_title']);

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['image'])){
			$data['column_image']	= $bundle_infos[$this->config->get('config_language_id')]['image'];
		} else {
			$data['column_image'] = $this->language->get('column_image');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['productname'])){
			$data['column_name']	= $bundle_infos[$this->config->get('config_language_id')]['productname'];
		} else {
			$data['column_name'] = $this->language->get('column_name');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['model'])){
			$data['column_model']	= $bundle_infos[$this->config->get('config_language_id')]['model'];
		} else {
			$data['column_model'] = $this->language->get('column_model');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['minquantity'])){
			$data['column_quantity']	= $bundle_infos[$this->config->get('config_language_id')]['minquantity'];
		} else {
			$data['column_quantity'] = $this->language->get('column_quantity');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['price'])){
			$data['column_price']	= $bundle_infos[$this->config->get('config_language_id')]['price'];
		} else {
			$data['column_price'] = $this->language->get('column_price');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['availability'])){
			$data['column_availabilty']	= $bundle_infos[$this->config->get('config_language_id')]['availability'];
		} else {
			$data['column_availabilty'] = $this->language->get('column_availabilty');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['option'])){
			$data['column_option']	= $bundle_infos[$this->config->get('config_language_id')]['option'];
		} else {
			$data['column_option'] = $this->language->get('column_option');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['addtocart'])){
			$data['button_cart']	= $bundle_infos[$this->config->get('config_language_id')]['addtocart'];
		} else {
			$data['button_cart'] = $this->language->get('button_cart');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['continueshopping'])){
			$data['button_shopping']	= $bundle_infos[$this->config->get('config_language_id')]['continueshopping'];
		} else {
			$data['button_shopping'] = $this->language->get('button_shopping');
		}

		
		$data['text_recurring_item']= $this->language->get('text_recurring_item');
		$data['text_next'] 			= $this->language->get('text_next');
		$data['text_next_choice'] 	= $this->language->get('text_next_choice');
		$data['text_select'] 		= $this->language->get('text_select');
		$data['text_loading'] 		= $this->language->get('text_loading');

		$data['button_upload'] 		= $this->language->get('button_upload');
		$data['button_update'] 		= $this->language->get('button_update');
		$data['button_remove'] 		= $this->language->get('button_remove');
		$data['button_clearcart'] 	= $this->language->get('button_clearcart');

		if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
			$data['error_warning'] = $this->language->get('error_stock');
		} elseif (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if(isset($this->request->get['bundle_id'])) {
			$bundle_id = $this->request->get['bundle_id'];
		} else {
			$bundle_id = 0;
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('common/home'),
			'text' => $this->language->get('text_home')
		);

		$data['continue'] = $this->url->link('common/home');

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('extension/shoppingcart', 'bundle_id=' . $bundle_id),
			'text' => $this->language->get('heading_title')
		);

		$data['bundle_id']=$bundle_id;
		$data['customergroups'] = $this->customer->getGroupId();

		$data['discountprice'] =0;
		$totalprices=0;
		$tn_price=0;

		$probundles = $this->model_extension_bundleproduct->getBundleProducts($bundle_id);
		foreach($probundles as $product_infos) {
			$pro_info = $this->model_catalog_product->getProduct($product_infos['product_id']);
			if($pro_info) {
				$tn_price =$this->tax->calculate($pro_info['special'] ? $pro_info['special'] : $pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax'));
				
				if(isset($product_infos['quantity'])) {
					$totalprices += $tn_price * $product_infos['quantity'];
				} else {
					$totalprices +=$tn_price;
				}
			}
		}
		
		$default_group_id = $this->config->get('config_customer_group_id');
	    $customer_group_id = $this->customer->getGroupId();
	    
	    if($customer_group_id) {
	      $group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle_id."' and customer_group_id='".(int)$customer_group_id."'");
	    } else {
	      $group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle_id."' and customer_group_id='".(int)$default_group_id."'");
	    }
		if ($group_query->num_rows) {
			if($group_query->row['discount_option'] == 'Percentage') {
				$data['discountprice'] = ($totalprices*($group_query->row['add_discount']/100));
			} else {
				$data['discountprice'] = $group_query->row['add_discount'];
			}
			$data['groupdiscount'] = $this->currency->format($data['discountprice'],$this->config->get('config_currency'));
		}
// 30 03 2021 //
				if (!$this->customer->isLogged() && $this->config->get('config_customer_price')) {
					$data['groupdiscount']=false;
				
				}
				// 30 03 2021 //
		$this->load->model('tool/image');
		$this->load->model('tool/upload');
		$this->load->model('extension/shoppingcart');

		$data['bundleproducts'] = array();
		$data['options'] = '';

		$bundleproducts = $this->model_extension_shoppingcart->getBundleProducts($bundle_id);

		foreach ($bundleproducts as $result) {
			$this->load->model('catalog/product');
			$products = $this->model_catalog_product->getProduct($result['product_id']);

			if($products){
				$recurrings=array();
				$recurrings = $this->model_catalog_product->getProfiles($products['product_id']);
				if(!empty($this->config->get('module_bundle_height'))){
					$imageheight = $this->config->get('module_bundle_height');
				} else {
					$imageheight = 60;
				}
				
				if(!empty($this->config->get('module_bundle_width'))){
					$imagewidth = $this->config->get('module_bundle_width');
				} else {
					$imagewidth = 60;
				}
				
				if (!empty($products['image'])) {
					$image = $this->model_tool_image->resize($products['image'], $imageheight, $imagewidth);
				} else {
					$image = $this->model_tool_image->resize('no_image.png', $imageheight, $imagewidth);
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$proprice = $this->currency->format($this->tax->calculate($products['price'], $products['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));
				} else {
					$proprice = false;
				}

				// 30 03 2021 //
				if (($this->customer->isLogged() || !$this->config->get('config_customer_price')) && (float)$products['special']) {
					// 30 03 2021 //
					$special = $this->currency->format($this->tax->calculate($products['special'], $products['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));
				} else {
					$special = false;
				}

				$options = array();

				foreach ($product_options = $this->model_extension_shoppingcart->getProductOptions($products['product_id']) as $option) {
					$product_option_value_data = array();

					foreach ($option['product_option_value'] as $option_value) {
						
						if (!$option_value['subtract'] || ($option_value['quantity'] > 0)) {
							if ((($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) && (float)$option_value['price']) {
								$price = $this->currency->format($this->tax->calculate($option_value['price'], $products['tax_class_id'], $this->config->get('config_tax') ? 'P' : false), $this->session->data['currency']);
							} else {
								$price = false;
							}

							if (!empty($option_value['image'])) {
								$pimage = $this->model_tool_image->resize($option_value['image'], 50, 50);
							} else {
								$pimage = $this->model_tool_image->resize('no_image.png', 50, 50);
							}

							$option_value_info = $this->model_extension_shoppingcart->getOptionValue($option_value['option_value_id']);

							$product_option_value_data[] = array(
								'product_option_value_id' => $option_value['product_option_value_id'],
								'option_value_id'         => $option_value['option_value_id'],
								'name'                    => $option_value_info['name'],
								'price'                   => $price,
								'image'               	  => $this->model_tool_image->resize($pimage, 50, 50),
								'price_prefix'            => $option_value['price_prefix']
							);

						}
					}

					$options[] = array(
						'product_option_id'    => $option['product_option_id'],
						'product_option_value' => $product_option_value_data,
						'option_id'            => $option['option_id'],
						'name'                 => $option['name'],
						'type'                 => $option['type'],
						'value'                => $option['value'],
						'required'             => $option['required']
					);
				}
				$data['options']=$options;
				$data['bundleproducts'][] = array(
					'product_id' => $products['product_id'],
					'thumb'   	 => $image,
					'options' 	 => $options,
					'name'   	 => $products['name'],
					'model'   	 => $products['model'],
					'price'   	 => $proprice,
					'quantity'    => $result['quantity'],
					'recurrings' => $recurrings,
					'special' 	 => $special,
					'href'       => $this->url->link('product/product', 'product_id=' . $products['product_id'])
				);

			}
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$data['continue'] = $this->url->link('common/home');

		$this->response->setOutput($this->load->view('extension/shoppingcart', $data));
		
	} 

	public function addBundle() {
		$this->load->language('checkout/cart');
		$this->load->model('catalog/product');
		$this->load->model('extension/shoppingcart');

		$json = array();

		if (isset($this->request->get['bundle_id'])) {
			$bundle_id = (int)$this->request->get['bundle_id'];
		} else {
			$bundle_id = 0;
		}

		$bundle_info = $this->model_extension_shoppingcart->getBundle($bundle_id);
		if(isset($bundle_info['title'])) {
			$bundletitles = $bundle_info['title'];
		} else {
			$bundletitles = '';
		}
			
		foreach($this->request->post['product'] as $product) {		
			$product_info = $this->model_catalog_product->getProduct($product['product_id']);
			if ($product_info) {
				if (isset($product['quantity'])) {
					$quantity = (int)$product['quantity'];
				} else {
					$quantity = 0;
				}

				if (isset($product['option'])) {
					$option = array_filter($product['option']);
				} else {
					$option = array();
				}
				
				$product_options = $this->model_catalog_product->getProductOptions($product['product_id']);

				foreach ($product_options as $product_option) {
					if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
						$json[$product['product_id']]['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
					}
				}

				if (isset($product['recurring_id'])) {
					$recurring_id = $product['recurring_id'];
				} else {
					$recurring_id = 0;
				}

				$recurrings = $this->model_catalog_product->getProfiles($product_info['product_id']);

				if ($recurrings) {
					$recurring_ids = array();

					foreach ($recurrings as $recurring) {
						$recurring_ids[] = $recurring['recurring_id'];
					}

					if (!in_array($recurring_id, $recurring_ids)) {
						$json['error']['recurring'] = $this->language->get('error_recurring_required');
					}
				}
			}	
		}
		
		if (!$json) {
			foreach($this->request->post['product'] as $product) {		
				$product_info = $this->model_catalog_product->getProduct($product['product_id']);
				if ($product_info) {
					if (isset($product['quantity'])) {
						$quantity = (int)$product['quantity'];
					} else {
						$quantity = 0;
					}

					if (isset($product['option'])) {
						$option = array_filter($product['option']);
					} else {
						$option = array();
					}
					
					$product_options = $this->model_catalog_product->getProductOptions($product['product_id']);

					foreach ($product_options as $product_option) {
						if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
							$json[$product['product_id']]['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
						}
					}

					if (isset($product['recurring_id'])) {
						$recurring_id = $product['recurring_id'];
					} else {
						$recurring_id = 0;
					}

					$recurrings = $this->model_catalog_product->getProfiles($product_info['product_id']);

					if ($recurrings) {
						$recurring_ids = array();

						foreach ($recurrings as $recurring) {
							$recurring_ids[] = $recurring['recurring_id'];
						}

						if (!in_array($recurring_id, $recurring_ids)) {
							$json['error']['recurring'] = $this->language->get('error_recurring_required');
						}
					}
					$this->cart->add($product_info['product_id'], $quantity, $option, $recurring_id,$bundle_id);

					$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('extension/shoppingcart', 'bundle_id=' . $bundle_id), $bundletitles, $this->url->link('checkout/cart'));

					// Unset all shipping and payment methods
					unset($this->session->data['shipping_method']);
					unset($this->session->data['shipping_methods']);
					unset($this->session->data['payment_method']);
					unset($this->session->data['payment_methods']);

					// Totals
					$this->load->model('setting/extension');

					$totals = array();
					$taxes = $this->cart->getTaxes();
					$total = 0;
			
					// Because __call can not keep var references so we put them into an array. 			
					$total_data = array(
						'totals' => &$totals,
						'taxes'  => &$taxes,
						'total'  => &$total
					);

					// Display prices
					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
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

						$sort_order = array();

						foreach ($totals as $key => $value) {
							$sort_order[$key] = $value['sort_order'];
						}

						array_multisort($sort_order, SORT_ASC, $totals);
					}

					$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
				}	
			}
				
		} else {
			$json['redirect'] = str_replace('&amp;', '&', $this->url->link('extension/shoppingcart', 'bundle_id=' . $bundle_id));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deletecart(){
		$json = array();
		$this->load->model('extension/shoppingcart');
	    $this->load->language('extension/shoppingcart');
		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {

			$this->model_extension_shoppingcart->deleteCart($this->request->get['bundle_id']);
			$json['success'] = $this->language->get('text_remove');
					
		}					
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
