<?php
class ControllerErrorNotFound extends Controller {
	public function index() {
		$this->load->language('error/not_found');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setDescription($this->language->get('meta_description'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		if (isset($this->request->get['route'])) {
			$url_data = $this->request->get;

			unset($url_data['_route_']);

			$route = $url_data['route'];

			unset($url_data['route']);

			$url = '';

			if ($url_data) {
				$url = '&' . urldecode(http_build_query($url_data, '', '&'));
			}

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link($route, $url, $this->request->server['HTTPS'])
			);
		}

		$data['continue'] = $this->url->link('common/home');
		$data['search_action'] = 'index.php';
		$data['special'] = $this->url->link('product/special');
		$data['brands'] = $this->url->link('product/manufacturer');
		$data['contact'] = $this->url->link('information/contact');
		$data['requested_path'] = !empty($this->request->server['REQUEST_URI'])
			? parse_url($this->request->server['REQUEST_URI'], PHP_URL_PATH)
			: '/';

		$this->load->model('catalog/category');
		$data['popular_categories'] = array();
		$excluded_categories = array('back to school', 'american dream');

		foreach ($this->model_catalog_category->getCategories(0) as $category) {
			if (in_array(utf8_strtolower(trim($category['name'])), $excluded_categories, true)) {
				continue;
			}

			$data['popular_categories'][] = array(
				'name' => $category['name'],
				'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'])
			);

			if (count($data['popular_categories']) === 6) {
				break;
			}
		}

		$this->registry->set('seo_entity', array(
			'type' => 'error',
			'breadcrumbs' => $data['breadcrumbs']
		));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
		$this->response->addHeader('X-Robots-Tag: noindex, follow');

		$this->response->setOutput($this->load->view('error/not_found', $data));
	}
}
