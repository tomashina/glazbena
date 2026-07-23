<?php

// Runtime configuration. Override any value with the corresponding environment variable.
$root = dirname(__DIR__) . '/';
$appUrl = getenv('APP_URL') ?: 'https://glazbena.test/';
$appUrl = rtrim($appUrl, '/') . '/';

// HTTP
define('HTTP_SERVER', $appUrl . 'admin/');
define('HTTP_CATALOG', $appUrl);

// HTTPS
define('HTTPS_SERVER', $appUrl . 'admin/');
define('HTTPS_CATALOG', $appUrl);

// DIR
define('DIR_APPLICATION', $root . 'admin/');
define('DIR_SYSTEM', $root . 'system/');
define('DIR_IMAGE', $root . 'image/');
define('DIR_STORAGE', $root . 'system/storage/');
define('DIR_CATALOG', $root . 'catalog/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');
define('DIR_MODIFICATION', DIR_STORAGE . 'modification/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

// DB
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysqli');
define('DB_HOSTNAME', getenv('DB_HOSTNAME') ?: '127.0.0.1');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'glazbena');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'glazbena');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'glazbena');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_PREFIX', getenv('DB_PREFIX') ?: 'u3050_');

// OpenCart API
define('OPENCART_SERVER', 'https://www.opencart.com/');

// Custom AGmedia
define('IMPORT_XML_VALIDATION_KEY', getenv('IMPORT_XML_VALIDATION_KEY') ?: '');
define('IMPORT_XML_PRODUCTS_URL', getenv('IMPORT_XML_PRODUCTS_URL') ?: dirname($root) . '/glazbena-kutija.hr/SHOP_sync.xml');
define('ENG_LANGUAGE_ID', 3);
