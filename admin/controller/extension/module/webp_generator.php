<?php
class ControllerExtensionModuleWebpGenerator extends Controller {
	private $route = 'extension/module/webp_generator';

	public function index() {
		$this->load->language($this->route);
		$this->load->model($this->route);

		$this->document->setTitle($this->language->get('heading_title'));

		$data = $this->language->all();
		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
		$data['generate_url'] = $this->url->link($this->route . '/generate', 'user_token=' . $this->session->data['user_token'], true);
		$data['stats_url'] = $this->url->link($this->route . '/stats', 'user_token=' . $this->session->data['user_token'], true);
		$data['sizes'] = $this->getImageSizes();
		$data['quality'] = ModelExtensionModuleWebpGenerator::QUALITY;
		$data['gd_available'] = extension_loaded('gd') && function_exists('imagewebp');
		$data['can_modify'] = $this->user->hasPermission('modify', $this->route);

		$stats = $this->model_extension_module_webp_generator->getWebpCacheStats();
		$data['total_product_images'] = $this->model_extension_module_webp_generator->getTotalProductImages();
		$data['webp_files'] = $stats['files'];
		$data['webp_size'] = $this->formatBytes($stats['bytes']);
		$data['target_count'] = $data['total_product_images'] * count($data['sizes']);

		$data['javascript_translations'] = array(
			'confirm_force' => $this->language->get('text_confirm_force'),
			'starting' => $this->language->get('text_starting'),
			'processing' => $this->language->get('text_processing'),
			'stopping' => $this->language->get('text_stopping'),
			'stopped' => $this->language->get('text_stopped'),
			'complete' => $this->language->get('text_complete'),
			'request_failed' => $this->language->get('error_request'),
			'generated' => $this->language->get('text_generated'),
			'skipped' => $this->language->get('text_skipped'),
			'failed' => $this->language->get('text_failed')
		);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->route, $data));
	}

	public function generate() {
		$this->load->language($this->route);

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$this->json(array('error' => $this->language->get('error_method')));
			return;
		}

		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->json(array('error' => $this->language->get('error_permission')));
			return;
		}

		if (!extension_loaded('gd') || !function_exists('imagewebp')) {
			$this->json(array('error' => $this->language->get('error_webp_support')));
			return;
		}

		$start = isset($this->request->post['start']) ? max(0, (int)$this->request->post['start']) : 0;
		$limit = isset($this->request->post['limit']) ? max(1, min(20, (int)$this->request->post['limit'])) : 5;
		$force = !empty($this->request->post['force']);

		$this->load->model($this->route);

		try {
			$total = $this->model_extension_module_webp_generator->getTotalProductImages();
			$result = $this->model_extension_module_webp_generator->generateBatch(
				$start,
				$limit,
				$this->getImageSizes(),
				$force
			);

			$processed = min($total, $start + $result['processed_sources']);
			$result['total'] = $total;
			$result['processed'] = $processed;
			$result['next_start'] = $processed;
			$result['done'] = $result['processed_sources'] === 0 || $processed >= $total;
			$result['percent'] = $total > 0 ? min(100, (int)round(($processed / $total) * 100)) : 100;
			$result['errors'] = $this->translateErrors($result['errors']);

			$this->json($result);
		} catch (Throwable $exception) {
			$this->log->write('WebP Generator: Batch failed: ' . $exception->getMessage());
			$this->json(array('error' => $this->language->get('error_generation')));
		}
	}

	public function stats() {
		$this->load->language($this->route);

		if (!$this->user->hasPermission('access', $this->route)) {
			$this->json(array('error' => $this->language->get('error_permission')));
			return;
		}

		$this->load->model($this->route);

		$cache = $this->model_extension_module_webp_generator->getWebpCacheStats();
		$total = $this->model_extension_module_webp_generator->getTotalProductImages();

		$this->json(array(
			'total_product_images' => $total,
			'webp_files' => $cache['files'],
			'webp_size' => $this->formatBytes($cache['bytes']),
			'target_count' => $total * count($this->getImageSizes())
		));
	}

	private function getImageSizes() {
		$defaults = array(
			'product' => array(400, 400),
			'thumb' => array(600, 600),
			'popup' => array(800, 800),
			'additional' => array(120, 120),
			'related' => array(334, 334),
			'compare' => array(165, 165),
			'wishlist' => array(70, 70),
			'cart' => array(100, 100)
		);
		$theme = $this->config->get('config_theme') ?: 'default';
		$sizes = array();

		foreach ($defaults as $key => $fallback) {
			$prefix = 'theme_' . $theme . '_image_' . $key . '_';
			$width = (int)$this->config->get($prefix . 'width');
			$height = (int)$this->config->get($prefix . 'height');

			if (($width < 1 || $height < 1) && $theme !== 'default') {
				$width = (int)$this->config->get('theme_default_image_' . $key . '_width');
				$height = (int)$this->config->get('theme_default_image_' . $key . '_height');
			}

			if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
				$width = $fallback[0];
				$height = $fallback[1];
			}

			$dimension_key = $width . 'x' . $height;

			if (isset($sizes[$dimension_key])) {
				$sizes[$dimension_key]['label'] .= ' / ' . $this->language->get('size_' . $key);
			} else {
				$sizes[$dimension_key] = array(
					'label' => $this->language->get('size_' . $key),
					'width' => $width,
					'height' => $height,
					'mode' => 'contain'
				);
			}
		}

		return array_values($sizes);
	}

	private function translateErrors(array $errors) {
		foreach ($errors as &$error) {
			$key = 'error_' . $error['code'];
			$message = $this->language->get($key);

			if ($message === $key) {
				$message = $this->language->get('error_conversion_failed');
			}

			$error['message'] = $message;
			unset($error['code']);
		}
		unset($error);

		return $errors;
	}

	private function formatBytes($bytes) {
		$bytes = max(0, (int)$bytes);
		$units = array('B', 'KB', 'MB', 'GB');
		$power = $bytes > 0 ? min((int)floor(log($bytes, 1024)), count($units) - 1) : 0;
		$value = $bytes / pow(1024, $power);

		return number_format($value, $power === 0 ? 0 : 1, ',', '.') . ' ' . $units[$power];
	}

	private function json(array $data) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}
}
