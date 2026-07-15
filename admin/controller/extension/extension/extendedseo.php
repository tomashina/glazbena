<?php 

/*******************************************************************************
*                                 Opencart SEO Pack                            *
*                             © Copyright Ovidiu Fechete                       *
*                              email: ovife21@gmail.com                        *
*                Below source-code or any part of the source-code              *
*                          cannot be resold or distributed.                    *
*******************************************************************************/

class ControllerExtensionExtensionExtendedSEO extends Controller {
	private $error = array(); 
	
	public function index() {
		$this->load->language('extension/extension/extendedseo');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
			
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('extendedseo', $this->request->post);				
			
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/extension/extendedseo', 'user_token=' . $this->session->data['user_token'], 'SSL'));
		}

		$data['heading_title'] = $this->language->get('heading_title');
		
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		
		$data['cancel'] = $this->url->link('extension/extension/extendedseo', 'user_token=' . $this->session->data['user_token'], 'SSL');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
  		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL'),
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => 'Auto Links',
			'href'      => $this->url->link('extension/extension/extendedseo', 'user_token=' . $this->session->data['user_token'], 'SSL'),       		
      		'separator' => ' :: '
   		);
		
   					
		$data['action'] = $this->url->link('extension/extension/extendedseo', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['extendedseo'] = array();
		
		if (isset($this->request->post['extendedseo'])) {
			$data['extendedseo'] = $this->request->post['extendedseo'];
		} else {
			$data['extendedseo'] = $this->config->get('extendedseo');
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

		$this->response->setOutput($this->load->view('extension/extension/extendedseo', $data));
		
	} 
	
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/extension/extendedseo')) {
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