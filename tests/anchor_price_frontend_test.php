<?php
// Offline storefront checks: php tests/anchor_price_frontend_test.php
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__);
require $root . '/system/storage/vendor/autoload.php';
require $root . '/system/engine/registry.php';
require $root . '/system/engine/controller.php';
require $root . '/system/engine/model.php';
require $root . '/system/library/config.php';
require $root . '/system/helper/utf8.php';
require $root . '/catalog/controller/extension/module/anchor_price.php';
require $root . '/catalog/model/extension/module/anchor_price.php';
require $root . '/catalog/controller/extension/basel/live_search.php';
if (!defined('DB_PREFIX')) define('DB_PREFIX', 'test_');
if (!defined('DB_DATABASE')) define('DB_DATABASE', 'anchor_frontend_test');

$checks = 0;
function anchorFrontendAssert($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

class AnchorFrontendDb {
    public $queries = array();
    public $records = array();
    public function escape($value) { return addslashes((string)$value); }
    public function query($sql) {
        $this->queries[] = $sql;
        $rows = array();
        if (strpos($sql, 'INFORMATION_SCHEMA.TABLES') !== false) {
            $rows[] = array('TABLE_NAME' => 'test_anchor_price');
        } else {
            anchorFrontendAssert(strpos($sql, "verification_status = 'confirmed'") !== false, 'Storefront lookup must filter unconfirmed snapshots.');
            preg_match('/product_id IN \(([0-9,]+)\)/', $sql, $ids);
            preg_match("/store_id = '([0-9]+)'/", $sql, $store);
            foreach ($this->records as $record) {
                if (in_array((int)$record['product_id'], array_map('intval', explode(',', $ids[1])), true)
                    && (int)$record['store_id'] === (int)$store[1]
                    && $record['verification_status'] === 'confirmed') $rows[] = $record;
            }
        }
        return (object)array('rows' => $rows, 'num_rows' => count($rows), 'row' => $rows ? reset($rows) : array());
    }
}
class AnchorFrontendCurrency {
    public $conversions = array();
    public function convert($value, $from, $to) {
        $this->conversions[] = array($value, $from, $to);
        return $from === 'EUR' && $to === 'USD' ? $value * 2 : $value;
    }
    public function format($value, $code, $rate = '') {
        return number_format($value * ($rate === '' && $code === 'USD' ? 2 : 1), 2, ',', '') . ' ' . $code;
    }
}
class AnchorFrontendLoader {
    public $models = array();
    public function model($route) { $this->models[] = $route; }
    public function language($route) {}
}
class AnchorFrontendCustomer { public $logged = false; public function isLogged() { return $this->logged; } }
class AnchorFrontendLanguage { public function get($key) { return $key; } }
class AnchorFrontendImage { public function resize($image, $width, $height) { return '/image/' . $image; } }
class AnchorFrontendUrl { public function link($route, $query = '') { return '/index.php?route=' . $route . '&' . $query; } }
class AnchorFrontendResponse {
    public $headers = array();
    public $output = '';
    public function addHeader($header) { $this->headers[] = $header; }
    public function setOutput($output) { $this->output = $output; }
}
class AnchorFrontendProducts {
    public $products;
    public function getTotalProducts($filter) { return count($this->products); }
    public function getProducts($filter) { return $this->products; }
}

$registry = new Registry();
$config = new Config();
foreach (array('module_anchor_price_status' => 1, 'config_store_id' => 0, 'config_currency' => 'EUR', 'config_language' => 'hr-hr', 'config_customer_price' => 0) as $key => $value) $config->set($key, $value);
$registry->set('config', $config);
$db = new AnchorFrontendDb();
$record = array('product_id' => 1, 'store_id' => 0, 'gross_price' => '199.00', 'currency_code' => 'EUR', 'reference_date' => '2026-09-10', 'rule_code' => 'first_listing', 'verification_status' => 'confirmed');
$db->records = array($record, array_merge($record, array('product_id' => 2, 'verification_status' => 'pending')), array_merge($record, array('product_id' => 3, 'store_id' => 1)));
$registry->set('db', $db);
$currency = new AnchorFrontendCurrency();
$registry->set('currency', $currency);
$session = (object)array('data' => array('currency' => 'EUR'));
$registry->set('session', $session);
$loader = new AnchorFrontendLoader();
$registry->set('load', $loader);
$model = new ModelExtensionModuleAnchorPrice($registry);
$registry->set('model_extension_module_anchor_price', $model);
$controller = new ControllerExtensionModuleAnchorPrice($registry);

$display = $model->getDisplayData($record);
anchorFrontendAssert($display['anchor_price_text'] === 'Cijena na 10. 9. 2026.: 199,00 EUR', 'Reference price must carry its configured reference date.');
anchorFrontendAssert($display['anchor_price_label'] === 'Cijena na 10. 9. 2026.:', 'Croatian reference label must be available separately from its amount.');
$session->data['currency'] = 'USD';
anchorFrontendAssert($model->getDisplayData($record)['anchor_price_value'] === '398,00 USD', 'A stored snapshot must convert its original currency exactly once.');
$session->data['currency'] = 'EUR';
$session->data['language'] = 'en-gb';
anchorFrontendAssert($model->getDisplayData($record)['anchor_price_text'] === 'Price on 10 Sep 2026: 199,00 EUR', 'Selected English must override the Croatian store default.');
anchorFrontendAssert($model->getDisplayData($record)['anchor_price_label'] === 'Price on 10 Sep 2026:', 'English reference label must be available separately from its amount.');
$config->set('config_language', 'en-gb');
$session->data['language'] = 'hr-hr';
anchorFrontendAssert($model->getDisplayData($record)['anchor_price_text'] === 'Cijena na 10. 9. 2026.: 199,00 EUR', 'Switching back to Croatian must override the store default.');
$config->set('config_language', 'hr-hr');
unset($session->data['language']);
$filtered = $model->getByProductIds(array(1, 1, 2, 3, '1 OR 1=1', -1, 0));
anchorFrontendAssert(array_keys($filtered) === array(1), 'Pending and other-store records must not enter storefront display data.');

$routes = array('product/product', 'product/quickview', 'product/category', 'product/search', 'product/special', 'product/manufacturer_info', 'product/compare', 'extension/module/featured', 'extension/module/latest', 'extension/module/special', 'extension/module/bestseller', 'extension/module/basel_products', 'account/wishlist', 'common/header', 'common/cart', 'checkout/cart', 'checkout/confirm', 'extension/quickcheckout/cart', 'extension/quickcheckout/confirm', 'blog/blog', 'basel/template/product/category');
foreach ($routes as $route) {
    $data = array('product_id' => 1, 'price' => '189,00 EUR', 'products' => array(array('product_id' => 1, 'price' => '189,00 EUR'), array('product_id' => 2, 'price' => '100,00 EUR')), 'menu' => array('product' => array('id' => 1, 'name' => 'Instrument', 'price' => '189,00 EUR', 'link' => '#'), 'category' => array('id' => 2, 'name' => 'Category')));
    $before = count($db->queries);
    $output = '';
    $controller->beforeView($route, $data, $output);
    anchorFrontendAssert(count($db->queries) === $before + 1, $route . ': references should be fetched in one batch.');
    anchorFrontendAssert(isset($data['anchor_price_text'], $data['products'][0]['anchor_price_text'], $data['menu']['product']['anchor_price_text']), $route . ': product and mega-menu references must be enriched.');
    anchorFrontendAssert(!isset($data['products'][1]['anchor_price_text']) && !isset($data['menu']['category']['anchor_price_text']), $route . ': pending products and ordinary menu IDs must remain untouched.');
    anchorFrontendAssert($output === '', $route . ': the enrichment hook must leave template source intact.');
}
foreach (array('information/information', 'mail/order_add') as $route) {
    $data = array('product_id' => 1); $before = count($db->queries);
    $controller->beforeView($route, $data);
    anchorFrontendAssert(count($db->queries) === $before && !isset($data['anchor_price_text']), 'Unrelated views must not trigger reference queries.');
}
$config->set('module_anchor_price_status', 0);
$route = 'product/category'; $data = array('product_id' => 1); $before = count($db->queries);
$controller->beforeView($route, $data);
anchorFrontendAssert(count($db->queries) === $before && !isset($data['anchor_price_text']), 'Disabled module must not query or enrich views.');
$config->set('module_anchor_price_status', 1);

$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader($root . '/catalog/view/theme'), array('autoescape' => false, 'cache' => false));
$templates = array(
    'basel/template/product/single_product.twig', 'basel/template/product/product.twig', 'basel/template/product/quickview.twig', 'basel/template/product/compare.twig', 'basel/template/account/wishlist.twig', 'basel/template/common/menus/mega_menu.twig', 'basel/template/common/cart.twig', 'basel/template/checkout/cart.twig', 'basel/template/checkout/confirm.twig', 'basel/template/extension/quickcheckout/cart.twig', 'basel/template/extension/quickcheckout/confirm.twig', 'basel/template/extension/module/basel_products.twig',
    'default/template/product/category.twig', 'default/template/product/search.twig', 'default/template/product/special.twig', 'default/template/product/manufacturer_info.twig', 'default/template/product/product.twig', 'default/template/product/compare.twig', 'default/template/account/wishlist.twig', 'default/template/common/cart.twig', 'default/template/checkout/cart.twig', 'default/template/checkout/confirm.twig', 'default/template/extension/module/featured.twig', 'default/template/extension/module/latest.twig', 'default/template/extension/module/bestseller.twig', 'default/template/extension/module/special.twig', 'basel/template/common/header.twig', 'default/template/common/header.twig'
);
foreach ($templates as $template) {
    $twig->compileSource(new Twig\Source(file_get_contents($root . '/catalog/view/theme/' . $template), $template));
    anchorFrontendAssert(true, $template . ' must compile.');
}
$anchor = 'Cijena na 10. 9. 2026.: 199,00 EUR <script>unsafe</script>';
$product = array('product_id' => 1, 'price' => '189,00 EUR', 'special' => false, 'anchor_price_text' => $anchor, 'name' => 'Test instrument', 'quantity' => 1, 'minimum' => 1, 'href' => '#', 'cart_id' => 1, 'key' => 1, 'option' => array());
$renderTemplates = array('basel/template/product/product.twig', 'basel/template/extension/module/basel_products.twig', 'default/template/product/category.twig', 'default/template/product/search.twig', 'default/template/product/special.twig', 'default/template/product/manufacturer_info.twig', 'default/template/product/product.twig', 'basel/template/product/single_product.twig', 'basel/template/product/quickview.twig', 'basel/template/product/compare.twig', 'basel/template/account/wishlist.twig', 'basel/template/common/cart.twig', 'basel/template/checkout/cart.twig', 'basel/template/checkout/confirm.twig', 'basel/template/extension/quickcheckout/cart.twig', 'basel/template/extension/quickcheckout/confirm.twig', 'default/template/product/compare.twig', 'default/template/account/wishlist.twig', 'default/template/checkout/cart.twig', 'default/template/checkout/confirm.twig', 'default/template/common/cart.twig', 'default/template/extension/module/featured.twig', 'default/template/extension/module/latest.twig', 'default/template/extension/module/bestseller.twig', 'default/template/extension/module/special.twig');
foreach ($renderTemplates as $template) {
    $context = array('product' => $product, 'products' => array($product), 'tabs' => array(array('products' => array($product))), 'price' => $product['price'], 'anchor_price_text' => $anchor, 'stock_badge_status' => false, 'options' => array(), 'totals' => array(), 'vouchers' => array(), 'discounts' => array(), 'confirmation_page' => true, 'img_w' => 400, 'img_h' => 400, 'img_a_w' => 80, 'img_a_h' => 80);
    $html = $twig->render($template, $context);
    anchorFrontendAssert(strpos($html, '&lt;script&gt;unsafe&lt;/script&gt;') !== false && strpos($html, '<script>unsafe</script>') === false, $template . ': reference must render as escaped text.');
    $context['price'] = false; $context['product']['price'] = false; $context['products'][0]['price'] = false; $context['tabs'][0]['products'][0]['price'] = false;
    anchorFrontendAssert(strpos($twig->render($template, $context), 'Cijena na ') === false, $template . ': reference must be hidden when prices are hidden.');
    unset($context['product']['anchor_price_text'], $context['products'][0]['anchor_price_text'], $context['tabs'][0]['products'][0]['anchor_price_text'], $context['anchor_price_text']);
    $context['price'] = $product['price']; $context['product']['price'] = $product['price']; $context['products'][0]['price'] = $product['price']; $context['tabs'][0]['products'][0]['price'] = $product['price'];
    anchorFrontendAssert(strpos($twig->render($template, $context), 'anchor-price-') === false, $template . ': absent or pending references must not render an empty label.');
}

