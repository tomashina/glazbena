<?php
class ControllerInformationAiContext extends Controller {
	public function index() {
		$this->load->language('information/ai_context');

		$base_url = $this->getStoreBaseUrl();

		$this->document->setTitle($this->language->get('meta_title'));
		$this->document->setDescription($this->language->get('meta_description'));
		$this->document->addLink($base_url . '/ai-context', 'canonical');

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $base_url . '/ai-context'
			)
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['intro'] = $this->language->get('text_intro');
		$data['llms_url'] = $base_url . '/llms.txt';
		$data['sections'] = $this->buildHtmlSections($base_url);

		$this->registry->set('seo_entity', array(
			'type' => 'information',
			'data' => array('description' => $this->language->get('meta_description')),
			'breadcrumbs' => $data['breadcrumbs']
		));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('information/ai_context', $data));
	}

	public function llms() {
		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		$this->response->addHeader('Cache-Control: public, max-age=3600');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->addHeader('Content-Disposition: inline; filename="llms.txt"');
		$this->response->setOutput($this->buildLlmsText($this->getStoreBaseUrl()));
	}

	private function buildHtmlSections($base_url) {
		return array(
			array(
				'title' => $this->language->get('section_machine_title'),
				'items' => array(
					array('label' => 'llms.txt', 'url' => $base_url . '/llms.txt', 'description' => $this->language->get('resource_llms')),
					array('label' => 'OpenAI product discovery feed', 'url' => $base_url . '/openai-products.jsonl', 'description' => $this->language->get('resource_openai_feed')),
					array('label' => 'Sitemap index', 'url' => $base_url . '/sitemap-index.xml', 'description' => $this->language->get('resource_sitemap')),
					array('label' => 'Robots policy', 'url' => $base_url . '/robots.txt', 'description' => $this->language->get('resource_robots'))
				)
			),
			array(
				'title' => $this->language->get('section_catalog_title'),
				'items' => array(
					array('label' => $this->language->get('link_home'), 'url' => $base_url . '/', 'description' => $this->language->get('resource_home')),
					array('label' => $this->language->get('link_brands'), 'url' => $base_url . '/brandovi', 'description' => $this->language->get('resource_brands')),
					array('label' => $this->language->get('link_categories'), 'url' => $base_url . '/gitare/', 'description' => $this->language->get('resource_categories')),
					array('label' => $this->language->get('link_specials'), 'url' => $base_url . '/akcije', 'description' => $this->language->get('resource_specials')),
					array('label' => $this->language->get('link_contact'), 'url' => $base_url . '/kontakt', 'description' => $this->language->get('resource_contact'))
				)
			),
			array(
				'title' => $this->language->get('section_policy_title'),
				'items' => array(
					array('label' => $this->language->get('link_delivery'), 'url' => $base_url . '/brza-i-pouzdana-dostava', 'description' => $this->language->get('resource_delivery')),
					array('label' => $this->language->get('link_returns'), 'url' => $base_url . '/povrati-artikla', 'description' => $this->language->get('resource_returns')),
					array('label' => $this->language->get('link_terms'), 'url' => $base_url . '/opci-uvjeti', 'description' => $this->language->get('resource_terms')),
					array('label' => $this->language->get('link_privacy'), 'url' => $base_url . '/pravila-privatnosti', 'description' => $this->language->get('resource_privacy')),
					array('label' => $this->language->get('link_withdrawal'), 'url' => $base_url . '/raskid-ugovora', 'description' => $this->language->get('resource_withdrawal'))
				)
			)
		);
	}

	private function buildLlmsText($base_url) {
		$lines = array(
			'# Glazbena Kutija',
			'',
			'> Glazbena Kutija is a Croatian retailer of musical instruments, pro audio equipment and accessories. The site contains product, category and brand pages, customer policies, XML sitemaps and a machine-readable product feed.',
			'',
			'Important notes:',
			'- Primary site language: Croatian.',
			'- Store country: HR; currency: EUR.',
			'- Prices, availability, delivery dates and compatibility can change. Verify them on the linked product page or current product feed.',
			'- Product discovery resources are intended for search and recommendations; checkout happens on www.glazbena-kutija.hr.',
			'- Respect robots.txt, canonical URLs and sitemap signals.',
			'',
			'## Core Resources',
			'- [Home](' . $base_url . '/): Main ecommerce site.',
			'- [AI context page](' . $base_url . '/ai-context): Human-readable overview of stable catalog and policy resources.',
			'- [Robots policy](' . $base_url . '/robots.txt): Crawler policy and sitemap reference.',
			'- [Sitemap index](' . $base_url . '/sitemap-index.xml): XML sitemap index for products, categories and information pages.',
			'- [OpenAI product discovery feed](' . $base_url . '/openai-products.jsonl): JSONL catalog feed with canonical product URLs, prices, availability, images and identifiers.',
			'',
			'## Main Categories',
			'- [Guitars](' . $base_url . '/gitare/): Guitars and related equipment.',
			'- [Drums](' . $base_url . '/bubnjevi/): Drums and percussion.',
			'- [Keyboards](' . $base_url . '/klavijature/): Keyboards, pianos and accessories.',
			'- [String instruments](' . $base_url . '/gudaci/): Orchestral string instruments and accessories.',
			'- [Wind instruments](' . $base_url . '/puhaci/): Wind instruments and accessories.',
			'- [Pro audio](' . $base_url . '/razglasi/): PA, studio and pro audio equipment.',
			'- [Microphones](' . $base_url . '/mikrofoni/): Microphones and related accessories.',
			'- [Brands](' . $base_url . '/brandovi): Manufacturer and brand listing.',
			'- [Special offers](' . $base_url . '/akcije): Current promotions.',
			'',
			'## Customer Information',
			'- [About the company](' . $base_url . '/o-nama): Company and store background.',
			'- [Contact](' . $base_url . '/kontakt): Store address, telephone and email.',
			'- [Delivery](' . $base_url . '/brza-i-pouzdana-dostava): Delivery methods, costs and exceptions.',
			'- [Payment methods](' . $base_url . '/nacini-placanja): Available payment methods.',
			'- [Returns](' . $base_url . '/povrati-artikla): Returns and withdrawal policy.',
			'- [Complaints](' . $base_url . '/reklamacije-i-prigovori): Complaints and product issue process.',
			'- [Terms](' . $base_url . '/opci-uvjeti): General purchase terms.',
			'- [Privacy policy](' . $base_url . '/pravila-privatnosti): Privacy and personal data policy.',
			'- [Withdrawal form](' . $base_url . '/raskid-ugovora): Online contract withdrawal form.',
			'',
			'## Usage Guidance',
			'- Prefer canonical product URLs from product pages, the sitemap or the product feed.',
			'- Cite the product URL when answering product questions.',
			'- Do not invent stock, delivery times, included accessories or compatibility claims that are not present on the product page or feed.',
			'- Use the policy pages above for delivery, returns, warranty, complaints and privacy answers.',
			''
		);

		return implode("\n", $lines);
	}

	private function getStoreBaseUrl() {
		$base_url = $this->config->get('config_ssl') ?: $this->config->get('config_url') ?: 'https://www.glazbena-kutija.hr/';

		return rtrim($base_url, '/');
	}
}
