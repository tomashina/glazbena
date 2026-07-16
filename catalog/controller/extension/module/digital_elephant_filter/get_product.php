<?php
class ControllerExtensionModuleDigitalElephantFilterGetProduct extends Controller
{
    private $log = null;

    /**
     * @var ControllerExtensionModuleDigitalElephantFilterHelperUrl
     */
    private $helperUrl = null;


    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->log = new Log('DE-filter.2302.log');
        $this->helperUrl = $this->load->controller('extension/module/digital_elephant_filter/helper_url/prototype');
    }


    public function prototype()
    {
        return $this;
    }


    public function index()
    {
        $data_url = $this->helperUrl->getUrlData();

        $this->load->model('catalog/category');

          $data['newURL'] = $_SERVER["REQUEST_URI"];

        $this->loadModel();
        $this->loadLanguage();

        $data = $this->getText();
        $data += $this->getFixCoreNotice();

        $data_filter = $this->getFilterDataByUrl($data_url);

        $data['wishlist_products'] = $this->getWishlistProducts();

        $data['cart_products'] = $this->getCartProducts();

        if (isset($this->request->get['path'])) {
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

            $parts = explode('_', (string)$this->request->get['path']);

            $category_id = (int)array_pop($parts);

            foreach ($parts as $path_id) {
                if (!$path) {
                    $path = (int)$path_id;
                } else {
                    $path .= '_' . (int)$path_id;
                }

                $category_info = $this->model_catalog_category->getCategory($path_id);

                if ($category_info) {
                    $data['breadcrumbs'][] = array(
                        'text' => $category_info['name'],
                        'href' => $this->url->link('product/category', 'path=' . $path . $url)
                    );
                }
            }
        } else {
            $category_id = 0;
        }



        // RTL support
		$data['direction'] = $this->language->get('direction');
		if ($this->language->get('direction') == 'rtl') { $data['tooltip_align'] = 'right'; } else { $data['tooltip_align'] = 'left'; }
		$data['basel_list_style'] = $this->config->get('basel_list_style');
		$data['salebadge_status'] = $this->config->get('salebadge_status');
		$data['stock_badge_status'] = $this->config->get('stock_badge_status');
		$data['countdown_status'] = $this->config->get('countdown_status');
		$data['compare'] = $this->url->link('product/compare');
		$data['basel_prod_grid'] = $this->config->get('basel_prod_grid');
		$data['sorts'] = array();
		$data['limits'] = array();

        $data['products'] = $this->getProducts($data_filter);

        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        if ($data['products']) {

        $this->response->setOutput($this->load->view('product/category', $data));

        } else {
            return false;
        }
    }

    protected function getFixCoreNotice()
    {
        $data['breadcrumbs'] = [];
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['thumb'] = '';
        $data['categories'] = [];
        $data['text_compare'] = '';
        $data['pagination'] = '';
        $data['results'] = '';
        $data['content_bottom'] = '';
        $data['heading_title'] = '';
        $data['description'] = '';
        $data['text_sort'] = '';
        $data['text_limit'] = '';
        $data['sorts'] = '';
        $data['limits'] = '';
        $data['button_grid'] = '';
        $data['button_list'] = '';

        return $data;
    }


    protected function loadModel()
    {
        $this->load->model('extension/module/digitalElephantFilter');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
		$this->load->model('extension/basel/basel');
    }

    protected function loadLanguage()
    {
        $this->load->language('product/category');
        $this->load->language('extension/module/digitalElephantFilter');
		$this->load->language('basel/basel_theme');
    }

    public function getFilterDataByUrl($data_url) {
        $filter_data = array(
            'filter_sub_category' => true,
            'filter_category_id' => $data_url['category_id'],
            'filter_filter'      => $data_url['opencart_filter'],
            'sub_categories'     => $data_url['sub_categories'],
            'manufacturers'      => $data_url['manufacturers'],
            'options'            => $data_url['option'],
            'attributes'         => $data_url['attribute'],
            'price'              => $data_url['price'],
            'sort'               => $data_url['sort'],
            'order'              => $data_url['order'],
            'start'              => ($data_url['page'] - 1) * $data_url['limit'],
            'limit'              => $data_url['limit'],
        );

        return $filter_data;
    }


    public function getCategories($product_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

        return $query->rows;
    }




    private function getProducts($data_filter) {

        $results = $this->model_extension_module_digitalElephantFilter->getProducts($data_filter);
		$this->model_catalog_product->primeConfiguredVariants($results);





        $products = array();

        foreach ($results as $result) {

        	if (VERSION >= '3.0.0.0') {
                $image_width = $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width');
                $image_height = $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height');
            } else {
                $image_width = $this->config->get($this->config->get('config_theme') . '_image_product_width');
                $image_height = $this->config->get($this->config->get('config_theme') . '_image_product_height');
            }

            if ($result['image'] && file_exists(DIR_IMAGE . $result['image'])) {
                $image = $this->model_tool_image->resize($result['image'], $image_width, $image_height);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', $image_width, $image_height);
            }

			$images = $this->model_catalog_product->getProductImages($result['product_id']);
			if(isset($images[0]['image']) && !empty($images[0]['image'])){
			$images =$images[0]['image'];
			} else {
			$images = false;
			}

            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                $price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                 if($this->session->data['currency']=='HRK'){
                    $priceeur = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), 'EUR');
                }
                else{
                  $priceeur = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), 'HRK');

                }
            } else {
                $price = false;
                 $priceeur  ='';
            }

            if ((float)$result['special']) {
                $special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                if($this->session->data['currency']=='HRK'){
                    $specialeur = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')),  'EUR');
                }
                else{
                   $specialeur = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')),  'HRK');

                }
            } else {
                $special = false;
                  $specialeur  ='';
            }

			$image2 = $this->model_catalog_product->getProductImages($result['product_id']);
			if(isset($image2[0]['image']) && !empty($image2[0]['image']) && $this->config->get('basel_thumb_swap')){
				$image2 = $image2[0]['image'];
			} else {
				$image2 = false;
			}

			if ( (float)$result['special'] && ($this->config->get('salebadge_status')) ) {
			if ($this->config->get('salebadge_status') == '2') {
				$sale_badge = '-' . number_format(((($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')))-($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax'))))/(($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')))/100)), 0, ',', '.') . '%';
			} else {
				$sale_badge = $this->language->get('basel_text_sale');
			}
			} else {
				$sale_badge = false;
			}

			if (strtotime($result['date_added']) > strtotime('-' . $this->config->get('newlabel_status') . ' day')) {
				$is_new = true;
			} else {
				$is_new = false;
			}

			if ((float)$result['special']) {
				$date_end = $this->model_extension_basel_basel->getSpecialEndDate($result['product_id']);
			} else {
				$date_end = false;
			}

            if ($this->config->get('config_tax')) {
                $tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
            } else {
                $tax = false;
            }

            if ($this->config->get('config_review_status')) {
                $rating = (int)$result['rating'];
            } else {
                $rating = false;
            }

           if (VERSION >= '3.0.0.0') {
                $description = utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..';
            } else {
                $description = utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get($this->config->get('config_theme') . '_product_description_length')) . '..';
            }






            $query_categories = $this->model_catalog_product->getCategories($result['product_id']);



            foreach ($query_categories as  $cat) {

                if ($cat['product_id'] == $result['product_id'] && $cat['category_id']) {
                    $ocb_category = $this->model_catalog_category->getCategory($cat['category_id']);

                    $category_info['category_id'] = $ocb_category['category_id'];
                    $data['category'] = $ocb_category['name'];

                }
            }



                        $this->load->language('product/product_variant');
            $text_variant = '';
            $variants = array();
            $variant_setting = $this->config->get('product_variant_setting');
            if($this->config->get('product_variant_setting_status') && $variant_setting['catalog']){
                $variant_images = $this->config->get('product_variant_image'); 
                $variant_count = 0;
                $produkts = array();
                
                if($variant_setting['type'] == 'custom') {
                    $variant_ids = $this->model_catalog_product->getProductVariant($result['product_id']);
                    foreach($variant_ids as $variant_id) {
                        $produkt = $this->model_catalog_product->getProduct($variant_id);
                        if($produkt){
                            $produkts[] = $produkt;
                        }
                    }
                } else {
                    if(isset($result[$variant_setting['type']]) && !empty($result[$variant_setting['type']])){
                        $filters = array(
                            'filter_name' => $result[$variant_setting['type']]
                        );
						$produkts = $this->model_catalog_product->getProductsByConfiguredVariant($filters['filter_name']);
                    }                       
                }
                
                foreach($produkts as $produkt) {
					if($variant_setting['quantity'] == 0 && $produkt['quantity'] <= 0) {
                        continue;
                    }
                                            
                    if($variant_setting['catalog'] == 2){
                            
                        if(isset($variant_images[$produkt['product_id']]) && !empty($variant_images[$produkt['product_id']])){
                            $variant_image = $variant_images[$produkt['product_id']];
                        } else {
                            $variant_image = $produkt['image'];
                        }
                        
                        $width = ($variant_setting['image_width']) ? $variant_setting['image_width'] : 30;
                        $height = ($variant_setting['image_width']) ? $variant_setting['image_height'] : 30;
                        
                        $variants[$produkt['product_id']]['variant_image'] = $this->model_tool_image->resize($variant_image, $width, $height);
                                                                                        
                
                        $variants[$produkt['product_id']]['name'] = $produkt['name'];
                        $variants[$produkt['product_id']]['href'] = $this->url->link('product/product', 'product_id=' . $produkt['product_id']);
                        
                    } else {
                        $variant_count++;
                    }
                }
                
                unset($variants[$result['product_id']]);
                
                $variant_count--;
                if ($variant_count > 1) {
                    $text_variant = sprintf($this->language->get('text_variantions'), $variant_count);
                } elseif ($variant_count == 1) {
                    $text_variant = sprintf($this->language->get('text_variantion'), 1);
                }                   
            }








            $products[] = array(
                'product_id'  => $result['product_id'],
                'jan'  => $result['jan'],
                'thumb'       => $image,
				'thumb2' 	 => $this->model_tool_image->resize($image2, $image_width, $image_height),
				'sale_end_date'  => is_array($date_end) && isset($date_end['date_end']) ? $date_end['date_end'] : '',
                'name'        => $result['name'],
				'quantity'  => $result['quantity'],
                'description' => $description,
                'category'  => $ocb_category['name'],
                'stock' => $result['stock_status'],
                'stock_id' => $result['stock_status_id'],
                'price'       => $price,
                 'priceeur'       => $priceeur,
                'specialeur'     => $specialeur,
				'sale_badge'  => $sale_badge,
				'new_label'   => $is_new,
                'special'     => $special,
                'text_variant'=> $text_variant,
            'variants'=> $variants,
                'tax'         => $tax,
                'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
                'rating'      => $result['rating'],
                'href'        => $this->url->link('product/product', 'path=' . $this->request->get['path'] . '&product_id=' . $result['product_id'])
            );
        }

        return $products;
    }


    private function getCartProducts() {
        $result = array();
        $cart_products = $this->cart->getProducts();
        if ($cart_products) {
            foreach ($cart_products as $p) {
                $result[] = $p['product_id'];
            }
        }

        return $result;
    }

    private function getWishlistProducts() {
        return isset($this->session->data['wishlist']) ? $this->session->data['wishlist'] : array();
    }

    private function getText() {
        $data = array();
        $data['text_tax'] = $this->language->get('text_tax');
        $data['button_cart'] = $this->language->get('button_cart');
        $data['button_wishlist'] = $this->language->get('button_wishlist');
        $data['button_compare'] = $this->language->get('button_compare');
        $data['button_continue'] = $this->language->get('button_continue');
		$data['basel_text_out_of_stock'] = $this->language->get('basel_text_out_of_stock');
		$data['default_button_cart'] = $this->language->get('button_cart');
		$data['basel_button_quickview'] = $this->language->get('basel_button_quickview');
		$data['basel_text_sale'] = $this->language->get('basel_text_sale');
		$data['basel_text_new'] = $this->language->get('basel_text_new');
		$data['basel_text_days'] = $this->language->get('basel_text_days');
		$data['basel_text_hours'] = $this->language->get('basel_text_hours');
		$data['basel_text_mins'] = $this->language->get('basel_text_mins');
		$data['basel_text_secs'] = $this->language->get('basel_text_secs');
		$data['basel_text_out_of_stock'] = $this->language->get('basel_text_out_of_stock');
		$data['default_button_cart'] = $this->language->get('button_cart');

        return $data;
    }

    public function getTotalProducts($data_filter) {
        $this->loadModel();
        return $this->model_extension_module_digitalElephantFilter->getTotalProducts($data_filter);
    }


}
