<?php
namespace Cache {
    function time() {
        return $GLOBALS['timed_cache_test_now'];
    }

    class TimedTestAdapter {
        public static $entries = array();

        public function __construct($expire) {}

        public function get($key) {
            return array_key_exists($key, self::$entries) ? self::$entries[$key] : false;
        }

        public function set($key, $value) {
            self::$entries[$key] = $value;
            return true;
        }

        public function delete($prefix) {
            foreach (array_keys(self::$entries) as $key) {
                if ($prefix === '*' || strpos($key, $prefix) === 0) {
                    unset(self::$entries[$key]);
                }
            }
        }
    }
}

namespace TimedModelFixture {
    // Old five-minute bucket keys must also see the deterministic test clock.
    function time() {
        return $GLOBALS['timed_cache_test_now'];
    }
}

namespace {
    error_reporting(E_ALL);
    set_error_handler(function($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        return true;
    });

    require_once __DIR__ . '/../system/library/cache.php';
    require_once __DIR__ . '/../system/engine/model.php';
    require_once __DIR__ . '/../system/engine/registry.php';
    require_once __DIR__ . '/../system/library/config.php';

    class TimedTestCache extends Cache {
        protected function timedCacheNow() {
            return $GLOBALS['timed_cache_test_now'];
        }
    }

    class TimedTestCustomer {
        public $logged = false;
        public $group = 1;

        public function isLogged() { return $this->logged; }
        public function getGroupId() { return $this->group; }
    }

    class TimedTestDatabase {
        public function query($sql) {
            throw new RuntimeException('A preseeded timed cache hit unexpectedly queried the database.');
        }
    }

