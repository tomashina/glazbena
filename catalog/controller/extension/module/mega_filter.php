<?php  
class ControllerExtensionModuleMegaFilter extends Controller {
	
	public static $_seo = null;
	
    /**
     * How frequently to execute garbage collection of cache
     *
     * @var int
     */
    protected $_gcFreq = 10;

    /**
     * Remove old files from cache directory
     */
    protected function _gc( $lifetime ) {
        $expire = time() - $lifetime * 60 * 60;
        $dir	= DIR_SYSTEM . 'cache_mfp';
		
		if( ! is_dir( $dir ) || ! is_writable( $dir ) ) return false;
		
        foreach( new DirectoryIterator( $dir ) as $file ) {
            if( ! $file->isDot() && ! $file->isDir() ) {
                if( $file->getMTime() < $expire ) {
                    unlink( $file->getPathname() );
                }
            }
        }
    }
	
	public function index( $setting ) {
		if( ! $setting || ! is_array( $setting ) || ! isset( $setting['status'] ) || ! isset( $setting['store_id'] ) ) {
			return;
		}
		
		$this->load->model('extension/module/mega_filter');
		
		if( ! class_exists( 'MegaFilterModule' ) || $this->rget('mfilterAjax') ) {
			return '';
		}
		
		if( self::seo( $this ) ) {			
			if( self::$_seo['meta_title'] ) {
				$this->document->setTitle(self::$_seo['meta_title']);
			}
				
			if( self::$_seo['meta_description'] ) {
				$this->document->setDescription(self::$_seo['meta_description']);
			}
				
			if( self::$_seo['meta_keyword'] ) {
				$this->document->setKeywords(self::$_seo['meta_keyword']);
			}
		}
		
		/* @var $settings array */
		$settings = $this->config->get('mega_filter_settings');
		
        if( ! empty( $settings['cache_enabled'] ) && mt_rand( 1, $this->_gcFreq ) == 1 ) {
            $this->_gc( isset( $settings['cache_lifetime'] ) ? (int) $settings['cache_lifetime'] : 24 );
        }
		
		/**
		 * Use this variable if you need to set any data to the view of MFP
		 * 
		 * @var $data
		 */
		//$data = array();
		
		return MegaFilterModule::newInstance( $this )/*->setData( $data )*/->render( $setting );
	}
	
