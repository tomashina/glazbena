<?php
class ModelExtensionBaselBasel extends Model {
	public function getSpecialEndDate($product_id) {
	  $query = $this->db->query("SELECT date_end FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . $this->config->get('config_customer_group_id') . "' AND ((date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");
        
		if ($query->num_rows) {
            return array(
                'date_end'   => $query->row['date_end'],
            );
        } else {
            return false;
        }
	}

	public function getSpecialEndDates(array $product_ids) {
		$product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

		if (!$product_ids) {
			return array();
		}

		$query = $this->db->query("SELECT product_id, date_end FROM " . DB_PREFIX . "product_special WHERE product_id IN (" . implode(',', $product_ids) . ") AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND date_end > NOW() ORDER BY product_id ASC, priority ASC, price ASC");
		$dates = array();

		foreach ($query->rows as $row) {
			$product_id = (int)$row['product_id'];

			if (!isset($dates[$product_id])) {
				$dates[$product_id] = $row['date_end'];
			}
		}

		return $dates;
	}
	

	
}
