<?php
// Run with: php tests/digital_elephant_catalog_cache_test.php
// Execute the model's real selection/count SQL against a small in-memory catalogue.
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

require_once __DIR__ . '/../system/engine/registry.php';
require_once __DIR__ . '/../system/engine/model.php';
require_once __DIR__ . '/../system/library/config.php';
require_once __DIR__ . '/../catalog/model/extension/module/digitalElephantFilter.php';

define('DB_PREFIX', 'test_');
$checks = 0;

function defCacheAssert($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class DefCacheTestDatabase {
    public $pdo;
    public $queries = array();

    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('NOW', function() { return '2026-10-31 12:00:00'; });
        $this->pdo->sqliteCreateFunction('LCASE', function($value) { return strtolower($value); });
        $this->pdo->sqliteCreateFunction('IF', function($test, $yes, $no) { return $test ? $yes : $no; });
        foreach (array(
            'product' => 'product_id INTEGER, price REAL, sort_order INTEGER, model TEXT, quantity INTEGER, date_added TEXT, manufacturer_id INTEGER, status INTEGER, stock_status_id INTEGER, date_available TEXT, tax_class_id INTEGER',
            'product_description' => 'product_id INTEGER, language_id INTEGER, name TEXT, description TEXT, tag TEXT',
            'product_to_store' => 'product_id INTEGER, store_id INTEGER',
            'product_to_category' => 'product_id INTEGER, category_id INTEGER',
            'category_path' => 'category_id INTEGER, path_id INTEGER',
            'product_filter' => 'product_id INTEGER, filter_id INTEGER',
            'product_attribute' => 'product_id INTEGER, attribute_id INTEGER, language_id INTEGER, text TEXT',
            'product_option_value' => 'product_id INTEGER, option_value_id INTEGER',
            'review' => 'product_id INTEGER, status INTEGER, rating INTEGER',
            'product_discount' => 'product_id INTEGER, customer_group_id INTEGER, quantity INTEGER, date_start TEXT, date_end TEXT, priority INTEGER, price REAL',
            'product_special' => 'product_id INTEGER, customer_group_id INTEGER, date_start TEXT, date_end TEXT, priority INTEGER, price REAL',
            'tax_rule' => 'tax_class_id INTEGER, tax_rate_id INTEGER, based TEXT',
            'tax_rate' => 'tax_rate_id INTEGER, type TEXT, rate REAL, geo_zone_id INTEGER',
            'tax_rate_to_customer_group' => 'tax_rate_id INTEGER, customer_group_id INTEGER',
            'zone_to_geo_zone' => 'country_id INTEGER, zone_id INTEGER, geo_zone_id INTEGER',
            'geo_zone' => 'geo_zone_id INTEGER'
        ) as $table => $columns) {
            $this->pdo->exec('CREATE TABLE test_' . $table . ' (' . $columns . ')');
        }
        foreach (array(1 => 30, 2 => 10, 3 => 20, 4 => 50, 5 => 60, 6 => 70, 7 => 40) as $id => $price) {
            $this->pdo->exec("INSERT INTO test_product VALUES ($id, $price, $id, 'M$id', 1, '2026-01-01', " . ($id % 2 ? 1 : 2) . ", 1, " . ($id === 6 ? 9 : 1) . ", '" . ($id === 4 ? '2099-01-01' : '2026-01-01') . "', 0)");
            $this->pdo->exec("INSERT INTO test_product_description VALUES ($id, 2, 'Product $id', 'Description', '')");
            $this->pdo->exec("INSERT INTO test_product_to_store VALUES ($id, " . ($id === 5 ? 1 : 0) . ")");
            $this->pdo->exec("INSERT INTO test_product_to_category VALUES ($id, 10)");
        }
        $this->pdo->exec("INSERT INTO test_category_path VALUES (10, 10), (11, 10), (11, 11)");
        $this->pdo->exec("INSERT INTO test_product_to_category VALUES (1, 11)");
        $this->pdo->exec("INSERT INTO test_product_attribute VALUES (1, 7, 2, 'Black'), (1, 8, 2, 'Small'), (2, 7, 1, 'Black'), (2, 8, 2, 'Small'), (3, 7, 2, 'White'), (3, 8, 2, 'Large')");
        $this->pdo->exec("INSERT INTO test_product_option_value VALUES (1, 10), (3, 10), (2, 20)");
        $this->pdo->exec("INSERT INTO test_product_filter VALUES (1, 6), (3, 6)");
        $this->pdo->exec("INSERT INTO test_product_discount VALUES (1, 1, 1, '0000-00-00', '0000-00-00', 1, 25)");
        $this->pdo->exec("INSERT INTO test_product_special VALUES (1, 1, '0000-00-00', '0000-00-00', 1, 15)");
    }

    public function query($sql) {
        // MySQL connection setting has no equivalent or side effect in SQLite.
        if ($sql === 'SET SQL_BIG_SELECTS=1') {
            return (object)array('rows' => array(), 'row' => array(), 'num_rows' => 0);
        }
        $this->queries[] = $sql;
        // MySQL coerces quoted numeric price bounds; SQLite expression
        // comparisons need numeric literals to provide that same behavior.
        $sqlite_sql = preg_replace("/([<>]=) '(-?[0-9]+(?:\\.[0-9]+)?)'/", '$1 $2', $sql);
        $rows = $this->pdo->query($sqlite_sql)->fetchAll(PDO::FETCH_ASSOC);
        return (object)array('rows' => $rows, 'row' => $rows ? $rows[0] : array(), 'num_rows' => count($rows));
    }

    public function escape($value) {
        return str_replace("'", "''", (string)$value);
    }
}

