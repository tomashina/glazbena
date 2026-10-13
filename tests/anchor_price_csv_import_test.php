<?php
// Real MySQL transaction tests. Only connection-local TEMPORARY tables are written.
// Run: php tests/anchor_price_csv_import_test.php
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__);
require $root . '/config.php';
require $root . '/system/engine/registry.php';
require $root . '/system/engine/model.php';
require $root . '/system/engine/controller.php';
require $root . '/system/library/config.php';
require $root . '/system/helper/utf8.php';
require $root . '/admin/model/extension/module/anchor_price.php';
require $root . '/admin/controller/extension/module/anchor_price.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connection = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
$connection->set_charset('utf8mb4');
$connection->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");

class AnchorCsvFixtureDb {
    public $connection;
    public $writes = 0;
    public $failAuditAt = 0;
    public $beforeBegin;
    private $auditWrites = 0;
    private $prefix;
    public function __construct($connection) {
        $this->connection = $connection;
        $this->prefix = 'anchor_csv_' . bin2hex(random_bytes(5)) . '_';
    }
    public function escape($value) { return $this->connection->real_escape_string((string)$value); }
    public function getLastId() { return $this->connection->insert_id; }
    public function countAffected() { return $this->connection->affected_rows; }
    public function query($sql) {
        if ($sql === 'START TRANSACTION' && $this->beforeBegin) {
            $callback = $this->beforeBegin;
            $this->beforeBegin = null;
            $callback();
        }
        $sql = str_replace('`' . DB_PREFIX, '`' . $this->prefix, $sql);
        if (preg_match('/^(INSERT|UPDATE|DELETE)\b/i', $sql)) $this->writes++;
        if ($this->failAuditAt && strpos($sql, 'INSERT INTO `' . $this->prefix . 'anchor_price_audit`') === 0) {
            $this->auditWrites++;
            if ($this->auditWrites === $this->failAuditAt) throw new RuntimeException('Deliberate audit failure');
        }
        $result = $this->connection->query($sql);
        if (!$result instanceof mysqli_result) return $result;
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return (object)array('rows' => $rows, 'row' => $rows ? reset($rows) : array(), 'num_rows' => count($rows));
    }
}
class AnchorCsvFixtureTax {
    public function getRates($price, $class) { return array(); }
    public function calculate($price, $class, $tax) { return $price; }
}
class AnchorCsvFixtureUser {
    public $access = true;
    public $modify = true;
    public $managerModify = false;
    public function hasPermission($type, $route) { return $route === 'extension/extension/module' && $type === 'modify' ? $this->managerModify : $this->$type; }
    public function isLogged() { return true; }
    public function getId() { return 7; }
    public function getGroupId() { return 1; }
}
class AnchorCsvFixtureLoader {
    public $models = 0;
    public function language($route) {}
    public function model($route) { $this->models++; }
}
class AnchorCsvFixtureResponse {
    public $headers = array();
    public $redirect;
    public $output;
    public function addHeader($value) { $this->headers[] = $value; }
    public function redirect($value) { $this->redirect = $value; }
    public function setOutput($value) { $this->output = $value; }
}
class AnchorCsvFixtureLanguage { public function get($key) { return $key; } }
class AnchorCsvFixtureUrl { public function link($route, $query, $secure) { return $route . '?' . $query; } }
class AnchorCsvFixtureLifecycle {
    public $installs = 0;
    public $uninstalls = 0;
    public $settings = array('module_anchor_price_reference_date' => '2026-10-01', 'module_anchor_price_cron_key' => 'fixture-key');
    public function install() { $this->installs++; }
    public function uninstall() { $this->uninstalls++; }
    public function seedExistingProducts($user) {}
    public function getSetting($code, $store) { return $this->settings; }
    public function editSetting($code, $settings, $store) { $this->settings = $settings; }
    public function getUserGroup($group) { return array('permission' => array()); }
    public function addPermission($group, $type, $route) {}
    public function deleteEventByCode($code) {}
    public function addEvent($code, $trigger, $action) {}
}

