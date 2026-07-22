<?php
class ControllerExtensionFeedGoogleSitemap extends Controller {
	public function index() {
		if ($this->config->get('feed_google_sitemap_status')) {
			$this->load->controller('extension/feed/boost_sitemap');
		}
	}
}
