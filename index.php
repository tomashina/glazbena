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

// Startup
require_once(DIR_SYSTEM . 'startup.php');
require_once(modification(DIR_SYSTEM . 'minify/classes/minify_minifier.php'));

// agmedia
if (is_file('system/helper/eloquent.php')) {
    require_once('system/helper/eloquent.php');
}

start('catalog');