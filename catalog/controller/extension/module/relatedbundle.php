<?php
class ControllerExtensionModuleRelatedbundle extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/relatedbundle');
		$this->load->model('extension/bundle');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		// FIX: Inicijaliziraj $disable (config -> default 0)
		$disable = (int)$this->config->get('module_relatedbundle_disable');
		if (!isset($disable)) {
			$disable = 0;
		}

		$bundle_infos = $this->config->get('module_bundle_languageseting');

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['title'])){
			$data['btitles']	= $bundle_infos[$this->config->get('config_language_id')]['title'];
		} else {
			$data['btitles'] = $this->language->get('heading_title');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['related_bundle'])){
			$data['related_heading']	= $bundle_infos[$this->config->get('config_language_id')]['related_bundle'];
		} else {
			$data['related_heading'] = $this->language->get('heading_title');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['description'])){
			$data['description']	= $bundle_infos[$this->config->get('config_language_id')]['description'];
		} else {
			$data['description'] = $this->language->get('heading_title');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['regularprice'])){
			$data['text_regularprice']	= $bundle_infos[$this->config->get('config_language_id')]['regularprice'];
		} else {
			$data['text_regularprice'] = $this->language->get('text_regularprice');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['taxprice'])){
			$data['text_taxprice']	= $bundle_infos[$this->config->get('config_language_id')]['taxprice'];
		} else {
			$data['text_taxprice'] = $this->language->get('text_taxprice');
		}
		
		if(isset($this->request->get['product_id'])) {
			$product_id = $this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		// FIX: Ispravno čitanje page parametra
		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$modulelimit = $this->config->get('module_bundle_modulelimit');
		if($modulelimit) {
			if (isset($this->request->get['limit'])) {
				$limit = (int)$this->request->get['limit'];
			} else {
				$limit = $modulelimit;
			}
		} else {
			if (isset($this->request->get['limit'])) {
				$limit = (int)$this->request->get['limit'];
			} else {
				$limit = 5;
			}
		}

		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (isset($this->request->get['limit'])) {
		 	$url .= '&limit=' . $this->request->get['limit'];
		}

		$data['probundles'] = array();
		$filter_data = array(
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		);

		$bundle_total = $this->model_extension_bundle->getTotalProBundles($filter_data, $product_id);
		$probundles = $this->model_extension_bundle->getBundleProducts($filter_data, $product_id);
		
		foreach($probundles as $result) {
			$bundle_info = $this->model_extension_bundle->getBundle($result['bundle_id']);
			if(isset($bundle_info['title'])) {
				$titles = $bundle_info['title'];
			} else {
				$titles = '';
			}
			
			if(isset($bundle_info['bundle_id'])) {
				$bundle_id = $bundle_info['bundle_id'];
			} else {
				$bundle_id = '';
			}
			
			if(isset($bundle_info['date_from'])) {
				$date_from = $bundle_info['date_from'];
			} else {
				$date_from = '';
			}
			
			if(isset($bundle_info['date_to'])) {
				$date_to = $bundle_info['date_to'];
			} else {
				$date_to = '';
			}

			$totalprices = 0;
			$tn_price = 0;

			$probundles = $this->model_extension_bundle->getBundlePros($bundle_info['bundle_id']);

			$products = array();
			foreach($probundles as $product_infos) {
				$pro_info = $this->model_catalog_product->getProduct($product_infos['product_id']);
				if($pro_info) {
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
						
					// OpenCart resize($filename, $width, $height) — ostavljam tvoj poredak
					if (isset($pro_info['image'])) {
						$image = $this->model_tool_image->resize($pro_info['image'], $imageheight, $imagewidth);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $imageheight, $imagewidth);
					}

					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$price = $this->currency->format($this->tax->calculate($pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));

						if ($this->session->data['currency'] == 'HRK') {
							$priceeur = $this->currency->format($this->tax->calculate($pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax')), 'EUR');
						} else {
							$priceeur = $this->currency->format($this->tax->calculate($pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax')), 'HRK');
						}
					} else {
						$price = false;
						$priceeur = '';
					}

					if ($pro_info['quantity'] < 1) {
						$this->db->query("UPDATE `oc_bundle` SET status = 0 WHERE bundle_id = '".$bundle_info['bundle_id']."'");
						// FIX: Osiguraj da je $disable postavljen (int)
						$disable = 1;
					}

					// 30 03 2021 //
					if (($this->customer->isLogged() || !$this->config->get('config_customer_price')) && (float)$pro_info['special']) {
						$special = $this->currency->format($this->tax->calculate($pro_info['special'], $pro_info['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));

						if ($this->session->data['currency'] == 'HRK') {
							$specialeur = $this->currency->format($this->tax->calculate($pro_info['special'], $pro_info['tax_class_id'], $this->config->get('config_tax')), 'EUR');
						} else {
							$specialeur = $this->currency->format($this->tax->calculate($pro_info['special'], $pro_info['tax_class_id'], $this->config->get('config_tax')), 'HRK');
						}
					} else {
						$special = false;
						$specialeur = '';
					}

					if ($this->config->get('config_tax')) {
						$tax = $this->currency->format((float)$pro_info['special'] ? $pro_info['special'] : $pro_info['price'], $this->config->get('config_currency'));
					} else {
						$tax = false;
					}

					$tn_price = $this->tax->calculate($pro_info['special'] ? $pro_info['special'] : $pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax'));
					
					if(isset($product_infos['quantity'])) {
						$totalprices += $tn_price * $product_infos['quantity'];
					} else {
						$totalprices += $tn_price;
					}

					$products[] = array(
						'product_id' => $pro_info['product_id'],
						'name'       => $pro_info['name'],
						'price'      => $price,
						'priceeur'   => $priceeur,
						'image'      => $image,
						'tax'        => $tax,
						'special'    => $special,
						'specialeur' => $specialeur,
						'href'       => $this->url->link('product/product', 'product_id=' . $pro_info['product_id'])
					);
				}
			}

			$discountprice = 0;
			$newprice = 0;
			$default_group_id = $this->config->get('config_customer_group_id');
			$customer_group_id = $this->customer->getGroupId();
		    
			if($customer_group_id) {
				$group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle_id."' and customer_group_id='".(int)$customer_group_id."'");
			} else {
				$group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle_id."' and customer_group_id='".(int)$default_group_id."'");
			}
			if ($group_query->num_rows) {
				$discountvalue = 0;
				if($group_query->row['discount_option'] == 'Percentage') {
					$discountprice = ($totalprices * ($group_query->row['add_discount'] / 100));
				} else {
					$discountprice = $group_query->row['add_discount'];
				}
				$newprice = $totalprices - $discountprice;
			}
			// 30 03 2021 //
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$totalprices   = $this->currency->format($totalprices, $this->config->get('config_currency'));
				$discountprice = $this->currency->format($discountprice, $this->config->get('config_currency'));
				$newprice      = $this->currency->format($newprice, $this->config->get('config_currency'));
			} else {
				$totalprices = false;
				$discountprice = false;
				$newprice = false;
			}
			// 30 03 2021 //
			$data['probundles'][] = array(
				'title'           => $titles,
				'product_infos'   => $products,
				'customer_groups' => $this->customer->getGroupId(),
				// 30 03 2021 //
				'total'           => $totalprices,
				'discount'        => $discountprice,
				'newprice'        => $newprice,
				// 30 03 2021 // 
				'bundlehref'      => $this->url->link('extension/shoppingcart', 'bundle_id=' . $bundle_id),
				/* 13 aug 2020 */	
				'date_from'       => $date_from,
				'date_to'         => strtotime($date_to),
				'current_date'    => strtotime(date('Y-m-d')),
				/* 13 aug 2020 */
			);
		}

		$data['time'] = time();
		$url1 = '';

		if(isset($this->request->get['product_id'])){
			$url1 .= '&product_id='.$this->request->get['product_id'];
		}

		$data['tmdrelatedproduct'] = str_replace('&amp;','&',$this->url->link('extension/module/relatedbundle/relatedproductpage',$url1));

		// Ako je $disable != 1, prikaži view (tvoja izvorna logika)
		if ($disable != 1) {
			return $this->load->view('extension/module/relatedbundle', $data);
		}
	}
	// ---------- kraj index() ----------

	public function relatedproductpage() {
		$this->load->language('extension/module/relatedbundle');
		$this->load->model('extension/bundle');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$bundle_infos = $this->config->get('module_bundle_languageseting');

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['title'])){
			$data['btitles']	= $bundle_infos[$this->config->get('config_language_id')]['title'];
		} else {
			$data['btitles'] = $this->language->get('heading_title');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['description'])){
			$data['description']	= $bundle_infos[$this->config->get('config_language_id')]['description'];
		} else {
			$data['description'] = $this->language->get('heading_title');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['yousave'])){
			$data['text_save']	= $bundle_infos[$this->config->get('config_language_id')]['yousave'];
		} else {
			$data['text_save'] = $this->language->get('text_save');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['price'])){
			$data['text_price']	= $bundle_infos[$this->config->get('config_language_id')]['price'];
		} else {
			$data['text_price'] = $this->language->get('text_price');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['regularprice'])){
			$data['text_regularprice']	= $bundle_infos[$this->config->get('config_language_id')]['regularprice'];
		} else {
			$data['text_regularprice'] = $this->language->get('text_regularprice');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['taxprice'])){
			$data['text_taxprice']	= $bundle_infos[$this->config->get('config_language_id')]['taxprice'];
		} else {
			$data['text_taxprice'] = $this->language->get('text_taxprice');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['addtocart'])){
			$data['button_addtocart']	= $bundle_infos[$this->config->get('config_language_id')]['addtocart'];
		} else {
			$data['button_addtocart'] = $this->language->get('button_addtocart');
		}

		if(!empty($bundle_infos[$this->config->get('config_language_id')]['continueshopping'])){
			$data['button_continue']	= $bundle_infos[$this->config->get('config_language_id')]['continueshopping'];
		} else {
			$data['button_continue'] = $this->language->get('button_continue');
		}

		if(isset($this->request->get['product_id'])) {
			$product_id = $this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$modulelimit = $this->config->get('module_bundle_modulelimit');
		if($modulelimit) {
			if (isset($this->request->get['limit'])) {
				$limit = (int)$this->request->get['limit'];
			} else {
				$limit = $modulelimit;
			}
		} else {
			if (isset($this->request->get['limit'])) {
				$limit = (int)$this->request->get['limit'];
			} else {
				$limit = 5;
			}
		}

		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (isset($this->request->get['limit'])) {
		 	$url .= '&limit=' . $this->request->get['limit'];
		}

		$data['probundles'] =array();
		$filter_data=array(
			'start'      	=> ($page - 1) * $limit,
			'limit'      	=> $limit
		);

		$bundle_total = $this->model_extension_bundle->getTotalProBundles($filter_data,$product_id);
		
		$probundles = $this->model_extension_bundle->getBundleProducts($filter_data,$product_id);
		foreach($probundles as $result) {
			$bundle_info = $this->model_extension_bundle->getBundle($result['bundle_id']);
			if(isset($bundle_info['title'])) {
				$titles = $bundle_info['title'];
			} else {
				$titles = '';
			}
			
			if(isset($bundle_info['bundle_id'])) {
				$bundle_id = $bundle_info['bundle_id'];
			} else {
				$bundle_id = '';
			}
			if(isset($bundle_info['date_from'])) {
				$date_from = $bundle_info['date_from'];
			} else {
				$date_from = '';
			}
			
			if(isset($bundle_info['date_to'])) {
				$date_to = $bundle_info['date_to'];
			} else {
				$date_to = '';
			}

			$totalprices=0;
			$tn_price=0;

			$probundles = $this->model_extension_bundle->getBundlePros($bundle_info['bundle_id']);

			$products=array();
			foreach($probundles as $product_infos) {
				$pro_info = $this->model_catalog_product->getProduct($product_infos['product_id']);
				if($pro_info) {
					if(!empty($this->config->get('module_bundle_height'))){
						$imageheight = $this->config->get('module_bundle_height');
					} else {
						$imageheight = '';
					}
					
					if(!empty($this->config->get('module_bundle_width'))){
						$imagewidth = $this->config->get('module_bundle_width');
					} else {
						$imagewidth = '';
					}

					if (isset($pro_info['image'])) {
						$image = $this->model_tool_image->resize($pro_info['image'], $imageheight, $imagewidth);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $imageheight, $imagewidth);
					}

					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$price = $this->currency->format($this->tax->calculate($pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));

						if($this->session->data['currency']=='HRK'){
							$priceeur = $this->currency->format($this->tax->calculate($pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax')), 'EUR');
						}
						else{
							$priceeur = $this->currency->format($this->tax->calculate($pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax')), 'HRK');
						}
					} else {
						$price = false;
						$priceeur  ='';
					}

					if (($this->customer->isLogged() || !$this->config->get('config_customer_price')) && (float)$pro_info['special']) {
						$special = $this->currency->format($this->tax->calculate($pro_info['special'], $pro_info['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));
						if($this->session->data['currency']=='HRK'){
							$specialeur = $this->currency->format($this->tax->calculate($pro_info['special'], $pro_info['tax_class_id'], $this->config->get('config_tax')),  'EUR');
						}
						else{
							$specialeur = $this->currency->format($this->tax->calculate($pro_info['special'], $pro_info['tax_class_id'], $this->config->get('config_tax')),  'HRK');
						}
					} else {
						$special = false;
						$specialeur  ='';
					}

					if ($this->config->get('config_tax')) {
						$tax = $this->currency->format((float)$pro_info['special'] ? $pro_info['special'] : $pro_info['price'], $this->config->get('config_currency'));
					} else {
						$tax = false;
					}

					$tn_price =$this->tax->calculate($pro_info['special'] ? $pro_info['special'] : $pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax'));
					
					if(isset($product_infos['quantity'])) {
						$totalprices += $tn_price * $product_infos['quantity'];
					} else {
						$totalprices +=$tn_price;
					}

					if(!empty($special)) {
						$price = $special;
						$priceeur = $specialeur;
					} else {
						$price = $price;
						$priceeur = $priceeur;
					}

					$products[]=array(
						'product_id' => $pro_info['product_id'],
						'name' 		 => $pro_info['name'],
						'minimum' 	 => $product_infos['quantity'],
						'price' 	 => $price,
						'priceeur' 	 => $priceeur,
						'image' 	 => $image,
						'href'       => $this->url->link('product/product', 'product_id=' . $pro_info['product_id'])
					);
				}
			}
			
			$discountprice =0;
			$newprice =0;
			$default_group_id = $this->config->get('config_customer_group_id');
			$customer_group_id = $this->customer->getGroupId();
		    
			if($customer_group_id) {
				$group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle_id."' and customer_group_id='".(int)$customer_group_id."'");
			} else {
				$group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle_id."' and customer_group_id='".(int)$default_group_id."'");
			}
			if ($group_query->num_rows) {
				$discountvalue=0;
				if($group_query->row['discount_option'] == 'Percentage') {
					$discountprice = ($totalprices*($group_query->row['add_discount']/100));
				} else {
					$discountprice = $group_query->row['add_discount'];
				}
				$newprice = $totalprices - $discountprice;
			}
			// 30 03 2021 //
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$totalprices= $this->currency->format($totalprices, $this->config->get('config_currency'));
				$discountprice= $this->currency->format($discountprice, $this->config->get('config_currency'));
				$newprice= $this->currency->format($newprice, $this->config->get('config_currency'));
			}
			else{
				$totalprices=false;
				$discountprice=false;
				$newprice=false;
			}
			// 30 03 2021 //
			$data['probundles'][] =array(
				'bundle_id'			=> $bundle_id,
				'title'			=> $titles,
				'product_infos'	=> $products,
				'customer_groups'=> $this->customer->getGroupId(),
				// 30 03 2021 //
				'total'			=> $totalprices,
				'discount'		=> $discountprice,
				'newprice'		=> $newprice,
				// 30 03 2021 // 
				'bundlehref'    => $this->url->link('extension/shoppingcart', 'bundle_id=' . $bundle_id),
				/* 13 aug 2020 */	
				'date_from'		 => $date_from,
				'date_to'		 => strtotime($date_to),
				'current_date' 	 => strtotime(date('Y-m-d')),
				/* 13 aug 2020 */
			);
		}
		
		$data['time'] = time();
		
		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (isset($this->request->get['limit'])) {
		 	$url .= '&limit=' . $this->request->get['limit'];
		}

		$pagination 		= new Pagination();
		$pagination->total  = $bundle_total;
		$pagination->page   = $page;
		$pagination->limit  = $limit;
		$pagination->url 	= $this->url->link('extension/module/relatedbundle/relatedproductpage&product_id='.$product_id,'page={page}');
		$data['pagination'] = $pagination->render();
		$data['results']    = sprintf($this->language->get('text_pagination'), ($bundle_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($bundle_total - $limit)) ? $bundle_total : ((($page - 1) * $limit) + $limit), $bundle_total, ceil($bundle_total / $limit));

		$this->response->setOutput($this->load->view('extension/module/relatedbundlepage', $data));
	}

	public function addToCart() {
		$this->load->language('checkout/cart');
		$this->load->model('catalog/product');
		$this->load->model('extension/shoppingcart');

		$json = array();

		if (isset($this->request->post['bundle_id'])) {
			$bundle_id = (int)$this->request->post['bundle_id'];
		} else {
			$bundle_id = 0;
		}

		$bundle_info = $this->model_extension_shoppingcart->getBundle($bundle_id);
		if(isset($bundle_info['title'])) {
			$bundletitles = $bundle_info['title'];
		} else {
			$bundletitles = '';
		}
			
		foreach($this->request->post['bundleproduct'] as $product) {		
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
			
			foreach($this->request->post['bundleproduct'] as $product) {		

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
					
					/* @Rakesh new */
					if(isset($bundle_id)) {
						$this->session->data['bundle_id'] = $bundle_id;
					}
					/* @Rakesh new */
				
					$this->cart->add($product_info['product_id'], $quantity, $option, $recurring_id, $bundle_id);

					if ($this->config->get('basel_cart_action') == 'redirect_cart') $json['success_redirect'] = $this->url->link('checkout/cart');
					if ($this->config->get('basel_cart_action') == 'redirect_checkout') $json['success_redirect'] = $this->url->link('checkout/checkout', '', true);

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
}
