<?php
class ControllerExtensionFeedOpenaiProductFeed extends Controller {
	const DEFAULT_BATCH_SIZE = 500;
	const MAX_BATCH_SIZE = 2000;

	public function index() {
		if (function_exists('set_time_limit')) {
			@set_time_limit(0);
		}

		$this->load->model('extension/feed/openai_product_feed');

		$batch_size = $this->getBoundedInt('batch', self::DEFAULT_BATCH_SIZE, 100, self::MAX_BATCH_SIZE);
		$offset = $this->getBoundedInt('offset', 0, 0, PHP_INT_MAX);
		$limit = isset($this->request->get['limit']) ? $this->getBoundedInt('limit', 0, 1, PHP_INT_MAX) : 0;
		$currency = $this->getFeedCurrencyCode();
		$output = '';
		$written = 0;

		do {
			$remaining = $limit ? ($limit - $written) : $batch_size;
			$current_limit = $limit ? min($batch_size, $remaining) : $batch_size;

			if ($current_limit <= 0) {
				break;
			}

			$products = $this->model_extension_feed_openai_product_feed->getProducts($offset, $current_limit);

			if (!$products) {
				break;
			}

			foreach ($products as $product) {
				$record = $this->buildRecord($product, $currency);

				if (!$record) {
					continue;
				}

				$json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

				if ($json !== false) {
					$output .= $json . "\n";
					$written++;
				}
			}

			$offset += count($products);

			if (count($products) < $current_limit) {
				break;
			}
		} while (!$limit || $written < $limit);

		$this->response->addHeader('Content-Type: application/x-ndjson; charset=utf-8');
		$this->response->addHeader('Cache-Control: public, max-age=900');
		$this->response->addHeader('X-Robots-Tag: noindex');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->addHeader('Content-Disposition: inline; filename="openai-products.jsonl"');
		$this->response->setOutput($output);
	}

	private function buildRecord(array $product, $currency) {
		$title = $this->truncateText($this->cleanText(isset($product['name']) ? $product['name'] : ''), 150);
		$description_source = !empty($product['description']) ? $product['description'] : (!empty($product['meta_description']) ? $product['meta_description'] : $title);
		$description = $this->truncateText($this->cleanText($description_source), 5000);
		$image_url = $this->getImageUrl(isset($product['image']) ? $product['image'] : '');
		$base_price = !empty($product['discount']) && (float)$product['discount'] > 0 ? $product['discount'] : (isset($product['price']) ? $product['price'] : 0);
		$regular_price = $this->getPriceValue($base_price, isset($product['tax_class_id']) ? $product['tax_class_id'] : 0, $currency);

		if ($title === '' || $description === '' || $image_url === '' || $regular_price <= 0) {
			return array();
		}

		$url = $this->normalizeUrl($this->url->link('product/product', 'product_id=' . (int)$product['product_id'], true));
		$brand_source = !empty($product['manufacturer']) ? $product['manufacturer'] : ($this->config->get('config_name') ?: 'Glazbena kutija');
		$brand = $this->truncateText($this->cleanText($brand_source), 70);
		$item_id = (string)(int)$product['product_id'];
		$record = array(
			'is_eligible_search' => 'true',
			'is_eligible_checkout' => 'false',
			'item_id' => $item_id,
			'title' => $title,
			'description' => $description,
			'url' => $url,
			'brand' => $brand,
			'condition' => 'new',
			'image_url' => $image_url,
			'price' => $this->formatPrice($regular_price, $currency),
			'availability' => $this->getAvailability($product),
			'seller_name' => $this->truncateText($this->cleanText($this->config->get('config_name') ?: 'Glazbena kutija'), 70),
			'seller_url' => $this->getStoreBaseUrl(),
			'seller_privacy_policy' => $this->getStoreBaseUrl() . '/pravila-privatnosti',
			'seller_tos' => $this->getStoreBaseUrl() . '/opci-uvjeti',
			'accepts_returns' => 'true',
			'return_deadline_in_days' => 30,
			'return_policy' => $this->getStoreBaseUrl() . '/povrati-artikla',
			'target_countries' => array('HR'),
			'store_country' => 'HR',
			'is_digital' => 'false',
			'offer_id' => $item_id
		);

		$gtin = $this->getGtin($product);

		if ($gtin !== '') {
			$record['gtin'] = $gtin;
		}

		$mpn = $this->getMpn($product);

		if ($mpn !== '') {
			$record['mpn'] = $mpn;
		}

		if (!empty($product['product_category'])) {
			$record['product_category'] = $this->cleanText($product['product_category']);
		}

		$additional_image_urls = $this->getAdditionalImageUrls($product, $image_url);

		if ($additional_image_urls) {
			$record['additional_image_urls'] = implode(',', $additional_image_urls);
		}

		$sale_price = $this->getSalePriceValue($product, $currency);

		if ($sale_price > 0 && $sale_price <= $regular_price) {
			$record['sale_price'] = $this->formatPrice($sale_price, $currency);
		}

		if (!empty($product['price_last_30']) && (float)$product['price_last_30'] > 0) {
			$last_30_price = $this->getPriceValue($product['price_last_30'], isset($product['tax_class_id']) ? $product['tax_class_id'] : 0, $currency);
			$record['pricing_trend'] = $this->truncateText('Lowest price in last 30 days: ' . $this->formatPrice($last_30_price, $currency), 80);
		}

		if (!empty($product['weight']) && (float)$product['weight'] > 0 && !empty($product['weight_class'])) {
			$record['weight'] = $this->trimDecimal($product['weight']);
			$record['item_weight_unit'] = strtolower($this->cleanText($product['weight_class']));
		}

		if ((float)(isset($product['length']) ? $product['length'] : 0) > 0 && (float)(isset($product['width']) ? $product['width'] : 0) > 0 && (float)(isset($product['height']) ? $product['height'] : 0) > 0 && !empty($product['length_class'])) {
			$unit = strtolower($this->cleanText($product['length_class']));
			$record['dimensions'] = $this->trimDecimal($product['length']) . 'x' . $this->trimDecimal($product['width']) . 'x' . $this->trimDecimal($product['height']) . ' ' . $unit;
		}

		if (!empty($product['reviews']) && (int)$product['reviews'] > 0 && !empty($product['rating']) && (float)$product['rating'] > 0) {
			$record['review_count'] = (int)$product['reviews'];
			$record['star_rating'] = number_format((float)$product['rating'], 2, '.', '');
		}

		return $record;
	}

