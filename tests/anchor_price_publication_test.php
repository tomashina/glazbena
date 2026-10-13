<?php
/**
 * End-to-end catalogue publication test against real MySQL and filesystem storage.
 *
 * Run: php81 tests/anchor_price_publication_test.php
 * DB_HOSTNAME/DB_USERNAME/DB_PASSWORD/DB_DATABASE/DB_PORT may override local defaults.
 * A random database (or random table prefix if CREATE DATABASE is unavailable)
 * and a temporary download directory are removed on success, failure, and shutdown.
 * No configured OpenCart tables, settings, downloads, or product data are changed.
 */

if (PHP_SAPI !== 'cli') {
	exit("CLI only.\n");
}
if (!extension_loaded('mysqli') || !extension_loaded('xmlwriter') || !extension_loaded('simplexml')) {
	exit("SKIP: mysqli, XMLWriter, and SimpleXML extensions are required.\n");
}

mysqli_report(MYSQLI_REPORT_OFF);
$host = getenv('DB_HOSTNAME') ?: '127.0.0.1';
$user = getenv('DB_USERNAME') ?: 'glazbena';
$password = getenv('DB_PASSWORD') ?: 'glazbena';
$port = (int)(getenv('DB_PORT') ?: 3306);
$configuredDatabase = getenv('DB_DATABASE') ?: 'glazbena';
$token = getmypid() . '_' . bin2hex(random_bytes(5));
$database = 'anchor_publication_test_' . $token;
$tablePrefix = 'apt_' . $token . '_';
$downloadDirectory = sys_get_temp_dir() . '/anchor_publication_test_' . $token . '/';
$storeId = random_int(100000000, 999999999);
$ownsDatabase = false;
$connection = @new mysqli($host, $user, $password, '', $port);
if ($connection->connect_errno) {
	exit("SKIP: local MySQL is unavailable.\n");
}
if ($connection->query("CREATE DATABASE `" . $database . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
	$ownsDatabase = true;
} else {
	$database = $configuredDatabase;
}
if (!$connection->select_db($database)) {
	exit("SKIP: local MySQL user cannot create an isolated database or access the configured database.\n");
}

function anchorTestRemoveDirectory($directory) {
	if (!is_dir($directory)) {
		return;
	}
	foreach (new DirectoryIterator($directory) as $entry) {
		if ($entry->isDot()) {
			continue;
		}
		$path = $entry->getPathname();
		if ($entry->isLink() || !$entry->isDir()) {
			unlink($path);
		} else {
			anchorTestRemoveDirectory($path);
		}
	}
	rmdir($directory);
}

$tables = array('product', 'product_to_store', 'product_description', 'product_special', 'product_discount', 'manufacturer', 'stock_status', 'language', 'currency', 'tax_rule', 'tax_rate', 'tax_rate_to_customer_group', 'zone_to_geo_zone', 'geo_zone', 'anchor_price', 'anchor_price_audit', 'anchor_price_publication');
$cleaned = false;
$cleanup = function() use (&$cleaned, $connection, $ownsDatabase, $database, $tablePrefix, $tables, $downloadDirectory) {
	if ($cleaned) {
		return;
	}
	$cleaned = true;
	if ($ownsDatabase) {
		$connection->query("DROP DATABASE IF EXISTS `" . $database . "`");
	} else {
		foreach ($tables as $table) {
			$connection->query("DROP TABLE IF EXISTS `" . $tablePrefix . $table . "`");
		}
	}
	anchorTestRemoveDirectory($downloadDirectory);
};
register_shutdown_function($cleanup);

define('DB_DATABASE', $database);
define('DB_PREFIX', $tablePrefix);
define('DIR_DOWNLOAD', $downloadDirectory);
define('DIR_LANGUAGE', dirname(__DIR__) . '/catalog/language/');
define('HTTPS_SERVER', 'https://anchor-publication.example.test/');
$root = dirname(__DIR__);
foreach (array('engine/registry.php', 'engine/model.php', 'engine/controller.php', 'library/config.php', 'library/db/mysqli.php', 'library/db.php', 'library/cart/tax.php', 'library/cart/currency.php', 'library/language.php', 'library/response.php', 'library/document.php', 'library/url.php') as $library) {
	require_once $root . '/system/' . $library;
}
require $root . '/catalog/model/extension/module/anchor_price.php';
require $root . '/catalog/controller/information/price_list.php';

class AnchorPublicationTestResponse extends Response {
	public $capturedHeaders = array();
	public function addHeader($header) {
		$this->capturedHeaders[] = $header;
		parent::addHeader($header);
	}
}

// Only unrelated page chrome is stubbed. The download controller, publication
// model, DB adapter, currency, tax, language, and response use production code.
class AnchorPublicationTestLoader {
	private $registry;
	public function __construct($registry) {
		$this->registry = $registry;
	}
	public function model($route) {
		if ($route !== 'extension/module/anchor_price') {
			throw new RuntimeException('Unexpected model dependency: ' . $route);
		}
	}
	public function language($route) {
		return $this->registry->get('language')->load($route);
	}
	public function controller($route) {
		return '';
	}
	public function view($route, $data) {
		return $route === 'error/not_found' ? 'PUBLICATION_NOT_FOUND' : 'UNEXPECTED_VIEW';
	}
}

$assertions = 0;
function anchorTestAssert($condition, $message) {
	global $assertions;
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
function anchorTestSame($expected, $actual, $message) {
	anchorTestAssert($expected === $actual, $message . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}
function anchorTestPublication($result) {
	anchorTestAssert(!empty($result['success']), 'Publication must succeed: ' . json_encode($result));
	return $result['locations']['WEB']['publication'];
}
function anchorTestRequest($registry, $method, array $parameters = array()) {
	$response = new AnchorPublicationTestResponse();
	$registry->set('response', $response);
	$registry->set('request', (object)array('get' => $parameters));
	$controller = new ControllerInformationPriceList($registry);
	$controller->$method();
	return $response;
}
function anchorTestNotFound($response, $message) {
	anchorTestAssert(in_array('HTTP/1.1 404 Not Found', $response->capturedHeaders, true), $message . ': 404 header');
	anchorTestSame('PUBLICATION_NOT_FOUND', $response->getOutput(), $message . ': no file bytes');
}
function anchorTestCsv($bytes) {
	$handle = fopen('php://temp', 'w+b');
	fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $bytes));
	rewind($handle);
	$header = fgetcsv($handle, 0, ';', '"', '');
	anchorTestSame(20, count($header), 'CSV header preserves all 20 publication columns');
	$products = array();
	while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
		anchorTestSame(20, count($row), 'Every CSV row must preserve all 20 columns and quoting');
		$products[(int)$row[1]] = array_combine($header, $row);
	}
	fclose($handle);
	return $products;
}

