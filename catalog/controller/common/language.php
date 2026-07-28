<?php
class ControllerCommonLanguage extends Controller {
	public function index() {
		$this->load->language('common/language');

		$data['action'] = $this->url->link('common/language/language', '', $this->request->server['HTTPS']);

		$data['code'] = $this->session->data['language'];

		$this->load->model('localisation/language');

		$data['languages'] = array();

		$results = $this->model_localisation_language->getLanguages();

		foreach ($results as $result) {
			if ($result['status']) {
				$data['languages'][] = array(
					'name' => $result['name'],
					'code' => $result['code']
				);
			}
		}

		if (!isset($this->request->get['route'])) {
			$data['redirect'] = $this->url->link('common/home');
		} else {
			$url_data = $this->request->get;

			unset($url_data['_route_']);

			$route = $url_data['route'];

			unset($url_data['route']);

			$url = '';

			if ($url_data) {
				$url = '&' . urldecode(http_build_query($url_data, '', '&'));
			}

			$data['redirect'] = $this->url->link($route, $url, $this->request->server['HTTPS']);
		}

		return $this->load->view('common/language', $data);
	}

	public function language() {
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['code'])) {
			$code = strtolower($this->request->post['code']);

			if (isset($languages[$code])) {
				$this->session->data['language'] = $code;
			}
		}

		if (isset($this->request->post['redirect']) && (strpos($this->request->post['redirect'], $this->config->get('config_url')) === 0 || strpos($this->request->post['redirect'], $this->config->get('config_ssl')) === 0)) {
			$this->response->redirect($this->removeLanguagePrefix($this->request->post['redirect'], array_keys($languages)));
		} else {
			$this->response->redirect($this->url->link('common/home'));
		}
	}

	private function removeLanguagePrefix($url, $language_codes) {
		$url_info = parse_url($url);

		if (!$url_info || empty($url_info['host'])) {
			return $url;
		}

		$path = isset($url_info['path']) ? $url_info['path'] : '/';
		$base_path = parse_url($this->config->get('config_url'), PHP_URL_PATH);
		$base_path = $base_path ? rtrim($base_path, '/') : '';
		$escaped_codes = array();

		foreach ($language_codes as $language_code) {
			$escaped_codes[] = preg_quote($language_code, '#');
		}

		if ($escaped_codes) {
			$pattern = '#^' . preg_quote($base_path, '#') . '/(?:' . implode('|', $escaped_codes) . ')(?=/|$)#i';
			$path = preg_replace($pattern, $base_path, $path, 1);
		}

		if ($path === '') {
			$path = '/';
		}

		$clean_url = $url_info['scheme'] . '://' . $url_info['host'];

		if (isset($url_info['port'])) {
			$clean_url .= ':' . $url_info['port'];
		}

		$clean_url .= $path;

		if (isset($url_info['query'])) {
			$clean_url .= '?' . $url_info['query'];
		}

		if (isset($url_info['fragment'])) {
			$clean_url .= '#' . $url_info['fragment'];
		}

		return $clean_url;
	}
}
