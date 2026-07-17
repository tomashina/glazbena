<?php
class ControllerStartupSeoUrl extends Controller {
	private $rewrite_cache = array();
	private $keyword_cache = array();
	private $keyword_groups_loaded = array();

	public function index() {
		// Add rewrite to url class
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}

		// Decode URL
		if (isset($this->request->get['_route_'])) {
			$static_routes = array(
				'kontakt' => 'information/contact',
				'contact' => 'information/contact',
				'raskid-ugovora' => 'extension/account/contract_withdrawal',
				'special' => 'product/special',
				'sitemap-index.xml' => 'extension/feed/boost_sitemap',
				'openai-products.jsonl' => 'extension/feed/openai_product_feed',
				'llms.txt' => 'information/ai_context/llms',
				'ai-context' => 'information/ai_context'
			);

			$route_key = trim($this->request->get['_route_'], '/');
			if (isset($static_routes[$route_key])) {
				$this->request->get['route'] = $static_routes[$route_key];
				return;
			}

			$parts = explode('/', $this->request->get['_route_']);

			// remove any empty arrays from trailing
			if (utf8_strlen(end($parts)) == 0) {
				array_pop($parts);
			}

			foreach ($parts as $part) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

				if ($query->num_rows) {
					$url = explode('=', $query->row['query']);

					if ($url[0] == 'product_id') {
						$this->request->get['product_id'] = $url[1];
					}

					if ($url[0] == 'category_id') {
						if (!isset($this->request->get['path'])) {
							$this->request->get['path'] = $url[1];
						} else {
							$this->request->get['path'] .= '_' . $url[1];
						}
					}

					if ($url[0] == 'manufacturer_id') {
						$this->request->get['manufacturer_id'] = $url[1];
					}

					if ($url[0] == 'information_id') {
						$this->request->get['information_id'] = $url[1];
					}

					if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
						$this->request->get['route'] = $query->row['query'];
					}
				} else {
					$this->request->get['route'] = 'error/not_found';

					break;
				}
			}

			if (!isset($this->request->get['route'])) {
				if (isset($this->request->get['product_id'])) {
					$this->request->get['route'] = 'product/product';
				} elseif (isset($this->request->get['path'])) {
					$this->request->get['route'] = 'product/category';
				} elseif (isset($this->request->get['manufacturer_id'])) {
					$this->request->get['route'] = 'product/manufacturer/info';
				} elseif (isset($this->request->get['information_id'])) {
					$this->request->get['route'] = 'information/information';
				}
			}
		}
	}

	public function rewrite($link) {
		if (isset($this->rewrite_cache[$link])) {
			return $this->rewrite_cache[$link];
		}

		$url_info = parse_url(str_replace('&amp;', '&', $link));

		$url = '';

		$data = array();

		parse_str($url_info['query'], $data);

		$static_routes = array(
			'common/home' => '',
			'information/contact' => 'kontakt',
			'extension/account/contract_withdrawal' => 'raskid-ugovora',
			'product/special' => 'special',
			'extension/feed/openai_product_feed' => 'openai-products.jsonl',
			'information/ai_context/llms' => 'llms.txt',
			'information/ai_context' => 'ai-context'
		);

		if (!empty($data['route']) && isset($static_routes[$data['route']])) {
			$url = '/' . $static_routes[$data['route']];
			unset($data['route']);
		}

		foreach ($data as $key => $value) {
			if (isset($data['route'])) {
				if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id')) {
					$keyword = $this->getKeyword($key . '=' . (int)$value);

					if ($keyword) {
						$url .= '/' . $keyword;

						unset($data[$key]);
					}
				} elseif ($key == 'path') {
					$categories = explode('_', $value);

					foreach ($categories as $category) {
						$keyword = $this->getKeyword('category_id=' . (int)$category);

						if ($keyword) {
							$url .= '/' . $keyword;
						} else {
							$url = '';

							break;
						}
					}

					unset($data[$key]);
				}
			}
		}

		if ($url) {
			unset($data['route']);

			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			$this->rewrite_cache[$link] = $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
		} else {
			$this->rewrite_cache[$link] = $link;
		}

		return $this->rewrite_cache[$link];
	}

	private function getKeyword($query) {
		$separator = strpos($query, '=');
		$type = $separator === false ? '' : substr($query, 0, $separator);
		$namespaces = array(
			'product_id'      => 'product',
			'category_id'     => 'category',
			'manufacturer_id' => 'manufacturer',
			'information_id'  => 'information'
		);

		if (isset($namespaces[$type])) {
			if (empty($this->keyword_groups_loaded[$type])) {
				$cache_key = $namespaces[$type] . '.seo_url.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_language_id');
				$keywords = $this->cache->get($cache_key);

				if (!is_array($keywords)) {
					$keywords = array();
					$result = $this->db->query("SELECT `query`, keyword FROM " . DB_PREFIX . "seo_url WHERE store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' AND `query` LIKE '" . $this->db->escape($type) . "=%'");

					foreach ($result->rows as $row) {
						if (!array_key_exists($row['query'], $keywords)) {
							$keywords[$row['query']] = $row['keyword'];
						}
					}

					$this->cache->set($cache_key, $keywords);
				}

				$this->keyword_cache += $keywords;
				$this->keyword_groups_loaded[$type] = true;
			}
		} elseif (!array_key_exists($query, $this->keyword_cache)) {
			$result = $this->db->query("SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($query) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");
			$this->keyword_cache[$query] = ($result->num_rows && $result->row['keyword']) ? $result->row['keyword'] : '';
		}

		return isset($this->keyword_cache[$query]) ? $this->keyword_cache[$query] : '';
	}
}