try {
	$db = new DB('mysqli', $host, $user, $password, $database, $port);
	$schema = array(
		'product' => "product_id INT PRIMARY KEY, model VARCHAR(100), sku VARCHAR(100), ean VARCHAR(100), upc VARCHAR(100), jan VARCHAR(100), isbn VARCHAR(100), price DECIMAL(15,4), quantity INT, tax_class_id INT, manufacturer_id INT, stock_status_id INT, status INT, date_available DATE",
		'product_to_store' => 'product_id INT, store_id INT, PRIMARY KEY(product_id, store_id)',
		'product_description' => 'product_id INT, language_id INT, name VARCHAR(255), PRIMARY KEY(product_id, language_id)',
		'product_special' => 'product_special_id INT AUTO_INCREMENT PRIMARY KEY, product_id INT, customer_group_id INT, priority INT, price DECIMAL(15,4), date_start DATE, date_end DATE',
		'product_discount' => 'product_discount_id INT AUTO_INCREMENT PRIMARY KEY, product_id INT, customer_group_id INT, quantity INT, priority INT, price DECIMAL(15,4), date_start DATE, date_end DATE',
		'manufacturer' => 'manufacturer_id INT PRIMARY KEY, name VARCHAR(100)',
		'stock_status' => 'stock_status_id INT, language_id INT, name VARCHAR(100)',
		'language' => 'language_id INT PRIMARY KEY, code VARCHAR(10)',
		'currency' => 'currency_id INT PRIMARY KEY, code CHAR(3), title VARCHAR(50), symbol_left VARCHAR(10), symbol_right VARCHAR(10), decimal_place INT, value DECIMAL(15,8)',
		'tax_rule' => 'tax_class_id INT, tax_rate_id INT, based VARCHAR(10), priority INT',
		'tax_rate' => 'tax_rate_id INT PRIMARY KEY, name VARCHAR(100), rate DECIMAL(15,4), type CHAR(1), geo_zone_id INT',
		'tax_rate_to_customer_group' => 'tax_rate_id INT, customer_group_id INT',
		'zone_to_geo_zone' => 'geo_zone_id INT, country_id INT, zone_id INT',
		'geo_zone' => 'geo_zone_id INT PRIMARY KEY',
		'anchor_price' => "anchor_price_id INT AUTO_INCREMENT PRIMARY KEY, product_id INT, store_id INT, price DECIMAL(15,4), gross_price DECIMAL(15,4), currency_code CHAR(3), tax_class_id INT, tax_context TEXT, reference_date DATE, rule_code VARCHAR(32), source VARCHAR(32), verification_status VARCHAR(20), created_by INT, date_added DATETIME, date_modified DATETIME, UNIQUE KEY product_store(product_id, store_id)",
		'anchor_price_audit' => 'audit_id INT AUTO_INCREMENT PRIMARY KEY, anchor_price_id INT, product_id INT, store_id INT, user_id INT, action VARCHAR(32), old_data MEDIUMTEXT, new_data MEDIUMTEXT, reason VARCHAR(255), date_added DATETIME',
		'anchor_price_publication' => "publication_id INT AUTO_INCREMENT PRIMARY KEY, store_id INT, batch_key CHAR(32), location_code VARCHAR(16), sequence_no INT, filename VARCHAR(255), relative_path VARCHAR(255), status VARCHAR(20), product_count INT, checksum_sha256 CHAR(64), error_message TEXT, published_at DATETIME NULL, date_added DATETIME, UNIQUE KEY store_location_sequence(store_id, location_code, sequence_no)"
	);
	foreach ($schema as $table => $columns) {
		$db->query('CREATE TABLE `' . DB_PREFIX . $table . '` (' . $columns . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
	}
	$sql = function($query) use ($db) {
		return $db->query(str_replace('{prefix}', DB_PREFIX, $query));
	};
	$sql("INSERT INTO `{prefix}language` VALUES(1,'hr-hr')");
	$sql("INSERT INTO `{prefix}currency` VALUES(1,'EUR','Euro','','€',2,1)");
	$sql("INSERT INTO `{prefix}manufacturer` VALUES(1,'Marka & proizvođač')");
	$sql("INSERT INTO `{prefix}stock_status` VALUES(1,1,'Na zalihi')");
	$sql("INSERT INTO `{prefix}tax_rule` VALUES(1,1,'store',1)");
	$sql("INSERT INTO `{prefix}tax_rate` VALUES(1,'PDV 25%',25,'P',1)");
	$sql("INSERT INTO `{prefix}tax_rate_to_customer_group` VALUES(1,1)");
	$sql("INSERT INTO `{prefix}zone_to_geo_zone` VALUES(1,53,0)");
	$sql("INSERT INTO `{prefix}geo_zone` VALUES(1)");
	$sql("INSERT INTO `{prefix}product` VALUES
		(1,'MODEL-1','SKU-1','4006381333931','flag-upc','flag-jan','flag-isbn',100,5,1,1,1,1,'2000-01-01'),
		(2,'MODEL-2','SKU-2','','4006381333931','flag-jan','flag-isbn',100,0,1,1,1,1,'2000-01-01'),
		(3,'MODEL-3','SKU-3','96385074','flag-upc','flag-jan','flag-isbn',20,1,1,1,1,1,'2000-01-01'),
		(4,'','','invalid','','','',1,0,1,0,1,0,'2000-01-01'),
		(5,'','','invalid','','','',1,0,1,0,1,1,'2999-01-01'),
		(6,'','','invalid','','','',1,0,1,0,1,1,'2000-01-01')");
	$sql("INSERT INTO `{prefix}product_to_store` VALUES(1,$storeId),(2,$storeId),(3,$storeId),(4,$storeId),(5,$storeId),(6," . ($storeId + 1) . ")");
	$productName = "Gitara & \"komplet\"; hrvatski čć\nTest; citat \\\"komplet\\\"; slash\\";
	$sql("INSERT INTO `{prefix}product_description` VALUES(1,1,'" . $db->escape($productName) . "'),(2,1,'Akcijski proizvod'),(3,1,'Besplatan proizvod')");
	$sql("INSERT INTO `{prefix}product_special` (product_id,customer_group_id,priority,price,date_start,date_end) VALUES
		(1,1,1,1,'2000-01-01','2000-01-02'),
		(2,1,1,80,'2000-01-01','2999-01-01'),
		(2,1,2,1,'2000-01-01','2999-01-01'),
		(2,2,0,1,'2000-01-01','2999-01-01'),
		(3,1,1,0,'2000-01-01','2999-01-01')");
	$sql("INSERT INTO `{prefix}product_discount` (product_id,customer_group_id,quantity,priority,price,date_start,date_end) VALUES
		(1,1,2,1,1,'2000-01-01','2999-01-01'),
		(2,1,1,1,90,'2000-01-01','2999-01-01'),
		(2,1,1,2,1,'2000-01-01','2999-01-01'),
		(2,2,1,0,1,'2000-01-01','2999-01-01')");
	$sql("INSERT INTO `{prefix}anchor_price` (product_id,store_id,price,gross_price,currency_code,tax_class_id,tax_context,reference_date,rule_code,source,verification_status,created_by,date_added,date_modified) VALUES
		(1,$storeId,88,110,'EUR',1,'{}','2026-09-10','cutover','test','confirmed',0,NOW(),NOW()),
		(2,$storeId,120,150,'EUR',1,'{}','2026-09-10','cutover','test','confirmed',0,NOW(),NOW())");
	$config = new Config();
	foreach (array('module_anchor_price_status' => 1, 'config_store_id' => $storeId, 'config_customer_group_id' => 1, 'config_currency' => 'EUR', 'config_language' => 'hr-hr', 'config_language_id' => 1, 'config_symbol_right_space' => 1) as $key => $value) {
		$config->set($key, $value);
	}
	$registry = new Registry();
	$registry->set('config', $config);
	$registry->set('db', $db);
	$language = new Language('hr-hr');
	$language->load('hr-hr');
	$registry->set('language', $language);
	$registry->set('session', (object)array('data' => array('currency' => 'EUR')));
	$registry->set('currency', new Cart\Currency($registry));
	$tax = new Cart\Tax($registry);
	$tax->setStoreAddress(53, 0);
	$registry->set('tax', $tax);
	$registry->set('document', new Document());
	$registry->set('url', new Url(HTTPS_SERVER));
	$registry->set('load', new AnchorPublicationTestLoader($registry));
	$model = new ModelExtensionModuleAnchorPrice($registry);
	$registry->set('model_extension_module_anchor_price', $model);

	// One missing snapshot must block the complete catalogue, including when
	// other products already have confirmed history. Observed prices stay pending.
	anchorTestSame(false, $model->generatePublication()['success'], 'Missing snapshot must block publication');
	anchorTestSame('pending', $sql("SELECT verification_status FROM `{prefix}anchor_price` WHERE product_id=3")->row['verification_status'], 'Automatically observed snapshot remains pending');
	anchorTestSame(1, $sql("SELECT audit_id FROM `{prefix}anchor_price_audit` WHERE product_id=3")->num_rows, 'Automatic snapshot has an audit trail');
	anchorTestSame(0, $sql("SELECT publication_id FROM `{prefix}anchor_price_publication`")->num_rows, 'Blocked catalogue creates no partial publication records');
	$sql("UPDATE `{prefix}anchor_price` SET verification_status='confirmed' WHERE product_id=3");
	$sql("UPDATE `{prefix}anchor_price` SET verification_status='pending' WHERE product_id=2");
	anchorTestSame(false, $model->generatePublication()['success'], 'Pending snapshot must block publication');
	$sql("UPDATE `{prefix}anchor_price` SET verification_status='confirmed' WHERE product_id=2");
	$sql("UPDATE `{prefix}product` SET ean='4006381333932',upc='4006381333931' WHERE product_id=2");
	anchorTestSame(false, $model->generatePublication()['success'], 'Invalid EAN cannot be rescued by a valid UPC');
	anchorTestSame(0, $sql("SELECT publication_id FROM `{prefix}anchor_price_publication`")->num_rows, 'Validation failures never publish a subset');
	anchorTestSame(array(), glob(DIR_DOWNLOAD . 'anchor_price/*.csv') ?: array(), 'Validation failures create no public CSV');
	$sql("UPDATE `{prefix}product` SET ean='' WHERE product_id=2");

	$first = anchorTestPublication($model->generatePublication());
	$firstPath = $model->publicationPath($first);
	$firstBytes = file_get_contents($firstPath);
	anchorTestSame('WEB', $first['location_code'], 'Only the online sales channel is published');
	anchorTestSame(3, (int)$first['product_count'], 'Inactive, future, and other-store products are excluded');
	anchorTestSame(hash('sha256', $firstBytes), $first['checksum_sha256'], 'Stored SHA-256 matches the frozen CSV');
	anchorTestAssert($model->publicationFileIsValid($first), 'Generated file passes the public integrity gate');
	anchorTestAssert(strpos($firstBytes, "\xEF\xBB\xBF") === 0, 'CSV is UTF-8 with a BOM for spreadsheet readers');
	$csv = anchorTestCsv($firstBytes);
	anchorTestSame(array(1, 2, 3), array_keys($csv), 'All eligible products are published in deterministic order');
	anchorTestSame($productName, $csv[1]['Naziv proizvoda'], 'CSV quoting round-trips Croatian text, ampersands, quotes, semicolons, newlines, and a trailing backslash');
	anchorTestSame('125,00', $csv[1]['Redovna maloprodajna cijena (EUR)'], 'Regular price includes actual configured VAT');
	anchorTestSame('125,00', $csv[1]['Aktualna maloprodajna cijena (EUR)'], 'Expired specials do not change the current price');
	anchorTestSame('NE', $csv[1]['Poseban oblik prodaje'], 'Regular product has no current sale');
	anchorTestSame('', $csv[1]['Aktualna akcijska cijena (EUR)'], 'Regular product has no special price');
	anchorTestSame('110,00', $csv[1]['Sidrena cijena (EUR)'], 'Confirmed historical gross price is preserved');
	anchorTestSame('100,00', $csv[2]['Aktualna maloprodajna cijena (EUR)'], 'Correct customer-group special and priority determine current gross price');
	anchorTestSame('112,50', $csv[2]['Redovna maloprodajna cijena (EUR)'], 'Regular price respects the quantity-one customer-group discount');
	anchorTestSame('100,00', $csv[2]['Cijena po jedinici (EUR)'], 'Unit price follows the actual current price');
	anchorTestSame('DA', $csv[2]['Poseban oblik prodaje'], 'Sale marker is explicit');
	anchorTestSame('100,00', $csv[2]['Aktualna akcijska cijena (EUR)'], 'Current special price includes VAT');
	anchorTestSame('Nije dostupno', $csv[2]['Dostupnost'], 'Zero stock remains visible with its availability');
	anchorTestSame('0,00', $csv[3]['Aktualna maloprodajna cijena (EUR)'], 'A zero-price special is a real current price');
	anchorTestSame('0,00', $csv[3]['Aktualna akcijska cijena (EUR)'], 'Zero-price special is not discarded as false');
	anchorTestSame('DA', $csv[3]['Poseban oblik prodaje'], 'Zero-price special retains its sale marker');
	anchorTestSame('4006381333931', $csv[1]['Barkod'], 'Valid EAN is exported despite flag values in UPC/JAN/ISBN');
	anchorTestSame('', $csv[2]['Barkod'], 'Empty EAN is allowed and UPC is never substituted');
	anchorTestSame('96385074', $csv[3]['Barkod'], 'Valid GTIN-8 is accepted');
	anchorTestSame('kom', $csv[1]['Jedinica mjere'], 'Default item unit is available');
	anchorTestSame('EUR', $csv[1]['Valuta'], 'Publication retains its currency');
	$sql("UPDATE `{prefix}anchor_price` SET verification_status='pending' WHERE product_id=2");
	anchorTestSame(false, $model->generatePublication(true)['success'], 'A newly pending product prevents replacing an existing catalogue with a subset');
	anchorTestSame(1, $sql("SELECT publication_id FROM `{prefix}anchor_price_publication`")->num_rows, 'Blocked regeneration preserves the published archive');
	anchorTestSame($firstBytes, file_get_contents($firstPath), 'Blocked regeneration leaves the earlier frozen file intact');
	$sql("UPDATE `{prefix}anchor_price` SET verification_status='confirmed' WHERE product_id=2");

	$download = anchorTestRequest($registry, 'download', array('publication_id' => $first['publication_id']));
	anchorTestSame($firstBytes, $download->getOutput(), 'Download returns the exact verified CSV bytes');
	anchorTestAssert(in_array('X-Content-Type-Options: nosniff', $download->capturedHeaders, true), 'Download prevents content sniffing');
	anchorTestSame($firstBytes, anchorTestRequest($registry, 'latest')->getOutput(), 'Stable latest URL returns the newest verified snapshot');
	$xmlBytes = anchorTestRequest($registry, 'download', array('publication_id' => $first['publication_id'], 'format' => 'xml'))->getOutput();
	$xml = simplexml_load_string($xmlBytes);
	anchorTestAssert($xml !== false, 'XML download is well formed');
	anchorTestSame(3, count($xml->product), 'XML contains all CSV products');
	anchorTestSame($productName, (string)$xml->product[0]->name, 'XML round-trips quotes, semicolons, newlines, and the trailing backslash from the frozen CSV');
	anchorTestSame('100.00', (string)$xml->product[1]->current_price, 'XML uses machine-readable decimal prices');
	anchorTestSame('0.00', (string)$xml->product[2]->special_price, 'XML preserves the zero special');
	anchorTestSame($xmlBytes, anchorTestRequest($registry, 'latest', array('format' => 'xml'))->getOutput(), 'Stable XML URL represents the same frozen snapshot');

	$sql("UPDATE `{prefix}product` SET price=999 WHERE product_id=1");
	$sql("UPDATE `{prefix}product_description` SET name='Changed after publication' WHERE product_id=1");
	$repeatResult = $model->generatePublication();
	$repeat = anchorTestPublication($repeatResult);
	anchorTestAssert(!empty($repeatResult['locations']['WEB']['existing']), 'Repeated daily generation reuses the immutable snapshot');
	anchorTestSame($first['publication_id'], $repeat['publication_id'], 'Same-day generation keeps the publication identity');
	anchorTestSame($firstBytes, file_get_contents($firstPath), 'Product edits cannot rewrite the archived CSV');
	anchorTestSame($xmlBytes, anchorTestRequest($registry, 'download', array('publication_id' => $first['publication_id'], 'format' => 'xml'))->getOutput(), 'Archived XML does not read later product changes');
	anchorTestSame(1, $sql("SELECT publication_id FROM `{prefix}anchor_price_publication`")->num_rows, 'Idempotent generation does not add archive records');
	$second = anchorTestPublication($model->generatePublication(true));
	anchorTestAssert($second['publication_id'] !== $first['publication_id'], 'Explicit regeneration creates a separate version');
	anchorTestSame($firstBytes, file_get_contents($firstPath), 'Explicit regeneration also preserves the older archive');
	$secondPath = $model->publicationPath($second);
	anchorTestAssert(file_get_contents($secondPath) !== $firstBytes, 'New publication captures the changed prices');

	file_put_contents($secondPath, "CORRUPTED\n", FILE_APPEND);
	anchorTestSame(false, $model->getPublication($second['publication_id']), 'Corrupt archive fails SHA-256 validation');
	anchorTestNotFound(anchorTestRequest($registry, 'download', array('publication_id' => $second['publication_id'])), 'Corrupt download is rejected');
	anchorTestSame($firstBytes, anchorTestRequest($registry, 'latest')->getOutput(), 'Stable URL skips corrupted snapshots');
	$replacement = anchorTestPublication($model->generatePublication());
	anchorTestSame('failed', $model->getPublication($second['publication_id'], false)['status'], 'Corrupt same-day record is marked failed');
	anchorTestAssert(!is_file($secondPath), 'Corrupt archive file is removed before regeneration');
	anchorTestAssert($model->publicationFileIsValid($replacement), 'Replacement publication has valid SHA-256');
	anchorTestSame(2, count($model->getPublications()), 'Only intact publications appear in the public archive');

	$clonePublication = function($filename, $relativePath, $publishedAt, $sequence) use ($sql, $db, $first) {
		$sql("INSERT INTO `{prefix}anchor_price_publication` (store_id,batch_key,location_code,sequence_no,filename,relative_path,status,product_count,checksum_sha256,published_at,date_added) VALUES (" . (int)$first['store_id'] . ",'" . bin2hex(random_bytes(16)) . "','WEB'," . (int)$sequence . ",'" . $db->escape($filename) . "','" . $db->escape($relativePath) . "','published',3,'" . $first['checksum_sha256'] . "','" . $db->escape($publishedAt) . "',NOW())");
		return $db->getLastId();
	};
	$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Zagreb'));
	copy($firstPath, DIR_DOWNLOAD . 'anchor_price/expired.csv');
	copy($firstPath, DIR_DOWNLOAD . 'anchor_price/recent.csv');
	$expiredId = $clonePublication('expired.csv', 'anchor_price/expired.csv', $now->modify('-31 days')->format('Y-m-d H:i:s'), 1000);
	$recentId = $clonePublication('recent.csv', 'anchor_price/recent.csv', $now->modify('-29 days')->format('Y-m-d H:i:s'), 1001);
	anchorTestSame(false, $model->getPublication($expiredId), 'Archive older than 30 days is inaccessible even before cleanup');
	anchorTestNotFound(anchorTestRequest($registry, 'download', array('publication_id' => $expiredId)), 'Expired download is rejected');
	anchorTestAssert((bool)$model->getPublication($recentId), 'Archive younger than 30 days remains downloadable');
	anchorTestPublication($model->generatePublication());
	anchorTestSame('expired', $model->getPublication($expiredId, false)['status'], 'Generation expires old archive records');
	anchorTestAssert(!is_file(DIR_DOWNLOAD . 'anchor_price/expired.csv'), 'Expiration removes the old archive file');
	anchorTestAssert(is_file(DIR_DOWNLOAD . 'anchor_price/recent.csv'), 'Expiration preserves recent archive files');

	foreach (array('anchor_price/../outside.csv', 'anchor_price\\..\\outside.csv', '../outside.csv', '/etc/outside.csv', 'anchor_price/unsafe.php') as $index => $unsafePath) {
		$unsafeId = $clonePublication('unsafe.csv', $unsafePath, $now->format('Y-m-d H:i:s'), 1100 + $index);
		anchorTestSame(false, $model->publicationPath($model->getPublication($unsafeId, false)), 'Unsafe path must not resolve: ' . $unsafePath);
		anchorTestNotFound(anchorTestRequest($registry, 'download', array('publication_id' => $unsafeId)), 'Traversal or non-CSV download is rejected');
	}
	anchorTestNotFound(anchorTestRequest($registry, 'download', array('publication_id' => 2147483647)), 'Unknown publication is rejected');
	$config->set('config_store_id', $storeId + 1);
	anchorTestNotFound(anchorTestRequest($registry, 'download', array('publication_id' => $first['publication_id'])), 'Other-store publication is rejected');
	$config->set('config_store_id', $storeId);

	// A filesystem symlink must not bypass the download directory boundary.
	copy($firstPath, DIR_DOWNLOAD . 'outside.csv');
	anchorTestSame(false, $model->publicationFileIsValid($first, DIR_DOWNLOAD . 'outside.csv'), 'An explicit path cannot bypass the archive boundary even with a matching checksum');
	if (symlink(DIR_DOWNLOAD . 'outside.csv', DIR_DOWNLOAD . 'anchor_price/linked.csv')) {
		$linkedId = $clonePublication('linked.csv', 'anchor_price/linked.csv', $now->format('Y-m-d H:i:s'), 1200);
		anchorTestNotFound(anchorTestRequest($registry, 'download', array('publication_id' => $linkedId)), 'Symlink outside the publication directory is rejected');
	}
	echo 'PASS: anchor-price publication integration (' . $assertions . " assertions).\n";
} catch (Throwable $exception) {
	fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
	exit(1);
} finally {
	$cleanup();
}
