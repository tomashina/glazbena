<?php

require_once (DIR_SYSTEM . "library/tmd/dompdf/src/Autoloader.php");
require_once (DIR_SYSTEM . "library/tmd/dompdf/lib/html5lib/Parser.php");
Dompdf\Autoloader::register();
use Dompdf\Dompdf;


class ControllerExtensionTmdformrecord extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/tmdformrecord');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/form');
		$this->load->model('extension/record');
		$this->load->model('localisation/language');
		$this->load->model('setting/store');

		$this->getForm();
	}

	public function getForm() {
		if(isset($this->session->data['token'])){
			$tokenchange = 'token=' . $this->session->data['token'];
		} else {
			$tokenchange =  'user_token=' . $this->session->data['user_token'];
		}

		if (isset($this->request->get['fs_id'])) {
			$fs_id = $this->request->get['fs_id'];
		} else {
			$fs_id = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_form'] = !isset($this->request->get['category_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_detail'] = $this->language->get('text_detail');
		$data['text_custdetail'] = $this->language->get('text_custdetail');
		$data['text_fields'] = $this->language->get('text_fields');
		
		$data['entry_fieldname'] = $this->language->get('entry_fieldname');
		$data['entry_fieldvalue'] = $this->language->get('entry_fieldvalue');

		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_pdf'] = $this->language->get('button_pdf');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $tokenchange, true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/tmdformrecord', $tokenchange . $url, true)
		);

		$data['pdfgenrate'] = $this->url->link('extension/tmdformrecord/Pdf', $tokenchange . '&fs_id=' . $this->request->get['fs_id'], true);

		$data['cancel'] = $this->url->link('extension/record', $tokenchange . $url, true);

		if(isset($this->request->get['fs_id'])){
			$tmdrecord_info = $this->model_extension_record->getTmdRecord($this->request->get['fs_id']);
		}

		if(isset($tmdrecord_info['language_id'])){
			$language_info = $this->model_localisation_language->getLanguage($tmdrecord_info['language_id']);
		}

		if(isset($language_info['name'])){
			$lname = $language_info['name'];
		} else {
			$lname ='';
		}

		if(isset($tmdrecord_info['store_id'])){
			$store_info = $this->model_setting_store->getStore($tmdrecord_info['store_id']);
		}

		if(isset($store_info['name'])){
			$sname = $store_info['name'];
		} else {
			$sname ='Default';
		}


		
		$data['sname'] 		= $sname;
		$data['lname'] 		= $lname;

		if(isset($tmdrecord_info['customer_name'])){
			$data['customer_name']= $tmdrecord_info['customer_name'];
		} else{
			$data['customer_name']='';
		}

		if(isset($tmdrecord_info['title'])){
			$data['title'] 		= $tmdrecord_info['title'];
		} else {
			$data['title']='';

		}
		if(isset($tmdrecord_info['ip'])){
			$data['ip'] 		= $tmdrecord_info['ip'];
		} else {
			$data['ip'] ='';
		}

		if(isset($tmdrecord_info['user_agent'])){
			$data['user_agent'] = (substr($tmdrecord_info['user_agent'], 0, 45));
		} else {
			$data['user_agent'] = '';
		}

		if(isset($tmdrecord_info['date_added'])){
			$data['date_added'] = $tmdrecord_info['date_added'];
		} else {
			$data['date_added'] ='';
		}
		
		$data['field_infos'] = array();
		$filter_data=array(
			'fs_id'=>$fs_id
		);
				
		$field_infos = $this->model_extension_record->getTmdFieldRecord($filter_data);
		
		foreach($field_infos as $fieldinfo){			
			if($fieldinfo['serialize']){
				$fieldinfos=unserialize($fieldinfo['value']);			
						
				$value='';
				foreach($fieldinfos as $field){
				
				
					$value .=$field.',';
				}
				$fieldinfo['value']=$value;
			}
			/* update code */
				$this->load->model('tool/upload');
				$type = $this->model_extension_form->getFieldType($fieldinfo['field_id']);
				
				if($type=='file') {
				 $upload_info = $this->model_tool_upload->getUploadByCode($fieldinfo['value']);
					$fieldinfo['value']='';
					if(isset($upload_info['name']))
					{
					$fieldinfo['value']='<a style="color:#1e91cf" href="'.$this->url->link('tool/upload/download', $tokenchange . '&code=' . $upload_info['code'], true).'">'.$upload_info['name'].'</a>'; 
					}
				}
			/* update code */
			
			$data['field_infos'][] = array(				
				'label'     =>$fieldinfo['label'],
				'value'     =>$fieldinfo['value']
				
			);
			
		}
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tmdformrecord_form', $data));
	}
	

	public function Pdf() {
		$this->load->language('extension/tmdformrecord');
		
		$this->load->model('extension/form');
		$this->load->model('extension/record');
		$this->load->model('localisation/language');
		$this->load->model('setting/store');


		if(isset($this->session->data['token'])){
			$tokenchange = 'token=' . $this->session->data['token'];
		} else {
			$tokenchange =  'user_token=' . $this->session->data['user_token'];
		}

		if (isset($this->request->get['fs_id'])) {
			$fs_id = $this->request->get['fs_id'];
		} else {
			$fs_id = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_form'] = !isset($this->request->get['category_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_detail'] = $this->language->get('text_detail');
		$data['text_custdetail'] = $this->language->get('text_custdetail');
		$data['text_fields'] = $this->language->get('text_fields');
		
		$data['entry_fieldname'] = $this->language->get('entry_fieldname');
		$data['entry_fieldvalue'] = $this->language->get('entry_fieldvalue');

		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $tokenchange, true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/tmdformrecord', $tokenchange . $url, true)
		);

		$data['cancel'] = $this->url->link('extension/record', $tokenchange . $url, true);

		if(isset($this->request->get['fs_id'])){
			$tmdrecord_info = $this->model_extension_record->getTmdRecord($this->request->get['fs_id']);
		}

		if(isset($tmdrecord_info['language_id'])){
			$language_info = $this->model_localisation_language->getLanguage($tmdrecord_info['language_id']);
		}

		if(isset($language_info['name'])){
			$lname = $language_info['name'];
		} else {
			$lname ='';
		}

		if(isset($tmdrecord_info['store_id'])){
			$store_info = $this->model_setting_store->getStore($tmdrecord_info['store_id']);
		}

		if(isset($store_info['name'])){
			$sname = $store_info['name'];
		} else {
			$sname ='Default';
		}
		
		$data['sname'] 		= $sname;
		$data['lname'] 		= $lname;

		if(isset($tmdrecord_info['customer_name'])){
			$data['customer_name']= $tmdrecord_info['customer_name'];
		} else{
			$data['customer_name']='';
		}

		if(isset($tmdrecord_info['title'])){
			$data['title'] 		= $tmdrecord_info['title'];
		} else {
			$data['title']='';

		}
		if(isset($tmdrecord_info['ip'])){
			$data['ip'] 		= $tmdrecord_info['ip'];
		} else {
			$data['ip'] ='';
		}

		if(isset($tmdrecord_info['user_agent'])){
			$data['user_agent'] = (substr($tmdrecord_info['user_agent'], 0, 45));
		} else {
			$data['user_agent'] = '';
		}

		if(isset($tmdrecord_info['date_added'])){
			$data['date_added'] = $tmdrecord_info['date_added'];
		} else {
			$data['date_added'] ='';
		}
		
		$data['field_infos'] = array();
		$filter_data=array(
			'fs_id'=>$fs_id
		);
				
		$field_infos = $this->model_extension_record->getTmdFieldRecord($filter_data);
		
		foreach($field_infos as $fieldinfo){			
			if($fieldinfo['serialize']){
				$fieldinfos=unserialize($fieldinfo['value']);			
						
				$value='';
				foreach($fieldinfos as $field){
				
				
					$value .=$field.',';
				}
				$fieldinfo['value']=$value;
			}
			/* update code */
				$this->load->model('tool/upload');
				$type = $this->model_extension_form->getFieldType($fieldinfo['field_id']);
				
				if($type=='file') {
				 $upload_info = $this->model_tool_upload->getUploadByCode($fieldinfo['value']);
					$fieldinfo['value']='';
					if(isset($upload_info['name']))
					{
					$fieldinfo['value']='<a style="color:#1e91cf" href="'.$this->url->link('tool/upload/download', $tokenchange . '&code=' . $upload_info['code'], true).'">'.$upload_info['name'].'</a>'; 
					}
				}
			/* update code */
			
			$data['field_infos'][] = array(				
				'label'     =>$fieldinfo['label'],
				'value'     =>$fieldinfo['value']
				
			);
			
		}

		$pdf  = $this->load->view('extension/tmdpdfrecord', $data);
		$data=html_entity_decode($pdf,ENT_QUOTES, 'UTF-8');
        $data=preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $data);

        $data=str_replace(HTTP_SERVER .'image/',DIR_IMAGE,$data);
        $data='<html><head>
        <style type="text/css">@page{margin:0px;size:A4;padding:10px;}
        *{
        padding: 0;
        margin: 0;}
        
        </style></head><body><div style="position: relative;width:100%;overflow: hidden;">'.$data.'</div></body></html>';
        
        $dompdf = new Dompdf();
        $dompdf->loadHtml($data);
        $dompdf->set_option('isHtml5ParserEnabled', true);
    
        $dompdf->setPaper('A4', 'landscape');

        $dompdf->render();

        $file_pdf         = DIR_CACHE.'/tmd.pdf';

        $file_pdf_name     = 'pdf'.$fs_id.'.pdf';

        $pdf_string     = $dompdf->output();
        

         file_put_contents($file_pdf, $pdf_string);

         $mask = '';
         header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . urlencode($mask ? $mask : basename($file_pdf)) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_pdf));
        readfile($file_pdf);
		
	}
	

}
