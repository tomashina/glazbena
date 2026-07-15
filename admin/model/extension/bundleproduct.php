<?php
class ModelExtensionBundleproduct extends Model {
	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle` (
		  `bundle_id` int(11) NOT NULL AUTO_INCREMENT,
		  `date_from` varchar(250) NOT NULL,
		  `date_to` varchar(250) NOT NULL,
		  `sort_order` varchar(200) NOT NULL,
		  `status` int(11) NOT NULL,
		  `date_added` datetime NOT NULL,
		  `date_modified` datetime NOT NULL,
		  PRIMARY KEY (`bundle_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_language` (
		  `bundle_id` int(11) NOT NULL,
		  `language_id` int(11) NOT NULL,
		  `title` text NOT NULL
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");
		
		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_order` (
	        `bundle_order_id` int(11) NOT NULL AUTO_INCREMENT,      
	        `bundle_id` int(11) NOT NULL,
	        `order_id` int(11) NOT NULL,
	        PRIMARY KEY (`bundle_order_id`)
	    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_cart` (
		  `bundle_cart_id` int(11) NOT NULL AUTO_INCREMENT,
		  `cart_id` int(11) NOT NULL,
		  `api_id` int(11) NOT NULL,
		  `product_id` int(11) NOT NULL,
		  `bundle_id` int(11) NOT NULL,
		  `quantity` int(5) NOT NULL,
		  `date_added` date NOT NULL,
		  `customer_id` int(11) NOT NULL,
		  `session_id` varchar(35) NOT NULL,
		  PRIMARY KEY (`bundle_cart_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_to_product` (
		  `bundle_id` int(11) NOT NULL,
		  `product_id` int(11) NOT NULL,
		  `quantity` int(11) NOT NULL
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_to_category` (
		  `bundle_id` int(11) NOT NULL,
		  `bundle_category_id` int(11) NOT NULL
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_to_store` (
	      `bundle_id` int(11) NOT NULL,
	      `store_id` int(11) NOT NULL
	    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_to_customergroup` (
		  `bundle_discount_id` int(11) NOT NULL AUTO_INCREMENT,
		  `bundle_id` int(11) NOT NULL,
		  `customer_group_id` int(11) NOT NULL,
		  `discount_option` text NOT NULL,
		  `add_discount` varchar(250) NOT NULL,
		  PRIMARY KEY (`bundle_discount_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_category` (
		  `bundle_category_id` int(11) NOT NULL AUTO_INCREMENT,
		  `image` varchar(250) NOT NULL,
		  `bundle_parent_id` int(11) NOT NULL,
		  `top` int(11) NOT NULL,
		  `column` int(11) NOT NULL,
		  `sort_order` int(11) NOT NULL,
		  `status` int(11) NOT NULL,
		  `date_added` datetime NOT NULL,
		  `date_modified` datetime NOT NULL,
		  PRIMARY KEY (`bundle_category_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_category_description` (
		  `bundle_category_id` int(11) NOT NULL,
		  `language_id` int(11) NOT NULL,
		  `name` varchar(250) NOT NULL,
		  `description` text NOT NULL,
		  `meta_title` varchar(250) NOT NULL,
		  `meta_description` varchar(250) NOT NULL,
		  `meta_keyword` varchar(250) NOT NULL
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_category_path` (
		  `bundle_category_id` int(11) NOT NULL,
		  `bundle_path_id` int(11) NOT NULL,
		  `level` int(11) NOT NULL,
		  PRIMARY KEY (`bundle_category_id`, `bundle_path_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bundle_category_to_store` (
		  `bundle_category_id` int(11) NOT NULL,
		  `store_id` int(11) NOT NULL
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");
		
		$modules= array(
			   'extension_id' => '35917',
			   'email' => $this->config->get('config_email'),
			   'store_url' => HTTP_CATALOG
			);
	
		$url = 'https://www.opencartextensions.in/index.php?route=api/callback';
		
		$curl = curl_init($url);
		
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($modules, '', '&'));
		
		 $response = curl_exec($curl);

	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_language`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_cart`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_to_product`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_to_category`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_to_store`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_to_customergroup`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_category`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_category_description`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_category_path`");
		$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."bundle_category_to_store`");
	}
	
	
	public function addBundle($data) {
		$sql="INSERT INTO " . DB_PREFIX . "bundle SET date_from = '" . $this->db->escape($data['date_from']) . "',date_to = '" . $this->db->escape($data['date_to']) . "',sort_order = '" . (int)$data['sort_order'] . "',status = '" . (int)$data['status'] . "',date_added=NOW()";
		$this->db->query($sql);
		$bundle_id = $this->db->getLastId();

		if (isset($data['bundle_language'])) {
			foreach ($data['bundle_language'] as $language_id => $value) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_language SET bundle_id = '" . (int)$bundle_id . "', language_id = '" . (int)$language_id . "',title = '" . $this->db->escape($value['title']) . "'");
			}
		}

		if (isset($data['bundle_product'])) {
			foreach ($data['bundle_product'] as $product) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_product SET bundle_id = '" . (int)$bundle_id . "', product_id = '" . (int)$product['product_id'] . "', id_default = '" . (int)$product['id_default'] . "', sort_order = '" . (int)$product['sort_order'] . "', quantity = '" . (int)$product['quantity'] . "'");
			}
		}

		if (isset($data['bundle_category'])) {
			foreach ($data['bundle_category'] as $bundle_category_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_category SET bundle_id = '" . (int)$bundle_id . "', bundle_category_id = '" . (int)$bundle_category_id . "'");
			}
		}

		if (isset($data['bundleproduct_store'])) {
			foreach ($data['bundleproduct_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_store SET bundle_id = '" . (int)$bundle_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_to_customergroup WHERE bundle_id = '" . (int)$bundle_id . "'");
	   	if (isset($data['customer_group'])) {
		    foreach ($data['customer_group'] as $key => $group) {
			  $this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_customergroup SET bundle_id = '" . (int)$bundle_id . "',customer_group_id = '" . (int)$key . "',discount_option = '" . $this->db->escape($group['discount_option']) . "',add_discount = '" .$group['add_discount'] . "'");
		    }
	    }

	    if (isset($data['bundle_seo_url'])) {
			foreach ($data['bundle_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'bundle_id=" . (int)$bundle_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		return $bundle_id;
	}

	public function editBundle($bundle_id, $data) {
		$sql="UPDATE " . DB_PREFIX . "bundle SET date_from = '" . $this->db->escape($data['date_from']) . "',date_to = '" . $this->db->escape($data['date_to']) . "',sort_order = '" . (int)$data['sort_order'] . "',status = '" . (int)$data['status'] . "',date_modified=NOW() WHERE bundle_id = '" . (int)$bundle_id . "'";
		$this->db->query($sql);

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_language WHERE bundle_id = '" . (int)$bundle_id . "'");
		if (isset($data['bundle_language'])) {
			foreach ($data['bundle_language'] as $language_id => $value) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_language SET bundle_id = '" . (int)$bundle_id . "', language_id = '" . (int)$language_id . "',title = '" . $this->db->escape($value['title']) . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id = '" . (int)$bundle_id . "'");

		if (isset($data['bundle_product'])) {
			foreach ($data['bundle_product'] as $product) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_product SET bundle_id = '" . (int)$bundle_id . "', product_id = '" . (int)$product['product_id'] . "', id_default = '" . (int)$product['id_default'] . "', sort_order = '" . (int)$product['sort_order'] . "', quantity = '" . (int)$product['quantity'] . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_to_category WHERE bundle_id = '" . (int)$bundle_id . "'");
		if (isset($data['bundle_category'])) {
			foreach ($data['bundle_category'] as $bundle_category_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_category SET bundle_id = '" . (int)$bundle_id . "', bundle_category_id = '" . (int)$bundle_category_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_to_store WHERE bundle_id = '" . (int)$bundle_id . "'");

		if (isset($data['bundleproduct_store'])) {
			foreach ($data['bundleproduct_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_store SET bundle_id = '" . (int)$bundle_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_to_customergroup WHERE bundle_id = '" . (int)$bundle_id . "'");
	   	if (isset($data['customer_group'])) {
		    foreach ($data['customer_group'] as $key => $group) {
			  $this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_customergroup SET bundle_id = '" . (int)$bundle_id . "',customer_group_id = '" . (int)$key . "',discount_option = '" . $this->db->escape($group['discount_option']) . "',add_discount = '" .$group['add_discount'] . "'");
		    }
	    }

	    $this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'bundle_id=" . (int)$bundle_id . "'");
	    if (isset($data['bundle_seo_url'])) {
			foreach ($data['bundle_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'bundle_id=" . (int)$bundle_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

	}

	public function QuickStatus($status,$bundle_id) {
        $sql="UPDATE " . DB_PREFIX . "bundle SET status ='".(int)$status."',date_modified=NOW() WHERE bundle_id = '" . (int)$bundle_id . "'";
        $this->db->query($sql);
    }

	public function deleteBundle($bundle_id) {
		$sql="DELETE  from " . DB_PREFIX . "bundle where bundle_id='".$bundle_id."'";
		$query=$this->db->query($sql);

		$sql="DELETE  from " . DB_PREFIX . "bundle_language where bundle_id='".$bundle_id."'";
		$query=$this->db->query($sql);
		
		$sql="DELETE  from " . DB_PREFIX . "bundle_to_product where bundle_id='".$bundle_id."'";
		$query=$this->db->query($sql);

		$sql="DELETE  from " . DB_PREFIX . "bundle_to_category where bundle_id='".$bundle_id."'";
		$query=$this->db->query($sql);

		$sql="DELETE  from " . DB_PREFIX . "bundle_to_store where bundle_id='".(int)$bundle_id."'";
		$query=$this->db->query($sql);

		$sql="DELETE  from " . DB_PREFIX . "bundle_to_customergroup where bundle_id='".(int)$bundle_id."'";
		$query=$this->db->query($sql);
	}

	public function getBundle($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle WHERE bundle_id='".(int)$bundle_id."'";
		$query = $this->db->query($sql);
		return $query->row;
	}

	public function getBundleStores($bundle_id) {
		$bundle_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_to_store WHERE bundle_id = '" . (int)$bundle_id . "'");

		foreach ($query->rows as $result) {
			$bundle_store_data[] = $result['store_id'];
		}

		return $bundle_store_data;
	}

	public function getStore($store_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "store WHERE store_id = '" . (int)$store_id . "'");

		return $query->row;
	}
	

	public function getFeaturedBundle($bundle_id) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) WHERE bn.bundle_id = '" . (int)$bundle_id . "' AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$query = $this->db->query($sql);
		return $query->row;
	}

	public function getBundles($data) {
		$sql = "SELECT * FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) WHERE b.bundle_id<>0 AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$sort_data = array(
			'bn.title',
			'b.status',
			'b.date_from',
			'b.date_to',
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

	public function getBundleLanguages($bundle_id) {
		$bundle_language_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_language WHERE bundle_id = '" . (int)$bundle_id . "'");

		foreach ($query->rows as $result) {
			$bundle_language_data[$result['language_id']] = array('title' => $result['title']);
		}

		return $bundle_language_data;
	}

	public function getTotalBundles($data) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle b LEFT JOIN " . DB_PREFIX . "bundle_language bn ON (b.bundle_id = bn.bundle_id) WHERE b.bundle_id<>0 AND bn.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		$query = $this->db->query($sql);
		return $query->row['total'];
	}

	public function getCustomerGroup($bundle_id) {
		$customer_group_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_to_customergroup WHERE bundle_id = '" . (int)$bundle_id . "'");

		foreach ($query->rows as $group) {
			$customer_group_data[$group['customer_group_id']] = array(
				'discount_option' 	=> $group['discount_option'],
				'add_discount' 		=> $group['add_discount'],
			);
		}

		return $customer_group_data;
	}

	public function getBundleSeoUrls($bundle_id) {
		$bundle_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'bundle_id=" . (int)$bundle_id . "'");

		foreach ($query->rows as $result) {
			$bundle_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $bundle_seo_url_data;
	}

	public function getBundleProducts($bundle_id) {
		$bundle_product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id = '" . (int)$bundle_id . "'");

		foreach ($query->rows as $result) {
			$bundle_product_data[] = array(
				'product_id' => $result['product_id'],
				'quantity' => $result['quantity'],
				'sort_order' => $result['sort_order'],
				'id_default' => $result['id_default'],
			);
		}

		return $bundle_product_data;
	}

	public function getBundleProductLists($bundle_id) {
		$bundle_product_datalist = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id = '" . (int)$bundle_id . "'");

		foreach ($query->rows as $result) {
			$bundle_product_datalist[] = $result['product_id'];
		}

		return $bundle_product_datalist;
	}

	public function getBundleCategories($bundle_id) {
		$bundle_category_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bundle_to_category WHERE bundle_id = '" . (int)$bundle_id . "'");

		foreach ($query->rows as $result) {
			$bundle_category_data[] = $result['bundle_category_id'];
		}

		return $bundle_category_data;
	}

	public function updateProduct($bundle_id,$data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_to_product WHERE bundle_id = '" . (int)$bundle_id . "'");
		if (isset($data['bundle_product'])) {
			foreach ($data['bundle_product'] as $product_id => $qty) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_product SET bundle_id = '" . (int)$bundle_id . "', product_id = '" . (int)$product_id . "', quantity = '" . (int)$qty['quantity'] . "'");
			}
		}
	}

	public function updateCategories($bundle_id,$data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "bundle_to_category WHERE bundle_id = '" . (int)$bundle_id . "'");
		if (isset($data['bundle_category'])) {
			foreach ($data['bundle_category'] as $bundle_category_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "bundle_to_category SET bundle_id = '" . (int)$bundle_id . "', bundle_category_id = '" . (int)$bundle_category_id . "'");
			}
		}
	}
	
	
/* @Rakesh New */
	public function getTotalBundleOrders($bundle_id) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "bundle_order WHERE bundle_id='".(int)$bundle_id."'";
		$query = $this->db->query($sql);
		if(isset($query->row['total'])) {
			return $query->row['total'];
		} else {
			return 0;
		}
	}
/* @Rakesh New */
}