    $checks = 0;
    function timedCacheAssert($condition, $message) {
        global $checks;
        $checks++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function timedModelKey($model, $method, $type, array $data) {
        $reflection = new ReflectionMethod($model, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($model, $type, $data);
    }

    $timed_cache_test_now = 1200;
    $cache = new TimedTestCache('TimedTestAdapter');
    $ordinary = array('expires_at' => 1, 'value' => array('legacy' => true));
    timedCacheAssert($cache->set('ordinary', $ordinary) === true, 'Ordinary set must preserve the adapter return value.');
    timedCacheAssert($cache->get('ordinary') === $ordinary, 'Ordinary get must preserve an envelope-shaped legacy value without unwrapping it.');
    foreach (array(array(), 0, false, null, '', 'plain') as $index => $value) {
        $cache->set('ordinary.' . $index, $value);
        timedCacheAssert($cache->get('ordinary.' . $index) === $value, 'Ordinary values must retain their types.');
        timedCacheAssert($cache->setTimed('timed.' . $index, $value, 300) === true, 'Timed set must preserve the adapter return value.');
        timedCacheAssert($cache->get('timed.' . $index) === array('expires_at' => 1500, 'value' => $value), 'Timed storage must contain its deadline and the unchanged value.');
    }
    $timed_cache_test_now = 1499;
    foreach (array(array(), 0, false, null, '', 'plain') as $index => $value) {
        timedCacheAssert($cache->getTimed('timed.' . $index) === $value, 'Timed values must remain fresh 299 seconds after writing.');
    }
    $timed_cache_test_now = 1500;
    foreach (array(array(), 0, false, null, '', 'plain') as $index => $value) {
        timedCacheAssert($cache->getTimed('timed.' . $index) === false, 'Timed values must expire at exactly 300 seconds.');
    }
    timedCacheAssert($cache->get('ordinary') === $ordinary, 'Timed expiry must not affect ordinary get semantics.');
    foreach (array(null, false, 'legacy', array(), array('value' => 'missing deadline'), array('expires_at' => 1800), array('expires_at' => 'bad', 'value' => 1), array('expires_at' => null, 'value' => 1), array('expires_at' => 1500, 'value' => 1)) as $index => $entry) {
        $cache->set('malformed.' . $index, $entry);
        timedCacheAssert($cache->getTimed('malformed.' . $index) === false, 'Malformed or expired timed entries must be cache misses.');
    }
    $cache->setTimed('immediate', 'value', 0);
    timedCacheAssert($cache->getTimed('immediate') === false, 'A zero TTL must expire immediately.');
    $cache->setTimed('negative', 'value', -1);
    timedCacheAssert($cache->getTimed('negative') === false, 'A negative TTL must expire immediately.');

    // Load the actual model sources into a namespace with a controllable clock.
    foreach (array('catalog/model/catalog/product.php', 'catalog/model/extension/module/digitalElephantFilter.php') as $path) {
        $source = file_get_contents(__DIR__ . '/../' . $path);
        eval('namespace TimedModelFixture; use \\Model; ' . preg_replace('/^<\\?php\\s*/', '', $source));
    }
    $registry = new Registry();
    $config = new Config();
    foreach (array('config_store_id' => 0, 'config_language_id' => 2, 'config_customer_group_id' => 1, 'config_currency' => 'EUR') as $key => $value) {
        $config->set($key, $value);
    }
    $session = new stdClass();
    $session->data = array();
    $customer = new TimedTestCustomer();
    $registry->set('config', $config);
    $registry->set('session', $session);
    $registry->set('customer', $customer);
    $registry->set('cache', $cache);
    $registry->set('db', new TimedTestDatabase());
    $product = new TimedModelFixture\ModelCatalogProduct($registry);
    $filter = new TimedModelFixture\ModelExtensionModuleDigitalElephantFilter($registry);
    $params = array('filter_category_id' => 12, 'start' => 0, 'limit' => 20, 'nested' => array('b' => 2, 'a' => 1));

    foreach (array(array($product, 'productQueryCacheKey'), array($filter, 'filterCacheKey')) as $fixture) {
        list($model, $method) = $fixture;
        $total_key = timedModelKey($model, $method, 'total', $params);
        $cache->setTimed($total_key, 42, 300);
        $other_page = array_replace($params, array('start' => 100, 'limit' => 100, 'sort' => 'p.price', 'order' => 'DESC'));
        timedCacheAssert(timedModelKey($model, $method, 'total', $other_page) === $total_key, 'Totals must share cache across pagination and sorting.');
        timedCacheAssert($model->getTotalProducts($other_page) === 42, 'An unchanged filtered total must not query the database for a different page.');
        timedCacheAssert(timedModelKey($model, $method, 'total', array_replace($params, array('filter_category_id' => 13))) !== $total_key, 'Totals must keep different category filters separate.');
    }

    $timed_cache_test_now = 299;
    $product_key = timedModelKey($product, 'productQueryCacheKey', 'list', $params);
    $filter_key = timedModelKey($filter, 'filterCacheKey', 'products', $params);
    $products = array(42 => array('product_id' => 42, 'name' => 'Guitar'));
    $cache->setTimed($product_key, $products, 300);
    $cache->setTimed($filter_key, $products, 300);
    $timed_cache_test_now = 301;
    timedCacheAssert(timedModelKey($product, 'productQueryCacheKey', 'list', $params) === $product_key, 'Product keys must remain stable across a five-minute clock boundary.');
    timedCacheAssert(timedModelKey($filter, 'filterCacheKey', 'products', $params) === $filter_key, 'Filter keys must remain stable across a five-minute clock boundary.');
    timedCacheAssert($product->getProducts($params) === $products, 'Product list reads must reuse a still-fresh timed hit across the clock boundary.');
    timedCacheAssert($filter->getProducts($params) === $products, 'Filter product reads must reuse a still-fresh timed hit across the clock boundary.');

    $reordered = array('nested' => array('a' => 1, 'b' => 2), 'limit' => 20, 'start' => 0, 'filter_category_id' => 12);
    foreach (array(array($product, 'productQueryCacheKey', $product_key), array($filter, 'filterCacheKey', $filter_key)) as $fixture) {
        list($model, $method, $base) = $fixture;
        $type = $method === 'productQueryCacheKey' ? 'list' : 'products';
        timedCacheAssert(timedModelKey($model, $method, $type, $reordered) === $base, 'Reordering associative query parameters must preserve cache identity.');
        timedCacheAssert(timedModelKey($model, $method, $type, array_replace($params, array('limit' => 21))) !== $base, 'Different query parameters must use separate keys.');
        timedCacheAssert(timedModelKey($model, $method, 'total', $params) !== $base, 'Different result types must use separate keys.');
        foreach (array('config_store_id', 'config_language_id', 'config_customer_group_id') as $setting) {
            $original = $config->get($setting);
            $config->set($setting, $original + 1);
            timedCacheAssert(timedModelKey($model, $method, $type, $params) !== $base, 'Cache identity must separate ' . $setting . '.');
            $config->set($setting, $original);
        }
    }
    $customer->logged = true;
    $customer->group = 8;
    $logged_key = timedModelKey($filter, 'filterCacheKey', 'products', $params);
    timedCacheAssert($logged_key !== $filter_key, 'Filter keys must use the actual logged-in customer group.');
    $customer->group = 9;
    timedCacheAssert(timedModelKey($filter, 'filterCacheKey', 'products', $params) !== $logged_key, 'Distinct logged-in customer groups must use separate filter keys.');
    $customer->logged = false;
    foreach (array('currency' => 'USD', 'shipping_country_id' => 53, 'shipping_zone_id' => 7, 'payment_country_id' => 54, 'payment_zone_id' => 8) as $setting => $value) {
        $session->data[$setting] = $value;
        timedCacheAssert(timedModelKey($filter, 'filterCacheKey', 'products', $params) !== $filter_key, 'Filter identity must separate session ' . $setting . '.');
        unset($session->data[$setting]);
    }
    $config->set('config_currency', 'USD');
    timedCacheAssert(timedModelKey($filter, 'filterCacheKey', 'products', $params) !== $filter_key, 'Filter identity must separate the fallback configured currency.');
    $config->set('config_currency', 'EUR');

    $cache->setTimed(timedModelKey($product, 'productQueryCacheKey', 'total', $params), 0, 300);
    timedCacheAssert($product->getTotalProducts($params) === 0, 'A cached zero product total must avoid a database query.');
    $cache->setTimed(timedModelKey($product, 'productQueryCacheKey', 'list', array()), array(), 300);
    timedCacheAssert($product->getProducts() === array(), 'An empty product list must remain a cache hit.');
    $settings = array('advance' => array('attributes' => array('a' => 1), 'manufacturers' => array('m' => 1), 'options' => array('o' => 1), 'categories' => array('c' => 1)));
    foreach (array('attributes' => 'getAttributes', 'manufacturers' => 'getManufacturers', 'options' => 'getOptions') as $type => $method) {
        $key = timedModelKey($filter, 'filterCacheKey', $type, array('settings' => $settings['advance'][$type], 'data' => $params));
        $cache->setTimed($key, array(), 300);
        timedCacheAssert($filter->$method($settings, $params) === array(), 'An empty cached filter ' . $type . ' result must avoid a database query.');
    }
    $price = array('min' => '10.00', 'max' => '99.00');
    $cache->setTimed(timedModelKey($filter, 'filterCacheKey', 'price', array('category_id' => 12)), $price, 300);
    timedCacheAssert($filter->getMinMaxPrice(12) === $price, 'Filter price reads must use timed cache hits.');
    $cache->setTimed(timedModelKey($filter, 'filterCacheKey', 'subcategories', array('category_id' => 12, 'settings' => $settings['advance']['categories'])), array(), 300);
    timedCacheAssert($filter->getSubCategories(12, $settings) === array(), 'Empty cached subcategories must avoid a database query.');
    $cache->setTimed(timedModelKey($filter, 'filterCacheKey', 'total', $params), 0, 300);
    timedCacheAssert($filter->getTotalProducts($params) === 0, 'A cached zero filter total must avoid a database query.');
    $id_params = $params;
    unset($id_params['start'], $id_params['limit']);
    $cache->setTimed(timedModelKey($filter, 'filterCacheKey', 'product_ids', $id_params), array('42', '7'), 300);
    timedCacheAssert($filter->getProductIds($params) === array(42, 7), 'Cached product IDs must retain order and be normalized to integers.');

    $cache->setTimed($product_key, array(), 300);
    timedCacheAssert($cache->getTimed($product_key) === array(), 'Rewriting a stable key must replace its previous timed payload.');
    timedCacheAssert(count(array_filter(array_keys(Cache\TimedTestAdapter::$entries), function($key) use ($product_key) { return $key === $product_key; })) === 1, 'Rewriting a stable key must retain a single stored entry.');
    $cache->delete('product');
    timedCacheAssert($cache->getTimed($product_key) === false && $cache->getTimed($filter_key) === false, 'Existing product prefix invalidation must clear timed product and filter entries.');
    timedCacheAssert($cache->get('ordinary') === $ordinary, 'Product prefix invalidation must preserve unrelated ordinary entries.');

    // Exercise the actual JSON file adapter, whose physical TTL stays longer.
    require_once __DIR__ . '/../system/library/cache/file.php';
    define('DIR_CACHE', sys_get_temp_dir() . '/glazbena-timed-cache-test-' . bin2hex(random_bytes(8)) . '/');
    mkdir(DIR_CACHE);
    register_shutdown_function(function() {
        foreach (new DirectoryIterator(DIR_CACHE) as $file) {
            if (!$file->isDot()) {
                unlink($file->getPathname());
            }
        }
        rmdir(DIR_CACHE);
    });
    $timed_cache_test_now = 1800;
    $file_cache = new TimedTestCache('File', 3600);
    $file_cache->setTimed('product.query.test', array('ids' => array(42), 'empty' => null), 300);
    timedCacheAssert($file_cache->getTimed('product.query.test') === array('ids' => array(42), 'empty' => null), 'Timed envelopes must roundtrip through the actual JSON file adapter.');
    $timed_cache_test_now = 2099;
    timedCacheAssert($file_cache->getTimed('product.query.test') === array('ids' => array(42), 'empty' => null), 'The file adapter must serve a timed entry at age 299 seconds.');
    $timed_cache_test_now = 2100;
    timedCacheAssert($file_cache->getTimed('product.query.test') === false && count(glob(DIR_CACHE . 'cache.product.query.test.*')) === 1, 'Logical TTL must expire at 300 seconds while its longer-lived cache file still exists.');
    $file_cache->setTimed('product.query.test', array(7), 300);
    timedCacheAssert($file_cache->getTimed('product.query.test') === array(7) && count(glob(DIR_CACHE . 'cache.product.query.test.*')) === 1, 'Rewriting a timed file key must replace the old physical entry.');
    $file_cache->setTimed('product.filter.test', array(), 300);
    $file_cache->set('unrelated', 'keep');
    $file_cache->delete('product');
    timedCacheAssert($file_cache->getTimed('product.query.test') === false && $file_cache->getTimed('product.filter.test') === false && glob(DIR_CACHE . 'cache.product.*') === array(), 'Actual file adapter prefix invalidation must remove timed query and filter entries.');
    timedCacheAssert($file_cache->get('unrelated') === 'keep', 'File adapter product invalidation must preserve unrelated entries.');

    echo 'PASS: ' . $checks . " timed catalogue cache assertions; warnings are test failures.\n";
}
