<?php

/*******************************************************************************
*                                   Opencart Cache                             *
*                             Copyright : Ovidiu Fechete                       *
*                              email: ovife21@gmail.com                        *
*                Below source-code or any part of the source-code              *
*                          cannot be resold or distributed.                    *
*******************************************************************************/

class ControllerExtensionExtensionBotReport extends Controller {
	private $error = array(); 
	
	public function index() {   
		$this->load->language('extension/extension/bot_report');

		$this->document->setTitle($this->language->get('heading_title'));
		
		
		$this->load->model('setting/setting');
				
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('bot_report', $this->request->post);					
					
			$this->session->data['success'] = $this->language->get('text_success');
						
			$this->bot_report($this->url->link('extension/extension/bot_report', 'user_token=' . $this->session->data['user_token'], 'SSL'));
		}
				
		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
				
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['redirect'] = $this->url->link('extension/extension/redirect', 'user_token=' . $this->session->data['user_token'], 'SSL');
		

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

  		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL'),
      		'separator' => false
   		);

   			
   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/extension/bot_report', 'user_token=' . $this->session->data['user_token'], 'SSL'),
      		'separator' => ' :: '
   		);
		
		$data['action'] = $this->url->link('extension/extension/bot_report', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL');		
				
			
		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}
		
		$url = '';
		
		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
		
		$this->load->model('extension/extension/bot_report');
		
		$fdata = array(			
			'start'           => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'           => $this->config->get('config_limit_admin')
		);
		
		$page_total = $this->model_extension_extension_bot_report->getTotalPages();
			
		$results = $this->model_extension_extension_bot_report->getPages($fdata);
						    	
		foreach ($results as $result) {
			
      		$data['links'][] = array(
				'link' 				=> $result['link'],							
				'cnt' 				=> $result['cnt'],							
				'lastdate' 			=> $result['lastdate']				
			);
    	}		
		
		$pagination = new Pagination();
		$pagination->total = $page_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link('extension/extension/bot_report', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', 'SSL');
			
		$data['pagination'] = $pagination->render();
		$data['clearlog'] = $this->url->link('extension/extension/bot_report/clearlog', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$this->load->model('design/layout');
				
		$data['layouts'] = $this->model_design_layout->getLayouts();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/extension/bot_report', $data));
	}
	
	public function clearlog() {
	
		$query = $this->db->query("delete from `" . DB_PREFIX . "bots_report`;");
		
		$this->session->data['success'] = " Success: You have successfully cleared your Bots Report! ";
		
		$this->response->redirect($this->url->link('extension/extension/bot_report', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
			}
	
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/extension/bot_report')) {
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