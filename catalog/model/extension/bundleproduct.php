<?php
class ModelExtensionBundleproduct extends Model {

	public function getBundleId($bundle_category_ids) {
		$bundle_ids = array();
		foreach($bundle_category_ids as $bundle_category_id) {
			$query = $this->db->query("SELECT bundle_id FROM ". DB_PREFIX ."bundle_to_category WHERE bundle_category_id= '".(int)$bundle_category_id."'");
			foreach($query->rows as $bundle_id) {
					$bundle_ids[] = $bundle_id['bundle_id'];
			}
		}
		return $bundle_ids;
	}
	
	
	public function getBundles($data) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) LEFT JOIN " . DB_PREFIX . "bundle_to_store bs2 ON (b.bundle_id = bs2.bundle_id) WHERE b.bundle_id<>0 AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and bs2.store_id = '" . (int)$this->config->get('config_store_id') . "' and b.status=1";

		$sort_data = array(
			'bn.title',
			'b.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY bn.title";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalBundles($data) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) LEFT JOIN " . DB_PREFIX . "bundle_to_store bs2 ON (b.bundle_id = bs2.bundle_id) WHERE b.bundle_id<>0 AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and bs2.store_id = '" . (int)$this->config->get('config_store_id') . "' and b.status=1";
		$query = $this->db->query($sql);
		return $query->row['total'];
	}

	public function getBundleProducts($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id = '" . (int)$bundle_id . "' ORDER BY sort_order ASC";
		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getBundle($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) WHERE b.bundle_id= '" . (int)$bundle_id . "' AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and b.status=1";
		$query = $this->db->query($sql);
		return $query->row;
	}

	public function getTotalPrice($bundle_id){
		$sql="SELECT sum(price) AS total FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "bundle_to_product b2p ON (p.product_id = b2p.product_id) WHERE b2p.bundle_id='".$bundle_id."'";		
		$query = $this->db->query($sql);
		return $query->row['total'];
	}

	public function getTotalCategoryToBundles($data = array()) {
		$sql = "SELECT COUNT(DISTINCT b.bundle_id) AS total";

		if (!empty($data['filter_bundle_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "bundle_category_path cp LEFT JOIN " . DB_PREFIX . "bundle_to_category p2c ON (cp.bundle_category_id = p2c.bundle_category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "bundle_to_category p2c";
			}
			$sql .= " LEFT JOIN " . DB_PREFIX . "bundle b ON (p2c.bundle_id = b.bundle_id)";
		} else {
			$sql .= " FROM " . DB_PREFIX . "bundle b";
		}
		
		$sql .= " LEFT JOIN " . DB_PREFIX . "bundle_to_store bs2 ON (b.bundle_id = bs2.bundle_id) WHERE bs2.store_id = '" . (int)$this->config->get('config_store_id') . "' and b.status = '1' AND ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW()))";

		if (!empty($data['filter_bundle_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.bundle_path_id = '" . (int)$data['filter_bundle_category_id'] . "'";
			} else {
				$sql .= " AND p2c.bundle_category_id = '" . (int)$data['filter_bundle_category_id'] . "'";
			}		
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}
}