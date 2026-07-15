<?php
class ControllerExtensionModuleAutoDetect extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/autodetect');

		$this->document->setTitle($this->language->get('heading_title1'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            if(isset($this->request->post['autodetect_status']))
			{
				$status=$this->request->post['autodetect_status'];
			}
			
			$postdata['module_autodetect_status']=$status;
			
			$this->model_setting_setting->editSetting('module_autodetect',$postdata);
			
			$this->model_setting_setting->editSetting('autodetect', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['heading_title']  	= $this->language->get('heading_title');

		$data['text_edit'] 			= $this->language->get('text_edit');
		$data['text_enabled']  		= $this->language->get('text_enabled');
		$data['text_disabled']  	= $this->language->get('text_disabled');
		$data['text_remove']  		= $this->language->get('text_remove');
		$data['text_add']  			= $this->language->get('text_add');

		$data['entry_status']  		= $this->language->get('entry_status');
		$data['entry_language']  	= $this->language->get('entry_language');
		$data['entry_currency']  	= $this->language->get('entry_currency');
		$data['entry_default'] 		= $this->language->get('entry_default');
		$data['entry_country'] 		= $this->language->get('entry_country');
		$data['text_select'] 		= $this->language->get('text_select');
		$data['text_default'] 		= $this->language->get('text_default');

		$data['button_save'] 		= $this->language->get('button_save');
		$data['button_cancel'] 		= $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/autodetect', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/autodetect', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		//language
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		//currency
		$this->load->model('localisation/currency');
		$data['currencies'] = $this->model_localisation_currency->getCurrencies();

		//country
		$this->load->model('localisation/country');
		$data['countries'] = $this->model_localisation_country->getCountries();


		if (isset($this->request->post['autodetect_status'])) {
			$data['autodetect_status'] = $this->request->post['autodetect_status'];
		} else {
			$data['autodetect_status'] = $this->config->get('autodetect_status');
		}

		if (isset($this->request->post['autodetect_language_id'])) {
			$data['autodetect_language_id'] = $this->request->post['autodetect_language_id'];
		} else {
			$data['autodetect_language_id'] = $this->config->get('autodetect_language_id');
		}

		if (isset($this->request->post['autodetect_currency_id'])) {
			$data['autodetect_currency_id'] = $this->request->post['autodetect_currency_id'];
		} else {
			$data['autodetect_currency_id'] = $this->config->get('autodetect_currency_id');
		}

		if (isset($this->request->post['autodetect_country_id'])) {
			$data['autodetect_country_id'] = $this->request->post['autodetect_country_id'];
		} else {
			$data['autodetect_country_id'] = $this->config->get('autodetect_country_id');
		}

	//multi country,corrency and language
		$data['autodetect_values'] = $this->config->get('autodetect_value');
		
		$autodetect_values = array();
		if(isset($autodetect_values)){
			foreach ($autodetect_values as $result) {
				$data['autodetect_values'][] = array(
					'country_id' 		=>$result['country_id'],
					'language_id' 		=>$result['language_id'],
					'currency_id' 		=>$result['currency_id']
				);
			}
		}
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/autodetect', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/autodetect')) {/**/
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}