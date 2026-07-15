<?php
class ControllerExtensionModuleBaselLayers extends Controller {
	public function index() {
		$params = 'user_token=' . $this->session->data['user_token'];

		if (isset($this->request->get['module_id'])) {
			$module_id = (int)$this->request->get['module_id'];
		} else {
			$query = $this->db->query("SELECT module_id FROM `" . DB_PREFIX . "module` WHERE code = 'basel_layerslider' ORDER BY (name LIKE '%Home%') DESC, module_id ASC LIMIT 1");
			$module_id = $query->num_rows ? (int)$query->row['module_id'] : 0;
		}

		if ($module_id) {
			$params .= '&module_id=' . $module_id;
		}

		$this->response->redirect($this->url->link('extension/module/basel_layerslider', $params, true));
	}
}
