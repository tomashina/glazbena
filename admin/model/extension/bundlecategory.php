<?php
class ModelExtensionBundlecategory extends Model {
	public function addCategory($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_category SET bundle_parent_id = '" . (int)$data['bundle_parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW(), date_added = NOW()");

		$bundle_category_id = $this->db->getLastId();

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "bundle_category SET image = '" . $this->db->escape($data['image']) . "' WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");
		}

		foreach ($data['category_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_category_description SET bundle_category_id = '" . (int)$bundle_category_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$level = 0;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_category_path` WHERE bundle_category_id = '" . (int)$data['bundle_parent_id'] . "' ORDER BY `level` ASC");

		foreach ($query->rows as $result) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "bundle_category_path` SET `bundle_category_id` = '" . (int)$bundle_category_id . "', `bundle_path_id` = '" . (int)$result['bundle_path_id'] . "', `level` = '" . (int)$level . "'");

			$level++;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "bundle_category_path` SET `bundle_category_id` = '" . (int)$bundle_category_id . "', `bundle_path_id` = '" . (int)$bundle_category_id . "', `level` = '" . (int)$level . "'");
		
		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_category_to_store SET bundle_category_id = '" . (int)$bundle_category_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		// SEO URL

		if (isset($data['bundlecategory_seo_url'])) {
			foreach ($data['bundlecategory_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'bundle_category_id=" . (int)$bundle_category_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		$this->cache->delete('category');

		return $bundle_category_id;
	}

	public function editCategory($bundle_category_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "bundle_category SET bundle_parent_id = '" . (int)$data['bundle_parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "bundle_category SET image = '" . $this->db->escape($data['image']) . "' WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_category_description WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

		foreach ($data['category_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_category_description SET bundle_category_id = '" . (int)$bundle_category_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_category_path` WHERE bundle_path_id = '" . (int)$bundle_category_id . "' ORDER BY level ASC");

		if ($query->rows) {
			foreach ($query->rows as $category_path) {
				// Delete the path below the current one
				$this->db->query("DELETE FROM `" . DB_PREFIX . "bundle_category_path` WHERE bundle_category_id = '" . (int)$category_path['bundle_category_id'] . "' AND level < '" . (int)$category_path['level'] . "'");

				$path = array();

				// Get the nodes new parents
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_category_path` WHERE bundle_category_id = '" . (int)$data['bundle_parent_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['bundle_path_id'];
				}

				// Get whats left of the nodes current path
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_category_path` WHERE bundle_category_id = '" . (int)$category_path['bundle_category_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['bundle_path_id'];
				}

				// Combine the paths with a new level
				$level = 0;

				foreach ($path as $bundle_path_id) {
					$this->db->query("REPLACE INTO `" . DB_PREFIX . "bundle_category_path` SET bundle_category_id = '" . (int)$category_path['bundle_category_id'] . "', `bundle_path_id` = '" . (int)$bundle_path_id . "', level = '" . (int)$level . "'");

					$level++;
				}
			}
		} else {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "bundle_category_path` WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bundle_category_path` WHERE bundle_category_id = '" . (int)$data['bundle_parent_id'] . "' ORDER BY level ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "bundle_category_path` SET bundle_category_id = '" . (int)$bundle_category_id . "', `bundle_path_id` = '" . (int)$result['bundle_path_id'] . "', level = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "bundle_category_path` SET bundle_category_id = '" . (int)$bundle_category_id . "', `bundle_path_id` = '" . (int)$bundle_category_id . "', level = '" . (int)$level . "'");
		}
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_category_to_store WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_category_to_store SET bundle_category_id = '" . (int)$bundle_category_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		// SEO URL
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'bundle_category_id=" . (int)$bundle_category_id . "'");

		if (isset($data['bundlecategory_seo_url'])) {
			foreach ($data['bundlecategory_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'bundle_category_id=" . (int)$bundle_category_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		$this->cache->delete('category');
	}

	public function deleteCategory($bundle_category_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_category_path WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_category_path WHERE bundle_path_id = '" . (int)$bundle_category_id . "'");

		foreach ($query->rows as $result) {
			$this->deleteCategory($result['bundle_category_id']);
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_category WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_category_description WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_category_to_store WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'bundle_category_id=" . (int)$bundle_category_id . "'");

		$this->cache->delete('category');
	}
		
	public function getCategory($bundle_category_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT GROUP_CONCAT(cd1.name ORDER BY level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') FROM " . DB_PREFIX . "bundle_category_path cp LEFT JOIN " . DB_PREFIX . "bundle_category_description cd1 ON (cp.bundle_path_id = cd1.bundle_category_id AND cp.bundle_category_id != cp.bundle_path_id) WHERE cp.bundle_category_id = c.bundle_category_id AND cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY cp.bundle_category_id) AS path FROM " . DB_PREFIX . "bundle_category c LEFT JOIN " . DB_PREFIX . "bundle_category_description cd2 ON (c.bundle_category_id = cd2.bundle_category_id) WHERE c.bundle_category_id = '" . (int)$bundle_category_id . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		
		return $query->row;
	}

	public function getCategories($data = array()) {
		$sql = "SELECT cp.bundle_category_id AS bundle_category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') AS name, c1.bundle_parent_id, c1.sort_order FROM " . DB_PREFIX . "bundle_category_path cp LEFT JOIN " . DB_PREFIX . "bundle_category c1 ON (cp.bundle_category_id = c1.bundle_category_id) LEFT JOIN " . DB_PREFIX . "bundle_category c2 ON (cp.bundle_path_id = c2.bundle_category_id) LEFT JOIN " . DB_PREFIX . "bundle_category_description cd1 ON (cp.bundle_path_id = cd1.bundle_category_id) LEFT JOIN " . DB_PREFIX . "bundle_category_description cd2 ON (cp.bundle_category_id = cd2.bundle_category_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND cd2.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sql .= " GROUP BY cp.bundle_category_id";

		$sort_data = array(
			'name',
			'sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY bundle_category_id";
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

	public function getCategoryDescriptions($bundle_category_id) {
		$category_description_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_category_description WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

		foreach ($query->rows as $result) {
			$category_description_data[$result['language_id']] = array(
				'name'             => $result['name'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword'],
				'description'      => $result['description']
			);
		}

		return $category_description_data;
	}
	
	public function getCategoryPath($bundle_category_id) {
		$query = $this->db->query("SELECT bundle_category_id, bundle_path_id, level FROM " . DB_PREFIX . "bundle_category_path WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

		return $query->rows;
	}
		
	public function getCategoryStores($bundle_category_id) {
		$category_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_category_to_store WHERE bundle_category_id = '" . (int)$bundle_category_id . "'");

		foreach ($query->rows as $result) {
			$category_store_data[] = $result['store_id'];
		}

		return $category_store_data;
	}

	public function getCategorySeoUrls($bundle_category_id) {
		$bundlecategory_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'bundle_category_id=" . (int)$bundle_category_id . "'");

		foreach ($query->rows as $result) {
			$bundlecategory_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $bundlecategory_seo_url_data;
	}
	
	public function getTotalCategories() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle_category");

		return $query->row['total'];
	}
		
	function generateSeoURL($string, $wordLimit = 0){
	    $separator = '-';
	    
	    if($wordLimit != 0){
	        $wordArr = explode(' ', $string);
	        $string = implode(' ', array_slice($wordArr, 0, $wordLimit));
	    }

	    $quoteSeparator = preg_quote($separator, '#');

	    $trans = array(
	        '&.+?;'                    => '',
	        '[^\w\d _-]'            => '',
	        '\s+'                    => $separator,
	        '('.$quoteSeparator.')+'=> $separator
	    );

	    $string = strip_tags($string);
	    foreach ($trans as $key => $val){
	        $string = preg_replace('#'.$key.'#i'.(UTF8_ENABLED ? 'u' : ''), $val, $string);
	    }

	    $string = strtolower($string);

	    return trim(trim($string, $separator));
	}
}
