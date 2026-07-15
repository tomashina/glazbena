<?php
set_time_limit(0);
ini_set('memory_limit','9999M');
error_reporting(-1);
require_once(DIR_SYSTEM.'/library/tmd/PHPExcel.php');

class ControllerExtensionForm extends Controller {
	private $error = array();
	public function index() {
		$this->load->language('extension/form');
		
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/form');
				
		$this->getList();
	}
 	public function add() {
		$this->load->language('extension/form');
		
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/form');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			
			$this->model_extension_form->addForm($this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');
			
			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}
			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}
			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}
			$this->response->redirect($this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}
		$this->getForm();
	}
 	public function edit(){
		$this->load->language('extension/form');
		
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/form');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			$this->model_extension_form->editForm($this->request->get['form_id'],$this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}
			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}
			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}
			if (isset($this->request->get['status'])) {
			$this->response->redirect($this->url->link('extension/form/edit', '&status=1&user_token=' . $this->session->data['user_token']. '&form_id=' . $this->request->get['form_id'] . $url, true));
			} else {
			$this->response->redirect($this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . $url, true));
			}
			
			
		}
		
		$this->getForm();
	}
	public function delete() {
		$this->load->language('extension/form');
		
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/form');
			//change delete//
		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $form_id){
				$this->model_extension_form->deleteForm($form_id);
			}
			
			$this->session->data['success'] = $this->language->get('text_success');
			
			$url = '';

			$this->response->redirect($this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}
		$this->getList();
	}
	
	public function getList() {
			
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
		 	$sort = 'title';
		}
		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}
		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}
	 
		$url = '';
			
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}
		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}
		
		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] 	= $this->url->link('extension/form/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/form/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['forms'] = array();
		$filter_data = array(
			'sort'  		=> $sort,
			'order' 		=> $order,
			'start' 		=> ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' 		=> $this->config->get('config_limit_admin')
		);
		
		$form_total = $this->model_extension_form->getTotalForm($filter_data);
		$forms = $this->model_extension_form->getForms($filter_data);
		
		
	 	foreach($forms as $form){
			
			$formurl = $this->model_extension_form->getForm($form['form_id']);
			/*$seostatus = $this->config->get('config_seo_url');
			 
			 if($seostatus==1  && !empty($formurl['keyword'] )){
				 $preview = HTTP_CATALOG.$formurl['keyword'];
				
			 } else {*/
				 $preview = HTTP_CATALOG.'index.php?route=extension/form'.'&form_id=' . $form['form_id']; 
			 
			
			 
			$data['forms'][] = array(
				'form_id' 	=> $form['form_id'],
				'title' 	=> $form['title'],
				'status'    => ($form['status'] ? $this->language->get('text_enable') : $this->language->get('text_disable')),
				'edit'		=> $this->url->link('extension/form/edit', 'user_token=' . $this->session->data['user_token'] .'&form_id=' . $form['form_id'] . $url, true),
				'view'		=> $this->url->link('extension/filedrecord', 'user_token=' . $this->session->data['user_token'] .'&form_id=' . $form['form_id'] .'&filter_title=' . $form['form_id'] . $url, true),
				'export'	=> $this->url->link('extension/form/export', 'user_token=' . $this->session->data['user_token'] .'&form_id=' . $form['form_id'] . $url, true),				
				'preview'	=>  $preview
			);
		}
   
		$data['heading_title']          = $this->language->get('heading_title');
		$data['text_list']           	= $this->language->get('text_list');
		$data['text_no_results'] 		= $this->language->get('text_no_results');
		$data['text_none'] 				= $this->language->get('text_none');
	 	$data['text_enable']            = $this->language->get('text_enable');
		$data['text_disable']           = $this->language->get('text_disable');
		$data['text_select']            = $this->language->get('text_select');
		$data['text_none']            	= $this->language->get('text_none');
		$data['column_status']			= $this->language->get('column_status');
		$data['column_title']			= $this->language->get('column_title');
		$data['column_preview']			= $this->language->get('column_preview');
		$data['column_action']			= $this->language->get('column_action');
		$data['button_edit']            = $this->language->get('button_edit');
		$data['button_add']             = $this->language->get('button_add');
		$data['button_view']            = $this->language->get('button_view');
		$data['button_filter']          = $this->language->get('button_filter');
		$data['button_delete']          = $this->language->get('button_delete');
		$data['text_confirm']           = $this->language->get('text_confirm');
		$data['name']                   = $this->language->get('name');
		$data['user_token']                  = $this->session->data['user_token'];
			
		$data['sort'] 	= $sort;
		$data['order'] 	= $order;
	  
		$data['sort_title']  		= $this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . '&sort=fd.title' . $url, true);
		$data['sort_status']  		= $this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . '&sort=f.status' . $url, true);
		$data['sort_preview']  		= $this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . '&sort=preview' . $url, true);
	
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
	 
	 	$url = ''; 
	 
	 	if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}
		
		
		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
	 	
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}
		 
		    
		$pagination 		= new Pagination();
		$pagination->total 	= $form_total;
		$pagination->page  	= $page;
		$pagination->limit 	= $this->config->get('config_limit_admin');
		$pagination->url   	= $this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($form_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($form_total - $this->config->get('config_limit_admin'))) ? $form_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $form_total, ceil($form_total / $this->config->get('config_limit_admin')));
		
	 	$data['sort']		=$sort;
		$data['order']		=$order;
	 
		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/form_list', $data));
	}
	 
 	protected function getForm() {
		$data['heading_title']          = $this->language->get('heading_title');
		$data['text_form']              = !isset($this->request->get['form_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_none']           	= $this->language->get('text_none');
		$data['text_default']           = $this->language->get('text_default');
		$data['text_enable']            = $this->language->get('text_enable');
		$data['text_disable']           = $this->language->get('text_disable');
		$data['text_select']            = $this->language->get('text_select');
		$data['text_all']            	= $this->language->get('text_all');
		$data['text_guest']            	= $this->language->get('text_guest');
		$data['text_customer']          = $this->language->get('text_customer');
		$data['text_noproduct']         = $this->language->get('text_noproduct');
		$data['text_allproduct']        = $this->language->get('text_allproduct');
		$data['text_selectproduct']     = $this->language->get('text_selectproduct');
		$data['text_yes']        		= $this->language->get('text_yes');
		$data['text_no']        		= $this->language->get('text_no');
		$data['text_choose']        	= $this->language->get('text_choose');
		$data['text_selects'] 			= $this->language->get('text_selects');
		$data['text_radio'] 			= $this->language->get('text_radio');
		$data['text_checkbox'] 			= $this->language->get('text_checkbox');
		$data['text_input'] 			= $this->language->get('text_input');
		$data['text_text'] 				= $this->language->get('text_text');
		$data['text_textarea'] 			= $this->language->get('text_textarea');
		$data['text_number'] 			= $this->language->get('text_number');
		$data['text_telephone'] 		= $this->language->get('text_telephone');
		$data['text_email'] 			= $this->language->get('text_email');
		$data['text_emailexists'] 		= $this->language->get('text_emailexists');
		$data['text_password'] 			= $this->language->get('text_password');
		$data['text_cpassword'] 		= $this->language->get('text_cpassword');
		$data['text_file'] 				= $this->language->get('text_file');
		$data['text_date'] 				= $this->language->get('text_date');
		$data['text_datetime'] 			= $this->language->get('text_datetime');
		$data['text_time']	 			= $this->language->get('text_time');
		$data['text_localisation']	 	= $this->language->get('text_localisation');
		$data['text_country']	 		= $this->language->get('text_country');
		$data['text_zone']	 			= $this->language->get('text_zone');
		$data['text_postcode']	 		= $this->language->get('text_postcode');
		$data['text_address']	 		= $this->language->get('text_address');
		$data['text_custemail']         = $this->language->get('text_custemail');
		$data['text_adminemail']        = $this->language->get('text_adminemail');
		$data['text_uniqueword']        = $this->language->get('text_uniqueword');
		$data['tab_seo']                = $this->language->get('tab_seo');
		
		$data['entry_shortcut']        	= $this->language->get('entry_shortcut');
		$data['text_shortcut']        	= $this->language->get('text_shortcut');
        $data['entry_title']        	= $this->language->get('entry_title');
		$data['entry_metatitle']      	= $this->language->get('entry_metatitle');
		$data['entry_metakeyword']      = $this->language->get('entry_metakeyword');
		$data['entry_metadesc']      	= $this->language->get('entry_metadesc');
		$data['entry_description']      = $this->language->get('entry_description');
		$data['entry_topdesc']      	= $this->language->get('entry_topdesc');
		$data['entry_buttonname']      	= $this->language->get('entry_buttonname');
		$data['entry_resetbuttonname']  = $this->language->get('entry_resetbuttonname');
		/* update code */
		$data['entry_popuplinkname']  = $this->language->get('entry_popuplinkname');
		/* update code */
		$data['entry_header']      		= $this->language->get('entry_header');
		$data['entry_productsize']      = $this->language->get('entry_productsize');
		$data['entry_footer']      		= $this->language->get('entry_footer');
		$data['entry_captcha']      	= $this->language->get('entry_captcha');
		$data['entry_resetbutton']      = $this->language->get('entry_resetbutton');
		$data['entry_status']        	= $this->language->get('entry_status');
		$data['entry_display']        	= $this->language->get('entry_display');
		$data['entry_custgroup']        = $this->language->get('entry_custgroup');
		$data['entry_store']        	= $this->language->get('entry_store');
		$data['entry_assignproduct']    = $this->language->get('entry_assignproduct');
		$data['entry_subject']    		= $this->language->get('entry_subject');
		$data['entry_message']    		= $this->language->get('entry_message');
		$data['entry_notification']    	= $this->language->get('entry_notification');
		$data['entry_descriptionss']   	= $this->language->get('entry_descriptionss');
		$data['entry_customestyle']   	= $this->language->get('entry_customestyle');
		$data['entry_product']   		= $this->language->get('entry_product');
		$data['entry_fieldname']   		= $this->language->get('entry_fieldname');
		$data['entry_helptext']   		= $this->language->get('entry_helptext');
		$data['entry_placeholder']   	= $this->language->get('entry_placeholder');
		$data['entry_error']   			= $this->language->get('entry_error');
		$data['entry_sortorder']   		= $this->language->get('entry_sortorder');
		$data['entry_required']   		= $this->language->get('entry_required');
		$data['entry_type']   			= $this->language->get('entry_type');
		$data['entry_option_value']   	= $this->language->get('entry_option_value');
		$data['entry_sort_order']   	= $this->language->get('entry_sort_order');
		$data['entry_image']   			= $this->language->get('entry_image');
		$data['entry_seourl']   		= $this->language->get('entry_seourl');
		$data['entry_category']         = $this->language->get('entry_category');
		$data['text_keyword']         = $this->language->get('text_keyword');
		$data['entry_manufacturer']     = $this->language->get('entry_manufacturer');
		
		$data['help_fieldname']   		= $this->language->get('help_fieldname');
		$data['help_helptext']   		= $this->language->get('help_helptext');
		$data['help_placeholder']   	= $this->language->get('help_placeholder');
		$data['help_error']   			= $this->language->get('help_error');
		$data['help_required']   		= $this->language->get('help_required');
		$data['help_type']   			= $this->language->get('help_type');
		$data['help_header']   			= $this->language->get('help_header');
		$data['help_footer']   			= $this->language->get('help_footer');
		$data['help_captcha']   		= $this->language->get('help_captcha');
		$data['help_resetbutton']   	= $this->language->get('help_resetbutton');
		/* update code */
		$data['help_popuplinkname']   = $this->language->get('help_popuplinkname');
		/* update code */
		$data['help_topdesc']   		= $this->language->get('help_topdesc');
		$data['help_botomdesc']   		= $this->language->get('help_botomdesc');
		$data['help_button']   		    = $this->language->get('help_button');
		$data['help_productsize']   	= $this->language->get('help_productsize');
		$data['help_resetbuttonname']   = $this->language->get('help_resetbuttonname');
		$data['help_category'] 			= $this->language->get('help_category');
		$data['help_manufacturer']		= $this->language->get('help_manufacturer');
		$data['tab_language']        	= $this->language->get('tab_language');
		$data['tab_setting']        	= $this->language->get('tab_setting');
		$data['tab_link']        	 	= $this->language->get('tab_link');
		$data['tab_notify']        	 	= $this->language->get('tab_notify');
		$data['tab_success']        	= $this->language->get('tab_success');
		$data['tab_custome']        	= $this->language->get('tab_custome');
		$data['tab_formfield']        	= $this->language->get('tab_formfield');
		$data['tab_general']        	= $this->language->get('tab_general');
		$data['tab_option']        	    = $this->language->get('tab_option');
		
		$data['button_address_add']     = $this->language->get('button_address_add');
		$data['button_option_add']      = $this->language->get('button_option_add');
		$data['button_save']            = $this->language->get('button_save');
		$data['button_add']             = $this->language->get('button_add');
		$data['button_remove']          = $this->language->get('button_remove');
		$data['button_cancel']          = $this->language->get('button_cancel');
		$data['text_none'] 				= $this->language->get('text_none');
		$data['button_stay'] 			= $this->language->get('button_stay');
		$data['entry_width'] 			= $this->language->get('entry_width');
		$data['entry_height'] 			= $this->language->get('entry_height');
		//09 03 2019 //
		$data['entry_information'] 		= $this->language->get('entry_information');
	//09 03 2019 //
		
	 	$url = '';
	 
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
	 
	 	if (isset($this->error['title'])) {
			$data['error_title'] = $this->error['title'];
		} else {
			$data['error_title'] = '';
		}
	 
	 	if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}
		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
			///  language //////
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		
	 	$this->load->model('customer/customer_group');
		$data['customergroups'] = $this->model_customer_customer_group->getCustomerGroups();

		$this->load->model('catalog/information');
		$data['informations'] = $this->model_catalog_information->getInformations();
		
	 	$url = '';
     
		$data['breadcrumbs'] = array();
	 
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
	 
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);
/// Display Type		
		$data['displaytypes'] = array();
			
		$data['displaytypes'][] = array(
			'display_type'  => $this->language->get('text_all'),
			'value' 		=> 'all'
		);
	 	$data['displaytypes'][] = array(
			'display_type'  => $this->language->get('text_guest'),
			'value' 		=> 'only guest'
		);
		$data['displaytypes'][] = array(
			'display_type'  => $this->language->get('text_customer'),
			'value' 		=> 'only customer'
		);
