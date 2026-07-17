<?php
class ModelExtensionSeo extends Model {
	public function build($title, $description, $links, $logo) {
		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';
		$entity = $this->registry->has('seo_entity') ? $this->registry->get('seo_entity') : array();
		$canonical = $this->getCanonical($links, $route);
		$description = $this->cleanText($description);

		if (!$description && !empty($entity['data']['description'])) {
			$description = $this->cleanText($entity['data']['description']);
		}

		$description = utf8_substr($description, 0, 300);
		$image = $this->getImage($entity, $links, $logo);
		$type = !empty($entity['type']) && $entity['type'] === 'product' ? 'product' : 'website';
		$currency = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');

		$meta = array(
			'robots'            => $this->getRobots($route),
			'canonical'         => $canonical,
			'og_type'           => $type,
			'og_title'          => $this->cleanText($title),
			'og_description'    => $description,
			'og_url'            => $canonical,
			'og_image'          => $image,
			'og_site_name'      => $this->config->get('config_name'),
			'og_locale'         => $this->getLocale(),
			'twitter_card'      => $image ? 'summary_large_image' : 'summary',
			'product_price'     => '',
			'product_currency'  => $currency,
			'product_available' => ''
		);
		$meta['preload_image'] = !empty($entity['preview_image']) ? $entity['preview_image'] : '';

		$graph = array();
		$store_url = rtrim($this->config->get('config_ssl') ?: $this->config->get('config_url'), '/') . '/';
		$organization_id = $store_url . '#organization';
		$website_id = $store_url . '#website';
		$webpage_id = $canonical . '#webpage';

		$organization = array(
			'@type' => 'OnlineStore',
			'@id'   => $organization_id,
			'name'  => $this->config->get('config_name'),
			'url'   => $store_url,
			'sameAs' => array(
				'https://www.facebook.com/GlazbenaKutijaDoo',
				'https://www.instagram.com/glazbenakutija/',
				'https://www.youtube.com/channel/UCgDP1zd_7Y7lHbvxqqfu0HA'
			)
		);
		$organization['hasMerchantReturnPolicy'] = array(
			'@type'                => 'MerchantReturnPolicy',
			'@id'                  => $store_url . '#return-policy',
			'applicableCountry'     => 'HR',
			'returnPolicyCountry'   => 'HR',
			'returnPolicyCategory'  => 'https://schema.org/MerchantReturnFiniteReturnWindow',
			'merchantReturnDays'    => 30,
			'merchantReturnLink'    => $store_url . 'povrati-artikla'
		);

		if ($logo) {
			$organization['logo'] = array('@type' => 'ImageObject', 'url' => $logo);
		}
		if ($this->config->get('config_email')) {
			$organization['email'] = $this->config->get('config_email');
		}
		if ($this->config->get('config_telephone')) {
			$organization['telephone'] = $this->config->get('config_telephone');
			$organization['contactPoint'] = array(
				'@type'       => 'ContactPoint',
				'telephone'   => $this->config->get('config_telephone'),
				'contactType' => 'customer service',
				'areaServed'  => 'HR',
				'availableLanguage' => array('hr')
			);
		}
		if ($this->config->get('config_address')) {
			$organization['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $this->cleanText($this->config->get('config_address')),
				'addressCountry'  => 'HR'
			);
		}
		$graph[] = $organization;

		$graph[] = array(
			'@type'     => 'WebSite',
			'@id'       => $website_id,
			'url'       => $store_url,
			'name'      => $this->config->get('config_name'),
			'inLanguage'=> $this->language->get('code'),
			'publisher' => array('@id' => $organization_id)
		);

		$webpage = array(
			'@type'      => $this->getWebPageType($type, $route),
			'@id'        => $webpage_id,
			'url'        => $canonical,
			'name'       => $this->cleanText($title),
			'isPartOf'   => array('@id' => $website_id),
			'inLanguage' => $this->language->get('code')
		);
		if ($description) {
			$webpage['description'] = $description;
		}
		if ($image) {
			$webpage['primaryImageOfPage'] = array('@type' => 'ImageObject', 'url' => $image);
		}

		$breadcrumbs = !empty($entity['breadcrumbs']) ? $this->buildBreadcrumbs($entity['breadcrumbs'], $canonical) : array();
		if ($breadcrumbs) {
			$breadcrumb_id = $canonical . '#breadcrumb';
			$webpage['breadcrumb'] = array('@id' => $breadcrumb_id);
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $breadcrumb_id,
				'itemListElement' => $breadcrumbs
			);
		}
		$collection_nodes = array();
		if (!empty($entity['products']) && in_array(isset($entity['type']) ? $entity['type'] : '', array('category', 'manufacturer', 'collection'), true)) {
			$collection_id = $canonical . '#itemlist';
			$webpage['mainEntity'] = array('@id' => $collection_id);
			$collection_nodes = $this->buildCollection($entity, $canonical, $currency, $organization_id, $collection_id);
		}

