<?php
class ControllerExtensionModuleBundleFeature extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/bundlefeature');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_tax'] = $this->language->get('text_tax');

		$data['button_cart'] = $this->language->get('button_cart');
		$data['button_wishlist'] = $this->language->get('button_wishlist');
		$data['button_compare'] = $this->language->get('button_compare');

			$bundle_infos = $this->config->get('bundlepro_languageseting');

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

		
		$this->load->model('extension/bundleproduct');
		$this->load->model('extension/shoppingcart');
		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		$data['bundlefeatures'] = array();

		if (!$setting['limit']) {
			$setting['limit'] = 4;
		}

		if (empty($setting['type'])) {

			$category = $setting['category'];
			$probundles = $this->model_extension_bundleproduct->getBundleId($setting['category']);

			if (!empty($probundles)) {
				$setting['bundle'] = $probundles;
			}	
		}

		if (!empty($setting['bundle'])) {
			$bundle_product = array_slice($setting['bundle'], 0, (int)$setting['limit']);

			foreach($bundle_product as $bundle_id){
				$result = $this->model_extension_shoppingcart->getBundle($bundle_id);

				if($result) {
					$totalprices=0;
					$tn_price=0;
					$probundles = $this->model_extension_bundleproduct->getBundleProducts($result['bundle_id']);

					$products=array();
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
								
							if (isset($pro_info['image'])) {
								$image = $this->model_tool_image->resize($pro_info['image'], $imageheight, $imagewidth);
							} else {
								$image = $this->model_tool_image->resize('placeholder.png', $imageheight, $imagewidth);
							}

							if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
								$price = $this->currency->format($this->tax->calculate($pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));
							} else {
								$price = false;
							}

							// 30 03 2021 //
							if (($this->customer->isLogged() || !$this->config->get('config_customer_price')) && (float)$pro_info['special']) {
							// 30 03 2021 //
								$special = $this->currency->format($this->tax->calculate($pro_info['special'], $pro_info['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'));
							} else {
								$special = false;
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
								$totalprices += $tn_price;
							}
							if(isset($product_infos['quantity'])) {
								$minimum = $product_infos['quantity'];
							} else {
								$minimum = 0;
							}

							if(!empty($special)) {
								$price = $special;
							} else {
								$price = $price;
							}

							$products[]=array(
								'product_id' => $pro_info['product_id'],
								'name' 		 => $pro_info['name'],
								'price' 	 => $price,
								'image' 	 => $image,
								'minimum' 	 => $minimum,
								'href'       => $this->url->link('product/product', 'product_id=' . $pro_info['product_id'])
							);
						}
					}
					$discountprice =0;
					$newprice =0;

					$default_group_id = $this->config->get('config_customer_group_id');
					$customer_group_id = $this->customer->getGroupId();
					
					if($customer_group_id) {
						$group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$result['bundle_id']."' and customer_group_id='".(int)$customer_group_id."'");
					} else {
						$group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$result['bundle_id']."' and customer_group_id='".(int)$default_group_id."'");
					}
					if ($group_query->num_rows) {
						if($group_query->row['discount_option'] == 'Percentage') {
							$discountprice = ($totalprices*($group_query->row['add_discount']/100));
						} else {
							$discountprice = $group_query->row['add_discount'];
						}
						$newprice = $totalprices - $discountprice;
					}

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
					$data['bundlefeatures'][] = array(
						'bundle_id'		=> $result['bundle_id'],
						'customer_groups'=> $this->customer->getGroupId(),
						'title'			=> $result['title'],
						'product_infos'	=> $products,
						// 30 03 2021 //
						'total'			=> $totalprices,
						'discount'		=> $discountprice,
						'newprice'		=> $newprice,
					// 30 03 2021 //
						'bundlehref'    => $this->url->link('bundle/shoppingcart', 'bundle_id=' . $result['bundle_id']),
						/* 13 aug 2020 */	
						'date_from'		 => $result['date_from'],
						'date_to'		 => strtotime($result['date_to']),
						'current_date' 	 => strtotime(date('Y-m-d')),
					/* 13 aug 2020 */
					);
				}
			}
		}
		$data['time'] = time();

		if ($data['bundlefeatures']) {
			return $this->load->view('extension/module/bundlefeature', $data);
		}
	}
}