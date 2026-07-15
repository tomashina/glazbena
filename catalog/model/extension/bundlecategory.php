<?php
class ModelExtensionBundlecategory extends Model {
	public function getCategory($bundle_category_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "bundle_category c LEFT JOIN " . DB_PREFIX . "bundle_category_description cd ON (c.bundle_category_id = cd.bundle_category_id) LEFT JOIN " . DB_PREFIX . "bundle_category_to_store c2s ON (c.bundle_category_id = c2s.bundle_category_id) WHERE c.bundle_category_id = '" . (int)$bundle_category_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1'");

		return $query->row;
	}

	public function getCategories($bundle_parent_id = 0) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_category c LEFT JOIN " . DB_PREFIX . "bundle_category_description cd ON (c.bundle_category_id = cd.bundle_category_id) LEFT JOIN " . DB_PREFIX . "bundle_category_to_store c2s ON (c.bundle_category_id = c2s.bundle_category_id) WHERE c.bundle_parent_id = '" . (int)$bundle_parent_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'  AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)");

		return $query->rows;
	}

	public function getCategoryFilters($category_id) {
		$implode = array();

		$query = $this->db->query("SELECT filter_id FROM " . DB_PREFIX . "category_filter WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$implode[] = (int)$result['filter_id'];
		}

		$filter_group_data = array();

		if ($implode) {
			$filter_group_query = $this->db->query("SELECT DISTINCT f.filter_group_id, fgd.name, fg.sort_order FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_group fg ON (f.filter_group_id = fg.filter_group_id) LEFT JOIN " . DB_PREFIX . "filter_group_description fgd ON (fg.filter_group_id = fgd.filter_group_id) WHERE f.filter_id IN (" . implode(',', $implode) . ") AND fgd.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY f.filter_group_id ORDER BY fg.sort_order, LCASE(fgd.name)");

			foreach ($filter_group_query->rows as $filter_group) {
				$filter_data = array();

				$filter_query = $this->db->query("SELECT DISTINCT f.filter_id, fd.name FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (f.filter_id = fd.filter_id) WHERE f.filter_id IN (" . implode(',', $implode) . ") AND f.filter_group_id = '" . (int)$filter_group['filter_group_id'] . "' AND fd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY f.sort_order, LCASE(fd.name)");

				foreach ($filter_query->rows as $filter) {
					$filter_data[] = array(
						'filter_id' => $filter['filter_id'],
						'name'      => $filter['name']
					);
				}

				if ($filter_data) {
					$filter_group_data[] = array(
						'filter_group_id' => $filter_group['filter_group_id'],
						'name'            => $filter_group['name'],
						'filter'          => $filter_data
					);
				}
			}
		}

		return $filter_group_data;
	}

	public function getCategoryLayoutId($category_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return $query->row['layout_id'];
		} else {
			return 0;
		}
	}

	public function getTotalCategoriesByCategoryId($parent_id = 0) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND c.status = '1'");

		return $query->row['total'];
	}

	public function getBundleToCategories($data,$bundle_category_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle_to_category b2c left join ". DB_PREFIX ."bundle b on (b2c.bundle_id = b.bundle_id) LEFT JOIN " . DB_PREFIX . "bundle_to_store bs2 ON (b.bundle_id = bs2.bundle_id) WHERE b2c.bundle_category_id='".(int)$bundle_category_id."' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and bs2.store_id = '" . (int)$this->config->get('config_store_id') . "' and b.status=1";
		
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

	public function getTotalCategoryToBundles($data,$bundle_category_id) {
		$sql = "SELECT COUNT(DISTINCT b.bundle_id) AS total";

		if (!empty($data['filter_bundle_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "bundle_category_path cp LEFT JOIN " . DB_PREFIX . "bundle_to_category p2c ON (cp.bundle_category_id = p2c.bundle_category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "bundle_to_category p2c";
			}
			$sql .= " LEFT JOIN " . DB_PREFIX . "bundle b ON (p2c.bundle_id = b.bundle_id)";
		} else {
			$sql .= " FROM " . DB_PREFIX . "bundle b ";
		}
		
		$sql .= " LEFT JOIN " . DB_PREFIX . "bundle_to_store bs2 ON (b.bundle_id = bs2.bundle_id) WHERE b.status = '1' AND ((b.date_from = '' OR b.date_from < NOW()) and bs2.store_id = '" . (int)$this->config->get('config_store_id') . "' AND (b.date_to = '' OR b.date_to > NOW()))";

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

	public function getTotalBundleCategories($data,$bundle_category_id) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle_to_category b2c left join ". DB_PREFIX ."bundle b on (b2c.bundle_id = b.bundle_id) WHERE b2c.bundle_category_id='".(int)$bundle_category_id."' and ((b.date_from = '' OR b.date_from < NOW()) AND (b.date_to = '' OR b.date_to > NOW())) and b.status=1";
		$query = $this->db->query($sql);
		return $query->row['total'];
	}
}