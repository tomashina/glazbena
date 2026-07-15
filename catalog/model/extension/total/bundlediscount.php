<?php
class ModelExtensionTotalBundlediscount extends Model {
	public function getTotal($total) {
		$this->load->language('extension/total/bundlediscount');
		$this->load->model('extension/bundleproduct');
		$this->load->model('catalog/product');
		$status = true;
		$discount=0;
		$dis_query = $this->db->query("SELECT bundle_id FROM " . DB_PREFIX . "bundle_cart Where customer_id='".$this->customer->getId()."' and session_id = '".$this->db->escape($this->session->getId())."' and api_id = '" . (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0) . "' GROUP BY bundle_id");
		foreach($dis_query->rows as $bundle) {
									
			$totalprices=0;
			$newprices=0;
			$probundles = $this->model_extension_bundleproduct->getBundleProducts($bundle['bundle_id']);
			
			foreach($probundles as $product_infos) {
				$pro_info = $this->model_catalog_product->getProduct($product_infos['product_id']);
				if($pro_info) {
					$newprices =$this->tax->calculate($pro_info['special'] ? $pro_info['special'] : $pro_info['price'], $pro_info['tax_class_id'], $this->config->get('config_tax'));
					
					if(isset($product_infos['quantity'])) {
						$totalprices += $newprices * $product_infos['quantity'];
					} else {
						$totalprices +=$newprices;
					}
				}
			}
			
			$default_group_id = $this->config->get('config_customer_group_id');
		    $customer_group_id = $this->customer->getGroupId();
		    
		    if($customer_group_id) {
		      $group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle['bundle_id']."' and customer_group_id='".(int)$customer_group_id."'");
		    } else {
		      $group_query =  $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_to_customergroup` WHERE bundle_id = '".$bundle['bundle_id']."' and customer_group_id='".(int)$default_group_id."'");
		    }
		    
			if ($group_query->num_rows) {
				$discountvalue=0;
				if($group_query->row['discount_option'] == 'Percentage') {
					$discountvalue = ($totalprices*($group_query->row['add_discount']/100));
				} else {
					$discountvalue = $group_query->row['add_discount'];
				}
				$group_query->row['add_discount']++;
				$discount += $discountvalue;
			}
		}
		if($discount > 0) {
			$total['totals'][] = array(
				'code'       => 'bundlediscount',
				'title'      => $this->language->get('text_dtotal'),
				'value'      => -$discount,
				'sort_order' => $this->config->get('total_bundlediscount_sort_order')
			);
			$total['total'] -= $discount;
		}
	}
}