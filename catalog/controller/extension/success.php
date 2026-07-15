<?php
class ControllerExtensionSuccess extends Controller {
	public function index() {
		$this->load->language('extension/success');

		
		$this->load->model('extension/form');
		
		if (isset($this->request->get['form_id'])) {
			$forms_id = $this->request->get['form_id'];
		} else {
			$forms_id = 0;
		}
		
		$tmdsuccessform_info = $this->model_extension_form->getSuccesstext($forms_id);
		
		if(isset($tmdsuccessform_info['success_meta_title'])){
		$this->document->setTitle($tmdsuccessform_info['success_meta_title']);
		} else {
		$this->document->setTitle($this->language->get('heading_title'));
		}
		
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('extension/success')
		);
		
		if(isset($tmdsuccessform_info['success_title'])){
		$data['heading_title'] = $tmdsuccessform_info['success_title'];
		} else {
		$data['heading_title'] = $this->language->get('heading_title');
		}
		
		if(isset($tmdsuccessform_info['success_description'])){
		$data['text_message'] = html_entity_decode($tmdsuccessform_info['success_description']);
		} else {
		$data['text_message'] = $this->language->get('text_message');
		}
		
		$data['continue'] = $this->url->link('common/home');
		
		$data['button_continue'] = $this->language->get('button_continue');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
		
		$this->response->setOutput($this->load->view('extension/success', $data));
		
	}
}