		$graph[] = $webpage;

		foreach ($collection_nodes as $collection_node) {
			$graph[] = $collection_node;
		}

		if ($type === 'product' && !empty($entity['data'])) {
			$product = $this->buildProduct($entity['data'], $canonical, $image, $organization_id, $currency, $entity);
			$graph[] = $product['schema'];
			$meta['product_price'] = $product['price'];
			$meta['product_available'] = $product['meta_availability'];
		}

		return array(
			'meta' => $meta,
			'json_ld' => json_encode(
				array('@context' => 'https://schema.org', '@graph' => $graph),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
			)
		);
	}

	private function buildProduct($product, $canonical, $image, $organization_id, $currency, $entity) {
		$price = (float)$product['price'];
		if (!empty($product['special']) && (float)$product['special'] > 0) {
			$price = (float)$product['special'];
		}
		$price = $this->tax->calculate($price, (int)$product['tax_class_id'], $this->config->get('config_tax'));
		$price = $this->currency->convert($price, $this->config->get('config_currency'), $currency);
		$price_string = number_format($price, 2, '.', '');
		$availability = $this->getSchemaAvailability($product);

		$schema = array(
			'@type'       => 'Product',
			'@id'         => $canonical . '#product',
			'name'        => $this->cleanText($product['name']),
			'url'         => $canonical,
			'description' => utf8_substr($this->cleanText(!empty($product['description']) ? $product['description'] : $product['meta_description']), 0, 5000),
			'mainEntityOfPage' => array('@id' => $canonical . '#webpage'),
			'offers'      => array(
				'@type'         => 'Offer',
				'url'           => $canonical,
				'priceCurrency' => $currency,
				'price'         => $price_string,
				'availability'  => $availability,
				'itemCondition' => 'https://schema.org/NewCondition',
				'seller'        => array('@id' => $organization_id)
			)
		);

		$images = array();
		if ($image) {
			$images[] = $image;
		}
		if (!empty($entity['images']) && is_array($entity['images'])) {
			foreach ($entity['images'] as $additional_image) {
				$additional_image = $this->normalizeUrl($additional_image);
				if ($additional_image) {
					$images[] = $additional_image;
				}
			}
		}
		if ($images) {
			$schema['image'] = array_values(array_unique($images));
		}
		if (!empty($product['model'])) {
			$schema['sku'] = $this->cleanText($product['model']);
		}
		if (!empty($product['mpn'])) {
			$schema['mpn'] = $this->cleanText($product['mpn']);
		}
		foreach (array('ean', 'upc', 'isbn', 'jan') as $gtin_field) {
			if (!empty($product[$gtin_field])) {
				$gtin = preg_replace('/\D+/', '', $product[$gtin_field]);
				if ($this->isValidGtin($gtin)) {
					$schema['gtin' . strlen($gtin)] = $gtin;
					break;
				}
			}
		}
		if (!empty($product['manufacturer'])) {
			$schema['brand'] = array('@type' => 'Brand', 'name' => $this->cleanText($product['manufacturer']));
		}
		if (!empty($entity['category'])) {
			$schema['category'] = $this->cleanText($entity['category']);
		}
		if (!empty($product['reviews']) && (float)$product['rating'] > 0) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (float)$product['rating'],
				'reviewCount' => (int)$product['reviews'],
				'bestRating'  => 5,
				'worstRating' => 1
			);
		}

		return array(
			'schema' => $schema,
			'price' => $price_string,
			'meta_availability' => $this->getMetaAvailability($availability)
		);
	}

	private function buildCollection($entity, $canonical, $currency, $organization_id, $collection_id) {
		$products = is_array($entity['products']) ? $entity['products'] : array();
		$total = isset($entity['total']) ? (int)$entity['total'] : count($products);
		$page = max(1, isset($entity['page']) ? (int)$entity['page'] : 1);
		$limit = max(1, isset($entity['limit']) ? (int)$entity['limit'] : count($products));
		$position = (($page - 1) * $limit) + 1;
		$items = array();

		foreach ($products as $product) {
			if (empty($product['product_id']) || empty($product['name'])) {
				continue;
			}

			$product_url = html_entity_decode($this->url->link('product/product', 'product_id=' . (int)$product['product_id']), ENT_QUOTES, 'UTF-8');
			$product_schema = array(
				'@type' => 'Product',
				'@id'   => $product_url . '#product',
				'name'  => $this->cleanText($product['name']),
				'url'   => $product_url
			);

			if (!empty($product['image'])) {
				$product_schema['image'] = array($this->getProductImageUrl($product['image']));
			}
			if (!empty($product['model'])) {
				$product_schema['sku'] = $this->cleanText($product['model']);
			}
			if (!empty($product['manufacturer'])) {
				$product_schema['brand'] = array('@type' => 'Brand', 'name' => $this->cleanText($product['manufacturer']));
			}

			$base_price = isset($product['price']) ? (float)$product['price'] : 0.0;
			$active_price = !empty($product['special']) && (float)$product['special'] > 0 ? (float)$product['special'] : $base_price;

			if ($active_price > 0) {
				$active_price = $this->tax->calculate($active_price, isset($product['tax_class_id']) ? (int)$product['tax_class_id'] : 0, $this->config->get('config_tax'));
				$active_price = $this->currency->convert($active_price, $this->config->get('config_currency'), $currency);
				$product_schema['offers'] = array(
					'@type'         => 'Offer',
					'url'           => $product_url,
					'priceCurrency' => $currency,
					'price'         => number_format($active_price, 2, '.', ''),
					'availability'  => $this->getSchemaAvailability($product),
					'itemCondition' => 'https://schema.org/NewCondition',
					'seller'        => array('@id' => $organization_id)
				);
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'item'     => $product_schema
			);
		}

		$nodes = array(array(
			'@type'           => 'ItemList',
			'@id'             => $collection_id,
			'url'             => $canonical,
			'numberOfItems'   => $total,
			'itemListOrder'   => 'https://schema.org/ItemListUnordered',
			'itemListElement' => $items
		));

		if (isset($entity['type']) && $entity['type'] === 'manufacturer' && !empty($entity['data']['name'])) {
			$nodes[] = array(
				'@type' => 'Brand',
				'@id'   => $canonical . '#brand',
				'name'  => $this->cleanText($entity['data']['name']),
				'url'   => $canonical
			);
		}

		return $nodes;
	}

	private function getSchemaAvailability($product) {
		if ((int)(isset($product['quantity']) ? $product['quantity'] : 0) > 0) {
			return 'https://schema.org/InStock';
		}

		$status = $this->normalizeText(isset($product['stock_status']) ? $product['stock_status'] : '');

		if (strpos($status, 'izvan proizvodnje') !== false || strpos($status, 'discontinued') !== false) {
			return 'https://schema.org/Discontinued';
		}

		if (strpos($status, 'naruci') !== false || strpos($status, 'narudz') !== false || strpos($status, 'u dolasku') !== false) {
			return 'https://schema.org/BackOrder';
		}

		return 'https://schema.org/OutOfStock';
	}

	private function getMetaAvailability($availability) {
		if ($availability === 'https://schema.org/InStock') {
			return 'instock';
		}
		if ($availability === 'https://schema.org/BackOrder') {
			return 'available for order';
		}
		if ($availability === 'https://schema.org/Discontinued') {
			return 'discontinued';
		}

		return 'oos';
	}

	private function getProductImageUrl($image) {
		$image = trim((string)$image);

		if ($image === '') {
			return '';
		}

		if (preg_match('#^https?://#i', $image)) {
			return str_replace(' ', '%20', $image);
		}

		return str_replace(' ', '%20', rtrim($this->config->get('config_ssl') ?: $this->config->get('config_url'), '/') . '/image/' . ltrim($image, '/'));
	}

	private function normalizeUrl($url) {
		$url = html_entity_decode(trim((string)$url), ENT_QUOTES, 'UTF-8');

		if ($url === '') {
			return '';
		}

		if (preg_match('#^https?://#i', $url)) {
			return str_replace(' ', '%20', $url);
		}

		return str_replace(' ', '%20', rtrim($this->config->get('config_ssl') ?: $this->config->get('config_url'), '/') . '/' . ltrim($url, '/'));
	}

	private function normalizeText($value) {
		$value = $this->cleanText($value);

		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
			if ($converted !== false) {
				$value = $converted;
			}
		}

		return strtolower($value);
	}

	private function buildBreadcrumbs($breadcrumbs, $canonical) {
		$items = array();
		$position = 1;
		foreach ($breadcrumbs as $breadcrumb) {
			$name = isset($breadcrumb['text']) ? $this->cleanText($breadcrumb['text']) : '';
			if (!$name) {
				$name = $position === 1 ? $this->config->get('config_name') : '';
			}
			if (!$name) {
				continue;
			}
			if ($position === 1) {
				$url = rtrim($this->config->get('config_ssl') ?: $this->config->get('config_url'), '/') . '/';
			} else {
				$url = !empty($breadcrumb['href']) ? html_entity_decode($breadcrumb['href'], ENT_QUOTES, 'UTF-8') : $canonical;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $name,
				'item'     => $url
			);
		}
		return $items;
	}

	private function getCanonical($links, $route) {
		foreach ($links as $link) {
			if (isset($link['rel']) && $link['rel'] === 'canonical') {
				return html_entity_decode($link['href'], ENT_QUOTES, 'UTF-8');
			}
		}

		if ($route === 'common/home') {
			return rtrim($this->config->get('config_ssl') ?: $this->config->get('config_url'), '/') . '/';
		}

		$scheme = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off' ? 'https://' : 'http://';
		$host = !empty($this->request->server['HTTP_HOST']) ? $this->request->server['HTTP_HOST'] : parse_url($this->config->get('config_url'), PHP_URL_HOST);
		$path = !empty($this->request->server['REQUEST_URI']) ? parse_url($this->request->server['REQUEST_URI'], PHP_URL_PATH) : '/';
		return $scheme . $host . $path;
	}

	private function getImage($entity, $links, $logo) {
		if (!empty($entity['data']['image']) && is_file(DIR_IMAGE . $entity['data']['image'])) {
			$base = $this->config->get('config_ssl') ?: $this->config->get('config_url');
			return $base . 'image/' . str_replace(' ', '%20', $entity['data']['image']);
		}
		foreach ($links as $link) {
			if (isset($link['rel']) && $link['rel'] === 'image') {
				return html_entity_decode($link['href'], ENT_QUOTES, 'UTF-8');
			}
		}
		return $logo;
	}

	private function getRobots($route) {
		$noindex_prefixes = array('account/', 'checkout/', 'affiliate/', 'api/', 'tool/', 'error/');
		$noindex_routes = array('product/search', 'product/compare', 'product/product/review', 'information/information/agree', 'extension/account/contract_withdrawal', 'extension/account/contract_withdrawal/success');
		foreach ($noindex_prefixes as $prefix) {
			if (strpos($route, $prefix) === 0) {
				return 'noindex, follow';
			}
		}
		if (in_array($route, $noindex_routes, true)) {
			return 'noindex, follow';
		}
		foreach (array('sort', 'order', 'limit', 'filter', 'search', 'tag') as $parameter) {
			if (isset($this->request->get[$parameter])) {
				return 'noindex, follow';
			}
		}
		return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
	}

	private function getWebPageType($type, $route) {
		if ($type === 'product') {
			return 'ItemPage';
		}
		if (strpos($route, 'product/category') === 0 || strpos($route, 'product/manufacturer') === 0 || $route === 'product/special' || $route === 'product/search') {
			return 'CollectionPage';
		}
		return 'WebPage';
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
		$check = (10 - ($sum % 10)) % 10;
		return $check === (int)$gtin[$length - 1];
	}

	private function getLocale() {
		$code = str_replace('-', '_', $this->language->get('code'));
		$parts = explode('_', $code);
		return strtolower($parts[0]) . (isset($parts[1]) ? '_' . strtoupper($parts[1]) : '_HR');
	}

	private function cleanText($value) {
		$value = (string)$value;
		for ($i = 0; $i < 3; $i++) {
			$decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
			if ($decoded === $value) {
				break;
			}
			$value = $decoded;
		}
		$value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)));
		return $value;
	}
}
