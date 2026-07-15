<?php 

/*******************************************************************************
*                                 Opencart SEO Pack                            *
*                             © Copyright Ovidiu Fechete                       *
*                              email: ovife21@gmail.com                        *
*                Below source-code or any part of the source-code              *
*                          cannot be resold or distributed.                    *
*******************************************************************************/

class ControllerExtensionExtensionseoimages extends Controller { 
	private $error = array();
 
	public function index() {
	
		$this->load->language('extension/extension/seoimages');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('seoimages', $this->request->post);		
					
			$this->session->data['success'] = $this->language->get('text_success');
						
			$this->response->redirect($this->url->link('extension/extension/seoimages', 'user_token=' . $this->session->data['user_token'], 'SSL'));
		}
	
		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL'),
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/extension/seoimages', 'user_token=' . $this->session->data['user_token'], 'SSL'),
      		'separator' => ' :: '
   		);
	
		$data['heading_title'] = $this->language->get('heading_title');
		$data['user_token'] = $this->session->data['user_token'];
		$data['action'] = $this->url->link('extension/extension/seoimages', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		
		$data['seopack_parameters'] = array();
		$data['seopack_parameters'] = $this->config->get('seopack_parameters');
		
		$data['seoimages_parameters'] = array();
		
		if (isset($this->request->post['seoimages_parameters'])) {
			$data['seoimages_parameters'] = $this->request->post['seoimages_parameters'];
		} elseif ($this->config->get('seoimages_parameters')) { 
			$data['seoimages_parameters'] = $this->config->get('seoimages_parameters');
		}
		$initial_seoimages_parameters = array('seoimages_parameters'=>array('keywords'=>'%p'));
		if (!$data['seoimages_parameters']) 
			{
			$this->model_setting_setting->editSetting('seoimages', $initial_seoimages_parameters);		
			$data['seoimages_parameters']  = $initial_seoimages_parameters['seoimages_parameters'];			
			}
		
				
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
		
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}
	
	
	$data['header'] = $this->load->controller('common/header');
						$data['column_left'] = $this->load->controller('common/column_left');
						$data['footer'] = $this->load->controller('common/footer');

						$this->response->setOutput($this->load->view('extension/extension/seoimages', $data));
	
		 
	}
	
	
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/extension/seoimages')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}
	

	
}
?>