$splitProduct = array_merge($product, $display, array(
    'anchor_price_label' => $display['anchor_price_label'] . '<script>label</script>',
    'anchor_price_value' => $display['anchor_price_value'] . '<script>value</script>'
));
$splitTemplates = array(
    'basel/template/product/category.twig', 'basel/template/product/manufacturer_info.twig',
    'basel/template/extension/module/basel_products.twig', 'default/template/extension/module/featured.twig',
    'default/template/extension/module/latest.twig', 'default/template/extension/module/bestseller.twig',
    'default/template/extension/module/special.twig'
);
foreach ($splitTemplates as $template) {
    $context = array('products' => array($splitProduct), 'tabs' => array(array('products' => array($splitProduct))), 'stock_badge_status' => false);
    $html = $twig->render($template, $context);
    anchorFrontendAssert(strpos($html, '<span class="anchor-price-label">Cijena na 10. 9. 2026.:&lt;script&gt;label&lt;/script&gt;</span> <span class="anchor-price-value">199,00 EUR&lt;script&gt;value&lt;/script&gt;</span>') !== false, $template . ': label and amount must render in separate escaped spans.');
    anchorFrontendAssert(strpos($html, '<script>label</script>') === false && strpos($html, '<script>value</script>') === false, $template . ': split reference fields must never render executable markup.');
    $context['products'][0]['price'] = false;
    $context['tabs'][0]['products'][0]['price'] = false;
    anchorFrontendAssert(strpos($twig->render($template, $context), 'anchor-price-card') === false, $template . ': split reference must remain hidden when prices are hidden.');
}