	private function getBoundedInt($key, $default, $min, $max) {
		$value = isset($this->request->get[$key]) ? (int)$this->request->get[$key] : (int)$default;

		return max((int)$min, min((int)$max, $value));
	}

	private function getFeedCurrencyCode() {
		if ($this->currency->has('EUR')) {
			return 'EUR';
		}

		return $this->config->get('config_currency') ?: 'EUR';
	}

	private function getPriceValue($price, $tax_class_id, $currency) {
		$taxed = $this->tax->calculate((float)$price, (int)$tax_class_id, $this->config->get('config_tax'));

		return (float)$this->currency->format($taxed, $currency, '', false);
	}

	private function getSalePriceValue(array $product, $currency) {
		if (!isset($product['special']) || $product['special'] === '' || (float)$product['special'] <= 0) {
			return 0.0;
		}

		return $this->getPriceValue($product['special'], isset($product['tax_class_id']) ? $product['tax_class_id'] : 0, $currency);
	}

	private function formatPrice($price, $currency) {
		return number_format((float)$price, 2, '.', '') . ' ' . $currency;
	}

	private function getAvailability(array $product) {
		if ((int)(isset($product['quantity']) ? $product['quantity'] : 0) > 0) {
			return 'in_stock';
		}

		$stock_status = $this->normalizeText(isset($product['stock_status']) ? $product['stock_status'] : '');

		if (strpos($stock_status, 'naruci') !== false || strpos($stock_status, 'narudz') !== false || strpos($stock_status, 'u dolasku') !== false) {
			return 'backorder';
		}

		return 'out_of_stock';
	}

	private function getGtin(array $product) {
		foreach (array('ean', 'upc', 'jan') as $field) {
			$gtin = preg_replace('/\D+/', '', isset($product[$field]) ? (string)$product[$field] : '');

			if ($this->isValidGtin($gtin)) {
				return $gtin;
			}
		}

		return '';
	}

