<?php
class ControllerCommonHome extends Controller {
	public function index() {
		if (isset($this->request->get['route']) && $this->request->get['route'] === 'common/home') {
			$query = $this->request->get;
			unset($query['route'], $query['_route_']);

			$home = !empty($this->request->server['HTTPS']) ? $this->config->get('config_ssl') : $this->config->get('config_url');
			if ($query) {
				$home .= '?' . http_build_query($query);
			}

			$this->response->redirect($home, 301);
		}

		$this->load->language('common/home');

		$language_id = (int)$this->config->get('config_language_id');
		$meta_title = $this->config->get('config_meta_title');
		$meta_description = $this->config->get('config_meta_description');
		$meta_keyword = $this->config->get('config_meta_keyword');

		if (is_array($meta_title)) {
			$meta_title = isset($meta_title[$language_id]) ? $meta_title[$language_id] : '';
		}

		if (is_array($meta_description)) {
			$meta_description = isset($meta_description[$language_id]) ? $meta_description[$language_id] : '';
		}

		if (is_array($meta_keyword)) {
			$meta_keyword = isset($meta_keyword[$language_id]) ? $meta_keyword[$language_id] : '';
		}

		$this->document->setTitle($meta_title ?: $this->config->get('config_name'));
		$this->document->setDescription($meta_description ?: $this->language->get('text_meta_description'));
		$this->document->setKeywords($meta_keyword);

		if (isset($this->request->get['route'])) {
			$this->document->addLink($this->config->get('config_url'), 'canonical');
		}

		$this->registry->set('seo_entity', array('type' => 'home', 'breadcrumbs' => array()));
		$data['heading_title'] = $this->language->get('heading_title');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