// Live search returns JSON rather than a Twig view, so it needs its own check.
$products = new AnchorFrontendProducts();
$searchProduct = array_merge($product, array('price' => 189, 'tax_class_id' => 0, 'image' => '', 'special' => 100, 'stock_status' => 'Dostupno'));
$products->products = array($searchProduct, array_merge($searchProduct, array('product_id' => 2)));
$registry->set('model_catalog_product', $products);
$registry->set('model_tool_image', new AnchorFrontendImage());
$registry->set('request', (object)array('get' => array('filter_name' => 'Test')));
$customer = new AnchorFrontendCustomer();
$registry->set('customer', $customer);
$registry->set('language', new AnchorFrontendLanguage());
$registry->set('url', new AnchorFrontendUrl());
$response = new AnchorFrontendResponse();
$registry->set('response', $response);
$registry->set('tax', new class { public function calculate($price, $class, $tax) { return $price; } });
$search = new ControllerExtensionBaselLiveSearch($registry);
$search->index();
$json = json_decode($response->output, true);
anchorFrontendAssert(!empty($json['products'][0]['anchor_price_text']) && $json['products'][1]['anchor_price_text'] === '', 'Live search must display only confirmed references.');
$config->set('config_customer_price', 1);
$search->index();
$json = json_decode($response->output, true);
anchorFrontendAssert($json['products'][0]['price'] === false && $json['products'][0]['special'] === false && $json['products'][0]['anchor_price_text'] === '', 'Anonymous live search must respect hidden prices for all price fields.');

