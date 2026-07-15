<?php
class ControllerExtensionModuleBundle extends Controller {
	private $error = array();
	public function install()
	{
	$this->load->model('extension/bundleproduct');
	$this->model_extension_bundleproduct->install();
	}	
	public function uninstall()
	{
	$this->load->model('extension/bundleproduct');
	$this->model_extension_bundleproduct->uninstall();
	}
	public function index() {
		$this->load->language('extension/module/bundle');

		$this->document->setTitle($this->language->get('heading_title1'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_bundle', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['heading_title'] 				= $this->language->get('heading_title');
		$data['heading_title1'] 			= $this->language->get('heading_title1');
		$data['heading_bundleshoppingcart']	= $this->language->get('heading_bundleshoppingcart');
		$data['heading_bundleproduct']		= $this->language->get('heading_bundleproduct');
		$data['text_edit'] 					= $this->language->get('text_edit');
		$data['text_enabled'] 				= $this->language->get('text_enabled');
		$data['text_disabled'] 				= $this->language->get('text_disabled');
		$data['entry_title'] 				= $this->language->get('entry_title');
		$data['entry_description']  		= $this->language->get('entry_description');
		$data['entry_pagelimit'] 			= $this->language->get('entry_pagelimit');
		$data['entry_modulelimit'] 			= $this->language->get('entry_modulelimit');
		$data['entry_status'] 				= $this->language->get('entry_status');
		$data['entry_bundleheading']		= $this->language->get('entry_bundleheading');
		$data['entry_image'] 				= $this->language->get('entry_image');
		$data['entry_productname'] 			= $this->language->get('entry_productname');
		$data['entry_model'] 				= $this->language->get('entry_model');
		$data['entry_minquantity'] 			= $this->language->get('entry_minquantity');
		$data['entry_price'] 				= $this->language->get('entry_price');
		$data['entry_availability'] 		= $this->language->get('entry_availability');
		$data['entry_option'] 				= $this->language->get('entry_option');
		$data['entry_addtocart'] 			= $this->language->get('entry_addtocart');
		$data['entry_continueshopping']		= $this->language->get('entry_continueshopping');
		$data['entry_bundleproduct']		= $this->language->get('entry_bundleproduct');
		$data['entry_yousave']				= $this->language->get('entry_yousave');
		$data['entry_relatedpro']			= $this->language->get('entry_relatedpro');
		$data['entry_allbundle']			= $this->language->get('entry_allbundle');
		$data['entry_regularprice']			= $this->language->get('entry_regularprice');
		$data['entry_taxprice']				= $this->language->get('entry_taxprice');
		$data['entry_menu']				    = $this->language->get('entry_menu');

		$data['help_title'] 				= $this->language->get('help_title');
		$data['help_description']   		= $this->language->get('help_description');
		$data['help_pagelimit'] 			= $this->language->get('help_pagelimit');
		$data['help_modulelimit'] 			= $this->language->get('help_modulelimit');

		$data['button_save'] 				= $this->language->get('button_save');
		$data['button_cancel'] 				= $this->language->get('button_cancel');

		$data['tab_setting'] 				= $this->language->get('tab_setting');
		$data['tab_language'] 				= $this->language->get('tab_language');
		$data['text_yes'] 				    = $this->language->get('text_yes');
		$data['text_no'] 				    = $this->language->get('text_no');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		if (isset($this->error['error_keynotfound'])) {
				$data['error_keynotfound'] = $this->error['error_keynotfound'];
			} else {
				$data['error_keynotfound'] = '';
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

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title1'),
			'href' => $this->url->link('extension/module/bundle', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/bundle', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['user_token'] = $this->session->data['user_token'];
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['module_bundle_languageseting'])) {
			$data['module_bundle_languageseting'] = $this->request->post['module_bundle_languageseting'];
		} else {
			$data['module_bundle_languageseting'] = $this->config->get('module_bundle_languageseting');
		}

		if (isset($this->request->post['module_bundle_pagelimit'])) {
			$data['module_bundle_pagelimit'] = $this->request->post['module_bundle_pagelimit'];
		} else {
			$data['module_bundle_pagelimit'] = $this->config->get('module_bundle_pagelimit');
		}

		if (isset($this->request->post['module_bundle_modulelimit'])) {
			$data['module_bundle_modulelimit'] = $this->request->post['module_bundle_modulelimit'];
		} else {
			$data['module_bundle_modulelimit'] = $this->config->get('module_bundle_modulelimit');
		}

		if (isset($this->request->post['module_bundle_menu'])) {
			$data['module_bundle_menu'] = $this->request->post['module_bundle_menu'];
		} else {
			$data['module_bundle_menu'] = $this->config->get('module_bundle_menu');
		}

		if (isset($this->request->post['module_bundle_status'])) {
			$data['module_bundle_status'] = $this->request->post['module_bundle_status'];
		} else {
			$data['module_bundle_status'] = $this->config->get('module_bundle_status');
		}
	/* new work */
		if (isset($this->request->post['module_bundle_height'])) {
			$data['module_bundle_height'] = $this->request->post['module_bundle_height'];
		} else {
			$data['module_bundle_height'] = $this->config->get('module_bundle_height');
		}
		
		if (isset($this->request->post['module_bundle_width'])) {
			$data['module_bundle_width'] = $this->request->post['module_bundle_width'];
		} else {
			$data['module_bundle_width'] = $this->config->get('module_bundle_width');
		}
	/* new work */
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bundle', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/bundle')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		$key=$this->config->get('moduledata_tmdbundle_key');
			if (empty(trim($key))) {			
				 $this->error['warning'] ='Module will Work after add License key!';
			}

		return !$this->error;
	}
}