class DefCacheTestCache {
    public $entries = array();
    public function getTimed($key) { return array_key_exists($key, $this->entries) ? $this->entries[$key] : false; }
    public function setTimed($key, $value, $ttl) { $this->entries[$key] = $value; }
}

class DefCacheTestCustomer {
    public function isLogged() { return false; }
}

class DefCacheTestLoader {
    public function model($route) {}
}

class DefCacheTestProducts {
    public $loads = array();
    public function getProducts($data) {
        $this->loads[] = $data['filter_product_ids'];
        $products = array();
        // Return a deliberately different order to verify the ID order wins.
        foreach (array_reverse($data['filter_product_ids']) as $id) {
            $products[$id] = array('product_id' => $id);
        }
        return $products;
    }
    public function getProduct($id) {
        $this->loads[] = array($id);
        return array('product_id' => $id);
    }
}

class DefCacheTestCurrency {
    public function convert($value, $from, $to) { return $value; }
}

function defCacheFixture() {
    $registry = new Registry();
    $db = new DefCacheTestDatabase();
    $config = new Config();
    foreach (array('config_store_id' => 0, 'config_language_id' => 2, 'config_customer_group_id' => 1, 'config_currency' => 'EUR', 'config_country_id' => 53, 'config_zone_id' => 0) as $key => $value) {
        $config->set($key, $value);
    }
    $session = new stdClass();
    $session->data = array('currency' => 'EUR');
    $products = new DefCacheTestProducts();
    foreach (array('db' => $db, 'config' => $config, 'cache' => new DefCacheTestCache(), 'session' => $session, 'customer' => new DefCacheTestCustomer(), 'load' => new DefCacheTestLoader(), 'currency' => new DefCacheTestCurrency(), 'model_catalog_product' => $products) as $key => $value) {
        $registry->set($key, $value);
    }
    return array(new ModelExtensionModuleDigitalElephantFilter($registry), $db, $products, $registry);
}

function defCacheParams(array $changes = array()) {
    return array_replace(array(
        'filter_category_id' => 10, 'filter_sub_category' => true, 'filter_filter' => '',
        'sub_categories' => array(), 'manufacturers' => array(), 'options' => array(), 'attributes' => array(),
        'price' => array('min' => '', 'max' => ''), 'sort' => 'p.price', 'order' => 'ASC', 'start' => 0, 'limit' => 2
    ), $changes);
}

list($model, $db, $products, $registry) = defCacheFixture();
$params = defCacheParams();
defCacheAssert($model->getTotalProducts($params) === 4, 'Counts must deduplicate category joins and exclude future, other-store and discontinued products.');
$count_sql = $db->queries[0];
defCacheAssert(strpos($count_sql, 'COUNT(DISTINCT p.product_id)') !== false, 'The count must be calculated in the database.');
defCacheAssert(!preg_match('/ORDER BY|GROUP BY|test_review|AS special|AS discount/i', $count_sql), 'Counts must not calculate per-product sort values or sort every matching row.');
defCacheAssert($model->getTotalProducts(defCacheParams(array('start' => 2, 'limit' => 100, 'sort' => 'pd.name', 'order' => 'DESC'))) === 4 && count($db->queries) === 1, 'Total cache hits must survive page, limit and sort changes.');

