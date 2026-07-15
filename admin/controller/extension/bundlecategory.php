<?php
class ControllerExtensionBundlecategory extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/bundlecategory');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/bundlecategory');

		$this->getList();
	}

	public function add() {
		$this->load->language('extension/bundlecategory');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/bundlecategory');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_bundlecategory->addCategory($this->request->post);

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

			$this->response->redirect($this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/bundlecategory');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/bundlecategory');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_bundlecategory->editCategory($this->request->get['bundle_category_id'], $this->request->post);

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

			$this->response->redirect($this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/bundlecategory');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/bundlecategory');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $bundle_category_id) {
				$this->model_extension_bundlecategory->deleteCategory($bundle_category_id);
			}

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

			$this->response->redirect($this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}
	
	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'bundle_category_id';
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('extension/bundlecategory/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/bundlecategory/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['categories'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$category_total = $this->model_extension_bundlecategory->getTotalCategories();

		$results = $this->model_extension_bundlecategory->getCategories($filter_data);

		foreach ($results as $result) {
			$data['categories'][] = array(
				'bundle_category_id' => $result['bundle_category_id'],
				'name'        => $result['name'],
				'sort_order'  => $result['sort_order'],
				'edit'        => $this->url->link('extension/bundlecategory/edit', 'user_token=' . $this->session->data['user_token'] . '&bundle_category_id=' . $result['bundle_category_id'] . $url, true),
				'delete'      => $this->url->link('extension/bundlecategory/delete', 'user_token=' . $this->session->data['user_token'] . '&bundle_category_id=' . $result['bundle_category_id'] . $url, true)
			);
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_list'] = $this->language->get('text_list');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');

		$data['column_name'] = $this->language->get('column_name');
		$data['column_sort_order'] = $this->language->get('column_sort_order');
		$data['column_action'] = $this->language->get('column_action');

		$data['button_add'] = $this->language->get('button_add');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['button_rebuild'] = $this->language->get('button_rebuild');

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

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
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

		$data['sort_name'] = $this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url, true);
		$data['sort_sort_order'] = $this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . '&sort=sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $category_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($category_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($category_total - $this->config->get('config_limit_admin'))) ? $category_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $category_total, ceil($category_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/bundlecategory_list', $data));
	}

	protected function getForm() {
		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_form'] = !isset($this->request->get['bundle_category_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_default'] = $this->language->get('text_default');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_description'] = $this->language->get('entry_description');
		$data['entry_meta_title'] = $this->language->get('entry_meta_title');
		$data['entry_meta_description'] = $this->language->get('entry_meta_description');
		$data['entry_meta_keyword'] = $this->language->get('entry_meta_keyword');
		$data['entry_keyword'] = $this->language->get('entry_keyword');
		$data['entry_parent'] = $this->language->get('entry_parent');
		$data['entry_filter'] = $this->language->get('entry_filter');
		$data['entry_store'] = $this->language->get('entry_store');
		$data['entry_image'] = $this->language->get('entry_image');
		$data['entry_top'] = $this->language->get('entry_top');
		$data['entry_column'] = $this->language->get('entry_column');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_layout'] = $this->language->get('entry_layout');

		$data['help_filter'] = $this->language->get('help_filter');
		$data['help_keyword'] = $this->language->get('help_keyword');
		$data['help_top'] = $this->language->get('help_top');
		$data['help_column'] = $this->language->get('help_column');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_data'] = $this->language->get('tab_data');
		$data['tab_design'] = $this->language->get('tab_design');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = array();
		}

		if (isset($this->error['meta_title'])) {
			$data['error_meta_title'] = $this->error['meta_title'];
		} else {
			$data['error_meta_title'] = array();
		}

		if (isset($this->error['keyword'])) {
			$data['error_keyword'] = $this->error['keyword'];
		} else {
			$data['error_keyword'] = '';
		}

		if (isset($this->error['parent'])) {
			$data['error_parent'] = $this->error['parent'];
		} else {
			$data['error_parent'] = '';
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
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['bundle_category_id'])) {
			$data['action'] = $this->url->link('extension/bundlecategory/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/bundlecategory/edit', 'user_token=' . $this->session->data['user_token'] . '&bundle_category_id=' . $this->request->get['bundle_category_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('extension/bundlecategory', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['bundle_category_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$category_info = $this->model_extension_bundlecategory->getCategory($this->request->get['bundle_category_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['category_description'])) {
			$data['category_description'] = $this->request->post['category_description'];
		} elseif (isset($this->request->get['bundle_category_id'])) {
			$data['category_description'] = $this->model_extension_bundlecategory->getCategoryDescriptions($this->request->get['bundle_category_id']);
		} else {
			$data['category_description'] = array();
		}

		if (isset($this->request->post['path'])) {
			$data['path'] = $this->request->post['path'];
		} elseif (!empty($category_info)) {
			$data['path'] = $category_info['path'];
		} else {
			$data['path'] = '';
		}

		if (isset($this->request->post['bundle_parent_id'])) {
			$data['bundle_parent_id'] = $this->request->post['bundle_parent_id'];
		} elseif (!empty($category_info)) {
			$data['bundle_parent_id'] = $category_info['bundle_parent_id'];
		} else {
			$data['bundle_parent_id'] = 0;
		}
		
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

		if (isset($this->request->post['category_store'])) {
			$data['category_store'] = $this->request->post['category_store'];
		} elseif (isset($this->request->get['bundle_category_id'])) {
			$data['category_store'] = $this->model_extension_bundlecategory->getCategoryStores($this->request->get['bundle_category_id']);
		} else {
			$data['category_store'] = array(0);
		}

		if (isset($this->request->post['bundlecategory_seo_url'])) {
			$data['bundlecategory_seo_url'] = $this->request->post['bundlecategory_seo_url'];
		} elseif (isset($this->request->get['bundle_category_id'])) {
			$data['bundlecategory_seo_url'] = $this->model_extension_bundlecategory->getCategorySeoUrls($this->request->get['bundle_category_id']);
			/*echo "<pre>";
			print_r($data['bundlecategory_seo_url']);die();*/
		} else {
			$data['bundlecategory_seo_url'] = array();
		}

		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($category_info)) {
			$data['image'] = $category_info['image'];
		} else {
			$data['image'] = '';
		}

		$this->load->model('tool/image');

		if (isset($this->request->post['image']) && is_file(DIR_IMAGE . $this->request->post['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($this->request->post['image'], 100, 100);
		} elseif (!empty($category_info) && is_file(DIR_IMAGE . $category_info['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($category_info['image'], 100, 100);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		if (isset($this->request->post['top'])) {
			$data['top'] = $this->request->post['top'];
		} elseif (!empty($category_info)) {
			$data['top'] = $category_info['top'];
		} else {
			$data['top'] = 0;
		}

		if (isset($this->request->post['column'])) {
			$data['column'] = $this->request->post['column'];
		} elseif (!empty($category_info)) {
			$data['column'] = $category_info['column'];
		} else {
			$data['column'] = 1;
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($category_info)) {
			$data['sort_order'] = $category_info['sort_order'];
		} else {
			$data['sort_order'] = 0;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($category_info)) {
			$data['status'] = $category_info['status'];
		} else {
			$data['status'] = true;
		}
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/bundlecategory_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/bundlecategory')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($this->request->post['category_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 2) || (utf8_strlen($value['name']) > 255)) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}

			if ((utf8_strlen($value['meta_title']) < 3) || (utf8_strlen($value['meta_title']) > 255)) {
				$this->error['meta_title'][$language_id] = $this->language->get('error_meta_title');
			}
		}

		if (isset($this->request->get['bundle_category_id']) && isset($this->request->post['bundle_parent_id'])) {
			$results = $this->model_extension_bundlecategory->getCategoryPath($this->request->post['bundle_parent_id']);
			
			foreach ($results as $result) {
				if ($result['bundle_path_id'] == $this->request->get['bundle_category_id']) {
					$this->error['parent'] = $this->language->get('error_parent');
					
					break;
				}
			}
		}

		if ($this->request->post['bundlecategory_seo_url']) {
			$this->load->model('design/seo_url');
			
			foreach ($this->request->post['bundlecategory_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						if (count(array_keys($language, $keyword)) > 1) {
							$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_unique');
						}

						$seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword);
	
						foreach ($seo_urls as $seo_url) {
							if (($seo_url['store_id'] == $store_id) && (!isset($this->request->get['bundle_category_id']) || ($seo_url['query'] != 'bundle_category_id=' . $this->request->get['bundle_category_id']))) {		
								$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');
				
								break;
							}
						}
					}
				}
			}
		}
		
		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}
		
		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/bundlecategory')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateRepair() {
		if (!$this->user->hasPermission('modify', 'extension/bundlecategory')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('extension/bundlecategory');

			$filter_data = array(
				'filter_name' => $this->request->get['filter_name'],
				'sort'        => 'name',
				'order'       => 'ASC',
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_extension_bundlecategory->getCategories($filter_data);

			foreach ($results as $result) {
				$json[] = array(
					'bundle_category_id' => $result['bundle_category_id'],
					'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	function generateSeoURL($string){
		$string = str_replace(array('[\', \']'), '', $string);
		$string = preg_replace('/\[.*\]/U', '', $string);
		$string = preg_replace('/&(amp;)?#?[a-z0-9]+;/i', '-', $string);
		$string = htmlentities($string, ENT_COMPAT, 'utf-8');
		$string = preg_replace('/&([a-z])(amp|acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig|quot|rsquo);/i', '\\1', $string );
		$string = str_replace('amp', '', $string);
		$string = str_replace(',', '', $string);
		$string = str_replace(':', '', $string);
		$string = str_replace('%', '', $string);
		$string = str_replace(';', '', $string);
		$string = str_replace('(', '', $string);
		$string = str_replace(')', '', $string);
		$string = str_replace('*', '', $string);
		$string = str_replace('.', '', $string);
		$string = str_replace('', '-', $string);
		$string= str_replace(' ', '-', $string);
		$string= str_replace('--', '-', $string);
		$string = preg_replace(array('/[^a-z0-9]/i', '/[-]+/') , '-', $string);
		return strtolower(trim($string, '-'));
	}
	public function SeoText() {
        $json = array();
        $this->load->model('extension/bundlecategory');
        $this->load->language('extension/bundlecategory');
        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
			
			if($this->request->get['name']) {
				$names = $this->request->get['name'];
			} else {
				$names ='';
			}
            
            $name = $this->generateSeoURL($names);
            if(!empty($name)) {
	            $json['seoname'] = $this->generateSeoURL($name);
	        } else {
	            $json['seoname'] = $names;
	        }
	        $json['name'] = $names;

            $json['success'] = $this->language->get('text_success');
                    
        }                   
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
