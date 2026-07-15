<?php
class ControllerExtensionRecord extends Controller {
 private $error = array();
		public function index() {
		$this->load->language('extension/record');
		
		$this->document->addScript('view/javascript/jquery/datetimepicker/moment.js');
		$this->document->addScript('view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
		$this->document->addStyle('view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
			
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/record');
		$this->load->model('extension/form');
				
		$this->getList();
	}
 
 
	public function getList() {
	 
	 	if (isset($this->request->get['filter_title'])) {
			$filter_title = $this->request->get['filter_title'];
		} else {
			$filter_title = '';
		}
	 
	 	if (isset($this->request->get['filter_name'])) {
			$filter_name = $this->request->get['filter_name'];
		} else {
			$filter_name = '';
		}
	 
	 	if (isset($this->request->get['filter_ip'])) {
			$filter_ip = $this->request->get['filter_ip'];
		} else {
			$filter_ip = '';
		}
	 
	  	if (isset($this->request->get['filter_date'])) {
			$filter_date = $this->request->get['filter_date'];
		} else {
			$filter_date = '';
		}
			 
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
		 	$sort = 'form_id';
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
	 
	 	if (isset($this->request->get['filter_title'])) {
			$url .= '&filter_title=' . $this->request->get['filter_title'];
		}
			
	 	if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . $this->request->get['filter_name'];
		}
	 
	 	if (isset($this->request->get['filter_ip'])) {
			$url .= '&filter_ip=' . $this->request->get['filter_ip'];
		}
	 
	 	if (isset($this->request->get['filter_date'])) {
			$url .= '&filter_date=' . $this->request->get['filter_date'];
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
			'href' => $this->url->link('extension/record', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);


		$data['records'] = array();

		$filter_data = array(
			'filter_title' 	=> $filter_title,
			'filter_name' 	=> $filter_name,
			'filter_ip'		=> $filter_ip,
			'filter_date'	=> $filter_date,
			'sort'  		=> $sort,
			'order'			=> $order,
			'start'			=> ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' 		=> $this->config->get('config_limit_admin')
		);
		
	
		$report_total = $this->model_extension_record->getTotalRecord($filter_data);
		$records = $this->model_extension_record->getFormRecords($filter_data);
		
			
	 	foreach($records as $result){
			
			if(!empty($result['product_id'])){
			$productname = $result['name'];
			} else {
			$productname = '';
			}
			
			
			if(!empty($result['customer_name'])){
			$customer_name = $result['customer_name'];
			} else {
			$customer_name = 'Guest';
			}
			
			$data['records'][]    = array(
				'fs_id'           =>$result['fs_id'],
				'form_id'         =>$result['form_id'],
				'title'           =>$result['title'],
				'customer_name'   =>$customer_name,				
				'productname'     =>$productname,
				'ip'              =>$result['ip'],
				'date_added'      =>date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'view'            => $this->url->link('extension/tmdformrecord', 'user_token=' . $this->session->data['user_token'] . '&fs_id=' . $result['fs_id'] . $url, true)
			);
		}
		
	 	
   		
		$data['heading_title']          = $this->language->get('heading_title');
		$data['text_list']           	= $this->language->get('text_list');
		$data['text_no_results'] 		= $this->language->get('text_no_results');
		$data['text_confirm']			= $this->language->get('text_confirm');
		$data['text_none'] 				= $this->language->get('text_none');
	 	$data['text_enable']            = $this->language->get('text_enable');
		$data['text_disable']           = $this->language->get('text_disable');
		$data['text_select']            = $this->language->get('text_select');
		$data['text_none']              = $this->language->get('text_none');
		$data['column_title']			= $this->language->get('column_title');
		$data['column_name']			= $this->language->get('column_name');
		$data['column_ip']		        = $this->language->get('column_ip');
		$data['column_date']			= $this->language->get('column_date');
		$data['column_action']			= $this->language->get('column_action');
		$data['entry_title']			= $this->language->get('entry_title');
		$data['entry_name']			    = $this->language->get('entry_name');
		$data['entry_ip']			    = $this->language->get('entry_ip');
		$data['entry_date']			    = $this->language->get('entry_date');
		$data['button_remove']          = $this->language->get('button_remove');
		$data['button_filter']          = $this->language->get('button_filter');
		$data['button_view']            = $this->language->get('button_view');
		$data['column_productname']     = $this->language->get('column_productname');
		$data['text_confirm']           = $this->language->get('text_confirm');
		$data['name']                   = $this->language->get('name');
		$data['user_token']                  = $this->session->data['user_token'];
		
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
	 
		$data['sort'] 			= $sort;
		$data['order'] 			= $order;
	 
		$data['sort_title']  	= $this->url->link('extension/record', 'user_token=' . $this->session->data['user_token'] . '&sort=title' . $url, true);
		$data['sort_name']  	= $this->url->link('extension/record', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url, true);
		$data['sort_ip']        = $this->url->link('extension/record', 'user_token=' . $this->session->data['user_token'] . '&sort=ip' . $url, true);
		$data['sort_date']  	= $this->url->link('extension/record', 'user_token=' . $this->session->data['user_token'] . '&sort=date' . $url, true);
		
	 	$url = '';
		
		if (isset($this->request->get['filter_title'])) {
			$url .= '&filter_title=' . $this->request->get['filter_title'];
		}
		
		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . $this->request->get['filter_name'];
		}
		
	 	if (isset($this->request->get['filter_ip'])) {
			$url .= '&filter_ip=' . $this->request->get['filter_ip'];
		}
	 	
	 	if (isset($this->request->get['filter_date'])) {
			$url .= '&filter_date=' . $this->request->get['filter_date'];
		}
	 		
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination 		= new Pagination();
		$pagination->total 	= $report_total;
		$pagination->page  	= $page;
		$pagination->limit 	= $this->config->get('config_limit_admin');
		$pagination->url   	= $this->url->link('extension/record', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
		$data['pagination'] = $pagination->render();


		$data['results'] = sprintf($this->language->get('text_pagination'), ($report_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($report_total - $this->config->get('config_limit_admin'))) ? $report_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $report_total, ceil($report_total / $this->config->get('config_limit_admin')));

		$data['filter_title']  = $filter_title;
		$data['filter_name']   = $filter_name;
		$data['filter_ip']     = $filter_ip;
		$data['filter_date']   = $filter_date;
		$data['sort']          = $sort;
		$data['order']         = $order;

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/record_list',$data));
		
	}
	
	public function autocomplete(){
		
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
		$this->load->model('extension/form');
			
		$filter_data = array(
		'sort'  => $sort,
		'order' => $order,
		//'filter_name' => $filter_name,
		'start' => ($page - 1) * $this->config->get('config_limit_admin'),
		'limit' => $this->config->get('config_limit_admin')
		);
		$results=$this->model_extension_form->getForms($filter_data);

		foreach ($results as $result) {

		$json[]     = array(
		'form_id'   => $result['form_id'],
		'title'     => strip_tags(html_entity_decode($result['title'], ENT_QUOTES, 'UTF-8'))
		);
		}
		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['title'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
  
			
}
?>
