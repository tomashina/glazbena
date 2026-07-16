<?php

/**
 * Created by PhpStorm.
 * User: Денис
 * Date: 9/23/2016
 * Time: 2:35 PM
 *
 * INTERFACE BY MANUFACTURER, CATEGORY, OPTIONS, ATTRIBUTES:
 * $input_name
 * $input_value
 * $input_label
 * $element_id(options, attributes, manufacturer, category)
 * $image
 */

/**
 * Render filter panel
 * Class ControllerExtensionModuleDigitalElephantFilter
 */
class ControllerExtensionModuleDigitalElephantFilter extends Controller
{
    /**
     * @var ControllerExtensionModuleDigitalElephantFilterSeo
     */
    private $seo = null;

    /**
     * @var ControllerExtensionModuleDigitalElephantFilterPanel
     */
    private $panel = null;


    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->seo = $this->load->controller('extension/module/digital_elephant_filter/seo/prototype');
        $this->panel = $this->load->controller('extension/module/digital_elephant_filter/panel/prototype');
    }

    public function index()
    {

		if ((float)VERSION >= 3.0) {
			$setting = $this->config->get('module_digitalElephantFilter_settings');
		} else {
		 	$setting = $this->config->get('digitalElephantFilter_settings');       
		}
        
        if ($rendered_panel = $this->panel->render($setting)) {
            $this->setStyle();
            $this->setScript();
            $this->runSEO($setting);
            return $rendered_panel;
        }

        if(isset($this->request->get['path'])) {
    $path = $this->request->get['path'];
    $cats = explode('_', $path);
    $cat_id = $cats[count($cats) - 1];
}


        $data['newURL'] = $cat_id;
    }
    protected function setScript()
    {
		
		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}	
		
        if ($this->language->get('direction') == 'rtl') {
		$this->document->addScript(rtrim($server, '/') . '/catalog/view/javascript/jquery/ui/jquery-ui-slider-rtl.min.js', 'footer');
        } else { 
		$this->document->addScript(rtrim($server, '/') . '/catalog/view/javascript/jquery/ui/jquery-ui-slider.min.js', 'footer');
		}
		
		$base = rtrim($server, '/');
		$this->document->addScript($base . '/catalog/view/theme/basel/js/jquery.ui.touch-punch.min.js', 'footer');
		$this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/main.js', 'footer');
		$this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/controller.js?v=2', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/helper.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/slider_price.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/url_private.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/url.js?v=2', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/pagination.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/show_more.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/quantity_products.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/sync.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/container_products.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/panel.js?v=2', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/limit.js', 'footer');
        $this->document->addScript($base . '/catalog/view/javascript/digitalElephantFilter/classes/sort.js', 'footer');
    }

    protected function setStyle()
    {
		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}
		
        $this->document->addStyle(rtrim($server, '/') . '/catalog/view/javascript/jquery/ui/jquery-ui.min.css', 'stylesheet', 'screen', 'footer');
    }

    private function runSEO($setting) {
        if (isset($setting['DEF_settings']['seo']['is_keywords']) && isset($this->request->get['ajax_digitalElephantFilter'])) {
            $this->seo->setMetaKeywords();
        }
    }
}
