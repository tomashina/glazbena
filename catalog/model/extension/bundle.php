<?php
class ModelExtensionBundle extends Model {
	
	public function getBundles($data) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) LEFT JOIN " . DB_PREFIX . "bundle_to_store bs2 ON (b.bundle_id = bs2.bundle_id) WHERE b.bundle_id<>0 AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "' and ((b.date_from = '0000-00-00' OR b.date_from < NOW()) AND (b.date_to = '0000-00-00' OR b.date_to > NOW())) and bs2.store_id = '" . (int)$this->config->get('config_store_id') . "'and b.status=1";

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
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) WHERE b.bundle_id<>0 AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and b.status=1";
		$query = $this->db->query($sql);
		return $query->row['total'];
	}

	public function getTotalBundleProducts($bundle_id) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id='".(int)$bundle_id."'";
		$query = $this->db->query($sql);
		return $query->row['total'];
	}

	public function getBundleProducts($data,$product_id) {
		$sql="SELECT * FROM " . DB_PREFIX . "bundle_to_product b2p LEFT JOIN " . DB_PREFIX . "bundle b ON (b2p.bundle_id = b.bundle_id) WHERE b2p.product_id='".(int)$product_id."' and b2p.id_default = 1 and((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and b.status=1";

		$sort_data = array(
			'b.bundle_id',
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY b.bundle_id";
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

	public function getBundle($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) WHERE b.bundle_id= '" . (int)$bundle_id . "' AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and b.status=1";
		$query = $this->db->query($sql);
		return $query->row;
	}

	public function getBundlePros($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id = '" . (int)$bundle_id . "' ORDER BY sort_order ASC";
		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalPrice($bundle_id){
		$sql="SELECT sum(price) AS total FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "bundle_to_product b2p ON (p.product_id = b2p.product_id) WHERE b2p.bundle_id='".$bundle_id."'";		
		$query = $this->db->query($sql);
		return $query->row['total'];
	}

	public function getTotalProBundles($data,$product_id) {
		$sql="SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle_to_product b2p LEFT JOIN " . DB_PREFIX . "bundle b ON (b2p.bundle_id = b.bundle_id) WHERE b2p.product_id='".(int)$product_id."' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and b.status=1";		
		$query = $this->db->query($sql);
		return $query->row['total'];
	}
}