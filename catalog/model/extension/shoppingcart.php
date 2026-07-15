<?php
class ModelExtensionShoppingcart extends Model {
	public function getBundleProducts($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id='".(int)$bundle_id."'";
		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getProduct($product_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "product p LEFT JOIN " .DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p.product_id='".(int)$product_id."'";
		$query = $this->db->query($sql);
		return $query->row;
	}

	public function getBundle($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) LEFT JOIN " . DB_PREFIX . "bundle_to_store bs2 ON (b.bundle_id = bs2.bundle_id) WHERE b.bundle_id= '" . (int)$bundle_id . "' AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "' and ((b.date_from = '0000-00-00' OR b.date_from < NOW()) AND (b.date_to = '0000-00-00' OR b.date_to > NOW())) and bs2.store_id = '" . (int)$this->config->get('config_store_id') . "' and b.status=1";
		$query = $this->db->query($sql);
		return $query->row;
	}

	public function getOption($option_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "option` o LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE o.option_id = '" . (int)$option_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getOptionValue($option_value_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_value_id = '" . (int)$option_value_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getProductOptions($product_id) {
		$product_option_data = array();

		$product_option_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		foreach ($product_option_query->rows as $product_option) {
			$product_option_value_data = array();

			$product_option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON(pov.option_value_id = ov.option_value_id) WHERE pov.product_option_id = '" . (int)$product_option['product_option_id'] . "' ORDER BY ov.sort_order ASC");

			foreach ($product_option_value_query->rows as $product_option_value) {
				$product_option_value_data[] = array(
					'product_option_value_id' => $product_option_value['product_option_value_id'],
					'option_value_id'         => $product_option_value['option_value_id'],
					'quantity'                => $product_option_value['quantity'],
					'subtract'                => $product_option_value['subtract'],
					'price'                   => $product_option_value['price'],
					'price_prefix'            => $product_option_value['price_prefix'],
					'points'                  => $product_option_value['points'],
					'points_prefix'           => $product_option_value['points_prefix'],
					'weight'                  => $product_option_value['weight'],
					'weight_prefix'           => $product_option_value['weight_prefix']
				);
			}

			$product_option_data[] = array(
				'product_option_id'    => $product_option['product_option_id'],
				'product_option_value' => $product_option_value_data,
				'option_id'            => $product_option['option_id'],
				'name'                 => $product_option['name'],
				'type'                 => $product_option['type'],
				'value'                => $product_option['value'],
				'required'             => $product_option['required']
			);
		}

		return $product_option_data;
	}

	public function getStockStatus($stock_status_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "stock_status WHERE stock_status_id = '" . (int)$stock_status_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

}