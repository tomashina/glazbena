<?php

class SeoKeyword {
	public static function autofill($db, array $seo_urls, array $titles, $entity_query = '') {
		$languages = self::getLanguages($db);
		$store_ids = self::getStoreIds($db);
		$reserved = array();

		foreach ($seo_urls as $store_id => $language_keywords) {
			if (!is_array($language_keywords)) {
				continue;
			}

			foreach ($language_keywords as $language_id => $keyword) {
				$keyword = trim((string)$keyword);

				if ($keyword !== '') {
					$reserved[(int)$store_id][self::normalize($keyword)] = true;
				}
			}
		}

		foreach ($store_ids as $store_id) {
			if (!isset($seo_urls[$store_id]) || !is_array($seo_urls[$store_id])) {
				$seo_urls[$store_id] = array();
			}

			foreach ($languages as $language_id => $language_code) {
				$current = isset($seo_urls[$store_id][$language_id])
					? trim((string)$seo_urls[$store_id][$language_id])
					: '';

				if ($current !== '') {
					continue;
				}

				$title = isset($titles[$language_id])
					? $titles[$language_id]
					: ($titles ? reset($titles) : '');
				$base = self::slugify($title);

				if ($base === '') {
					continue;
				}

				$candidate = $base;

				if (
					self::isReserved($reserved, $store_id, $candidate) ||
					self::exists($db, $store_id, $candidate, $entity_query)
				) {
					$language_suffix = self::slugify($language_code);
					$candidate = $base . ($language_suffix !== '' ? '-' . $language_suffix : '');
				}

				$unique = $candidate;
				$suffix = 2;

				while (
					self::isReserved($reserved, $store_id, $unique) ||
					self::exists($db, $store_id, $unique, $entity_query)
				) {
					$unique = $candidate . '-' . $suffix++;
				}

				$seo_urls[$store_id][$language_id] = $unique;
				$reserved[$store_id][self::normalize($unique)] = true;
			}
		}

		return $seo_urls;
	}

	public static function slugify($value) {
		$value = strip_tags((string)$value);

		for ($decode = 0; $decode < 2; $decode++) {
			$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
		}

		$value = strtr($value, array(
			'Č' => 'C', 'Ć' => 'C', 'Đ' => 'D', 'Š' => 'S', 'Ž' => 'Z',
			'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z'
		));

		if (function_exists('iconv')) {
			$transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

			if ($transliterated !== false) {
				$value = $transliterated;
			}
		}

		$value = self::normalize($value);
		$value = preg_replace('/[^a-z0-9]+/', '-', $value);

		return rtrim(substr(trim($value, '-'), 0, 240), '-');
	}

	private static function getLanguages($db) {
		$languages = array();
		$query = $db->query(
			"SELECT language_id, code FROM `" . DB_PREFIX . "language` " .
			"WHERE status = '1' ORDER BY sort_order, name"
		);

		foreach ($query->rows as $language) {
			$languages[(int)$language['language_id']] = $language['code'];
		}

		return $languages;
	}

	private static function getStoreIds($db) {
		$store_ids = array(0);
		$query = $db->query("SELECT store_id FROM `" . DB_PREFIX . "store` ORDER BY store_id");

		foreach ($query->rows as $store) {
			$store_ids[] = (int)$store['store_id'];
		}

		return array_values(array_unique($store_ids));
	}

	private static function exists($db, $store_id, $keyword, $entity_query) {
		$sql = "SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url` " .
			"WHERE store_id = '" . (int)$store_id . "' " .
			"AND LCASE(keyword) = '" . $db->escape(self::normalize($keyword)) . "'";

		if ($entity_query !== '') {
			$sql .= " AND query != '" . $db->escape($entity_query) . "'";
		}

		$sql .= " LIMIT 1";

		return (bool)$db->query($sql)->num_rows;
	}

	private static function isReserved(array $reserved, $store_id, $keyword) {
		return isset($reserved[(int)$store_id][self::normalize($keyword)]);
	}

	private static function normalize($value) {
		if (function_exists('mb_strtolower')) {
			return mb_strtolower((string)$value, 'UTF-8');
		}

		return strtolower((string)$value);
	}
}
