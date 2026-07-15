<?php
class ControllerExtensionModuleBundlecategory extends Controller {
	public function index() {
		$this->load->language('extension/module/bundlecategory');

		$data['heading_title'] = $this->language->get('heading_title');

		if (isset($this->request->get['bundlepath'])) {
			$parts = explode('_', (string)$this->request->get['bundlepath']);
		} else {
			$parts = array();
		}

		if (isset($parts[0])) {
			$data['bundle_category_id'] = $parts[0];
		} else {
			$data['bundle_category_id'] = 0;
		}

		if (isset($parts[1])) {
			$data['child_id'] = $parts[1];
		} else {
			$data['child_id'] = 0;
		}

		$this->load->model('extension/bundlecategory');

		$this->load->model('extension/bundleproduct');

		$data['categories'] = array();

		$data['bundles'] = $this->model_extension_bundleproduct->getBundles($data);

		$categories = $this->model_extension_bundlecategory->getCategories(0);

		foreach ($categories as $category) {
			$children_data = array();

			if ($category['bundle_category_id'] == $data['bundle_category_id']) {
				$children = $this->model_extension_bundlecategory->getCategories($category['bundle_category_id']);

				foreach($children as $child) {
					$filter_data = array('filter_bundle_category_id' => $child['bundle_category_id'], 'filter_sub_category' => true);

					$children_data[] = array(
						'bundle_category_id' => $child['bundle_category_id'],
						'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_extension_bundleproduct->getTotalCategoryToBundles($filter_data) . ')' : ''),
						'href' => $this->url->link('extension/bundleproduct', 'bundlepath=' . $category['bundle_category_id'] . '_' . $child['bundle_category_id'])
					);
				}
			}

			$filter_data = array(
				'filter_bundle_category_id'  => $category['bundle_category_id'],
				'filter_sub_category' => true
			);

			$data['categories'][] = array(
				'bundle_category_id' => $category['bundle_category_id'],
				'name'        => $category['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_extension_bundleproduct->getTotalCategoryToBundles($filter_data) . ')' : ''),
				'children'    => $children_data,
				'href'        => $this->url->link('extension/bundleproduct', 'bundlepath=' . $category['bundle_category_id'])
			);
		}

		return $this->load->view('extension/module/bundlecategory', $data);
	}
}