<?php
class ControllerExtensionBaseProductgroups extends Controller {
	public function index() {
		$token_key = isset($this->session->data['user_token']) ? 'user_token' : 'token';
		$token = isset($this->session->data[$token_key]) ? $this->session->data[$token_key] : '';

		$this->response->redirect($this->url->link('extension/basel/productgroups', $token_key . '=' . $token, true));
	}
}
