<?php
class ControllerExtensionModuleBundlefeature extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/bundlefeature');

		$this->document->setTitle($this->language->get('heading_title1'));

			$this->load->model('setting/module');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('bundlefeature', $this->request->post);
			
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_product'] = $this->language->get('entry_product');
		$data['entry_limit'] = $this->language->get('entry_limit');
		$data['entry_width'] = $this->language->get('entry_width');
		$data['entry_height'] = $this->language->get('entry_height');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_type'] 			= $this->language->get('entry_type');
		$data['text_bundle'] 			= $this->language->get('text_bundle');
		$data['text_bundle_category'] 	= $this->language->get('text_bundle_category');
		$data['entry_category'] 	    = $this->language->get('entry_category');

		$data['help_product'] = $this->language->get('help_product');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['width'])) {
			$data['error_width'] = $this->error['width'];
		} else {
			$data['error_width'] = '';
		}

		if (isset($this->error['height'])) {
			$data['error_height'] = $this->error['height'];
		} else {
			$data['error_height'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/bundlefeature', 'user_token=' . $this->session->data['user_token'], true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/bundlefeature', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
		}

		if (!isset($this->request->get['module_id'])) {
			$data['action'] = $this->url->link('extension/module/bundlefeature', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/bundlefeature', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info)) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = '';
		}

		if (isset($this->request->post['type'])) {
			$data['type'] = $this->request->post['type'];
		} elseif (!empty($module_info)) {
			$data['type'] = $module_info['type'];
		} else {
			$data['type'] = '';
		}

		$data['bundle_categories'] = array();

		if (!empty($this->request->post['category'])) {
			$bundle_category = $this->request->post['category'];
		} elseif (!empty($module_info['category'])) {
			$bundle_category = $module_info['category'];
		} else {
			$bundle_category = array();
		}

		$this->load->model('extension/bundlecategory');
		foreach ($bundle_category as $bundle_category_id) {

			$bundle_info = $this->model_extension_bundlecategory->getCategory($bundle_category_id);

			if ($bundle_info) {
				$data['bundle_categories'][] = array(
					'bundle_category_id'   	=> $bundle_info['bundle_category_id'],
					'name'       			=> $bundle_info['name']
				);
			}
		}


		$data['bundles'] = array();

		if (!empty($this->request->post['bundle'])) {
			$bundle = $this->request->post['bundle'];
		} elseif (!empty($module_info['bundle'])) {
			$bundle = $module_info['bundle'];
		} else {
			$bundle = array();
		}

		$this->load->model('extension/bundleproduct');
		foreach ($bundle as $bundle_id) {

			$bundle_info = $this->model_extension_bundleproduct->getFeaturedBundle($bundle_id);

			if ($bundle_info) {
				$data['bundles'][] = array(
					'bundle_id'   => $bundle_info['bundle_id'],
					'title'       => $bundle_info['title']
				);
			}
		}

		if (isset($this->request->post['limit'])) {
			$data['limit'] = $this->request->post['limit'];
		} elseif (!empty($module_info)) {
			$data['limit'] = $module_info['limit'];
		} else {
			$data['limit'] = 5;
		}

		if (isset($this->request->post['width'])) {
			$data['width'] = $this->request->post['width'];
		} elseif (!empty($module_info)) {
			$data['width'] = $module_info['width'];
		} else {
			$data['width'] = 200;
		}

		if (isset($this->request->post['height'])) {
			$data['height'] = $this->request->post['height'];
		} elseif (!empty($module_info)) {
			$data['height'] = $module_info['height'];
		} else {
			$data['height'] = 200;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($module_info)) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bundlefeature', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/bundlefeature')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (!$this->request->post['width']) {
			$this->error['width'] = $this->language->get('error_width');
		}

		if (!$this->request->post['height']) {
			$this->error['height'] = $this->language->get('error_height');
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();
		if (isset($this->request->get['filter_title'])) {
			$this->load->model('extension/bundleproduct');

			$filter_data = array(
				'filter_title' => trim($this->request->get['filter_title']),
				'start'           => 0,
				'limit'           => 5
			);

			$results = $this->model_extension_bundleproduct->getBundles($filter_data);
			foreach ($results as $result) {
				$json[] = array(
					'bundle_id' 		=> $result['bundle_id'],
				    'title'            	=> $result['title']
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key]  = $value['title'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}