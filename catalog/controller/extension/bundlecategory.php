<?php
class ControllerExtensionBundlecategory extends Controller {
	public function index() {
		$this->load->language('extension/bundlecategory');
		$this->load->language('extension/bundleproduct');

		$this->load->model('extension/bundlecategory');
		$this->load->model('extension/bundleproduct');
		$this->load->model('extension/bundle');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->get['filter'])) {
			$filter = $this->request->get['filter'];
		} else {
			$filter = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'p.sort_order';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}
		
		$modulelimit = $this->config->get('module_bundle_pagelimit');
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		if (isset($this->request->get['bundlepath'])) {
			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$path = '';

			$parts = explode('_', (string)$this->request->get['bundlepath']);

			$bundle_category_id = (int)array_pop($parts);

			foreach ($parts as $bundle_path_id) {
				if (!$path) {
					$path = (int)$bundle_path_id;
				} else {
					$path .= '_' . (int)$bundle_path_id;
				}

				$category_info = $this->model_extension_bundlecategory->getCategory($bundle_path_id);

				if ($category_info) {
					$data['breadcrumbs'][] = array(
						'text' => $category_info['name'],
						'href' => $this->url->link('extension/bundlecategory', 'bundlepath=' . $path . $url)
					);
				}
			}
		} else {
			$bundle_category_id = 0;
		}

		$category_info = $this->model_extension_bundlecategory->getCategory($bundle_category_id);

		if ($category_info) {
			$this->document->setTitle($category_info['meta_title']);
			$this->document->setDescription($category_info['meta_description']);
			$this->document->setKeywords($category_info['meta_keyword']);

			$data['heading_title'] = $category_info['name'];

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

			$data['text_empty'] 	= $this->language->get('text_empty');
			$data['text_refine'] 	= $this->language->get('text_refine');

			// Set the last category breadcrumb
			$data['breadcrumbs'][] = array(
				'text' => $category_info['name'],
				'href' => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'])
			);

			if ($category_info['image']) {
				$data['thumb'] = $this->model_tool_image->resize($category_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
			} else {
				$data['thumb'] = '';
			}
			
			$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['categories'] = array();

			$results = $this->model_extension_bundlecategory->getCategories($bundle_category_id);

			foreach ($results as $result) {
				$filter_data = array(
					'filter_bundle_category_id'  => $result['bundle_category_id'],
					'filter_sub_category' => true
				);

				$data['categories'][] = array(
					'name' => $result['name'] .' ('. $this->model_extension_bundleproduct->getTotalCategoryToBundles($filter_data).')',
					'href' => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '_' . $result['bundle_category_id'] . $url)
				);
			}

			$data['categorybundles'] = array();

			$filter_data = array(
				'filter_bundle_category_id' => $bundle_category_id,
				'filter_filter'      => $filter,
				'sort'               => $sort,
				'order'              => $order,
				'start'              => ($page - 1) * $limit,
				'limit'              => $limit
			);

			$bundletotal_info = $this->model_extension_bundlecategory->getTotalCategoryToBundles($filter_data,$bundle_category_id);
			$cateinfos = $this->model_extension_bundlecategory->getBundleToCategories($filter_data,$bundle_category_id);
			foreach($cateinfos as $result) {
				$bundle_info = $this->model_extension_bundle->getBundle($result['bundle_id']);
				if(isset($bundle_info['bundle_id'])) {
					$bundle_id = $bundle_info['bundle_id'];
				} else {
					$bundle_id = '';
				}

				if(isset($bundle_info['title'])) {
					$titles = $bundle_info['title'];
				} else {
					$titles = '';
				}

				if(isset($bundle_info['date_from'])) {
					$datefrom = $bundle_info['date_from'];
				} else {
					$datefrom = '';
				}

				if(isset($bundle_info['date_to'])) {
					$dateto = $bundle_info['date_to'];
				} else {
					$dateto = '';
				}

				if(isset($bundle_info['discount_option'])) {
					$discountoption = $bundle_info['discount_option'];
				} else {
					$discountoption = '';
				}

				if(isset($bundle_info['add_discount'])) {
					$adddiscount = $bundle_info['add_discount'];
				} else {
					$adddiscount = '';
				}
				
				$tn_price=0;
				$totalprices=0;
				
				$probundles = $this->model_extension_bundle->getBundlePros($result['bundle_id']);

				$products=array();
				foreach($probundles as $product_infos) {
					$pro_info = $this->model_catalog_product->getProduct($product_infos['product_id']);
					if($pro_info) {
						if (isset($pro_info['image'])) {
							$image = $this->model_tool_image->resize($pro_info['image'], 300, 300);
						} else {
							$image = $this->model_tool_image->resize('placeholder.png', 300, 300);
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

						// 30 03 2021 //
						if (($this->customer->isLogged() || !$this->config->get('config_customer_price')) && (float)$pro_info['special']) {
					// 30 03 2021 //
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

						if(isset($product_infos['quantity'])) {
							$minimum = $product_infos['quantity'];
						} else {
							$minimum = 0;
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
							'minimum' 	 => $minimum,
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
				
				$data['categorybundles'][] =array(
					'bundle_id'		=> $bundle_id,
					'title'			=> $titles,
					'product_infos'	=> $products,
					'customer_groups'=> $this->customer->getGroupId(),
					// 30 03 2021 //
					'total'			=> $totalprices,
					'discount'		=> $discountprice,
					'newprice'		=> $newprice,
					// 30 03 2021 // 
					'bundlehref'    => $this->url->link('extension/shoppingcart', 'bundle_id=' . $bundle_id)
				);
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = array();

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=p.sort_order&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . '&sort=p.model&order=DESC' . $url)
			);

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			$data['limits'] = array();

			$limits = array_unique(array($this->config->get($this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));

			sort($limits);

			foreach($limits as $value) {
				$data['limits'][] = array(
					'text'  => $value,
					'value' => $value,
					'href'  => $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . $url . '&limit=' . $value)
				);
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}
			
			$pagination = new Pagination();
			$pagination->total = $bundletotal_info;
			$pagination->page = $page;
			$pagination->limit = $limit;

			$pagination->url = $this->url->link('extension/bundlecategory', 'bundlepath=' . $this->request->get['bundlepath'] . $url . '&page={page}');

			$data['pagination'] = $pagination->render();
			$data['results'] = sprintf($this->language->get('text_pagination'), ($bundletotal_info) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($bundletotal_info - $limit)) ? $bundletotal_info : ((($page - 1) * $limit) + $limit), $bundletotal_info, ceil($bundletotal_info / $limit));
			
			if ($page == 1) {
			    $this->document->addLink($this->url->link('extension/bundlecategory', 'bundlepath=' . $category_info['bundle_category_id']), 'canonical');
			} else {
				$this->document->addLink($this->url->link('extension/bundlecategory', 'bundlepath=' . $category_info['bundle_category_id'] . '&page='. $page), 'canonical');
			}
			
			if ($page > 1) {
			    $this->document->addLink($this->url->link('extension/bundlecategory', 'bundlepath=' . $category_info['bundle_category_id'] . (($page - 2) ? '&page='. ($page - 1) : '')), 'prev');
			}

			if ($limit && ceil($bundletotal_info / $limit) > $page) {
			    $this->document->addLink($this->url->link('extension/bundlecategory', 'bundlepath=' . $category_info['bundle_category_id'] . '&page='. ($page + 1)), 'next');
			}
			
			$data['sort'] = $sort;
			$data['order'] = $order;
			$data['limit'] = $limit;

			$data['continue'] = $this->url->link('common/home');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('extension/bundlecategory', $data));
		} else {
			$url = '';

			if (isset($this->request->get['bundlepath'])) {
				$url .= '&path=' . $this->request->get['bundlepath'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('extension/bundlecategory', $url)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['heading_title'] = $this->language->get('text_error');

			$data['text_error'] = $this->language->get('text_error');

			$data['button_continue'] = $this->language->get('button_continue');

			$data['continue'] = $this->url->link('common/home');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}
}