foreach (array('basel', 'default') as $theme) {
    $footer = $twig->render($theme . '/template/common/footer.twig', array(
        'informations' => array(array('title' => 'O nama', 'href' => '/about')),
        'text_information' => 'Informacije', 'text_service' => 'Plaćanje i dostava',
        'price_list' => '/price-list', 'text_price_list' => 'Digitalni cjenik',
        'contract_withdrawal' => '/withdrawal', 'text_contract_withdrawal' => 'Obrazac za raskid ugovora',
        'text_legal_guarantee' => 'Zakonsko jamstvo – najmanje 2 godine',
        'legal_guarantee_url' => 'https://europa.eu/youreurope/jamstva_hr',
        'styles' => array(), 'scripts' => array()
    ));
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $footer);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    $info = $xpath->query('//*[self::h2 or self::h5][normalize-space()="Informacije"]/following-sibling::ul[1]')->item(0);
    anchorFrontendAssert($info && strpos($info->textContent, 'Digitalni cjenik') !== false && strpos($info->textContent, 'Obrazac za raskid ugovora') !== false, $theme . ' must place both requested links in Information.');
    $trigger = $xpath->query('//a[@data-target="#legal-guarantee-modal"]')->item(0);
    anchorFrontendAssert($trigger && $trigger->getAttribute('data-remote') === 'false', 'Bootstrap must not load the external fallback URL into the notice modal.');
    if ($theme === 'basel') {
        $assurances = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " footer-assurances ")]')->item(0);
        anchorFrontendAssert($assurances && strpos($assurances->textContent, 'Zakonsko jamstvo') !== false, 'The guarantee button must sit with the Certified Shop badge inside the footer.');
        // Existing Basel code empties every span inside .links-holder while moving
        // breadcrumbs. Exercise that behavior to prevent the blank-button regression.
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " links-holder ")]//span') as $span) {
            $span->nodeValue = '';
        }
        anchorFrontendAssert(strpos($trigger->textContent, 'Zakonsko jamstvo') !== false, 'Theme breadcrumb handling must not erase the guarantee label.');
    }
}
$priceList = $twig->render('basel/template/information/price_list.twig', array(
    'heading_title' => 'Cjenici', 'breadcrumbs' => array(array('href' => '/', 'text' => 'Početna')),
    'content_bottom' => '<div id="price-list-bottom-fixture">Brands module</div>', 'publications' => array()
));
$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML($priceList);
libxml_clear_errors();
libxml_use_internal_errors($previous);
$xpath = new DOMXPath($dom);
anchorFrontendAssert($xpath->query('//*[@id="content"]//*[@id="price-list-bottom-fixture"]')->length === 0, 'Basel bottom modules must remain outside the narrow content container.');
anchorFrontendAssert($xpath->query('//*[@id="title-holder"]')->length === 1, 'Basel must have a title destination before its script moves the page heading.');
echo 'PASS: ' . $checks . " anchor price frontend assertions.\n";