	public static function seo( & $ctrl ) {
		if( self::$_seo === null && isset( $ctrl->request->request['mfp_seo_alias'] ) ) {
			self::$_seo = $ctrl->db->query( "
				SELECT 
					* 
				FROM 
					`" . DB_PREFIX . "mfilter_url_alias` 
				WHERE 
					`alias` = '" . $ctrl->db->escape( $ctrl->request->request['mfp_seo_alias'] ) . "' AND
					`language_id` = " . (int) $ctrl->config->get('config_language_id') . " AND
					`store_id` = " . (int) $ctrl->config->get('config_store_id') . "
			")->row;
		}
		
		return self::$_seo;
	}
	
	public function js_direction(){
		header('Content-type: text/javascript');
		
		echo 'var MFP_RTL = ' . ( $this->language->get('direction') == 'rtl' ? 'true' : 'false' ) . ';';
	}
	
	public function getajaxmodule() {
		header('X-Robots-Tag: noindex');
		
		$this->load->model('extension/module/mega_filter');
		
		if( ! class_exists( 'MegaFilterModule' ) ) {
			return '';
		}
		
		return MegaFilterModule::newInstance( $this )->render(array());
	}
	
	private function rget( $name ) {
		if( isset( $this->request->post[$name] ) ) {
			return $this->request->post[$name];
		}
		
		if( isset( $this->request->get[$name] ) ) {
			return $this->request->get[$name];
		}
		
		return null;
	}
	
	public function getajaxinfo() {
		header('X-Robots-Tag: noindex');
		
		$this->load->model('extension/module/mega_filter');
		
		$idx = 0;
		
		if( $this->rget('mfilterIdx') !== null ) {
			$idx = (int) $this->rget('mfilterIdx');
		}
		
		$baseTypes = array( 'stock_status', 'manufacturers', 'rating', 'attributes', 'options', 'filters' );
		
		if( $this->rget('mfilterBTypes') !== null ) {
			$baseTypes = explode( ',', $this->rget('mfilterBTypes') );
		}
		
		if( false !== ( $idx2 = array_search( 'categories:tree', $baseTypes ) ) ) {
			unset( $baseTypes[$idx2] );
		}
		
		/**
		 * Settings
		 */
		$settings	= $this->config->get('mega_filter_settings');
		$setting	= $this->model_extension_module_mega_filter->getModuleSettings( $idx );
		
		if( isset( $setting['configuration'] ) ) {
			foreach( $setting['configuration'] as $k => $v ) {
				$settings[$k] = $v;
			}
		}
		
		$core = MegaFilterCore::newInstance( $this, NULL, array( 'mfp_overwrite_path' => true ), $settings );
		
		$cache = null;
		
		if( ! empty( $settings['cache_enabled'] ) ) {
			$cache = 'idx.' . $idx . '.getajaxinfo.' . $core->cacheName();
		}
		
		/**
		 * Cache
		 */
		if( ! $cache || NULL == ( $response = $core->_getCache( $cache ) ) ) {
			$response = base64_encode( json_encode( $core->getJsonData($baseTypes, $idx) ) );
			
			if( ! empty( $settings['cache_enabled'] ) ) {
				$core->_setCache( $cache, $response );
			}
		}
		
		echo '<div id="mfilter-json">' . $response . '</div>';
	}
	
	public function getcategories() {
		header('X-Robots-Tag: noindex');
		
		$cats = array();
		
		if( ! empty( $this->request->post['cat_id'] ) ) {
			$this->load->model('catalog/category');
			
			foreach( $this->model_catalog_category->getCategories( $this->request->post['cat_id'] ) as $cat ) {
				$cats[] = array(
					'id' => $cat['category_id'],
					'name' => $cat['name']
				);
			}
		}
		
		echo json_encode( $cats );
	}
	
	public function results() {
		$data = array();
    	$data = $this->language->load('product/search');
		
		$this->load->model('catalog/category');		
		$this->load->model('catalog/product');		
		$this->load->model('tool/image');
		
		$keys	= array( 'sort' => 'p.sort_order', 'order' => 'ASC', 'page' => 1, 'limit' => $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit') );
		
		$url = '';
		
		foreach( $keys as $key => $keyDef ) {
			${$key} = isset( $this->request->get[$key] ) ? $this->request->get[$key] : $keyDef;
			
			if( isset( $this->request->get[$key] ) ) {
				$url .= '&' . $key . '=' . $this->request->get[$key];
			}
			
		}
		
		$this->document->setTitle($this->language->get('heading_title'));					

		/**
		 * Breadcrumb 
		 */
		$data['breadcrumbs'] = array();
   		$data['breadcrumbs'][] = array( 
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home'),
      		'separator' => false
   		);
		
   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/module/mega_filter/results', $url),
      		'separator' => $this->language->get('text_separator')
   		);
		
		$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));
		$data['compare'] = $this->url->link('product/compare');
		
		$data['products'] = array();

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