$checks = 0;
function anchorCsvAssert($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function anchorCsvReject($model, $content, $message) {
    $file = tempnam(sys_get_temp_dir(), 'anchor-csv-');
    file_put_contents($file, $content);
    $failed = false;
    try { $model->importCsv($file, 7); } catch (Exception $exception) { $failed = true; }
    finally { unlink($file); }
    anchorCsvAssert($failed, $message);
}
function anchorCsvImport($model, $content) {
    $file = tempnam(sys_get_temp_dir(), 'anchor-csv-');
    file_put_contents($file, $content);
    try { return $model->importCsv($file, 7); } finally { unlink($file); }
}

$db = new AnchorCsvFixtureDb($connection);
$registry = new Registry();
$registry->set('db', $db);
$registry->set('tax', new AnchorCsvFixtureTax());
$registry->set('config', new Config());
$model = new ModelExtensionModuleAnchorPrice($registry);
$header = "product_id;model;price;gross_price;reference_date;verification_status;reason\n";
$date = (new DateTime('now', new DateTimeZone('Europe/Zagreb')))->format('Y-m-d');

try {
    $db->query('CREATE TEMPORARY TABLE `' . DB_PREFIX . 'product` (product_id INT PRIMARY KEY, model VARCHAR(100), tax_class_id INT, status INT, date_added DATETIME, price DECIMAL(15,4) DEFAULT 0, date_available DATE DEFAULT "2026-01-01") ENGINE=InnoDB');
    $db->query('CREATE TEMPORARY TABLE `' . DB_PREFIX . 'product_to_store` (product_id INT, store_id INT, PRIMARY KEY(product_id,store_id)) ENGINE=InnoDB');
    $db->query('CREATE TEMPORARY TABLE `' . DB_PREFIX . 'product_special` (product_id INT,customer_group_id INT,price DECIMAL(15,4),priority INT,date_start DATE,date_end DATE) ENGINE=InnoDB');
    $db->query('CREATE TEMPORARY TABLE `' . DB_PREFIX . 'product_discount` (product_id INT,customer_group_id INT,price DECIMAL(15,4),quantity INT,priority INT,date_start DATE,date_end DATE) ENGINE=InnoDB');
    $db->query('CREATE TEMPORARY TABLE `' . DB_PREFIX . 'anchor_price` (anchor_price_id INT AUTO_INCREMENT PRIMARY KEY, product_id INT, store_id INT, price DECIMAL(15,4), gross_price DECIMAL(15,4), currency_code CHAR(3), tax_class_id INT, tax_context TEXT, reference_date DATE, rule_code VARCHAR(32), source VARCHAR(32), verification_status VARCHAR(20), created_by INT, date_added DATETIME, date_modified DATETIME, UNIQUE KEY product_store(product_id,store_id)) ENGINE=InnoDB');
    $db->query('CREATE TEMPORARY TABLE `' . DB_PREFIX . 'anchor_price_audit` (audit_id INT AUTO_INCREMENT PRIMARY KEY, anchor_price_id INT, product_id INT, store_id INT, user_id INT, action VARCHAR(32), old_data LONGTEXT, new_data LONGTEXT, reason VARCHAR(255), date_added DATETIME) ENGINE=InnoDB');
    $db->query("INSERT INTO `" . DB_PREFIX . "product` (product_id,model,tax_class_id,status,date_added) VALUES (1,'ONE',0,1,'2026-01-01'),(2,'TWO',0,1,'2026-01-01'),(3,'DUPLICATE',0,0,'2026-01-01'),(4,'DUPLICATE',0,0,'2026-01-01'),(5,'NEW',0,1,'2026-10-01')");
    $db->query('INSERT INTO `' . DB_PREFIX . 'product_to_store` VALUES (1,0),(2,0),(3,0),(4,0),(5,0)');
    $db->query("INSERT INTO `" . DB_PREFIX . "anchor_price` (product_id,store_id,price,gross_price,currency_code,tax_class_id,tax_context,reference_date,rule_code,source,verification_status,created_by,date_added,date_modified) VALUES (1,0,10,10,'EUR',0,'{}','2026-09-10','baseline_2026_09_10','install','pending',0,NOW(),NOW()),(2,0,20,20,'EUR',0,'{}','2026-09-10','baseline_2026_09_10','install','pending',0,NOW(),NOW())");
    $db->writes = 0;

    anchorCsvReject($model, $header . "1;ONE;11;11;2026-09-10;confirmed;Verified invoice\n2;TWO;bad;22;2026-09-10;confirmed;Verified invoice\n", 'Reject an invalid later row');
    anchorCsvAssert($db->writes === 0, 'Validate the complete file before any writes');
    anchorCsvAssert($db->query('SELECT price FROM `' . DB_PREFIX . "anchor_price` WHERE product_id=1")->row['price'] === '10.0000', 'Earlier valid row remains unchanged');
    anchorCsvReject($model, $header . "1;ONE;11;11;2026-09-10;confirmed;Verified invoice\n;ONE;12;12;2026-09-10;confirmed;Verified invoice\n", 'Reject a duplicate product using different identifiers');
    anchorCsvReject($model, $header . ";DUPLICATE;1;1;2026-09-10;pending;Verified invoice\n", 'Reject ambiguous models');
    anchorCsvReject($model, $header . "1;TWO;1;1;2026-09-10;confirmed;Verified invoice\n", 'Reject conflicting model and product ID');
    foreach (array('-1', '1e3', 'INF', '1.23456', '123456789012', '1,2,3') as $amount) {
        anchorCsvReject($model, $header . "1;ONE;" . $amount . ";1;2026-09-10;confirmed;Verified invoice\n", 'Reject invalid amount ' . $amount);
    }
    anchorCsvReject($model, $header . "1;ONE;1;1;2026-02-30;confirmed;Verified invoice\n", 'Reject an impossible date');
    anchorCsvReject($model, $header . "1;ONE;1;1;2099-10-13;confirmed;Verified invoice\n", 'Reject a future date');
    anchorCsvReject($model, $header . "5;NEW;1;1;2026-09-10;confirmed;Verified invoice\n", 'Reject fabricated baseline for a later product');
    anchorCsvReject($model, $header . "1;ONE;1;1;2026-09-10;wrong;Verified invoice\n", 'Reject unknown status');
    anchorCsvReject($model, $header . "1;ONE;1;1;2026-09-10;confirmed;x\n", 'Require audit evidence');
    anchorCsvReject($model, $header . "1;ONE;1;1;2026-09-10;confirmed;\xFF\n", 'Reject invalid UTF-8');
    anchorCsvReject($model, "product_id;price;price;gross_price;reference_date;verification_status;reason\n", 'Reject duplicate headers');
    anchorCsvAssert($db->writes === 0, 'Rejected input cannot alter prices or audits');

    anchorCsvAssert(anchorCsvImport($model, "\xEF\xBB\xBF" . $header . "1;ONE;11,25;11,25;2026-09-10;confirmed;Verified invoice\n;TWO;22.5;22.5;2026-09-10;confirmed;Potvrđeni arhiv\n") === 2, 'Import BOM and decimal comma with ID or unique model');
    anchorCsvAssert($db->query('SELECT price FROM `' . DB_PREFIX . "anchor_price` WHERE product_id=1")->row['price'] === '11.2500', 'Commit validated price');
    anchorCsvAssert((int)$db->query('SELECT COUNT(*) AS total FROM `' . DB_PREFIX . 'anchor_price_audit`')->row['total'] === 2, 'Audit every updated row');
    $audit = $db->query('SELECT * FROM `' . DB_PREFIX . 'anchor_price_audit` ORDER BY audit_id LIMIT 1')->row;
    anchorCsvAssert(json_decode($audit['old_data'], true)['price'] === '10.0000' && json_decode($audit['new_data'], true)['source'] === 'csv_import' && (int)$audit['user_id'] === 7, 'Retain old/new values, source and administrator');
    anchorCsvAssert(anchorCsvImport($model, "product_id,price,gross_price,reference_date,verification_status,reason\n5,30,30," . $date . ",confirmed,Verified first listing\n") === 1, 'Create an explicit missing anchor from comma-separated CSV');
    anchorCsvAssert($db->query('SELECT rule_code FROM `' . DB_PREFIX . "anchor_price` WHERE product_id=5")->row['rule_code'] === 'first_listing', 'Apply first-listing rule to an explicit new anchor');
    anchorCsvReject($model, $header . "1;ONE;1;1;2026-09-10;pending;Unconfirmed change\n", 'Prevent unconfirming an active confirmed anchor');

    $before = $db->query('SELECT * FROM `' . DB_PREFIX . 'anchor_price` ORDER BY product_id')->rows;
    $beforeAuditCount = (int)$db->query('SELECT COUNT(*) AS total FROM `' . DB_PREFIX . 'anchor_price_audit`')->row['total'];
    $db->failAuditAt = 2;
    anchorCsvReject($model, $header . "1;ONE;100;100;2026-09-10;confirmed;Verified invoice\n2;TWO;200;200;2026-09-10;confirmed;Verified invoice\n", 'Simulate audit write failure midway through the transaction');
    anchorCsvAssert($db->query('SELECT * FROM `' . DB_PREFIX . 'anchor_price` ORDER BY product_id')->rows === $before, 'Roll back every price update on audit failure');
    anchorCsvAssert((int)$db->query('SELECT COUNT(*) AS total FROM `' . DB_PREFIX . 'anchor_price_audit`')->row['total'] === $beforeAuditCount, 'Roll back earlier audit writes too');
    $db->failAuditAt = 0;

    $db->beforeBegin = function () use ($db) { $db->query('UPDATE `' . DB_PREFIX . 'anchor_price` SET price=99 WHERE product_id=1'); };
    anchorCsvReject($model, $header . "1;ONE;100;100;2026-09-10;confirmed;Verified invoice\n2;TWO;200;200;2026-09-10;confirmed;Verified invoice\n", 'Detect a concurrent edit between validation and locked update');
    anchorCsvAssert($db->query('SELECT price FROM `' . DB_PREFIX . 'anchor_price` WHERE product_id=1')->row['price'] === '99.0000', 'Do not overwrite an intervening administrator edit');
    anchorCsvAssert($db->query('SELECT price FROM `' . DB_PREFIX . 'anchor_price` WHERE product_id=2')->row['price'] === '22.5000', 'Do not apply any row from a stale batch');
    anchorCsvAssert((int)$db->query('SELECT COUNT(*) AS total FROM `' . DB_PREFIX . 'anchor_price_audit`')->row['total'] === $beforeAuditCount, 'Stale batch cannot add audits');
    $db->query('UPDATE `' . DB_PREFIX . 'anchor_price` SET price=11.25 WHERE product_id=1');

    $user = new AnchorCsvFixtureUser();
    $loader = new AnchorCsvFixtureLoader();
    $request = (object)array('server' => array('REQUEST_METHOD' => 'GET'), 'files' => array());
    $registry->set('user', $user);
    $registry->set('load', $loader);
    $registry->set('request', $request);
    $registry->set('session', (object)array('data' => array('user_token' => 'fixture-token')));
    $registry->set('language', new AnchorCsvFixtureLanguage());
    $registry->set('response', new AnchorCsvFixtureResponse());
    $registry->set('url', new AnchorCsvFixtureUrl());
    $controller = new ControllerExtensionModuleAnchorPrice($registry);
    foreach (array(array('GET', true, true), array('POST', false, true), array('POST', true, false)) as $case) {
        $request->server['REQUEST_METHOD'] = $case[0];
        $user->access = $case[1];
        $user->modify = $case[2];
        $controller->importCsv();
        anchorCsvAssert($loader->models === 0 && $registry->get('session')->data['error'] === 'error_permission', 'Require POST and both access/modify permissions before loading data');
    }
    $user->access = $user->modify = true;
    $controller->importCsv();
    anchorCsvAssert($loader->models === 0 && $registry->get('session')->data['error'] === 'error_csv_upload', 'Reject files that were not uploaded through HTTP');

    $lifecycle = new AnchorCsvFixtureLifecycle();
    foreach (array('model_extension_module_anchor_price', 'model_setting_setting', 'model_user_user_group', 'model_setting_event') as $key) $registry->set($key, $lifecycle);
    foreach (array('install', 'uninstall') as $operation) {
        $request->server['REQUEST_METHOD'] = 'GET';
        $request->get = array('route' => 'extension/module/anchor_price/' . $operation);
        $user->modify = true;
        $user->managerModify = true;
        $modelsBefore = $loader->models;
        $controller->$operation();
        anchorCsvAssert($loader->models === $modelsBefore && $registry->get('response')->output === 'Forbidden', 'Reject direct lifecycle GET even for a privileged administrator');
        $request->get = array('route' => 'extension/extension/module/' . $operation, 'extension' => 'anchor_price');
        $user->modify = false;
        $user->managerModify = false;
        $controller->$operation();
        anchorCsvAssert($loader->models === $modelsBefore, 'Reject read-only extension manager before loading any model');
        $user->managerModify = true;
        $request->get['extension'] = 'other_module';
        $controller->$operation();
        anchorCsvAssert($loader->models === $modelsBefore, 'Require the exact anchor_price extension in manager lifecycle requests');
        $request->get['extension'] = 'anchor_price';
        $counter = $operation === 'install' ? 'installs' : 'uninstalls';
        $countBefore = $lifecycle->$counter;
        $controller->$operation();
        anchorCsvAssert($lifecycle->$counter === $countBefore + 1, 'Allow native manager GET despite stale missing module modify permission');
        anchorCsvAssert($lifecycle->settings['module_anchor_price_reference_date'] === '2026-10-01', 'Preserve the original snapshot reference date through lifecycle changes');
        $request->get = array('route' => 'extension/module/anchor_price/' . $operation);
        $request->server['REQUEST_METHOD'] = 'POST';
        $user->managerModify = false;
        $user->modify = false;
        $modelsBefore = $loader->models;
        $controller->$operation();
        anchorCsvAssert($loader->models === $modelsBefore, 'Reject direct lifecycle POST without module modify permission');
        $user->modify = true;
        $countBefore = $lifecycle->$counter;
        $controller->$operation();
        anchorCsvAssert($lifecycle->$counter === $countBefore + 1, 'Allow authorized direct lifecycle POST');
    }
    $writer = new ReflectionMethod(ModelExtensionModuleAnchorPrice::class, 'writeCsvRow');
    $writer->setAccessible(true);
    $stream = fopen('php://temp', 'w+b');
    $csvRow = array('Test; trailing slash\\', 'Quote " and newline' . "\n" . 'end', 'next');
    $writer->invoke($model, $stream, $csvRow);
    rewind($stream);
    anchorCsvAssert(fgetcsv($stream, 0, ';', '"', '') === $csvRow, 'Admin CSV writer round-trips delimiters, quotes, newlines and trailing backslashes');
    fclose($stream);

    $db->query("INSERT INTO `" . DB_PREFIX . "product` (product_id,model,tax_class_id,status,date_added,price) VALUES (6,'ZERO-SPECIAL',0,1,'2026-01-01',60),(7,'DISCOUNT',0,1,'2026-01-01',70),(8,'DRAFT',0,0,'2026-01-01',80),(9,'LATER-PRODUCT',0,1,'2026-10-01',90)");
    $db->query('INSERT INTO `' . DB_PREFIX . 'product_to_store` VALUES (6,0),(7,0),(8,0),(9,0)');
    $db->query("INSERT INTO `" . DB_PREFIX . "product_special` VALUES (6,0,0,0,'2026-01-01','2099-01-01')");
    $db->query("INSERT INTO `" . DB_PREFIX . "product_discount` VALUES (6,0,40,1,0,'2026-01-01','2099-01-01'),(7,0,50,1,0,'2026-01-01','2099-01-01'),(7,0,1,5,0,'2026-01-01','2099-01-01')");
    anchorCsvAssert($model->seedExistingProducts(7) === 3, 'Seed only currently published products missing an anchor');
    $zero = $db->query('SELECT * FROM `' . DB_PREFIX . 'anchor_price` WHERE product_id=6')->row;
    anchorCsvAssert($zero['price'] === '0.0000' && $zero['reference_date'] === '2026-09-10' && $zero['rule_code'] === 'baseline_2026_09_10' && $zero['verification_status'] === 'confirmed', 'Merchant-selected baseline uses September reference date and current zero-special amount with precedence over discount');
    $later = $db->query('SELECT * FROM `' . DB_PREFIX . 'anchor_price` WHERE product_id=9')->row;
    anchorCsvAssert($later['price'] === '90.0000' && $later['reference_date'] === $date && $later['rule_code'] === 'first_listing' && $later['verification_status'] === 'pending', 'A later product uses its first observed date pending manual confirmation, never the earlier baseline');
    anchorCsvAssert($db->query('SELECT price FROM `' . DB_PREFIX . 'anchor_price` WHERE product_id=7')->row['price'] === '50.0000', 'Use a quantity-one discount and ignore bulk discounts');
    anchorCsvAssert($db->query('SELECT * FROM `' . DB_PREFIX . 'anchor_price` WHERE product_id=8')->num_rows === 0, 'Do not invent an initial publication for a draft');
    anchorCsvAssert($model->seedExistingProducts(7) === 0, 'Repeated installation never overwrites retained anchor prices');
    anchorCsvAssert($db->query('SELECT price FROM `' . DB_PREFIX . "anchor_price` WHERE product_id=1")->row['price'] === '11.2500', 'Retain verified historical import through reinstallation');
    echo 'PASS: ' . $checks . " CSV import checks, including real transaction rollback.\n";
} finally {
    $connection->close();
}
