<?php
// Version



define('VERSION', '3.0.3.2');

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
	header('Location: install/index.php');
	exit;
}

// Herd/Nginx sends clean URLs to this front controller without Apache's
// RewriteRule, so provide the OpenCart SEO route normally set by .htaccess.
if (!isset($_GET['route']) && !isset($_GET['_route_']) && isset($_SERVER['REQUEST_URI'])) {
	$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$request_path = trim(rawurldecode((string)$request_path), '/');

	if ($request_path && $request_path !== 'index.php') {
		$_GET['_route_'] = $request_path;
	}
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');
require_once(modification(DIR_SYSTEM . 'minify/classes/minify_minifier.php'));

// agmedia
if (is_file('system/helper/eloquent.php')) {
    require_once('system/helper/eloquent.php');
}

start('catalog');
