<?php
class ControllerExtensionPopupForm extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/popupform');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
		
	}
	
	public function popupformpage() {
		
		$this->load->language('extension/popupform');
		$this->load->model('extension/form');
		$this->load->model('tool/image');	
		$this->load->model('catalog/product');	
		
		
		if (isset($this->request->get['product_id'])) {
			$data['product_id'] = $this->request->get['product_id'];
		} else {
			$data['product_id'] = 0;
		}
		if (isset($this->request->get['form_id'])) {
			$data['form_id'] = $this->request->get['form_id'];
			$form_id = $this->request->get['form_id'];
		} else {
			$data['form_id'] = 0;
			$form_id = 0;
		}
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
	
		$data['heading_title'] 		= $this->language->get('heading_title');
		$data['text_yes'] 			= $this->language->get('text_yes');
		$data['text_no'] 			= $this->language->get('text_no');
		$data['text_select'] 		= $this->language->get('text_select');
		$data['text_none'] 			= $this->language->get('text_none');
		$data['text_loading'] 		= $this->language->get('text_loading');
		$data['text_option'] 		= $this->language->get('text_option');
		$data['button_upload']  = $this->language->get('button_upload');
		$data['text_entercode'] = $this->language->get('text_entercode');
	
		
		
		$this->load->model('localisation/country');

		$data['countries'] = $this->model_localisation_country->getCountries();
		
			
			
		$tmdforms_info = $this->model_extension_form->getForm($form_id);

		if($tmdforms_info['display_type']=='Only customer') { 
			if (!$this->customer->isLogged()) {
				$this->session->data['redirect'] = $this->url->link('extension/form', 'form_id='.$forms_id, true);
				$this->response->redirect($this->url->link('account/login', '', true));
			}
		}

		if($tmdforms_info['display_type']=='Only guest') {
			if ($this->customer->isLogged()) {
				//$this->response->redirect($this->url->link('common/home', '', true));
				$this->session->data['redirect'] = $this->url->link('extension/form', 'form_id='.$forms_id, true);
			}
		}
		
		$data['resetbutton'] = $tmdforms_info['resetbutton'];	
		
		$data['customcss'] = $tmdforms_info['custome_style'];	
		$data['form_id'] = $tmdforms_info['form_id'];
		$data['title'] 	 = $this->document->setTitle($tmdforms_info['title']);
		$data['formtitle'] = $tmdforms_info['title'];
		
		if(!empty($tmdforms_info['top_description'])){
		$data['top_description']    = html_entity_decode($tmdforms_info['top_description']);
		} else {
		$data['top_description']='';
		}
		if(!empty($tmdforms_info['bottom_description'])){
		$data['bottom_description']    = html_entity_decode($tmdforms_info['bottom_description']);
		} else {
		$data['bottom_description']='';	
		}
		
		
		
		if(!empty($tmdforms_info['resetbuttonname'])){
		$data['resetbutton_name'] = $tmdforms_info['resetbuttonname'];
		} else {
		$data['resetbutton_name'] =  $this->language->get('button_reset');	
		}
		
		if(!empty($tmdforms_info['button_name'])){
		$data['button_name'] = $tmdforms_info['button_name'];
		} else {
		$data['button_name'] =  $this->language->get('button_name');	
		}
		
		
		$data['form_fields'] = array();	
		
		foreach($this->model_extension_form->getFormFieldById($tmdforms_info['form_id']) as $option) {
			if($option['form_status'] == 1){	
				$form_field_option_data = array();

				foreach ($option['form_field_option'] as $option_value) {
					

						$form_field_option_data[] = array(
							'field_id' => $option_value['field_id'],
							'form_id'         => $option_value['form_id'],
							'name'                    => $option_value['name'],
							'image'                   => $this->model_tool_image->resize($option_value['image'], 50, 50),						
							'sort_order'                    => $option_value['sort_order']
						);
					}
				
			
				$data['form_fields'][] = array(
					'field_id'          => $option['field_id'],
					'form_id'           => $option['form_id'],
					'form_field_option' => $form_field_option_data,
					'field_name'        => $option['field_name'],				
					'type'              => $option['type'],
					'required'          => $option['required'],
					'form_status'       => $option['form_status'],
					'help_text'         => $option['help_text'],
					'placeholder'       => $option['placeholder'],
					'placeholder'       => $option['placeholder'],
					'error_message'     => $option['error_message'],
				);
			}
		}
		
		if (isset($this->error['form_fields'])) {
			 $data['error_message'] = $this->error['form_fields'];
		} else {
			$data['error_message'] ='';
		}
		
		
		$producturls = $this->url->link('extension/success','form_id=' . $tmdforms_info['form_id']);
		
		$data['producturl'] =str_replace('&amp;','&',$producturls);
		
		
		
		$product_info = $this->model_catalog_product->getProduct($this->request->get['product_id']);
		
		if ($product_info['image']) {
			/* update code */
			if(!empty($tmdforms_info['productwidth'])){
			$productwidth = $tmdforms_info['productwidth'];
			} else {
			$productwidth = 100;	
			}
			if(!empty($tmdforms_info['productheight'])){
			$productheight = $tmdforms_info['productheight'];
			} else {
			$productheight = 100;	
			}
			
			$data['thumb'] = $this->model_tool_image->resize($product_info['image'],$productwidth, $productheight);
			} else {
			$data['thumb'] = '';
			/* update code */	
		}
		$data['productname'] = $product_info['name'];

		$data['captchastatus'] = $tmdforms_info['captcha'];
		
		if ($tmdforms_info['captcha']==1) {
			// Captcha
			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('contact', (array)$this->config->get('config_captcha_page'))) {
				$data['captcha'] = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha'), $this->error);
			} else {
				$data['captcha'] = '';
			}
		}
		
		$this->response->setOutput($this->load->view('extension/popupform', $data));
		
	}
	
	public function addpoup() {
		
		$this->load->language('extension/form');

		$json = array();

		if (isset($this->request->post['form_id'])) {
			$form_id = (int)$this->request->post['form_id'];
		} else {
			$form_id = 0;
		}

		$this->load->model('extension/form');
		$tmdforms_info = $this->model_extension_form->getForm($this->request->post['form_id']);	
		
		$form_info = $this->model_extension_form->getFormFieldById($form_id);

		if ($form_info) {			
			
			$form_options = $this->model_extension_form->getFormFieldById($this->request->post['form_id']);
			
			foreach ($form_options as $form_option) {					
				// Email
			if($form_option['form_status'] == 1){
				if($form_option['type']=='email'){
					if (!preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $this->request->post['formfields'][$form_option['field_id']])) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_email');
					}
				}
				
				// Exist Email
				
				if($form_option['type']=='emaile_exists'){
					
					if ($this->model_tmdform_form->getEmailExist($this->request->post['formfields'][$form_option['field_id']],$form_option['field_id'])) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_exists');
					}
					if (!preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $this->request->post['formfields'][$form_option['field_id']])) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_email');
					}
					
				}
				
				// Password
				
				if($form_option['type']=='password'){
					if ((utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) < 4) || (utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) > 20)) {
						$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_password');
					}
				}
				// Confirm Password
				
				if($form_option['type']=='confirm'){
					if ((utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) < 4) || (utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) > 20)) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_confirm');
					} 
				}
				
				
				if ($form_option['required'] && empty($this->request->post['formfields'][$form_option['field_id']])) {
					$json['error']['formfields'][$form_option['field_id']] = sprintf($form_option['error_message'], $form_option['field_name']);
				}
			}
		}
			
			
        if ($tmdforms_info['captcha']==1) {
			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('contact', (array)$this->config->get('config_captcha_page'))) {
			$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

			if ($captcha) {
				$json['error']['g-recaptcha-response'] = $captcha;
			}
		    }
		}

				
			if ($json) {
				$json['warning'] = $this->language->get('text_warrning');
			}
		
			if (!$json) {
				$this->model_extension_form->addForm($this->request->post);
				
				
				$json['success'] = sprintf($this->language->get('text_success'));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	public function add() {
		
		$this->load->language('extension/form');

		$json = array();

		if (isset($this->request->post['form_id'])) {
			$form_id = (int)$this->request->post['form_id'];
		} else {
			$form_id = 0;
		}

		$this->load->model('extension/form');
		$tmdforms_info = $this->model_extension_form->getForm($this->request->post['form_id']);	
		
		$form_info = $this->model_extension_form->getFormFieldById($form_id);

		if ($form_info) {			
			
			$form_options = $this->model_extension_form->getFormFieldById($this->request->post['form_id']);
			
			foreach ($form_options as $form_option) {					
				// Email
			if($form_option['form_status'] == 1){
				if($form_option['type']=='email'){
					if (!preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $this->request->post['formfields'][$form_option['field_id']])) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_email');
					}
				}
				
				// Exist Email
				
				if($form_option['type']=='emaile_exists'){
					
					if ($this->model_extension_form->getEmailExist($this->request->post['formfields'][$form_option['field_id']],$form_option['field_id'])) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_exists');
					}
					if (!preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $this->request->post['formfields'][$form_option['field_id']])) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_email');
					}
					
				}
				
				// Password
				
				if($form_option['type']=='password'){
					if ((utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) < 4) || (utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) > 20)) {
						$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_password');
					}
				}
				// Confirm Password
				
				if($form_option['type']=='confirm'){
					if ((utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) < 4) || (utf8_strlen($this->request->post['formfields'][$form_option['field_id']]) > 20)) {
					$json['error']['formfields'][$form_option['field_id']] = $this->language->get('error_confirm');
					} 
				}
				
				
				if ($form_option['required'] && empty($this->request->post['formfields'][$form_option['field_id']])) {
					$json['error']['formfields'][$form_option['field_id']] = sprintf($form_option['error_message'], $form_option['field_name']);
				}
			}
			
			}
			
		
		if ($tmdforms_info['captcha']==1) {	
			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('contact', (array)$this->config->get('config_captcha_page'))) {
			$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

			if ($captcha) {
				$json['error']['g-recaptcha-response'] = $captcha;
			}
			}
		}
	


				
			if ($json) {
				$json['warning'] = $this->language->get('text_warrning');
			}
			
			if (!$json) {
				$this->model_extension_form->addForm($this->request->post);
				
				
				$json['success'] = sprintf($this->language->get('text_success'));
			
		
			}

		}		

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}	
}