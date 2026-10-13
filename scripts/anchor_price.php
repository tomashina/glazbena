<?php
/** CLI installation, daily publication and health check for the price modules. */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$command = isset($argv[1]) ? $argv[1] : '';
if (!in_array($command, array('install', 'publish', 'audit', 'refresh'), true)) {
	fwrite(STDERR, "Usage: php scripts/anchor_price.php install|publish|audit|refresh\n");
	exit(2);
}

$admin = in_array($command, array('install', 'refresh'), true);
$root = dirname(__DIR__);
require $root . ($admin ? '/admin/config.php' : '/config.php');
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/index.php';
$_SERVER['HTTP_HOST'] = parse_url($admin ? HTTPS_CATALOG : HTTPS_SERVER, PHP_URL_HOST);
$_SERVER['HTTPS'] = true;
$_SERVER['REQUEST_METHOD'] = 'POST';
require DIR_SYSTEM . 'startup.php';
date_default_timezone_set('Europe/Zagreb');

try {
	$registry = new Registry();
	$config = new Config();
	$config->load('default');
	$config->load($admin ? 'admin' : 'catalog');
	$registry->set('config', $config);
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
	$registry->set('db', $db);
	$db->query("SET time_zone = '" . $db->escape(date('P')) . "'");
	foreach ($db->query("SELECT `key`, `value`, serialized FROM `" . DB_PREFIX . "setting` WHERE store_id = 0")->rows as $setting) {
		$config->set($setting['key'], $setting['serialized'] ? json_decode($setting['value'], true) : $setting['value']);
	}
	$config->set('config_store_id', 0);
	$config->set('config_language', $config->get('config_language') ?: 'hr-hr');
	$lang = $db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code = '" . $db->escape($config->get('config_language')) . "' LIMIT 1");
	$config->set('config_language_id', (int)$lang->row['language_id']);
	$registry->set('event', new Event($registry));
	$registry->set('load', new Loader($registry));
	$registry->set('cache', new Cache('file', 3600));
	$registry->set('log', new Log('anchor_price.log'));
	$registry->set('request', new Request());
	$registry->set('response', new Response());
	$registry->set('document', new Document());
	$registry->set('url', new Url(HTTP_SERVER, HTTPS_SERVER));
	$session = new stdClass();
	$session->data = array('currency' => $config->get('config_currency'));
	$registry->set('session', $session);
	$language = new Language($config->get('config_language'));
	$language->load($config->get('config_language'));
	$registry->set('language', $language);
	$tax = new Cart\Tax($registry);
	$tax->setShippingAddress($config->get('config_country_id'), $config->get('config_zone_id'));
	$tax->setPaymentAddress($config->get('config_country_id'), $config->get('config_zone_id'));
	$tax->setStoreAddress($config->get('config_country_id'), $config->get('config_zone_id'));
	$registry->set('tax', $tax);
	$registry->set('currency', new Cart\Currency($registry));

	if ($admin) {
		$user = $db->query("SELECT user_id, user_group_id FROM `" . DB_PREFIX . "user` WHERE status = 1 AND user_group_id = 1 ORDER BY user_id LIMIT 1");
		if (!$user->num_rows) {
			throw new RuntimeException('An active administrator in group 1 is required.');
		}
		// This identity exists only in this local CLI process; no web session is created.
		$registry->set('user', new class($user->row) {
			private $row;
			public function __construct($row) { $this->row = $row; }
			public function getId() { return (int)$this->row['user_id']; }
			public function getGroupId() { return (int)$this->row['user_group_id']; }
			public function isLogged() { return true; }
			public function hasPermission($type, $route) { return true; }
		});
	}

	if ($command === 'install') {
		require DIR_APPLICATION . 'controller/extension/module/anchor_price.php';
		$controller = new ControllerExtensionModuleAnchorPrice($registry);
		$controller->install();
		$registry->get('load')->model('setting/extension');
		$registry->get('model_setting_extension')->install('module', 'anchor_price');
		$count = $db->query("SELECT verification_status, COUNT(*) AS total FROM `" . DB_PREFIX . "anchor_price` GROUP BY verification_status")->rows;
		echo json_encode(array('installed' => true, 'snapshots' => $count), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
	} elseif ($command === 'refresh') {
		require DIR_APPLICATION . 'controller/marketplace/modification.php';
		$controller = new ControllerMarketplaceModification($registry);
		$controller->refresh(array('return' => true));
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DIR_CACHE, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getFilename() !== 'index.html') {
				unlink($file->getPathname());
			} elseif ($file->isDir()) {
				@rmdir($file->getPathname());
			}
		}
		echo "OpenCart modifications and template cache refreshed.\n";
	} elseif ($command === 'publish') {
		if (!$config->get('module_anchor_price_status')) {
			throw new RuntimeException('The anchor price module is disabled.');
		}
		require DIR_APPLICATION . 'model/extension/module/anchor_price.php';
		$model = new ModelExtensionModuleAnchorPrice($registry);
		$result = $model->generatePublication(false);
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
		if (empty($result['success'])) {
			exit(1);
		}
	} else {
		require DIR_APPLICATION . 'model/extension/module/anchor_price.php';
		$model = new ModelExtensionModuleAnchorPrice($registry);
		if (!$model->tableExists()) {
			throw new RuntimeException('The anchor price module has not been installed.');
		}
		$summary = $db->query("SELECT COUNT(*) AS active_products, SUM(ap.anchor_price_id IS NULL) AS missing, SUM(COALESCE(ap.verification_status, '') <> 'confirmed') AS unconfirmed FROM `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "product_to_store` s ON (s.product_id = p.product_id AND s.store_id = 0) LEFT JOIN `" . DB_PREFIX . "anchor_price` ap ON (ap.product_id = p.product_id AND ap.store_id = s.store_id) WHERE p.status = 1 AND p.date_available <= NOW()")->row;
		$publications = $model->getPublications();
		echo json_encode(array('products' => $summary, 'verified_publications' => count($publications), 'latest' => $publications ? reset($publications)['filename'] : null), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
		if ((int)$summary['missing'] || (int)$summary['unconfirmed']) {
			exit(1);
		}
	}
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}
