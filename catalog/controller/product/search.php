<?php
class ControllerProductSearch extends Controller {
	public function index() {
		$this->load->language('product/search');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$search = isset($this->request->get['search']) ? $this->request->get['search'] : '';
		$tag    = isset($this->request->get['tag'])    ? $this->request->get['tag']    : (isset($this->request->get['search']) ? $this->request->get['search'] : '');
		$description = isset($this->request->get['description']) ? $this->request->get['description'] : '';
		$category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
		$sub_category = isset($this->request->get['sub_category']) ? $this->request->get['sub_category'] : '';
		$sort  = isset($this->request->get['sort'])  ? $this->request->get['sort']  : 'p.sort_order';
		$order = isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC';
		$page  = isset($this->request->get['page'])  ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');

		// SEO title
		if ($search !== '') {
			$this->document->setTitle($this->language->get('heading_title') . ' - ' . $search);
		} elseif ($tag !== '') {
			$this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('heading_tag') . $tag);
		} else {
			$this->document->setTitle($this->language->get('heading_title'));
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		// build URL helper
		$url = '';
		if ($search !== '')      $url .= '&search=' . urlencode(html_entity_decode($search, ENT_QUOTES, 'UTF-8'));
		if ($tag !== '')         $url .= '&tag=' . urlencode(html_entity_decode($tag, ENT_QUOTES, 'UTF-8'));
		if ($description !== '') $url .= '&description=' . $description;
		if ($category_id)        $url .= '&category_id=' . $category_id;
		if ($sub_category !== '')$url .= '&sub_category=' . $sub_category;
		if (isset($this->request->get['sort']))  $url .= '&sort=' . $this->request->get['sort'];
		if (isset($this->request->get['order'])) $url .= '&order=' . $this->request->get['order'];
		if (isset($this->request->get['page']))  $url .= '&page=' . $this->request->get['page'];
		if (isset($this->request->get['limit'])) $url .= '&limit=' . $this->request->get['limit'];

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/search', $url)
		);

		$data['heading_title'] = $search !== ''
			? $this->language->get('heading_title') . ' - ' . $search
			: $this->language->get('heading_title');

