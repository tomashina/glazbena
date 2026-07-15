<?php
class ControllerExtensionTmdEmailTemplate extends Controller {
	
	public function index() {
		$this->load->language('extension/tmdemailtemplate');

		$this->load->model('catalog/product');
		$this->load->model('extension/form');
		
		 if (isset($this->session->data['form_id'])) {
			$forms_id = $this->session->data['form_id'];
		} else {
			$forms_id = 0;
		} 
		
		
		
		$data['text_detail'] = $this->language->get('text_detail');
		$data['text_custdetail'] = $this->language->get('text_custdetail');
		$data['text_fields'] = $this->language->get('text_fields');
		$data['entry_fieldname'] = $this->language->get('entry_fieldname');
		$data['entry_fieldvalue'] = $this->language->get('entry_fieldvalue');
		
		
		if(isset($language_info['name'])){
			$lname = $language_info['name'];
		} else {
			$lname ='';
		}
		
		$tmdrecord_info = $this->model_extension_form->getTmdRecord($forms_id);
		
		$store_info = $this->model_extension_form->getStore($tmdrecord_info['store_id']);
		
		if(isset($store_info['name'])){
			$sname = $store_info['name'];
		} else {
			$sname ='Default';
		}

			$data['product_id']= $tmdrecord_info['product_id'];

			$product_info = $this->model_catalog_product->getProduct($data['product_id']);
			
			
		$data['product_name'] = $product_info['name'];
		
		$data['sname'] 		= $sname;
		$data['lname'] 		= $lname;
		$data['customer_name']= $tmdrecord_info['customer_name'];
		$data['title'] 		= $tmdrecord_info['title'];
		$data['ip'] 		= $tmdrecord_info['ip'];
		$data['user_agent'] = (substr($tmdrecord_info['user_agent'], 0, 45));
		$data['date_added'] = $tmdrecord_info['date_added'];
		
		
		$data['field_infos'] = array();
		$filter_data=array(
			'fs_id'=>$tmdrecord_info['fs_id']
		);
			
		$field_infos = $this->model_extension_form->getTmdFieldRecord($filter_data);
		
		$this->session->data['emailfiles']=array();
		foreach($field_infos as $fieldinfo){			
			if($fieldinfo['serialize']){
				$fieldinfos=unserialize($fieldinfo['value']);			
						
				$value='';
				foreach($fieldinfos as $field){
				
				
					$value .=$field.',';
				}
				$fieldinfo['value']=$value;
			}
		
				$this->load->model('tool/upload');
				$type = $this->model_extension_form->getfieldtype($fieldinfo['field_id']);
				
				if($type=='file') {
					$upload_info = $this->model_tool_upload->getUploadByCode($fieldinfo['value']);
					// Extra code 26-07-2018 //
					if(isset($upload_info['name']))
					{
					$fieldinfo['value']=$upload_info['name'];
					copy(DIR_UPLOAD.$upload_info['filename'], DIR_UPLOAD.$upload_info['name']);
					$this->session->data['emailfiles'][]=DIR_UPLOAD.$upload_info['name'];
					}
					// Extra code 26-07-2018 //
				}
			
			
			$data['field_infos'][] = array(				
				'label'     =>$fieldinfo['label'],
				'value'     =>$fieldinfo['value']				
			);
			
		}
		
		return $this->load->view('extension/tmdemailtemplate', $data);
        
        //$this->response->setOutput($this->load->view('extension/tmdemailtemplate', $data));
	}
	
}