		if (isset($this->request->get['limit'])) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}
		
		if( $limit < 1 ) {
			$limit = 1;
		}
		
		$filter_data = array(
			'sort'                => $sort,
			'order'               => $order,
			'start'               => ($page - 1) * $limit,
			'limit'               => $limit
		);
		
		//if( empty( $this->request->get['path'] ) && ! empty( $this->request->get['mfilterPath'] ) ) {
		//	$this->request->get['path'] = MegaFilterCore::__parsePath( $this, $this->request->get['mfilterPath'] );
		//}
		
		if( ! empty( $this->request->get['path'] ) ) {
			$filter_data['filter_category_id'] = explode( '_', $this->request->get['path'] );
			$filter_data['filter_category_id'] = end( $filter_data['filter_category_id'] );
		}
		
		$product_total = $this->model_catalog_product->getTotalProducts($filter_data);								
		$results = $this->model_catalog_product->getProducts($filter_data);
		
		foreach ($results as $result) {			
			$description = '';
			$image = false;
			
			$description = utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..';

			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
			}
				
			if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
				if(version_compare( VERSION, '2.2.0.0', '>=' )) {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')));
				}
			} else {
				$price = false;
			}
				
			if ((float)$result['special']) {
				if(version_compare( VERSION, '2.2.0.0', '>=' )) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')));
				}
			} else {
				$special = false;
			}	
				
			if ($this->config->get('config_tax')) {
				if(version_compare( VERSION, '2.2.0.0', '>=' )) {
					$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
				} else {
					$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price']);
				}
			} else {
				$tax = false;
			}				
				
			if ($this->config->get('config_review_status')) {
				$rating = (int)$result['rating'];
			} else {
				$rating = false;
			}
			
			$data['products'][] = array(
				'product_id'  => $result['product_id'],
				'thumb'       => $image,
				'name'        => $result['name'],
				'description' => $description,
				'price'       => $price,
				'special'     => $special,
				'tax'         => $tax,
				'minimum'     => isset( $result['minimum'] ) && $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => $result['rating'],
				'reviews'     => sprintf($this->language->get('text_reviews'), (int)$result['reviews']),
				'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url),
			);
		}
					
		$url = '';
						
		$data['sorts'] = array();
			
		$data['sorts'][] = array(
			'text'  => $this->language->get('text_default'),
			'value' => 'p.sort_order-ASC',
			'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=p.sort_order&order=ASC' . $url)
		);
			
		$data['sorts'][] = array(
			'text'  => $this->language->get('text_name_asc'),
			'value' => 'pd.name-ASC',
			'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=pd.name&order=ASC' . $url)
		); 
	
		$data['sorts'][] = array(
			'text'  => $this->language->get('text_name_desc'),
			'value' => 'pd.name-DESC',
			'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=pd.name&order=DESC' . $url)
		);
	
		$data['sorts'][] = array(
			'text'  => $this->language->get('text_price_asc'),
			'value' => 'p.price-ASC',
			'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=p.price&order=ASC' . $url)
		); 
	
		$data['sorts'][] = array(
			'text'  => $this->language->get('text_price_desc'),
			'value' => 'p.price-DESC',
			'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=p.price&order=DESC' . $url)
		); 
			
		if ($this->config->get('config_review_status')) {
			$data['sorts'][] = array(
				'text'  => $this->language->get('text_rating_desc'),
				'value' => 'rating-DESC',
				'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=rating&order=DESC' . $url)
			); 
				
			$data['sorts'][] = array(
				'text'  => $this->language->get('text_rating_asc'),
				'value' => 'rating-ASC',
				'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=rating&order=ASC' . $url)
			);
		}
			
		$data['sorts'][] = array(
			'text'  => $this->language->get('text_model_asc'),
			'value' => 'p.model-ASC',
			'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=p.model&order=ASC' . $url)
		); 
	
		$data['sorts'][] = array(
			'text'  => $this->language->get('text_model_desc'),
			'value' => 'p.model-DESC',
			'href'  => $this->url->link('extension/module/mega_filter/results', 'sort=p.model&order=DESC' . $url)
		);
	
		$url = '';
						
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}	
	
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}
			
		$data['limits'] = array();
	
		$limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));
			
		sort($limits);
	
		foreach($limits as $limits){
			$data['limits'][] = array(
				'text'  => $limits,
				'value' => $limits,
				'href'  => $this->url->link('extension/module/mega_filter/results', $url . '&limit=' . $limits)
			);
		}
					
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

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('extension/module/mega_filter/results', $url . '&page={page}');

		$data['pagination'] = $pagination->render();

		$this->document->addLink($this->url->link('extension/module/mega_filter/results', $url . '&page=' . $pagination->page), 'canonical');

		if ($pagination->limit && ceil($pagination->total / $pagination->limit) > $pagination->page) {
			$this->document->addLink($this->url->link('extension/module/mega_filter/results', $url . '&page=' . ($pagination->page + 1)), 'next');
		}

		if ($pagination->page > 1) {
			$this->document->addLink($this->url->link('extension/module/mega_filter/results', $url . '&page=' . ($pagination->page - 1)), 'prev');
		}
		
		$data['results'] = sprintf(
			$this->language->get('text_pagination'), 
			($product_total) ? 
				(($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : 
				((($page - 1) * $limit) + $limit), 
			$product_total, 
			ceil($product_total / $limit)
		);

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
		
		/**
		 * Template
		 */
		if(version_compare( VERSION, '2.2.0.0', '>=' )) {
			$this->response->setOutput($this->load->view('product/special', $data));
		} else {
			if( file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/product/special.tpl') ) {
				$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/product/special.tpl', $data));
			} else {
				$this->response->setOutput($this->load->view('default/template/product/special.tpl', $data));
			}
		}
	}
}
?>