/// Display Type
		
/// Product		
		$data['assigns'] = array();
		
		$data['assigns'][] = array(
			'assign_product'  => $this->language->get('text_noproduct'),
			'value' 		=> '1'
		);
	 	$data['assigns'][] = array(
			'assign_product'  => $this->language->get('text_allproduct'),
			'value' 		=> '2'
		);
		$data['assigns'][] = array(
			'assign_product'  => $this->language->get('text_selectproduct'),
			'value' 		=> '3'
		);
/// Product		
	 	if (isset($this->request->post['selected'])) {
			$data['selected'] = (array) $this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}
	 
		if (!isset($this->request->get['form_id'])) {
			$data['action'] = $this->url->link('extension/form/add', 'user_token=' . $this->session->data['user_token'] . $url, true);			
    	} else {
			$data['action'] = $this->url->link('extension/form/edit', 'user_token=' . $this->session->data['user_token'] . '&form_id=' . $this->request->get['form_id'] . $url, true);
			$data['staysave'] = $this->url->link('extension/form/edit', '&status=1&user_token=' . $this->session->data['user_token']. '&form_id=' . $this->request->get['form_id'] . $url, true);
    	}
			
		$data['cancel'] = $this->url->link('extension/form', 'user_token=' . $this->session->data['user_token'] . $url, true);
			
		if (isset($this->request->get['form_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$form_info = $this->model_extension_form->getForm($this->request->get['form_id']);
	
		}
		$data['user_token'] = $this->session->data['user_token'];
		
		//////// editform /////////
		
		$this->load->model('setting/store');

		$data['stores'] = array();
		
		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);
		
		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}
		
		if (isset($this->request->post['form_seo_url'])) {
			$data['form_seo_url'] = $this->request->post['form_seo_url'];
		} elseif (isset($this->request->get['form_id'])) {
			$data['form_seo_url'] = $this->model_extension_form->getFormSeoUrls($this->request->get['form_id']);
		} else {
			$data['form_seo_url'] = array(0);
		}
		
		
		
		if(isset($this->request->post['form_id'])){
			$data['form_id']=$this->request->post['form_id'];
		} else if(!empty($form_info)){
			$data['form_id']=$form_info['form_id'];
		} else {
			$data['form_id']='';
		}
		
		if(isset($this->request->post['status'])){
			$data['status']=$this->request->post['status'];
		} else if(!empty($form_info)){
			$data['status']=$form_info['status'];
		} else {
			$data['status']='';
		}
		
		if(isset($this->request->post['resetbutton'])){
			$data['resetbutton']=$this->request->post['resetbutton'];
		} else if(!empty($form_info)){
			$data['resetbutton']=$form_info['resetbutton'];
		} else {
			$data['resetbutton']='';
		}
		if(isset($this->request->post['captcha'])){
			$data['captcha']=$this->request->post['captcha'];
		} else if(!empty($form_info)){
			$data['captcha']=$form_info['captcha'];
		} else {
			$data['captcha']='';
		}
		
		if(isset($this->request->post['headerlink'])){
			$data['headerlink']=$this->request->post['headerlink'];
		} else if(!empty($form_info['headerlink'])){
			$data['headerlink']=$form_info['headerlink'];
		} else {
			$data['headerlink']='';
		}
		
		if(isset($this->request->post['footerlink'])){
			$data['footerlink']=$this->request->post['footerlink'];
		} else if(!empty($form_info['footerlink'])){
			$data['footerlink']=$form_info['footerlink'];
		} else {
			$data['footerlink']='';
		}
		
		if(isset($this->request->post['productwidth'])){
			$data['productwidth']=$this->request->post['productwidth'];
		} else if(!empty($form_info['productwidth'])){
			$data['productwidth']=$form_info['productwidth'];
		} else {
			$data['productwidth']='';
		}
		
		if(isset($this->request->post['productheight'])){
			$data['productheight']=$this->request->post['productheight'];
		} else if(!empty($form_info['productheight'])){
			$data['productheight']=$form_info['productheight'];
		} else {
			$data['productheight']='';
		}
		
				
		if(isset($this->request->post['display_type'])){
			$data['display_type']=$this->request->post['display_type'];
		} else if(!empty($form_info)){
			$data['display_type']=$form_info['display_type'];
		} else {
			$data['display_type']='';
		}
		
		if(isset($this->request->post['assign_product'])){
			$data['assign_product']=$this->request->post['assign_product'];
		} else if(!empty($form_info)){
			$data['assign_product']=$form_info['assign_product'];
		} else {
			$data['assign_product']='';
		}
		
		if(isset($this->request->post['customer_notification'])){
			$data['customer_notification']=$this->request->post['customer_notification'];
		} else if(!empty($form_info)){
			$data['customer_notification']=$form_info['customer_notification'];
		} else {
			$data['customer_notification']='';
		}
		
		if(isset($this->request->post['admin_notification'])){
			$data['admin_notification']=$this->request->post['admin_notification'];
		} else if(!empty($form_info)){
			$data['admin_notification']=$form_info['admin_notification'];
		} else {
			$data['admin_notification']='';
		}
		
		if(isset($this->request->post['custome_style'])){
			$data['custome_style']=$this->request->post['custome_style'];
		} else if(isset($form_info['custome_style'])){
			$data['custome_style']=$form_info['custome_style'];
		} else {
			$data['custome_style']='';
		}
				
		if (isset($this->request->post['form_description'])) {
			$data['form_description'] = $this->request->post['form_description'];
		}elseif (isset($form_info)) {
			$data['form_description'] = $this->model_extension_form->getFormDescriptions($this->request->get['form_id']);
		}else {
			$data['form_description'] = '';
		}
		
		if (isset($this->request->post['succes_form'])) {
			$data['succes_form'] = $this->request->post['succes_form'];
		}elseif (isset($form_info)) {
			$data['succes_form'] = $this->model_extension_form->getFormSuccess($this->request->get['form_id']);
		}else {
			$data['succes_form'] = '';
		}
		
		if (isset($this->request->post['form_notification'])) {
			$data['form_notification'] = $this->request->post['form_notification'];
		}elseif (isset($form_info)) {
			$data['form_notification'] = $this->model_extension_form->getFormNotification($this->request->get['form_id']);
		}else {
			$data['form_notification'] = '';
		}
		
		if (isset($this->request->post['form_customer'])) {
			$data['form_customer'] = $this->request->post['form_customer'];
		} elseif (isset($this->request->get['form_id'])) {
			$data['form_customer'] = $this->model_extension_form->getCustomerCheckbox($this->request->get['form_id']);
			//print_r($data['form_customer']);die();
		} else {
			$data['form_customer'] = array();
		}
		
		if (isset($this->request->post['form_store'])) {
			$data['form_store'] = $this->request->post['form_store'];
		} elseif (isset($this->request->get['form_id'])) {
			$data['form_store'] = $this->model_extension_form->getFormByStoreId($this->request->get['form_id']);
		} else {
			$data['form_store'] = array(0);
		}
		
		if (isset($this->request->post['product_id'])){
			$product_ids = $this->request->post['product_id'];
		}elseif (isset($form_info)){
			$product_ids = $this->model_extension_form->getFormProductbyid($this->request->get['form_id']);
		} else{
			$product_ids = '';
		}
		
		if (isset($this->request->post['category_id'])){
			$category_ids = $this->request->post['category_id'];
		}elseif (isset($form_info)){
			$category_ids = $this->model_extension_form->getFormCategorybyid($this->request->get['form_id']);
		} else{
			$category_ids = '';
		}
		
		if (isset($this->request->post['manufacturer_id'])){
			$manufacturer_ids = $this->request->post['manufacturer_id'];
		}elseif (isset($form_info)){
			$manufacturer_ids = $this->model_extension_form->getFormManufacturerbyid($this->request->get['form_id']);
		} else{
			$manufacturer_ids = '';
		}
		
		
		
		/// name this is input 
		$data['products']=array();
		$this->load->model('catalog/product');
		if(!empty($product_ids)){
			foreach($product_ids as $product_id){
				$product_info=$this->model_catalog_product->getProduct($product_id);
				if(!empty($product_info['name'])){
					$product=$product_info['name'];
					$data['products'][]=array(
						'product_id'=>$product_id,
						'name'=>$product
					);
				}
			}
		}
		
		
		// Category
		$this->load->model('catalog/category');
		
		$data['categories']=array();		
		if(!empty($category_ids)){
			foreach($category_ids as $category_id){
				$category_info=$this->model_catalog_category->getCategory($category_id);
				if(!empty($category_info['name'])){
					$data['categories'][]=array(
						'category_id'=>$category_id,
						'name'        => ($category_info['path']) ? $category_info['path'] . ' &gt; ' . $category_info['name'] : $category_info['name']
					);
				}
			}
		}		
		
		// Manufacturer
		$this->load->model('catalog/manufacturer');
		
		$data['manufacturers']=array();		
		if(!empty($manufacturer_ids)){
			foreach($manufacturer_ids as $manufacturer_id){
				$manufacturer_info=$this->model_catalog_manufacturer->getManufacturer($manufacturer_id);
				if(!empty($manufacturer_info['name'])){
					$data['manufacturers'][]=array(
						'manufacturer_id'=>$manufacturer_id,
						'name'        => $manufacturer_info['name']
					);
				}
			}
		}		
		
		
		if (isset($this->request->post['type'])) {
			$data['type'] = $this->request->post['type'];
		} else {
			$data['type'] = '';
		}
		
		if (isset($this->request->post['form_status'])) {
			$data['form_status'] = $this->request->post['form_status'];
		} else {
			$data['form_status'] = '';
		}
		
		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		}  else {
			$data['sort_order'] = '';
		}
		
		if (isset($this->request->post['required'])) {
			$data['required'] = $this->request->post['required'];
		} else {
			$data['required'] = '';
		}
		
		if (isset($this->request->post['option_fields'])) {
			$optionfieldss = $this->request->post['option_fields'];
		} elseif (isset($this->request->get['form_id'])) {
			$optionfieldss = $this->model_extension_form->getFormFieldById($this->request->get['form_id']);
		} else {
			$optionfieldss = array();
		}
		//print_r($optionfieldss);die();
		$data['optionfieldss'] = $optionfieldss;
		/*
		foreach ($optionfieldss as $optionfield) {
			
			$form_field_description_data = array();
			if (isset($optionfield['form_field_description'])) {
				foreach ($optionfield['form_field_description'] as $form) {
					$optionfield_data[] = array(
						'form_id' 		=> $form['form_id'],
						'field_id' 		=> $form['field_id'],
						'field_name' 	=> $form['field_name'],
						'help_text' 	=> $form['help_text'],
						'placeholder' 	=> $form['placeholder'],
						'error_message' => $form['error_message']
					);
				}
			}
			
			$data['optionfieldss'][] = array(
				'form_status' 	=> $optionfield['form_status'],
				'type' 			=> $optionfield['type'],
				'required' 		=> $optionfield['required'],
				'form' 			=> $optionfield_data
			);
		}
		*/

		// 09 03 2019 //

		if (isset($this->request->post['information'])){
			$data['information'] = $this->request->post['information'];
		}elseif (isset($form_info['form_id'])){
			$data['information'] = $this->model_extension_form->getFormInformation($this->request->get['form_id']);
		} else{
			$data['information'] = array(0);
		}

		// 09 03 2019 //

		$this->load->model('tool/image');
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		
		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/form_form', $data));
	}
	protected function validate() {
		if (!$this->user->hasPermission('modify','extension/form')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		foreach ($this->request->post['form_description'] as $language_id => $value) {
			if ((utf8_strlen($value['title']) < 3) || (utf8_strlen($value['title']) > 255)) {
				$this->error['title'][$language_id] = $this->language->get('error_title');
			}
		}
		
		if ($this->request->post['form_seo_url']) {
			$this->load->model('design/seo_url');
			
			foreach ($this->request->post['form_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						if (count(array_keys($language, $keyword)) > 1) {
							$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_unique');
						}						
						
						$seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword);
						
						foreach ($seo_urls as $seo_url) {
							if (($seo_url['store_id'] == $store_id) && (!isset($this->request->get['form_id']) || ($seo_url['query'] != 'form_id=' . $this->request->get['form_id']))) {
								$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');
							}
						}
					}
				}
			}
		}
		
		
		return !$this->error;
	}
     
 	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/form')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}

	public function export(){
		if (isset($this->request->get['form_id'])) {
			$form_id = $this->request->get['form_id'];
		} else {
		 	$form_id = '';
		}
		$this->load->language('extension/form');
		$this->load->model('extension/form');
		
		$data=array();
				
		$objPHPExcel = new PHPExcel();

		
		$objPHPExcel->getProperties()->setCreator("TMD Export");
		$objPHPExcel->getProperties()->setLastModifiedBy("TMD Export");
		$objPHPExcel->getProperties()->setTitle("Office Excel");
		$objPHPExcel->getProperties()->setSubject("Office Excel");
		$objPHPExcel->getProperties()->setDescription("Office Excel");
		$objPHPExcel->setActiveSheetIndex(0);
		
		
		$form_infos=$this->model_extension_form->getexportheading($this->request->get['form_id']);
		
		$i=1;
		$cel="A";
		if(isset($form_infos))
		{
		 foreach($form_infos as $form_info) {
						
		  $objPHPExcel->getActiveSheet()->SetCellValue($cel.$i, $form_info['label']);
			$cel++;
			
			}	
		}
		 
		 $objPHPExcel->getActiveSheet()->SetCellValue($cel.$i, 'Customer Id');
		 $cel++;
		 $objPHPExcel->getActiveSheet()->SetCellValue($cel.$i, 'IP');
		 $cel++;
		 $objPHPExcel->getActiveSheet()->SetCellValue($cel.$i, 'Date Add');
		  $cel++;
		 
		$submitdatas=$this->model_extension_form->getexportsubmit($this->request->get['form_id']);
		
		$cel="A";
		if(isset($submitdatas)) {			
			foreach($submitdatas as $submitdata) {					
				$cel="A";
				$i++;
				if(isset($form_infos)){

				 foreach($form_infos as $form_info) {
					
				$value=$this->model_extension_form->getFieldExport($submitdata['fs_id'],$this->request->get['form_id'],$form_info['field_id']);
					
				  $objPHPExcel->getActiveSheet()->SetCellValue($cel.$i,$value);
					$cel++;					
					}	
				}
				
				$objPHPExcel->getActiveSheet()->SetCellValue($cel.$i, $submitdata['customer_id']);
				$cel++;
				$objPHPExcel->getActiveSheet()->SetCellValue($cel.$i, $submitdata['ip']);
				$cel++;
				$objPHPExcel->getActiveSheet()->SetCellValue($cel.$i, $submitdata['date_added']);
				$cel++;
					
			}
		}
		
		
			/* color setup */
				$al='BC';
				for($col = 'A'; $col != $al; $col++) {
			   $objPHPExcel->getActiveSheet()->getColumnDimension($col)->setWidth(20);
			 	}
				
				$objPHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(30);
				
				$objPHPExcel->getActiveSheet()
				->getStyle('A1:'.$al.'1')
				->getFill()
				->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('02057D');
				
				$styleArray = array(
					'font'  => array(
					'bold'  => true,
					'color' => array('rgb' => 'FFFFFF'),
					'size'  => 9,
					'name'  => 'Verdana'
				));
				
				$objPHPExcel->getActiveSheet()->getStyle('A1:'.$al.'1')->applyFromArray($styleArray);

				/* color setup */  
				
				
		$filename = 'FormBuilder.xls';
		$objPHPExcel->getActiveSheet()->setTitle('All Form Builder');
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save($filename );
		header('Content-type: application/vnd.ms-excel');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		$objWriter->save('php://output');
		unlink($filename);
	}
	
	public function fielddelete(){
		$json = array();
		$this->load->model('extension/form');
		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
			
			$this->model_extension_form->deleteAllFieldById($this->request->get['field_id']);
			
			$json['success'] = $this->language->get('text_success');
		}					
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
}
?>
