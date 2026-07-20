<?php
class ModelCatalogProduct extends Model {
	const DISCONTINUED_STOCK_STATUS_ID = 9;

    public function updateViewed($product_id) {
        // Umjesto updatea na oc_product, piši u pomoćnu tablicu (brže, bez locka)
        $this->db->query("INSERT INTO " . DB_PREFIX . "product_viewed (product_id, viewed)
                          VALUES ('" . (int)$product_id . "', 1)
                          ON DUPLICATE KEY UPDATE viewed = viewed + 1");
    }

    public function getProduct($product_id) {
        $query = $this->db->query("
            SELECT DISTINCT
                p.*,
                pd.name AS name,
                pd.description AS description,
                pd.meta_title AS meta_title,
                pd.meta_description AS meta_description,
                pd.meta_keyword AS meta_keyword,
                pd.tag AS tag,
                p.image,
                m.name AS manufacturer,
                (
                    SELECT price FROM " . DB_PREFIX . "product_discount pd2
                    WHERE pd2.product_id = p.product_id
                      AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                      AND pd2.quantity = '1'
                      AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW())
                      AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW()))
                    ORDER BY pd2.priority ASC, pd2.price ASC
                    LIMIT 1
                ) AS discount,
                (
                    SELECT price FROM " . DB_PREFIX . "product_special ps
                    WHERE ps.product_id = p.product_id
                      AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                      AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW())
                      AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
                    ORDER BY ps.priority ASC, ps.price ASC
                    LIMIT 1
                ) AS special,
                (
                    SELECT points FROM " . DB_PREFIX . "product_reward pr
                    WHERE pr.product_id = p.product_id
                      AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                ) AS reward,
                (
                    SELECT ss.name FROM " . DB_PREFIX . "stock_status ss
                    WHERE ss.stock_status_id = p.stock_status_id
                      AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "'
                ) AS stock_status,
                (
                    SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd
                    WHERE p.weight_class_id = wcd.weight_class_id
                      AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "'
                ) AS weight_class,
                (
                    SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd
                    WHERE p.length_class_id = lcd.length_class_id
                      AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "'
                ) AS length_class,
                (
                    SELECT AVG(rating) AS total
                    FROM " . DB_PREFIX . "review r1
                    WHERE r1.product_id = p.product_id AND r1.status = '1'
                    GROUP BY r1.product_id
                ) AS rating,
                (
                    SELECT COUNT(*) AS total
                    FROM " . DB_PREFIX . "review r2
                    WHERE r2.product_id = p.product_id AND r2.status = '1'
                    GROUP BY r2.product_id
                ) AS reviews
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
            LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
            LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
            WHERE p.product_id = '" . (int)$product_id . "'
              AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
              AND p.status = '1'
              AND p.date_available <= NOW()
              AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
        ");

        if ($query->num_rows) {
            return array(
                'product_id'       => $query->row['product_id'],
                'name'             => $query->row['name'],
                'description'      => $query->row['description'],
                'meta_title'       => $query->row['meta_title'],
                'meta_description' => $query->row['meta_description'],
                'meta_keyword'     => $query->row['meta_keyword'],
                'tag'              => $query->row['tag'],
                'model'            => $query->row['model'],
                'sku'              => $query->row['sku'],
                'upc'              => $query->row['upc'],
                'ean'              => $query->row['ean'],
                'jan'              => $query->row['jan'],
                'isbn'             => $query->row['isbn'],
                'mpn'              => $query->row['mpn'],
                'location'         => $query->row['location'],
                'quantity'         => $query->row['quantity'],
                'stock_status'     => $query->row['stock_status'],
                'stock_status_id'  => $query->row['stock_status_id'],
                'image'            => $query->row['image'],
                'manufacturer_id'  => $query->row['manufacturer_id'],
                'manufacturer'     => $query->row['manufacturer'],
                'price'            => ($query->row['discount'] ? $query->row['discount'] : $query->row['price']),
                'price_last_30'    => $this->getProductLowestPrice((int)$product_id),
                'special'          => $query->row['special'],
                'reward'           => $query->row['reward'],
                'points'           => $query->row['points'],
                'tax_class_id'     => $query->row['tax_class_id'],
                'date_available'   => $query->row['date_available'],
                'weight'           => $query->row['weight'],
                'weight_class_id'  => $query->row['weight_class_id'],
                'length'           => $query->row['length'],
                'width'            => $query->row['width'],
                'height'           => $query->row['height'],
                'length_class_id'  => $query->row['length_class_id'],
                'subtract'         => $query->row['subtract'],
                'rating'           => round((float)$query->row['rating'], 2),
                'reviews'          => $query->row['reviews'] ? (int)$query->row['reviews'] : 0,
                'minimum'          => $query->row['minimum'],
                'sort_order'       => $query->row['sort_order'],
                'status'           => $query->row['status'],
                'date_added'       => $query->row['date_added'],
                'date_modified'    => $query->row['date_modified'],
                'viewed'           => $query->row['viewed']
            );
        } else {
            return false;
        }
    }

    /**
     * Return products that belong to the same configured variant group.
     *
     * The variants extension used to pass the identifier through the normal
     * catalogue name search, which performs several leading-wildcard LIKE
     * checks. Keep the same result shape, but use the exact product field.
     */
    public function getProductsByVariant($field, $value) {
        $allowed_fields = array('model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn');

        if (!in_array($field, $allowed_fields, true) || $value === '') {
            return array();
        }

        return $this->getProducts(array(
            'filter_variant_field' => $field,
            'filter_variant_value' => $value
        ));
    }

	/**
	 * Preload all configured variants needed by one product-listing request.
	 *
	 * The product variant extension asks for variants once per displayed product.
	 * Loading all group values in one query preserves those lookups while avoiding
	 * an N+1 query for category and filtered result pages.
	 */
	public function primeConfiguredVariants(array $products) {
		$registry_key = 'catalog_product_configured_variants';
		$configured_variant_products = array();
		$this->registry->set($registry_key, array('primed' => false, 'products' => array()));

		$settings = $this->config->get('product_variant_setting');
		$allowed_fields = array('model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn');

		if (!$this->config->get('product_variant_setting_status') || !is_array($settings) || empty($settings['catalog']) || empty($settings['type']) || $settings['type'] === 'custom' || !in_array($settings['type'], $allowed_fields, true)) {
			return false;
		}

		$field = $settings['type'];
		$values = array();

		foreach ($products as $product) {
			if (!isset($product[$field]) || $product[$field] === '') {
				continue;
			}

			$value = (string)$product[$field];
			$key = $this->variantCacheKey($value);
			$values[$key] = $value;
			$configured_variant_products[$key] = array();
		}

		if (!$values) {
			$this->registry->set($registry_key, array('primed' => true, 'products' => $configured_variant_products));
			return true;
		}

		$variants = $this->getProducts(array(
			'filter_variant_field'  => $field,
			'filter_variant_values' => array_values($values)
		));

		foreach ($variants as $variant) {
			if (!isset($variant[$field]) || $variant[$field] === '') {
				continue;
			}

			$key = $this->variantCacheKey($variant[$field]);

			if (array_key_exists($key, $configured_variant_products)) {
				$configured_variant_products[$key][] = $variant;
			}
		}

		$this->registry->set($registry_key, array('primed' => true, 'products' => $configured_variant_products));

		return true;
	}

    public function getProductsByConfiguredVariant($value) {
        $settings = $this->config->get('product_variant_setting');
        $field = is_array($settings) && !empty($settings['type']) ? $settings['type'] : '';
		$key = $this->variantCacheKey($value);
		$registry_key = 'catalog_product_configured_variants';
		$variant_cache = $this->registry->has($registry_key) ? $this->registry->get($registry_key) : array();

		if (!empty($variant_cache['primed']) && isset($variant_cache['products']) && array_key_exists($key, $variant_cache['products'])) {
			return $variant_cache['products'][$key];
		}

        return $this->getProductsByVariant($field, $value);
    }

	private function variantCacheKey($value) {
		return strtolower((string)$value);
	}

	private function productQueryCacheKey($type, array $data) {
		$context = array(
			'store_id'          => (int)$this->config->get('config_store_id'),
			'language_id'       => (int)$this->config->get('config_language_id'),
			'customer_group_id' => (int)$this->config->get('config_customer_group_id'),
			'time_bucket'       => (int)floor(time() / 300),
			'data'              => $this->normalizeProductQueryCacheData($data)
		);

		return 'product.query.v2.' . $type . '.' . hash('sha256', serialize($context));
	}

	private function normalizeProductQueryCacheData($value) {
		if (!is_array($value)) {
			return $value;
		}

		foreach ($value as $key => $item) {
			$value[$key] = $this->normalizeProductQueryCacheData($item);
		}

		if (array_keys($value) !== array_keys(array_keys($value))) {
			ksort($value);
		}

		return $value;
	}

	private function shouldExcludeDiscontinuedProducts(array $data) {
		return !empty($data['filter_category_id'])
			&& empty($data['filter_name'])
			&& empty($data['filter_tag']);
	}

   // ======================= getProducts() =======================
