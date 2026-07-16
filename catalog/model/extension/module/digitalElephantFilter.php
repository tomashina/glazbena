<?php

class ModelExtensionModuleDigitalElephantFilter extends Model
{
//    private $DEF_settings;
    private $storageSort = null;
    private $storageAttribute = null;

    public function getMinMaxPrice($category_id)
    {
		$cache_key = $this->filterCacheKey('price', array('category_id' => (int)$category_id));
		$cached_price = $this->cache->get($cache_key);

		if ($cached_price !== false) {
			return $cached_price;
		}

        $customer_group_id = $this->config->get('config_customer_group_id');
        $shipping_country_id = isset($this->session->data['shipping_country_id']) ? $this->session->data['shipping_country_id'] : (($this->config->get('config_tax_default') == 'shipping') ? $this->config->get('config_country_id') : null);
        $shipping_zone_id = isset($this->session->data['shipping_zone_id']) ? $this->session->data['shipping_zone_id'] : (($this->config->get('config_tax_default') == 'shipping') ? $this->config->get('config_zone_id') : null);
        $payment_country_id = isset($this->session->data['payment_country_id']) ? $this->session->data['payment_country_id'] : (($this->config->get('config_tax_default') == 'payment') ? $this->config->get('config_country_id') : null);
        $payment_zone_id = isset($this->session->data['payment_zone_id']) ? $this->session->data['payment_zone_id'] : (($this->config->get('config_tax_default') == 'payment') ? $this->config->get('config_zone_id') : null);

        $conditions = array();

        if ($shipping_country_id || $shipping_zone_id) {
            $conditions[] = "tr1.based = 'shipping' AND z2gz.country_id = '" . (int)$shipping_country_id . "' AND z2gz.zone_id IN ('0', '" . (int)$shipping_zone_id . "')";
        }

        if ($payment_country_id || $payment_zone_id) {
            $conditions[] = "tr1.based = 'payment' AND z2gz.country_id = '" . (int)$payment_country_id . "' AND z2gz.zone_id IN ('0', '" . (int)$payment_zone_id . "')";
        }

        $conditions[] = "tr1.based = 'store' AND z2gz.country_id = '" . (int)$this->config->get('config_country_id') . "' AND z2gz.zone_id IN ('0', '" . (int)$this->config->get('config_zone_id') . "')";

        $sql = "SELECT
              MIN( (IF ( special IS NOT NULL, special, IF( discount IS NOT NULL, discount, price ) ) * (1 + IFNULL(percent_tax, 0)/100) + IFNULL(fixed_tax, 0)) ) AS min,
              MAX( (IF ( special IS NOT NULL, special, IF( discount IS NOT NULL, discount, price ) ) * (1 + IFNULL(percent_tax, 0)/100) + IFNULL(fixed_tax, 0) )) AS max
              FROM (
                SELECT
                  p.product_id,
                  p.price,
                  MIN(pd2.price) AS discount,
                  MIN(ps.price) AS special,
                  AVG(rating) AS total,
                  fixed_tax,
                  percent_tax FROM (
                   SELECT p.* FROM `" . DB_PREFIX . "product` AS p
                   INNER JOIN `" . DB_PREFIX . "product_to_category` AS p2c ON (p.product_id = p2c.product_id)
                   WHERE p2c.category_id IN (
                            SELECT category_id FROM " . DB_PREFIX . "category_path WHERE path_id = " . $category_id . "
                    )
                  ) AS p
                   INNER JOIN `" . DB_PREFIX . "product_to_store` AS p2s ON (p.product_id = p2s.product_id)
                   LEFT JOIN `" . DB_PREFIX . "product_discount` AS pd2 ON (pd2.product_id = p.product_id
                        AND pd2.quantity = '1'
                        AND (pd2.date_start = '0000-00-00' OR pd2.date_start < NOW())
                        AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())
                        AND pd2.customer_group_id = '" . (int)$customer_group_id . "')
                   LEFT JOIN `" . DB_PREFIX . "product_special` AS ps ON (ps.product_id = p.product_id
                        AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())
                        AND (ps.date_start = '0000-00-00' OR ps.date_start < NOW())
                        AND ps.customer_group_id = '0')
                   LEFT JOIN `" . DB_PREFIX . "review` AS r1 ON (r1.product_id = p.product_id AND r1.status = 1)
                   LEFT JOIN (
                    SELECT
                        SUM(t.rate) AS fixed_tax,
                        t.tax_class_id
                      FROM (
                       SELECT
                        DISTINCT tr1.tax_class_id,
                        rate
                       FROM `" . DB_PREFIX . "tax_rule` AS tr1
                       LEFT JOIN `" . DB_PREFIX . "tax_rate` AS tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id)
                       INNER JOIN `" . DB_PREFIX . "tax_rate_to_customer_group` AS tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id)
                       LEFT JOIN `" . DB_PREFIX . "zone_to_geo_zone` AS z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id)
                       LEFT JOIN `" . DB_PREFIX . "geo_zone` AS gz ON (tr2.geo_zone_id = gz.geo_zone_id)
                       WHERE tr2.type = 'F' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "'";


        if (count($conditions)) {
            $sql .= ' AND ((' . implode(') OR (', $conditions) . '))';
        }

        $sql .= ") AS t
            GROUP BY t.tax_class_id) AS tr1 ON (tr1.tax_class_id = p.tax_class_id)
            LEFT JOIN (
            SELECT tax_class_id,
                   SUM(rate) AS percent_tax
            FROM (
            SELECT DISTINCT tr1.tax_class_id,
                            rate
            FROM `" . DB_PREFIX . "tax_rule` AS tr1
            LEFT JOIN `" . DB_PREFIX . "tax_rate` AS tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id)
            INNER JOIN `" . DB_PREFIX . "tax_rate_to_customer_group` AS tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id)
            LEFT JOIN `" . DB_PREFIX . "zone_to_geo_zone` AS z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id)
            LEFT JOIN `" . DB_PREFIX . "geo_zone` AS gz ON (tr2.geo_zone_id = gz.geo_zone_id)
            WHERE tr2.type = 'P'
              AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "'";


        if (count($conditions)) {
            $sql .= ' AND ((' . implode(') OR (', $conditions) . '))';
        }

        $sql .= ") AS t GROUP BY t.tax_class_id) AS tr2 ON (tr2.tax_class_id = p.tax_class_id) WHERE p2s.store_id = '" . $this->config->get('config_store_id') . "' AND p.status = '1' AND p.date_available <= NOW() GROUP BY p.product_id) as min_max";

		$query = $this->db->query($sql);
		$this->cache->set($cache_key, $query->row);

		return $query->row;
    }

    public function getAttributes($DEF_settings, $data = array())
    {
		$cache_key = $this->filterCacheKey('attributes', array(
			'settings' => isset($DEF_settings['advance']['attributes']) ? $DEF_settings['advance']['attributes'] : array(),
			'data'     => $data
		));
		$cached_attributes = $this->cache->get($cache_key);

		if ($cached_attributes !== false) {
			return $cached_attributes;
		}

		if (array_key_exists('product_ids', $data)) {
			$data['product_ids'] = array_values(array_unique(array_filter(array_map('intval', (array)$data['product_ids']))));

			if (!$data['product_ids']) {
				$this->cache->set($cache_key, array());
				return array();
			}
		}

        $customer_group_id = $this->getCustomerGroup();

        $has_category = !empty($data['category_id']);

        if ($has_category) {
            // Start with the small set of products in the requested category.
            // STRAIGHT_JOIN prevents MySQL from expanding all attribute/group
            // combinations before it applies the category condition.
            $sql = "SELECT STRAIGHT_JOIN DISTINCT pa.text, a.`attribute_id`, ad.`name`, ag.attribute_group_id, agd.name as attribute_group_name" .
                " FROM `" . DB_PREFIX . "category_path` cp" .
                " INNER JOIN " . DB_PREFIX . "product_to_category p2c ON(p2c.category_id=cp.category_id)" .
                " INNER JOIN " . DB_PREFIX . "product p ON(p.product_id=p2c.product_id AND p.status='1' AND p.date_available<=NOW())" .
                " INNER JOIN " . DB_PREFIX . "product_to_store p2s ON(p2s.product_id=p.product_id AND p2s.store_id='" . (int)$this->config->get('config_store_id') . "')" .
                " INNER JOIN " . DB_PREFIX . "product_attribute pa ON(pa.product_id=p.product_id AND pa.language_id='" . (int)$this->config->get('config_language_id') . "')" .
                " INNER JOIN " . DB_PREFIX . "attribute a ON(a.attribute_id=pa.attribute_id)" .
                " INNER JOIN " . DB_PREFIX . "attribute_description ad ON(ad.attribute_id=a.attribute_id AND ad.language_id='" . (int)$this->config->get('config_language_id') . "')" .
                " INNER JOIN " . DB_PREFIX . "attribute_group ag ON(ag.attribute_group_id=a.attribute_group_id)" .
                " INNER JOIN " . DB_PREFIX . "attribute_group_description agd ON(agd.attribute_group_id=ag.attribute_group_id AND agd.language_id='" . (int)$this->config->get('config_language_id') . "')";
        } else {
            $sql = "SELECT DISTINCT pa.text, a.`attribute_id`, ad.`name`, ag.attribute_group_id, agd.name as attribute_group_name FROM `" . DB_PREFIX . "product_attribute` pa" .
                " LEFT JOIN " . DB_PREFIX . "attribute a ON(pa.attribute_id=a.`attribute_id`) " .
                " LEFT JOIN " . DB_PREFIX . "attribute_description ad ON(a.attribute_id=ad.`attribute_id`) " .
                " LEFT JOIN " . DB_PREFIX . "attribute_group ag ON(ag.attribute_group_id=a.`attribute_group_id`) " .
                " LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON(agd.attribute_group_id=ag.`attribute_group_id`) " .
                " LEFT JOIN " . DB_PREFIX . "product p ON(p.product_id=pa.`product_id`) " .
                " LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON(p.product_id=p2s.product_id) ";
        }

        if (isset($data['special']) && $data['special']) {
            $sql .= " INNER JOIN " . DB_PREFIX . "product_special ps ON (ps.product_id=p.product_id)";
        }

        if ($has_category) {
            $sql .= " WHERE cp.path_id='" . (int)$data['category_id'] . "'";
        } else {
            $sql .= " WHERE p.status='1' AND p.date_available<=NOW() AND p2s.store_id='" . (int)$this->config->get('config_store_id') . "'" .
                " AND pa.language_id='" . (int)$this->config->get('config_language_id') . "'" .
                " AND ad.language_id='" . (int)$this->config->get('config_language_id') . "'" .
                " AND agd.language_id='" . (int)$this->config->get('config_language_id') . "'";
        }

        if (isset($data['special']) && $data['special']) {
            $sql .= " AND ps.customer_group_id = '" . (int)$customer_group_id . "'" .
                " AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW( )) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW( )))";
        }

        if (isset($data['manufacturer_id']) && $data['manufacturer_id']) {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['manufacturer_id'] . "'";
        }

		if (!empty($data['product_ids'])) {
			$sql .= " AND p.product_id IN (" . implode(',', $data['product_ids']) . ")";
		}

        $sql .= " ORDER BY ";

        switch ($DEF_settings['advance']['attributes']['sort']) {
            case 'sort':
                $sql .= "a.sort_order";
                break;
            case 'name':
                $sql .= "ad.name";
                break;
            default:
                $sql .= "ag.sort_order, agd.name, a.sort_order, ad.name, pa.text";
                break;
        }


        $query = $this->db->query($sql);

        $attributes = array();

        foreach ($query->rows as $row) {
            if (!isset($attributes[$row['attribute_group_id']])) {
                $attributes[$row['attribute_group_id']] = array(
                    'name' => $row['attribute_group_name'],
                    'attribute_values' => array()
                );
            }

            if (!isset($attributes[$row['attribute_group_id']]['attribute_values'][$row['attribute_id']])) {
                $attributes[$row['attribute_group_id']]['attribute_values'][$row['attribute_id']] = array('name' => $row['name'], 'values' => array());
            }

            $row['text'] = htmlspecialchars_decode($row['text'], ENT_COMPAT);
            foreach (explode(':', $row['text']) as $text) {
                if (!in_array($text, $attributes[$row['attribute_group_id']]['attribute_values'][$row['attribute_id']]['values'])) {
                    $attributes[$row['attribute_group_id']]['attribute_values'][$row['attribute_id']]['values'][] = htmlspecialchars($text, ENT_COMPAT);

                }
            }
        }

        foreach ($attributes as $attribute_group_id => $attribute_group) {
            foreach ($attribute_group['attribute_values'] as $attribute_id => $attribute) {
                sort($attributes[$attribute_group_id]['attribute_values'][$attribute_id]['values']);
            }
        }
		$this->cache->set($cache_key, $attributes);

		return $attributes;
    }

    public function getManufacturers($DEF_settings, $data = array())
    {
        $customer_group_id = $this->getCustomerGroup();

        if (isset($data['manufacturer_id']) && $data['manufacturer_id']) {
            return array();
        }

		$cache_key = $this->filterCacheKey('manufacturers', array(
			'settings' => isset($DEF_settings['advance']['manufacturers']) ? $DEF_settings['advance']['manufacturers'] : array(),
			'data'     => $data
		));
		$cached_manufacturers = $this->cache->get($cache_key);

		if ($cached_manufacturers !== false) {
			return $cached_manufacturers;
		}

        $sql = "SELECT DISTINCT m.`manufacturer_id`, m.`name`, m.`image` FROM `" . DB_PREFIX . "manufacturer` m" .
            " LEFT JOIN " . DB_PREFIX . "product p ON(p.manufacturer_id=m.`manufacturer_id`) ";
        if (isset($data['category_id']) && $data['category_id']) {
            $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON(p.product_id=p2c.product_id) ";
        }
        if (isset($data['special']) && $data['special']) {
            $sql .= " LEFT JOIN " . DB_PREFIX . "product_special ps ON ( ps.product_id = p.product_id )";
        }
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON(p.product_id=p2s.product_id) " .
            " WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = " . (int)$this->config->get('config_store_id');
        if (isset($data['category_id']) && $data['category_id']) {
            $sql .= " AND p2c.category_id IN
                    (SELECT category_id FROM " . DB_PREFIX . "category_path WHERE path_id = " . $data['category_id'] . ")";
        }
        if (isset($data['special']) && $data['special']) {
            $sql .= " AND ps.customer_group_id = '" . (int)$customer_group_id . "'" .
                " AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW( )) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW( )))";
        }

        $sql .= " ORDER BY ";

        switch ($DEF_settings['advance']['manufacturers']['sort']) {
            case 'sort':
                $sql .= "m.sort_order";
                break;
            case 'name':
                $sql .= "m.name";
                break;
            default:
                $sql .= "m.sort_order, m.name";
                break;
        }

		$query = $this->db->query($sql);
		$this->cache->set($cache_key, $query->rows);

		return $query->rows;
    }


    public function getSubCategories($category_id, $data = array())
    {
		$cache_key = $this->filterCacheKey('subcategories', array(
			'category_id' => (int)$category_id,
			'settings'    => isset($data['advance']['categories']) ? $data['advance']['categories'] : array()
		));
		$cached_categories = $this->cache->get($cache_key);

		if ($cached_categories !== false) {
			return $cached_categories;
		}

        $sql = "SELECT DISTINCT cd.category_id, cd.name, c.image FROM `" . DB_PREFIX . "category` c" .
            " LEFT JOIN " . DB_PREFIX . "category_description cd ON(cd.category_id=c.category_id) " .
            " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON(c.category_id=p2c.category_id) " .
            " LEFT JOIN " . DB_PREFIX . "product p ON(p.product_id=p2c.`product_id`) " .
            " LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON(p.product_id=p2s.product_id) " .
            " WHERE c.status=1 AND c.parent_id = '" . (int)$category_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id =" . (int)$this->config->get('config_store_id');


        $sql .= " GROUP BY cd.category_id ORDER BY ";

        switch ($data['advance']['categories']['sort']) {
            case 'sort':
                $sql .= "c.sort_order";
                break;
            case 'name':
                $sql .= "cd.name";
                break;
            default:
                $sql .= "c.sort_order, cd.name";
                break;
        }

		$query = $this->db->query($sql);
		$this->cache->set($cache_key, $query->rows);

		return $query->rows;
    }

    public function getOptions($DEF_settings, $data = array())
    {
		$cache_key = $this->filterCacheKey('options', array(
			'settings' => isset($DEF_settings['advance']['options']) ? $DEF_settings['advance']['options'] : array(),
			'data'     => $data
		));
		$cached_options = $this->cache->get($cache_key);

		if ($cached_options !== false) {
			return $cached_options;
		}

        $customer_group_id = $this->getCustomerGroup();
        $sql = "SELECT DISTINCT ovd.option_value_id, ovd.*, od.name as 'option_name', ov.image FROM `" . DB_PREFIX . "option_value_description` ovd
    LEFT JOIN " . DB_PREFIX . "option_value ov ON(ovd.option_value_id=ov.option_value_id)
    LEFT JOIN " . DB_PREFIX . "option_description od ON(ov.option_id=od.option_id)
    LEFT JOIN `" . DB_PREFIX . "option` o ON(ov.option_id=o.option_id)
    LEFT JOIN " . DB_PREFIX . "product_option_value pov ON(ovd.`option_value_id`=pov.`option_value_id`)
    LEFT JOIN " . DB_PREFIX . "product p ON(pov.product_id = p.product_id) ";
        if (isset($data['category_id']) && $data['category_id']) {
            $sql .= "LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON(p.product_id = p2c.product_id) ";
        }
        if (isset($data['special']) && $data['special']) {
            $sql .= " LEFT JOIN " . DB_PREFIX . "product_special ps ON ( ps.product_id = p.product_id )";
        }
        $sql .= "LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON(p.product_id=p2s.product_id)
    WHERE ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'  AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id =" . (int)$this->config->get('config_store_id');

        if (isset($data['category_id']) && $data['category_id']) {
            $sql .= " AND p2c.category_id IN
                    (SELECT category_id FROM " . DB_PREFIX . "category_path WHERE path_id = " . $data['category_id'] . ")";
        }

        if (isset($data['special']) && $data['special']) {
            $sql .= " AND ps.customer_group_id = '" . (int)$customer_group_id . "'" .
                " AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW( )) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW( )))";
        }
        if (isset($data['manufacturer_id']) && $data['manufacturer_id']) {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['manufacturer_id'] . "'";
        }
        $sql .= " ORDER BY ";

        switch ($DEF_settings['advance']['options']['sort']) {
            case 'sort':
                $sql .= "o.sort_order, ov.sort_order, ovd.option_id";
                break;
            case 'name':
                $sql .= "ovd.name, o.sort_order, ov.sort_order, ovd.option_id";
                break;
            default:
                $sql .= "o.sort_order, ov.sort_order, ovd.option_id";
                break;
        }

        $query = $this->db->query($sql);
        $options = array();
        foreach ($query->rows as $row) {
            if (!isset($options[$row['option_id']])) {
                $options[$row['option_id']] = array('option_id' => $row['option_id'],
                    'name' => $row['option_name'],
                    'option_values' => array());
            }

            $options[$row['option_id']]['option_values'][] = array('option_value_id' => $row['option_value_id'], 'name' => $row['name'], 'image' => $row['image']);
        }
		$this->cache->set($cache_key, $options);

		return $options;
    }


    public function getTotalProducts($data)
    {
		$cache_key = $this->filterCacheKey('total', $data);
		$cached_total = $this->cache->get($cache_key);

		if ($cached_total !== false) {
			return (int)$cached_total;
		}

        $sql = $this->generalizeProducts($data);

        $query = $this->db->query($sql);

        if ($generalizeAttribute = $this->generalizeAttribute($data, $query)) {
            $query = $generalizeAttribute;
        }

        $total = count($query->rows);

		$this->cache->set($cache_key, $total);

		return $total;
    }

	private function filterCacheKey($type, array $data) {
		$context = array(
			'store_id'            => (int)$this->config->get('config_store_id'),
			'language_id'         => (int)$this->config->get('config_language_id'),
			'customer_group_id'   => (int)$this->getCustomerGroup(),
			'currency'            => isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency'),
			'shipping_country_id' => isset($this->session->data['shipping_country_id']) ? (int)$this->session->data['shipping_country_id'] : 0,
			'shipping_zone_id'    => isset($this->session->data['shipping_zone_id']) ? (int)$this->session->data['shipping_zone_id'] : 0,
			'payment_country_id'  => isset($this->session->data['payment_country_id']) ? (int)$this->session->data['payment_country_id'] : 0,
			'payment_zone_id'     => isset($this->session->data['payment_zone_id']) ? (int)$this->session->data['payment_zone_id'] : 0,
			'time_bucket'         => (int)floor(time() / 300),
			'data'                => $this->normalizeFilterCacheData($data)
		);

		return 'product.filter.v1.' . $type . '.' . hash('sha256', serialize($context));
	}

	private function normalizeFilterCacheData($value) {
		if (!is_array($value)) {
			return $value;
		}

		foreach ($value as $key => $item) {
			$value[$key] = $this->normalizeFilterCacheData($item);
		}

		if (array_keys($value) !== array_keys(array_keys($value))) {
			ksort($value);
		}

		return $value;
	}

    private function getCustomerGroup()
    {
        if ($this->customer->isLogged()) {
            $customer_group_id = $this->customer->getGroupId();
            return $customer_group_id;
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
            return $customer_group_id;
        }
    }

    public function getProducts($data = array())
    {
		$cache_key = $this->filterCacheKey('products', $data);
		$cached_products = $this->cache->get($cache_key);

		if ($cached_products !== false) {
			return $cached_products;
		}

        $sql = $this->generalizeProducts($data);

        $product_data = array();

        $query = $this->db->query($sql);

        if ($generalizeAttribute = $this->generalizeAttribute($data, $query)) {
            $query = $generalizeAttribute;
        }

		$product_id_data = $data;
		unset($product_id_data['start'], $product_id_data['limit']);
		$this->cache->set(
			$this->filterCacheKey('product_ids', $product_id_data),
			array_values(array_unique(array_map('intval', array_column($query->rows, 'product_id'))))
		);

        //load model catalog_product
        $this->load->model('catalog/product');

        $products = $query->rows;

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $products = array_splice($products, $data['start'], $data['limit']);
        }

		$product_ids = array_column($products, 'product_id');

		if ($this->config->get('module_boost_special_manager_include') == '1') {
			foreach ($product_ids as $product_id) {
				$product_data[$product_id] = $this->model_catalog_product->getProduct($product_id);
			}
		} else {
			$loaded_products = $this->model_catalog_product->getProducts(array('filter_product_ids' => $product_ids));

			foreach ($product_ids as $product_id) {
				if (isset($loaded_products[$product_id])) {
					$product_data[$product_id] = $loaded_products[$product_id];
				}
			}
		}

		$this->cache->set($cache_key, $product_data);

		return $product_data;
    }

	public function getProductIds($data = array())
	{
		unset($data['start'], $data['limit']);

		$cache_key = $this->filterCacheKey('product_ids', $data);
		$cached_product_ids = $this->cache->get($cache_key);

		if ($cached_product_ids !== false) {
			return array_values(array_map('intval', $cached_product_ids));
		}

		$query = $this->db->query($this->generalizeProducts($data));

		if ($generalizeAttribute = $this->generalizeAttribute($data, $query)) {
			$query = $generalizeAttribute;
		}

		$product_ids = array_values(array_unique(array_map('intval', array_column($query->rows, 'product_id'))));
		$this->cache->set($cache_key, $product_ids);

		return $product_ids;
	}

    public function getOptionValueDescriptions($option_id)
    {
        $option_value_description = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_value_description WHERE option_value_id = '" . (int)$option_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

        return $option_value_description->row;
    }

    private function generalizeAttribute($data, $query)
    {
//        if ($this->storageAttribute == null) {

            $this->storageAttribute = false;
            //ATTRIBUTES
            if ($data['attributes']) {

                $without_attr_product_ids = [];
                foreach ($query->rows as $row) {
                    $without_attr_product_ids[] = "'" . $row['product_id'] . "'";
                }

                if ($without_attr_product_ids) {
                    $sql = "SELECT
                          DISTINCT(pa.product_id),
                          p.price,
                          (SELECT AVG(rating) AS total
                               FROM " . DB_PREFIX . "review r1
                               WHERE r1.product_id = p.product_id
                                 AND r1.status = '1'
                               GROUP BY r1.product_id) AS rating,
                          (SELECT price
                               FROM " . DB_PREFIX . "product_discount pd2
                               WHERE pd2.product_id = pa.product_id
                                 AND pd2.customer_group_id = '1'
                                 AND pd2.quantity = '1'
                                 AND ((pd2.date_start = '0000-00-00'
                                       OR pd2.date_start < NOW())
                                      AND (pd2.date_end = '0000-00-00'
                                           OR pd2.date_end > NOW()))
                               ORDER BY pd2.priority ASC, pd2.price ASC
                               LIMIT 1) AS discount,
                               (SELECT price
                                   FROM " . DB_PREFIX . "product_special ps
                                   WHERE ps.product_id = pa.product_id
                                     AND ps.customer_group_id = '1'
                                     AND ((ps.date_start = '0000-00-00'
                                           OR ps.date_start < NOW())
                                          AND (ps.date_end = '0000-00-00'
                                               OR ps.date_end > NOW()))
                                   ORDER BY ps.priority ASC, ps.price ASC
                                   LIMIT 1) AS special
                        FROM " . DB_PREFIX . "product_attribute pa";

					$attribute_ids = array();
					foreach ($data['attributes'] as $key => $attribute_values) {
						$attribute_id = (int)$key;

						if ($attribute_id < 1 || !is_array($attribute_values)) {
							continue;
						}

						foreach ($attribute_values as $attribute_value) {
							if ($attribute_value !== '') {
								$attribute_ids[$attribute_id][] = "'" . $this->db->escape((string)$attribute_value) . "'";
							}
						}

						if (!empty($attribute_ids[$attribute_id])) {
							$sql .= " LEFT JOIN " . DB_PREFIX . "product_attribute pa" . $attribute_id
								. " ON (pa.product_id = pa" . $attribute_id . ".product_id"
								. " AND pa" . $attribute_id . ".attribute_id = '" . $attribute_id . "'"
								. " AND pa" . $attribute_id . ".language_id = '" . (int)$this->config->get('config_language_id') . "')";
						}
					}

                    $sql .= " LEFT JOIN (SELECT price, product_id FROM " . DB_PREFIX . "product_discount) AS pd2 ON (pd2.product_id = pa.product_id)
                    LEFT JOIN (SELECT price, product_id FROM " . DB_PREFIX . "product_special) AS ps ON (ps.product_id = pa.product_id)
                    LEFT JOIN (SELECT price, sort_order, model, product_id FROM " . DB_PREFIX . "product) AS p ON (p.product_id = pa.product_id)
                    LEFT JOIN (SELECT name, product_id FROM " . DB_PREFIX . "product_description) AS pd ON (pd.product_id = pa.product_id)";

                    $sql .= " WHERE pa.product_id IN (" . implode(',', $without_attr_product_ids) . ")";

                    if ($attribute_ids) {
						foreach ($attribute_ids as $attribute_id => $ids) {
							$sql .= " AND pa" . $attribute_id . ".text IN (" . implode(',', $ids) . ")";
						}
                    }

                    $sql .= $this->generalizeSort($data);

                    $query = $this->db->query($sql);


                    $this->storageAttribute = $query;
                }
            }
//        }
        return $this->storageAttribute;
    }

    private function generalizeProducts($data)
    {
    $this->db->query("SET SQL_BIG_SELECTS=1");

        $sql = "SELECT "
            . "p.product_id, "
            . "(SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review "
            . "r1 WHERE r1.product_id = p.product_id "
            . "AND r1.status = '1' "
            . "GROUP BY r1.product_id) AS rating, "
            . "(SELECT price FROM " . DB_PREFIX . "product_discount pd2 "
            . "WHERE pd2.product_id = p.product_id "
            . "AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' "
            . "AND pd2.quantity = '1' "
            . "AND ((pd2.date_start = '0000-00-00' "
            . "OR pd2.date_start < NOW()) "
            . "AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) "
            . "ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, "
            . "(SELECT price FROM " . DB_PREFIX . "product_special ps "
            . "WHERE ps.product_id = p.product_id "
            . "AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' "
            . "AND ((ps.date_start = '0000-00-00' "
            . "OR ps.date_start < NOW()) "
            . "AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) "
            . "ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

        if (!empty($data['filter_category_id'])) {

            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        //PRICE JOIN BEGIN
        if ($data['price'] && $data['price']['min'] && $data['price']['max']) {

            $customer_group_id = $this->config->get('config_customer_group_id');
            $shipping_country_id = isset($this->session->data['shipping_country_id']) ? $this->session->data['shipping_country_id'] : (($this->config->get('config_tax_default') == 'shipping') ? $this->config->get('config_country_id') : null);
            $shipping_zone_id = isset($this->session->data['shipping_zone_id']) ? $this->session->data['shipping_zone_id'] : (($this->config->get('config_tax_default') == 'shipping') ? $this->config->get('config_zone_id') : null);
            $payment_country_id = isset($this->session->data['payment_country_id']) ? $this->session->data['payment_country_id'] : (($this->config->get('config_tax_default') == 'payment') ? $this->config->get('config_country_id') : null);
            $payment_zone_id = isset($this->session->data['payment_zone_id']) ? $this->session->data['payment_zone_id'] : (($this->config->get('config_tax_default') == 'payment') ? $this->config->get('config_zone_id') : null);

            $conditions = array();

            if ($shipping_country_id || $shipping_zone_id) {
                $conditions[] = "tr1.based = 'shipping' AND z2gz.country_id = '" . (int)$shipping_country_id . "' AND z2gz.zone_id IN ('0', '" . (int)$shipping_zone_id . "')";
            }

            if ($payment_country_id || $payment_zone_id) {
                $conditions[] = "tr1.based = 'payment' AND z2gz.country_id = '" . (int)$payment_country_id . "' AND z2gz.zone_id IN ('0', '" . (int)$payment_zone_id . "')";
            }

            $conditions[] = "tr1.based = 'store' AND z2gz.country_id = '" . (int)$this->config->get('config_country_id') . "' AND z2gz.zone_id IN ('0', '" . (int)$this->config->get('config_zone_id') . "')";

            $sql .= " LEFT JOIN (SELECT SUM(t.rate) AS fixed_tax, t.tax_class_id FROM (SELECT DISTINCT tr1.tax_class_id, rate FROM `" . DB_PREFIX . "tax_rule` AS tr1 LEFT JOIN `" . DB_PREFIX . "tax_rate` AS tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN `" . DB_PREFIX . "tax_rate_to_customer_group` AS tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN `" . DB_PREFIX . "zone_to_geo_zone` AS z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN `" . DB_PREFIX . "geo_zone` AS gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr2.type = 'F' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "'";


            if (count($conditions)) {
                $sql .= ' AND ((' . implode(') OR (', $conditions) . '))';
            }

            $sql .= ") AS t GROUP BY t.tax_class_id) AS tr1 ON (tr1.tax_class_id = p.tax_class_id) LEFT JOIN (SELECT tax_class_id, SUM(rate) AS percent_tax FROM (SELECT DISTINCT tr1.tax_class_id, rate FROM `" . DB_PREFIX . "tax_rule` AS tr1 LEFT JOIN `" . DB_PREFIX . "tax_rate` AS tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN `" . DB_PREFIX . "tax_rate_to_customer_group` AS tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN `" . DB_PREFIX . "zone_to_geo_zone` AS z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN `" . DB_PREFIX . "geo_zone` AS gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr2.type = 'P' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "'";


            if (count($conditions)) {
                $sql .= ' AND ((' . implode(') OR (', $conditions) . '))';
            }

            $sql .= ") AS t GROUP BY t.tax_class_id) AS tr2 ON (tr2.tax_class_id = p.tax_class_id)";

            $sql .= " LEFT JOIN " . DB_PREFIX . "product_discount AS pd2 ON (pd2.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_special AS ps ON (ps.product_id = p.product_id)";
        }
        //PRICE JOIN END


        //OPTIONS JOIN BEGIN
        if ($data['options']) {
            foreach ($data['options'] as $key => $option_group) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_option_value pov" . $key . " ON (p.product_id = pov" . $key . ".product_id)";
            }
        }
        //OPTIONS JOIN END


        if (isset($data['filter_store_id'])) {
            $store_id = $data['filter_store_id'];
        } else {
            $store_id = $this->config->get('config_store_id');
        }

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$store_id . "'";

        //OPTIONS WHERE BEGIN
        if ($data['options']) {
            foreach ($data['options'] as $key => $option_group) {
                $option_ids = [];
                foreach ($option_group as $option_value_id) {
                    if ($option_value_id) {
                        $option_ids[] = (int)$option_value_id;
                    }
                }

                if ($option_ids) {
                    $sql .= " AND pov" . $key . ".option_value_id IN (" . implode(',', $option_ids) . ")";
                }
            }
        }
        //OPTIONS WHERE END

        //CATEGORIES WHERE BEGIN
        if (!empty($data['filter_category_id'])) {

            if ($data['sub_categories']) {
                $sql .= " AND cp.path_id IN (";
                foreach ($data['sub_categories'] as $sub_category) {
                    $sql .= (int)$sub_category . ",";
                }

                //cut last ',';
                $sql = substr($sql, 0, -1);

                $sql .= ")";
            } else if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }
        //CATEGORIES WHERE END


        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            $sql .= " AND (";

            if (!empty($data['filter_name'])) {
                $implode = array();

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                foreach ($words as $word) {
                    $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= " " . implode(" AND ", $implode) . "";
                }

                if (!empty($data['filter_description'])) {
                    $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                }
            }

            if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                $sql .= " OR ";
            }

            if (!empty($data['filter_tag'])) {
                $sql .= "pd.tag LIKE '%" . $this->db->escape($data['filter_tag']) . "%'";
            }

            if (!empty($data['filter_name'])) {
                $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
            }

            $sql .= ")";
        }


        if (isset($data['filter_sku']) && !empty($data['filter_sku'])) {

            $sql .= " AND (";
            if (isset($data['filter_sku']) && !empty($data['filter_sku'])) {

                $sql .= " p.sku = '" . $data['filter_sku'] . "'";
            }

            if (isset($data['filter_sku_sex']) && !empty($data['filter_sku_sex'])) {
                $sql .= " AND p.sku_sex = '" . $data['filter_sku_sex'] . "'";
            }

            if (isset($data['filter_sku_type']) && !empty($data['filter_sku_type'])) {
                $sql .= " AND p.sku_type = '" . $data['filter_sku_type'] . "'";
            }

            $sql .= ")";
        }


        //MANUFACTURER WHERE BEGIN
        if ($data['manufacturers']) {

            $sql .= " AND p.manufacturer_id  IN (";

            foreach ($data['manufacturers'] as $manufacturers) {
                foreach ($manufacturers as $manufacturer) {
                    $sql .= (int)$manufacturer . ",";
                }
            }

            //cut last ',';
            $sql = substr($sql, 0, -1);
            $sql .= ")";
        }
        //MANUFACTURER WHERE BEGIN


        //PRICE WHERE BEGIN
        if (!empty($data['price']['min']) && !empty($data['price']['max'])) {
            $price_min = $this->currency->convert($data['price']['min'], $this->session->data['currency'], $this->config->get('config_currency'));
            $price_max = $this->currency->convert($data['price']['max'], $this->session->data['currency'], $this->config->get('config_currency'));

            $sql .= " AND (IF(ps.price IS NOT NULL, ps.price, IF(pd2.price IS NOT NULL, pd2.price, p.price)) * (1 + IFNULL(percent_tax, 0)/100) + IFNULL(fixed_tax, 0)) >= '" . $this->db->escape($price_min) . "'";
            $sql .= " AND (IF(ps.price IS NOT NULL, ps.price, IF(pd2.price IS NOT NULL, pd2.price, p.price)) * (1 + IFNULL(percent_tax, 0)/100) + IFNULL(fixed_tax, 0)) <= '" . $this->db->escape($price_max) . "'";
        }

        //PRICE WHERE END

        $sql .= $this->generalizeSort($data);

        return $sql;
    }

    private function generalizeSort($data)
    {
        if ($this->storageSort == null) {
            $sql = '';

            $sql .= " GROUP BY p.product_id";

            $sort_data = array(
                'pd.name',
                'p.model',
                'p.quantity',
                'p.price',
                'rating',
                'p.sort_order',
                'p.date_added'
            );

            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                    $sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
                } elseif ($data['sort'] == 'p.price') {
                    $sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
                } else {
                    $sql .= " ORDER BY " . $data['sort'];
                }
            } else {
                $sql .= " ORDER BY p.sort_order";
            }

            if (isset($data['order']) && ($data['order'] == 'DESC')) {
                $sql .= " DESC, LCASE(pd.name) DESC";
            } else {
                $sql .= " ASC, LCASE(pd.name) ASC";
            }

            $this->storageSort = $sql;
        }

        return $this->storageSort;
    }

    public function getAllCategoryIds() {
        $query = $this->db->query("SELECT DISTINCT category_id FROM " . DB_PREFIX . "category WHERE status = '1' ORDER BY sort_order");
        $output = [];

        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $output[] = $row['category_id'];
            }
        }
        return $output;
    }
}
