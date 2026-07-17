<?php
class ControllerExtensionFeedBoostSitemap extends Controller {
	private $limit = 1000;

	public function index() {
		$type = isset($this->request->get['type']) ? (string)$this->request->get['type'] : '';
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		if ($type === 'products') {
			$this->products($page);
			return;
		}

		if ($type === 'pages') {
			$this->pages();
			return;
		}

		$total = $this->getProductTotal();
		$pages = max(1, (int)ceil($total / $this->limit));
		$output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$output .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		for ($i = 1; $i <= $pages; $i++) {
			$output .= '<sitemap><loc>' . $this->xml($this->url->link('extension/feed/boost_sitemap', 'type=products&page=' . $i, true)) . '</loc></sitemap>' . "\n";
		}
		$output .= '<sitemap><loc>' . $this->xml($this->url->link('extension/feed/boost_sitemap', 'type=pages', true)) . '</loc></sitemap>' . "\n";
		$output .= '</sitemapindex>';
		$this->xmlResponse($output);
	}

	private function products($page) {
		$start = ($page - 1) * $this->limit;
		$query = $this->db->query("SELECT p.product_id, p.image, p.date_modified, pd.name FROM " . DB_PREFIX . "product p INNER JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.product_id ASC LIMIT " . (int)$start . "," . (int)$this->limit);

		$output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
		foreach ($query->rows as $product) {
			$output .= '<url><loc>' . $this->xml($this->url->link('product/product', 'product_id=' . (int)$product['product_id'], true)) . '</loc>';
			$output .= '<lastmod>' . date('c', strtotime($product['date_modified'])) . '</lastmod>';
			if ($product['image']) {
				$image = ($this->config->get('config_ssl') ?: $this->config->get('config_url')) . 'image/' . str_replace(' ', '%20', $product['image']);
				$output .= '<image:image><image:loc>' . $this->xml($image) . '</image:loc><image:title>' . $this->xml($product['name']) . '</image:title></image:image>';
			}
			$output .= '</url>' . "\n";
		}
		$output .= '</urlset>';
		$this->xmlResponse($output);
	}

	private function pages() {
		$output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$output .= $this->urlEntry(rtrim($this->config->get('config_ssl') ?: $this->config->get('config_url'), '/') . '/');

		$categories = $this->db->query("SELECT c.category_id, c.date_modified FROM " . DB_PREFIX . "category c INNER JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.status = '1' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");
		foreach ($categories->rows as $category) {
			$output .= $this->urlEntry($this->url->link('product/category', 'path=' . (int)$category['category_id'], true), $category['date_modified']);
		}

		$manufacturers = $this->db->query("SELECT m.manufacturer_id FROM " . DB_PREFIX . "manufacturer m INNER JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");
		foreach ($manufacturers->rows as $manufacturer) {
			$output .= $this->urlEntry($this->url->link('product/manufacturer/info', 'manufacturer_id=' . (int)$manufacturer['manufacturer_id'], true));
		}

		$informations = $this->db->query("SELECT i.information_id FROM " . DB_PREFIX . "information i INNER JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE i.status = '1' AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");
		foreach ($informations->rows as $information) {
			$output .= $this->urlEntry($this->url->link('information/information', 'information_id=' . (int)$information['information_id'], true));
		}
		$output .= $this->urlEntry($this->url->link('information/contact', '', true));
		$output .= $this->urlEntry($this->url->link('information/ai_context', '', true));
		$output .= $this->urlEntry($this->url->link('product/manufacturer', '', true));
		$output .= $this->urlEntry($this->url->link('product/special', '', true));
		$output .= '</urlset>';
		$this->xmlResponse($output);
	}

	private function getProductTotal() {
		$query = $this->db->query("SELECT COUNT(DISTINCT p.product_id) AS total FROM " . DB_PREFIX . "product p INNER JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");
		return (int)$query->row['total'];
	}

	private function urlEntry($url, $modified = '') {
		$output = '<url><loc>' . $this->xml($url) . '</loc>';
		if ($modified && strtotime($modified)) {
			$output .= '<lastmod>' . date('c', strtotime($modified)) . '</lastmod>';
		}
		return $output . '</url>' . "\n";
	}

	private function xml($value) {
		return htmlspecialchars(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	private function xmlResponse($output) {
		$this->response->addHeader('Content-Type: application/xml; charset=utf-8');
		$this->response->setOutput($output);
	}
}