public function getProducts($data = array()) {
	$cache_key = $this->productQueryCacheKey('list', $data);
	$cached_products = $this->cache->get($cache_key);

	if ($cached_products !== false) {
		return $cached_products;
	}

    $sql = "
        SELECT
            p.*,
            pd.name,
            pd.description,
            pd.meta_title,
            pd.meta_description,
            pd.meta_keyword,
            pd.tag,
            m.name AS manufacturer,
            ss.name AS stock_status,
            (
                SELECT AVG(rating) AS total
                FROM " . DB_PREFIX . "review r1
                WHERE r1.product_id = p.product_id AND r1.status = '1'
                GROUP BY r1.product_id
            ) AS rating,
            (
                SELECT COUNT(*)
                FROM " . DB_PREFIX . "review r2
                WHERE r2.product_id = p.product_id AND r2.status = '1'
            ) AS reviews,
            (
                SELECT price FROM " . DB_PREFIX . "product_discount pd2
                WHERE pd2.product_id = p.product_id
                  AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                  AND pd2.quantity = '1'
                  AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW())
                  AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW()))
                ORDER BY pd2.priority ASC, pd2.price ASC
                LIMIT 1
            ) AS discount,
            (
                SELECT price FROM " . DB_PREFIX . "product_special ps
                WHERE ps.product_id = p.product_id
                  AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                  AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW())
                  AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
                ORDER BY ps.priority ASC, ps.price ASC
                LIMIT 1
            ) AS special,
            (
                SELECT ps30.price
                FROM " . DB_PREFIX . "product_special ps30
                WHERE ps30.product_id = p.product_id
                  AND ((ps30.date_start = '0000-00-00' OR ps30.date_start < DATE_SUB(CURDATE(), INTERVAL 30 DAY))
                  AND (ps30.date_end = '0000-00-00' OR ps30.date_end > DATE_SUB(CURDATE(), INTERVAL 30 DAY)))
                ORDER BY ps30.price ASC
                LIMIT 1
            ) AS price_last_30
    ";

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

    $sql .= "
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
        LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
        LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
        LEFT JOIN " . DB_PREFIX . "stock_status ss ON (p.stock_status_id = ss.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "')
        WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
          AND p.status = '1'
          AND p.date_available <= NOW()
          AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
    ";

	// Discontinued products remain searchable and directly accessible, but are
	// omitted from normal category listings.
	if ($this->shouldExcludeDiscontinuedProducts($data)) {
		$sql .= " AND p.stock_status_id <> '" . self::DISCONTINUED_STOCK_STATUS_ID . "'";
	}

    // proizvođač
    if (!empty($data['filter_manufacturer_id'])) {
        $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
    }

    // kategorija + filteri
    if (!empty($data['filter_category_id'])) {
        if (!empty($data['filter_sub_category'])) {
            $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
        } else {
            $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
        }

        if (!empty($data['filter_filter'])) {
            $filters = explode(',', $data['filter_filter']);
            $implode = array();
            foreach ($filters as $filter_id) {
                $implode[] = (int)$filter_id;
            }
            if ($implode) {
                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }
    }

    if (!empty($data['filter_variant_field'])) {
        $allowed_variant_fields = array('model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn');

        if (in_array($data['filter_variant_field'], $allowed_variant_fields, true)) {
			if (array_key_exists('filter_variant_values', $data)) {
				$variant_values = array();

				if (is_array($data['filter_variant_values'])) {
					foreach ($data['filter_variant_values'] as $variant_value) {
						if ($variant_value !== '') {
							$variant_values[] = "'" . $this->db->escape((string)$variant_value) . "'";
						}
					}
				}

				$variant_values = array_values(array_unique($variant_values));

				if (!$variant_values) {
					return array();
				}

				$sql .= " AND p.`" . $data['filter_variant_field'] . "` IN (" . implode(',', $variant_values) . ")";
			} elseif (isset($data['filter_variant_value'])) {
				$sql .= " AND p.`" . $data['filter_variant_field'] . "` = '" . $this->db->escape($data['filter_variant_value']) . "'";
			}
        }
    }

	if (array_key_exists('filter_product_ids', $data)) {
		$product_ids = is_array($data['filter_product_ids']) ? array_values(array_unique(array_filter(array_map('intval', $data['filter_product_ids'])))) : array();

		if (!$product_ids) {
			return array();
		}

		$sql .= " AND p.product_id IN (" . implode(',', $product_ids) . ")";
	}

    // === SEARCH BUILDER (tvrdo) ===
    $hasSearch = (!empty($data['filter_name']) || !empty($data['filter_tag']));
    if ($hasSearch) {
        $where = array();

        // normalizacija
        $name_raw = isset($data['filter_name']) ? trim(preg_replace('/\s+/', ' ', $data['filter_name'])) : '';
        $tag_raw  = isset($data['filter_tag'])  ? trim(preg_replace('/\s+/', ' ', $data['filter_tag']))  : '';

        // NAME: riječi >=2 znaka (AND), LIKE '%...%'
        if ($name_raw !== '') {
            $words = array_filter(explode(' ', $name_raw), function($w) {
                return utf8_strlen($w) >= 2;
            });
            $nameParts = array();
            foreach ($words as $word) {
                $nameParts[] = "pd.meta_title LIKE '%" . $this->db->escape($word) . "%'";
            }
            if ($nameParts) {
                $where[] = '(' . implode(' AND ', $nameParts) . ')';
            }

            // opis samo ako je upit dulji (>=3)
            if (!empty($data['filter_description']) && utf8_strlen($name_raw) >= 3) {
                $where[] = "pd.description LIKE '%" . $this->db->escape($name_raw) . "%'";
            }
        }

        // TAG: riječi >=2 znaka (AND)
        if ($tag_raw !== '') {
            $twords = array_filter(explode(' ', $tag_raw), function($w) {
                return utf8_strlen($w) >= 2;
            });
            $tagParts = array();
            foreach ($twords as $word) {
                $tagParts[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
            }
            if ($tagParts) {
                $where[] = '(' . implode(' AND ', $tagParts) . ')';
            }
        }

        // TOČNI KODOVI (samo ako je name_raw >=3 i ima alfanumerik)
        if ($name_raw !== '' && utf8_strlen($name_raw) >= 3 && preg_match('/[[:alnum:]]/u', $name_raw)) {
            $safe = $this->db->escape($name_raw);
            $exactCols = array('p.model','p.sku','p.upc','p.ean','p.jan','p.isbn','p.mpn');
            $codeParts = array();
            foreach ($exactCols as $col) {
                $codeParts[] = $col . " = '" . $safe . "'";
            }
            $where[] = '(' . implode(' OR ', $codeParts) . ')';
        }

        if ($where) {
            $sql .= ' AND (' . implode(' OR ', $where) . ')';
        } else {
            // traženo je, ali nije ostao nijedan smislen uvjet -> nema rezultata
            return array();
        }
    }

    $sql .= " GROUP BY p.product_id";

    // ORDER BY
    $sort_data = array(
        'pd.name',
        'p.model',
        'p.quantity',
        'p.price',
        'rating',
        'p.sort_order',
        'p.date_added'
    );

    $orderDir = (isset($data['order']) && ($data['order'] == 'DESC')) ? 'DESC' : 'ASC';

    if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
        if ($data['sort'] == 'p.price') {
            $sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END) " . $orderDir . ", pd.name ASC";
        } else {
            $sql .= " ORDER BY " . $data['sort'] . " " . $orderDir . ", pd.name ASC";
        }
    } else {
        $sql .= " ORDER BY p.sort_order ASC, pd.name ASC";
    }

    // LIMIT
    if (isset($data['start']) || isset($data['limit'])) {
        $start = isset($data['start']) ? (int)$data['start'] : 0;
        $limit = isset($data['limit']) ? (int)$data['limit'] : 20;

        if ($start < 0) $start = 0;
        if ($limit < 1) $limit = 20;

        $sql .= " LIMIT " . $start . "," . $limit;
    }

    $product_data = array();
    $query = $this->db->query($sql);

    foreach ($query->rows as $result) {
        if ($result['discount'] !== null) {
            $result['price'] = $result['discount'];
        }

        $result['price_last_30'] = $result['price_last_30'] !== null ? (float)$result['price_last_30'] : 0.0;
        $result['rating'] = round((float)$result['rating'], 2);
        $result['reviews'] = (int)$result['reviews'];
        $product_data[$result['product_id']] = $result;
    }

	$this->cache->set($cache_key, $product_data);

	return $product_data;
}


 public function getProductSpecials($data = array()) {
    // Base SELECT
    $sql  = "SELECT DISTINCT p.*, pd.name, pd.description, pd.meta_title, pd.meta_description, pd.meta_keyword, pd.tag, m.name AS manufacturer, ss.name AS stock_status, ";
    $sql .= "  (SELECT AVG(rating) ";
    $sql .= "   FROM " . DB_PREFIX . "review r1 ";
    $sql .= "   WHERE r1.product_id = ps.product_id AND r1.status = '1' ";
    $sql .= "   GROUP BY r1.product_id) AS rating, ";
    $sql .= "  (SELECT COUNT(*) FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1') AS reviews, ";
    $sql .= "  (SELECT ps2.price FROM " . DB_PREFIX . "product_special ps2 WHERE ps2.product_id = p.product_id ";
    $sql .= "   AND ps2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' ";
    $sql .= "   AND ((ps2.date_start = '0000-00-00' OR ps2.date_start < NOW()) AND (ps2.date_end = '0000-00-00' OR ps2.date_end > NOW())) ";
    $sql .= "   ORDER BY ps2.priority ASC, ps2.price ASC LIMIT 1) AS special, ";
    $sql .= "  (SELECT ps30.price FROM " . DB_PREFIX . "product_special ps30 WHERE ps30.product_id = p.product_id ";
    $sql .= "   AND ((ps30.date_start = '0000-00-00' OR ps30.date_start < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) ";
    $sql .= "   AND (ps30.date_end = '0000-00-00' OR ps30.date_end > DATE_SUB(CURDATE(), INTERVAL 30 DAY))) ";
    $sql .= "   ORDER BY ps30.price ASC LIMIT 1) AS price_last_30 ";

    // FROM + required joins (all joins come before WHERE)
    $sql .= "FROM " . DB_PREFIX . "product_special ps ";
    $sql .= "LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) ";
    $sql .= "LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) ";
    $sql .= "LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) ";
    $sql .= "LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id) ";
    $sql .= "LEFT JOIN " . DB_PREFIX . "stock_status ss ON (p.stock_status_id = ss.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') ";

    // Optional category filtering join(s)
    if (!empty($data['filter_category_id'])) {
        if (!empty($data['filter_sub_category'])) {
            // include subcategories via category_path
            $sql .= "LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id) ";
            $sql .= "LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id) ";
        } else {
            $sql .= "LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id) ";
        }
    }

    // WHERE (only once)
    $sql .= "WHERE p.status = '1' ";
    $sql .= "  AND p.date_available <= NOW() ";
    $sql .= "  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ";
    $sql .= "  AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' ";
    $sql .= "  AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' ";
    $sql .= "  AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) ";
    $sql .= "  AND  (ps.date_end   = '0000-00-00' OR ps.date_end   > NOW())) ";

    // Category filter condition(s)
    if (!empty($data['filter_category_id'])) {
        if (!empty($data['filter_sub_category'])) {
            $sql .= "AND cp.path_id = '" . (int)$data['filter_category_id'] . "' ";
        } else {
            $sql .= "AND p2c.category_id = '" . (int)$data['filter_category_id'] . "' ";
        }
    }

    // Group
    $sql .= "GROUP BY ps.product_id ";

    // Sorting
    $sort_data = array('pd.name','p.model','ps.price','rating','p.sort_order','p.date_added');
    $orderDir = (isset($data['order']) && ($data['order'] == 'DESC')) ? 'DESC' : 'ASC';

    if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
        $sql .= "ORDER BY " . $data['sort'] . " " . $orderDir . ", pd.name ASC ";
    } else {
        $sql .= "ORDER BY p.sort_order ASC, pd.name ASC ";
    }

    // Limit
    if (isset($data['start']) || isset($data['limit'])) {
        $start = isset($data['start']) ? (int)$data['start'] : 0;
        $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
        if ($start < 0) $start = 0;
        if ($limit < 1) $limit = 20;
        $sql .= "LIMIT " . $start . "," . $limit;
    }

    $product_data = array();
    $query = $this->db->query($sql);

    foreach ($query->rows as $result) {
        $result['price_last_30'] = $result['price_last_30'] !== null ? (float)$result['price_last_30'] : 0.0;
        $result['rating'] = round((float)$result['rating'], 2);
        $result['reviews'] = (int)$result['reviews'];
        $product_data[$result['product_id']] = $result;
    }

    return $product_data;
}

 public function getProductSpecialCategories($parent_id = 0) {
    $sql = "SELECT DISTINCT c.category_id, cd.name, c.image
        FROM " . DB_PREFIX . "product_special ps
        INNER JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id)
        INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
        INNER JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)
        INNER JOIN " . DB_PREFIX . "category c ON (p2c.category_id = c.category_id)
        INNER JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id)
        WHERE p.status = '1'
          AND p.date_available <= NOW()
          AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
          AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
          AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW())
          AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
          AND c.status = '1'
          AND c.parent_id = '" . (int)$parent_id . "'
          AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
        ORDER BY c.sort_order ASC, LCASE(cd.name) ASC";

    return $this->db->query($sql)->rows;
}


    public function getProductLowestPrice($product_id) {
        // najniža special cijena u zadnjih 30 dana
        $thirty = date('Y-m-d', strtotime('-30 days'));

        $q = $this->db->query("
            SELECT ps.price
            FROM " . DB_PREFIX . "product_special ps
            WHERE ps.product_id = '" . (int)$product_id . "'
              AND ((ps.date_start = '0000-00-00' OR ps.date_start < '" . $this->db->escape($thirty) . "')
              AND (ps.date_end   = '0000-00-00' OR ps.date_end   > '" . $this->db->escape($thirty) . "'))
            ORDER BY ps.price ASC
            LIMIT 1
        ");

        if ($q->num_rows) {
            return (float)$q->row['price'];
        }
        return 0.0;
    }

    public function getLatestProducts($limit) {
        $key = 'product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit;
        $product_data = $this->cache->get($key);

        if (!$product_data) {
            $product_data = array();
            $query = $this->db->query("
                SELECT p.product_id
                FROM " . DB_PREFIX . "product p
                LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
                WHERE p.status = '1'
                  AND p.date_available <= NOW()
                  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
                ORDER BY p.date_added DESC
                LIMIT " . (int)$limit . "
            ");

            foreach ($query->rows as $result) {
                $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
            }

            $this->cache->set($key, $product_data);
        }

        return $product_data;
    }

    public function getPopularProducts($limit) {
        $key = 'product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit;
        $product_data = $this->cache->get($key);

        if (!$product_data) {
            $product_data = array();
            $query = $this->db->query("
                SELECT p.product_id
                FROM " . DB_PREFIX . "product p
                LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
                WHERE p.status = '1'
                  AND p.date_available <= NOW()
                  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
                ORDER BY p.viewed DESC, p.date_added DESC
                LIMIT " . (int)$limit . "
            ");

            foreach ($query->rows as $result) {
                $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
            }

            $this->cache->set($key, $product_data);
        }

        return $product_data;
    }

    public function getBestSellerProducts($limit) {
        $key = 'product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit;
        $product_data = $this->cache->get($key);

        if (!$product_data) {
            $product_data = array();

            $query = $this->db->query("
                SELECT op.product_id, SUM(op.quantity) AS total
                FROM " . DB_PREFIX . "order_product op
                LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id)
                LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id)
                LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
                WHERE o.order_status_id > '0'
                  AND p.status = '1'
                  AND p.date_available <= NOW()
                  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
                GROUP BY op.product_id
                ORDER BY total DESC
                LIMIT " . (int)$limit . "
            ");

            foreach ($query->rows as $result) {
                $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
            }

            $this->cache->set($key, $product_data);
        }

        return $product_data;
    }

    public function getProductAttributes($product_id) {
        $product_attribute_group_data = array();

        $product_attribute_group_query = $this->db->query("
            SELECT ag.attribute_group_id, agd.name
            FROM " . DB_PREFIX . "product_attribute pa
            LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id)
            LEFT JOIN " . DB_PREFIX . "attribute_group ag ON (a.attribute_group_id = ag.attribute_group_id)
            LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id)
            WHERE pa.product_id = '" . (int)$product_id . "'
              AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            GROUP BY ag.attribute_group_id
            ORDER BY ag.sort_order, agd.name
        ");

        foreach ($product_attribute_group_query->rows as $product_attribute_group) {
            $product_attribute_data = array();

            $product_attribute_query = $this->db->query("
                SELECT a.attribute_id, ad.name, pa.text
                FROM " . DB_PREFIX . "product_attribute pa
                LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id)
                LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id)
                WHERE pa.product_id = '" . (int)$product_id . "'
                  AND a.attribute_group_id = '" . (int)$product_attribute_group['attribute_group_id'] . "'
                  AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'
                  AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
                ORDER BY a.sort_order, ad.name
            ");

            foreach ($product_attribute_query->rows as $product_attribute) {
                $product_attribute_data[] = array(
                    'attribute_id' => $product_attribute['attribute_id'],
                    'name'         => $product_attribute['name'],
                    'text'         => $product_attribute['text']
                );
            }

            $product_attribute_group_data[] = array(
                'attribute_group_id' => $product_attribute_group['attribute_group_id'],
                'name'               => $product_attribute_group['name'],
                'attribute'          => $product_attribute_data
            );
        }

        return $product_attribute_group_data;
    }

    public function getProductOptions($product_id) {
        $product_option_data = array();

        // PAZI: `option` je rezervirana riječ — mora imati backtick!
        $product_option_query = $this->db->query("
            SELECT * FROM " . DB_PREFIX . "product_option po
            INNER JOIN `" . DB_PREFIX . "option` o ON o.option_id = po.option_id
            INNER JOIN " . DB_PREFIX . "option_description od ON od.option_id = o.option_id AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'
            WHERE po.product_id = '" . (int)$product_id . "'
            ORDER BY o.sort_order
        ");

        foreach ($product_option_query->rows as $product_option) {
            $product_option_value_data = array();

            $product_option_value_query = $this->db->query("
                SELECT *
                FROM " . DB_PREFIX . "product_option_value pov
                LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id)
                LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id)
                WHERE pov.product_id = '" . (int)$product_id . "'
                  AND pov.product_option_id = '" . (int)$product_option['product_option_id'] . "'
                  AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
                ORDER BY ov.sort_order
            ");

            foreach ($product_option_value_query->rows as $product_option_value) {
                $product_option_value_data[] = array(
                    'product_option_value_id' => $product_option_value['product_option_value_id'],
                    'option_value_id'         => $product_option_value['option_value_id'],
                    'name'                    => $product_option_value['name'],
                    'image'                   => $product_option_value['image'],
                    'quantity'                => $product_option_value['quantity'],
                    'subtract'                => $product_option_value['subtract'],
                    'price'                   => $product_option_value['price'],
                    'price_prefix'            => $product_option_value['price_prefix'],
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

    public function getProductDiscounts($product_id) {
        $query = $this->db->query("
            SELECT *
            FROM " . DB_PREFIX . "product_discount
            WHERE product_id = '" . (int)$product_id . "'
              AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
              AND quantity > 1
              AND ((date_start = '0000-00-00' OR date_start < NOW())
              AND (date_end = '0000-00-00' OR date_end > NOW()))
            ORDER BY quantity ASC, priority ASC, price ASC
        ");
        return $query->rows;
    }

    public function getProductImages($product_id) {
        $query = $this->db->query("
            SELECT * FROM " . DB_PREFIX . "product_image
            WHERE product_id = '" . (int)$product_id . "'
            ORDER BY sort_order ASC
        ");
        return $query->rows;
    }

    public function getPrimaryImagesByProductIds(array $product_ids) {
        $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

        if (!$product_ids) {
            return array();
        }

        $query = $this->db->query("SELECT product_id, image FROM " . DB_PREFIX . "product_image WHERE product_id IN (" . implode(',', $product_ids) . ") ORDER BY product_id ASC, sort_order ASC, product_image_id ASC");
        $images = array();

        foreach ($query->rows as $row) {
            $product_id = (int)$row['product_id'];

            if (!isset($images[$product_id]) && $row['image']) {
                $images[$product_id] = $row['image'];
            }
        }

        return $images;
    }

    public function getProductRelated($product_id) {
        $product_data = array();

        $query = $this->db->query("
            SELECT *
            FROM " . DB_PREFIX . "product_related pr
            LEFT JOIN " . DB_PREFIX . "product p ON (pr.related_id = p.product_id)
            LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
            WHERE pr.product_id = '" . (int)$product_id . "'
              AND p.status = '1'
              AND p.date_available <= NOW()
              AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
            ORDER BY pr.sort ASC
        ");

        foreach ($query->rows as $result) {
            $product_data[$result['related_id']] = $this->getProduct($result['related_id']);
        }

        return $product_data;
    }

    public function getProductLayoutId($product_id) {
        $query = $this->db->query("
            SELECT * FROM " . DB_PREFIX . "product_to_layout
            WHERE product_id = '" . (int)$product_id . "'
              AND store_id = '" . (int)$this->config->get('config_store_id') . "'
        ");

        if ($query->num_rows) {
            return (int)$query->row['layout_id'];
        } else {
            return 0;
        }
    }

    public function getCategories($product_id) {
        $query = $this->db->query("
            SELECT * FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product_id . "'
        ");
        return $query->rows;
    }

    public function getCategoriesfilter($product_id) {
        $query = $this->db->query("
            SELECT DISTINCT * FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product_id . "'
        ");
        return $query->rows;
    }

   // ======================= getTotalProducts() =======================
public function getTotalProducts($data = array()) {
	$cache_key = $this->productQueryCacheKey('total', $data);
	$cached_total = $this->cache->get($cache_key);

	if ($cached_total !== false) {
		return (int)$cached_total;
	}

    $sql = "SELECT COUNT(DISTINCT p.product_id) AS total";

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

    $sql .= "
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
        LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
        WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
          AND p.status = '1'
          AND p.date_available <= NOW()
          AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
    ";

	// Keep category totals in sync with the products shown on the page. Search
	// results may still include discontinued products.
	if ($this->shouldExcludeDiscontinuedProducts($data)) {
		$sql .= " AND p.stock_status_id <> '" . self::DISCONTINUED_STOCK_STATUS_ID . "'";
	}

    // proizvođač
    if (!empty($data['filter_manufacturer_id'])) {
        $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
    }

    // kategorija + filteri
    if (!empty($data['filter_category_id'])) {
        if (!empty($data['filter_sub_category'])) {
            $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
        } else {
            $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
        }

        if (!empty($data['filter_filter'])) {
            $filters = explode(',', $data['filter_filter']);
            $implode = array();
            foreach ($filters as $filter_id) {
                $implode[] = (int)$filter_id;
            }
            if ($implode) {
                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }
    }

    // === SEARCH BUILDER (tvrdo) ===
    $hasSearch = (!empty($data['filter_name']) || !empty($data['filter_tag']));
    if ($hasSearch) {
        $where = array();

        // normalizacija
        $name_raw = isset($data['filter_name']) ? trim(preg_replace('/\s+/', ' ', $data['filter_name'])) : '';
        $tag_raw  = isset($data['filter_tag'])  ? trim(preg_replace('/\s+/', ' ', $data['filter_tag']))  : '';

        // NAME: riječi >=2 znaka (AND)
        if ($name_raw !== '') {
            $words = array_filter(explode(' ', $name_raw), function($w) {
                return utf8_strlen($w) >= 2;
            });
            $nameParts = array();
            foreach ($words as $word) {
                $nameParts[] = "pd.meta_title LIKE '%" . $this->db->escape($word) . "%'";
            }
            if ($nameParts) {
                $where[] = '(' . implode(' AND ', $nameParts) . ')';
            }

            // opis (>=3 znaka)
            if (!empty($data['filter_description']) && utf8_strlen($name_raw) >= 3) {
                $where[] = "pd.description LIKE '%" . $this->db->escape($name_raw) . "%'";
            }
        }

        // TAG: riječi >=2 znaka (AND)
        if ($tag_raw !== '') {
            $twords = array_filter(explode(' ', $tag_raw), function($w) {
                return utf8_strlen($w) >= 2;
            });
            $tagParts = array();
            foreach ($twords as $word) {
                $tagParts[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
            }
            if ($tagParts) {
                $where[] = '(' . implode(' AND ', $tagParts) . ')';
            }
        }

        // TOČNI KODOVI (samo name_raw >=3 i ima alfanumerik)
        if ($name_raw !== '' && utf8_strlen($name_raw) >= 3 && preg_match('/[[:alnum:]]/u', $name_raw)) {
            $safe = $this->db->escape($name_raw);
            $exactCols = array('p.model','p.sku','p.upc','p.ean','p.jan','p.isbn','p.mpn');
            $codeParts = array();
            foreach ($exactCols as $col) {
                $codeParts[] = $col . " = '" . $safe . "'";
            }
            $where[] = '(' . implode(' OR ', $codeParts) . ')';
        }

        if ($where) {
            $sql .= ' AND (' . implode(' OR ', $where) . ')';
        } else {
            return 0;
        }
    }

	$query = $this->db->query($sql);
	$total = (int)$query->row['total'];
	$this->cache->set($cache_key, $total);

	return $total;
}


    public function getProfile($product_id, $recurring_id) {
        $query = $this->db->query("
            SELECT *
            FROM " . DB_PREFIX . "recurring r
            JOIN " . DB_PREFIX . "product_recurring pr ON (pr.recurring_id = r.recurring_id AND pr.product_id = '" . (int)$product_id . "')
            WHERE pr.recurring_id = '" . (int)$recurring_id . "'
              AND status = '1'
              AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
        ");
        return $query->row;
    }

    public function getProfiles($product_id) {
        $query = $this->db->query("
            SELECT rd.*
            FROM " . DB_PREFIX . "product_recurring pr
            JOIN " . DB_PREFIX . "recurring_description rd ON (rd.language_id = " . (int)$this->config->get('config_language_id') . " AND rd.recurring_id = pr.recurring_id)
            JOIN " . DB_PREFIX . "recurring r ON r.recurring_id = rd.recurring_id
            WHERE pr.product_id = " . (int)$product_id . "
              AND status = '1'
              AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
            ORDER BY sort_order ASC
        ");
        return $query->rows;
    }

   public function getTotalProductSpecials($data = array()) {
    $sql  = "SELECT COUNT(DISTINCT p.product_id) AS total ";
    $sql .= "FROM " . DB_PREFIX . "product_special ps ";
    $sql .= "JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) ";
    $sql .= "JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) ";

    if (!empty($data['filter_category_id'])) {
        $sql .= "JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id) ";
        if (!empty($data['filter_sub_category'])) {
            $sql .= "JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id) ";
        }
    }

    $sql .= "WHERE p.status = '1' ";
    $sql .= "  AND p.date_available <= NOW() ";
    $sql .= "  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ";
    $sql .= "  AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' ";
    $sql .= "  AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) ";
    $sql .= "   AND (ps.date_end   = '0000-00-00' OR ps.date_end   > NOW())) ";

    if (!empty($data['filter_category_id'])) {
        if (!empty($data['filter_sub_category'])) {
            $sql .= "AND cp.path_id = '" . (int)$data['filter_category_id'] . "' ";
        } else {
            $sql .= "AND p2c.category_id = '" . (int)$data['filter_category_id'] . "' ";
        }
    }

    $query = $this->db->query($sql);
    return isset($query->row['total']) ? (int)$query->row['total'] : 0;
}

}