		$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));
		$data['compare'] = $this->url->link('product/compare');
		$data['lang'] = $this->language->get('code');

		// 3-level categories
		$data['categories'] = array();
		$categories_1 = $this->model_catalog_category->getCategories(0);
		foreach ($categories_1 as $category_1) {
			$level_2_data = array();
			$categories_2 = $this->model_catalog_category->getCategories($category_1['category_id']);
			foreach ($categories_2 as $category_2) {
				$level_3_data = array();
				$categories_3 = $this->model_catalog_category->getCategories($category_2['category_id']);
				foreach ($categories_3 as $category_3) {
					$level_3_data[] = array(
						'category_id' => $category_3['category_id'],
						'name'        => $category_3['name'],
					);
				}
				$level_2_data[] = array(
					'category_id' => $category_2['category_id'],
					'name'        => $category_2['name'],
					'children'    => $level_3_data
				);
			}
			$data['categories'][] = array(
				'category_id' => $category_1['category_id'],
				'name'        => $category_1['name'],
				'children'    => $level_2_data
			);
		}

		$data['products'] = array();

		// 🔒 VALIDACIJA UPITA: minimalno 2 znaka i barem jedan alfanumerik
		$search_norm = trim(preg_replace('/\s+/', ' ', (string)$search));
		$tag_norm    = trim(preg_replace('/\s+/', ' ', (string)$tag));

		$has_query = ($search_norm !== '' || $tag_norm !== '');
		$valid_query = false;
		if ($search_norm !== '' && utf8_strlen($search_norm) >= 2 && preg_match('/[[:alnum:]]/u', $search_norm)) $valid_query = true;
		if ($tag_norm !== ''    && utf8_strlen($tag_norm)    >= 2 && preg_match('/[[:alnum:]]/u', $tag_norm))    $valid_query = true;

		if ($has_query) {
			if (!$valid_query) {
				// 🔒 Ako je upit "glup" (npr. ijeoijoihoih ili 1 znak), NE zovi model, vrati prazno bez fallbacka
				$product_total = 0;
				$data['products'] = array();
				$data['results'] = sprintf($this->language->get('text_pagination'), 0, 0, 0, 0);
				$data['pagination'] = '';
			} else {
				$filter_data = array(
					'filter_name'         => $search_norm,
					'filter_tag'          => $tag_norm,
					'filter_description'  => $description,
					'filter_category_id'  => $category_id,
					'filter_sub_category' => $sub_category,
					'sort'                => $sort,
					'order'               => $order,
					'start'               => ($page - 1) * $limit,
					'limit'               => $limit
				);

				$product_total = $this->model_catalog_product->getTotalProducts($filter_data);
				$results = $this->model_catalog_product->getProducts($filter_data);

				foreach ($results as $result) {
					if ($result['image']) {
						$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
					}

					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						$price_last_30 = $this->currency->format($this->tax->calculate($result['price_last_30'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

						if ($this->session->data['currency'] == 'HRK') {
							$priceeur = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), 'EUR');
						} else {
							$priceeur = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), 'HRK');
						}
					} else {
						$price = false;
						$price_last_30 = false;
						$priceeur = '';
					}

					if ((float)$result['special']) {
						$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						if ($this->session->data['currency'] == 'HRK') {
							$specialeur = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), 'EUR');
						} else {
							$specialeur = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), 'HRK');
						}
					} else {
						$special = false;
						$specialeur = '';
					}

					if ($this->config->get('config_tax')) {
						$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
					} else {
						$tax = false;
					}

					$rating = $this->config->get('config_review_status') ? (int)$result['rating'] : false;

					$data['products'][] = array(
						'product_id'  => $result['product_id'],
						'thumb'       => $image,
						'name'        => $result['name'],
						'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
						'price'       => $price,
						'special'     => $special,
						'stock'       => $result['stock_status'],
						'stock_id'    => $result['stock_status_id'],
						'priceeur'    => $priceeur,
						'specialeur'  => $specialeur,
						'price_last_30' => $price_last_30,
						'tax'         => $tax,
						'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
						'rating'      => $rating,
						'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url)
					);
				}

				// rebuild url for sorts/limits/pagination
				$url = '';
				if ($search_norm !== '') $url .= '&search=' . urlencode(html_entity_decode($search_norm, ENT_QUOTES, 'UTF-8'));
				if ($tag_norm !== '')    $url .= '&tag=' . urlencode(html_entity_decode($tag_norm, ENT_QUOTES, 'UTF-8'));
				if ($description !== '') $url .= '&description=' . $description;
				if ($category_id)        $url .= '&category_id=' . $category_id;
				if ($sub_category !== '')$url .= '&sub_category=' . $sub_category;
				if (isset($this->request->get['limit'])) $url .= '&limit=' . $this->request->get['limit'];

				// sorts
				$data['sorts'] = array();
				$data['sorts'][] = array('text' => $this->language->get('text_default'),'value' => 'p.sort_order-ASC','href'  => $this->url->link('product/search', 'sort=p.sort_order&order=ASC' . $url));
				$data['sorts'][] = array('text' => $this->language->get('text_name_asc'),'value' => 'pd.name-ASC','href'  => $this->url->link('product/search', 'sort=pd.name&order=ASC' . $url));
				$data['sorts'][] = array('text' => $this->language->get('text_name_desc'),'value' => 'pd.name-DESC','href'  => $this->url->link('product/search', 'sort=pd.name&order=DESC' . $url));
				$data['sorts'][] = array('text' => $this->language->get('text_price_asc'),'value' => 'p.price-ASC','href'  => $this->url->link('product/search', 'sort=p.price&order=ASC' . $url));
				$data['sorts'][] = array('text' => $this->language->get('text_price_desc'),'value' => 'p.price-DESC','href'  => $this->url->link('product/search', 'sort=p.price&order=DESC' . $url));
				if ($this->config->get('config_review_status')) {
					$data['sorts'][] = array('text' => $this->language->get('text_rating_desc'),'value' => 'rating-DESC','href'  => $this->url->link('product/search', 'sort=rating&order=DESC' . $url));
					$data['sorts'][] = array('text' => $this->language->get('text_rating_asc'),'value' => 'rating-ASC','href'  => $this->url->link('product/search', 'sort=rating&order=ASC' . $url));
				}
				$data['sorts'][] = array('text' => $this->language->get('text_model_asc'),'value' => 'p.model-ASC','href'  => $this->url->link('product/search', 'sort=p.model&order=ASC' . $url));
				$data['sorts'][] = array('text' => $this->language->get('text_model_desc'),'value' => 'p.model-DESC','href'  => $this->url->link('product/search', 'sort=p.model&order=DESC' . $url));

				// limits
				$url_limits = $url;
				if (isset($this->request->get['sort']))  $url_limits .= '&sort=' . $this->request->get['sort'];
				if (isset($this->request->get['order'])) $url_limits .= '&order=' . $this->request->get['order'];

				$data['limits'] = array();
				$limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));
				sort($limits);
				foreach ($limits as $value) {
					$data['limits'][] = array(
						'text'  => $value,
						'value' => $value,
						'href'  => $this->url->link('product/search', $url_limits . '&limit=' . (int)$value)
					);
				}

				// pagination
				$url_p = $url_limits;
				if (isset($this->request->get['limit'])) $url_p .= '&limit=' . $this->request->get['limit'];

				$pagination = new Pagination();
				$pagination->total = $product_total;
				$pagination->page = $page;
				$pagination->limit = $limit;
				$pagination->url = $this->url->link('product/search', $url_p . '&page={page}');
				$data['pagination'] = $pagination->render();

				$data['results'] = sprintf($this->language->get('text_pagination'),
					($product_total) ? (($page - 1) * $limit) + 1 : 0,
					((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit),
					$product_total,
					ceil($product_total / $limit)
				);

				if ($valid_query && (int)$product_total === 0) {
    // eksplicitno nema rezultata
    $data['products'] = array();
    $data['pagination'] = '';
    $data['results'] = sprintf($this->language->get('text_pagination'), 0, 0, 0, 0);
    $data['text_empty'] = $this->language->get('text_empty'); // “Nema proizvoda...”

    // 🔒 ključni trik: NE učitavaj kolone i content_* (tu su fallback moduli)
    $data['column_left']   = '';
    $data['column_right']  = '';
    $data['content_top']   = '';
    $data['content_bottom']= '';

    // ipak prikaži header/footer da stranica bude normalna
    $data['footer'] = $this->load->controller('common/footer');
    $data['header'] = $this->load->controller('common/header');

    // i odmah renderiraj view bez daljnjih modula
    $this->response->setOutput($this->load->view('product/search', $data));
    return;
}
			}
		}

		// view vars
		$data['search'] = $search;
		$data['description'] = $description;
		$data['category_id'] = $category_id;
		$data['sub_category'] = $sub_category;
		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['limit'] = $limit;

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/search', $data));
	}
}