defCacheAssert(array_keys($model->getProducts($params)) === array(2, 1), 'The first page must respect effective special-price ordering after a count query.');
defCacheAssert(count($db->queries) === 2 && $products->loads[0] === array(2, 1), 'Only the current page of ordered IDs must be loaded in bulk.');
$second_request = new ModelExtensionModuleDigitalElephantFilter($registry);
defCacheAssert(array_keys($second_request->getProducts(defCacheParams(array('start' => 2)))) === array(3, 7), 'A second request must read the next page from cached ordered IDs.');
defCacheAssert(count($db->queries) === 2 && $products->loads[1] === array(3, 7), 'Pagination must not repeat the complete selection query.');
defCacheAssert(array_keys($model->getProducts($params)) === array(2, 1) && count($products->loads) === 2, 'A repeated rendered-product page must reuse its cache.');
defCacheAssert(array_keys($model->getProducts(defCacheParams(array('sort' => 'pd.name', 'order' => 'DESC')))) === array(7, 3), 'Changing sort on one model instance must not reuse its previous SQL ordering.');
defCacheAssert(count($db->queries) === 3, 'A different ordering must have its own ordered ID cache.');
defCacheAssert(array_keys($model->getProducts(defCacheParams(array('sort' => 'pd.name', 'order' => 'DESC', 'start' => 2)))) === array(2, 1) && count($db->queries) === 3, 'The new ordering must also be reused across pages.');
defCacheAssert($model->getProducts(defCacheParams(array('start' => 1000))) === array() && count($db->queries) === 3, 'An out-of-range page must not trigger another complete selection.');

// Compare counts and ID selection using actual SQL for every supported filter family.
$cases = array(
    array(array('attributes' => array(7 => array('Black'))), array(1)),
    array(array('attributes' => array(7 => array('Black', 'White'), 8 => array('Small'))), array(1)),
    array(array('attributes' => array(7 => array("O'Reilly"))), array()),
    array(array('attributes' => array(0 => array('Ignored'))), array(2, 1, 3)),
    array(array('attributes' => array(7 => array(''))), array(2, 1, 3)),
    array(array('manufacturers' => array('manufacturer' => array(2))), array(2)),
    array(array('options' => array(4 => array(10))), array(1, 3)),
    array(array('filter_filter' => '6'), array(1, 3)),
    array(array('sub_categories' => array(11)), array(1)),
    array(array('price' => array('min' => 12, 'max' => 26)), array(1, 3))
);
foreach ($cases as $case) {
    list($fresh_model, $fresh_db) = defCacheFixture();
    $filtered = defCacheParams($case[0]);
    $total = $fresh_model->getTotalProducts($filtered);
    $ids = $fresh_model->getProductIds($filtered);
    defCacheAssert($total === count($case[1]) && $ids === $case[1], 'Filtered count and ordered ID results must agree: ' . json_encode($case[0]));
    $queries = count($fresh_db->queries);
    defCacheAssert($fresh_model->getTotalProducts(array_replace($filtered, array('start' => 100, 'sort' => 'pd.name', 'order' => 'DESC'))) === $total && count($fresh_db->queries) === $queries, 'Filtered totals must be shared across list presentation changes.');
}

list($fresh_model, $fresh_db, $fresh_products, $fresh_registry) = defCacheFixture();
$fresh_model->getProductIds($params);
defCacheAssert($fresh_model->getTotalProducts($params) === 4 && count($fresh_db->queries) === 1, 'A freshly calculated ID list must also populate its total cache.');
$fresh_registry->get('config')->set('module_boost_special_manager_include', '1');
defCacheAssert(array_keys($fresh_model->getProducts($params)) === array(2, 1) && $fresh_products->loads === array(array(2), array(1)), 'Boost special manager must retain per-product loading for only the requested page.');

echo 'PASS: ' . $checks . " digital elephant catalogue cache assertions; warnings are test failures.\n";