	private function isValidGtin($gtin) {
		$length = strlen($gtin);

		if (!in_array($length, array(8, 12, 13, 14), true)) {
			return false;
		}

		$sum = 0;

		for ($i = $length - 2, $weight = 3; $i >= 0; $i--, $weight = $weight === 3 ? 1 : 3) {
			$sum += (int)$gtin[$i] * $weight;
		}

		return (10 - ($sum % 10)) % 10 === (int)$gtin[$length - 1];
	}

	private function getMpn(array $product) {
		foreach (array('mpn', 'model', 'sku') as $field) {
			$identifier = $this->truncateText($this->cleanText(isset($product[$field]) ? $product[$field] : ''), 70);

			if ($this->isUsableManufacturerIdentifier($identifier)) {
				return $identifier;
			}
		}

		return '';
	}

	private function isUsableManufacturerIdentifier($identifier) {
		if ($identifier === '' || $identifier === '-' || $identifier === '0' || strlen($identifier) < 3) {
			return false;
		}

		$normalized = $this->normalizeText($identifier);

		if (preg_match('/^\d+\s*(godin|mjesec|mj|year|years|month|months)/', $normalized)) {
			return false;
		}

		if (preg_match('/\b(garanc|jamstvo|warranty|dobavlj|naruc|narud|isporuk)\b/', $normalized)) {
			return false;
		}

		return (bool)preg_match('/[a-z0-9]/i', $identifier);
	}

	private function getAdditionalImageUrls(array $product, $primary_image_url) {
		$urls = array();
		$images = isset($product['additional_images']) && is_array($product['additional_images']) ? $product['additional_images'] : array();

		foreach ($images as $image) {
			$url = $this->getImageUrl($image);

			if ($url !== '' && $url !== $primary_image_url) {
				$urls[] = $url;
			}
		}

		return array_values(array_unique($urls));
	}

	private function getImageUrl($image) {
		$image = trim((string)$image);

		if ($image === '' || !preg_match('/\.(jpe?g|png)(?:\?.*)?$/i', $image)) {
			return '';
		}

		if (preg_match('#^https?://#i', $image)) {
			return str_replace(' ', '%20', $image);
		}

		$path = ltrim($image, '/');

		if (strpos($path, 'image/') !== 0) {
			$path = 'image/' . $path;
		}

		return str_replace(' ', '%20', $this->getStoreBaseUrl() . '/' . $path);
	}

	private function normalizeUrl($url) {
		$url = html_entity_decode(trim((string)$url), ENT_QUOTES, 'UTF-8');

		if ($url === '') {
			return $this->getStoreBaseUrl();
		}

		if (preg_match('#^https?://#i', $url)) {
			return str_replace(' ', '%20', $url);
		}

		return str_replace(' ', '%20', $this->getStoreBaseUrl() . '/' . ltrim($url, '/'));
	}

	private function getStoreBaseUrl() {
		return rtrim($this->config->get('config_ssl') ?: $this->config->get('config_url'), '/');
	}

	private function cleanText($text) {
		$text = (string)$text;
		for ($i = 0; $i < 3; $i++) {
			$decoded = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
			if ($decoded === $text) {
				break;
			}
			$text = $decoded;
		}
		$text = strip_tags($text);
		$clean = preg_replace('/\s+/u', ' ', $text);

		if ($clean !== null) {
			$text = $clean;
		}

		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

			if ($converted !== false) {
				$text = $converted;
			}
		}

		return trim($text);
	}

	private function normalizeText($text) {
		$text = $this->cleanText($text);

		if (function_exists('iconv')) {
			$transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

			if ($transliterated !== false) {
				$text = $transliterated;
			}
		}

		return strtolower($text);
	}

	private function truncateText($text, $limit) {
		$text = (string)$text;
		$limit = (int)$limit;

		if ($text === '' || $limit <= 0) {
			return '';
		}

		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($text, 'UTF-8') <= $limit) {
				return $text;
			}

			return rtrim(mb_substr($text, 0, $limit - 3, 'UTF-8')) . '...';
		}

		if (strlen($text) <= $limit) {
			return $text;
		}

		return rtrim(substr($text, 0, $limit - 3)) . '...';
	}

	private function trimDecimal($value) {
		return rtrim(rtrim(number_format((float)$value, 4, '.', ''), '0'), '.');
	}
}
