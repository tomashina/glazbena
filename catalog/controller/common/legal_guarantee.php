<?php
class ControllerCommonLegalGuarantee extends Controller {
	public function index() {
		$data = $this->load->language('common/legal_guarantee');
		$data['legal_guarantee_url'] = 'https://europa.eu/youreurope/jamstva_hr';
		$this->document->addStyle('catalog/view/theme/basel/stylesheet/legal-guarantee.css?v=1.0.3', 'stylesheet', 'screen', 'footer');

		return $this->load->view('common/legal_guarantee', $data);
	}
}
