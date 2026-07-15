<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script can only be run from the command line.\n");
	exit(1);
}

$root = dirname(__DIR__, 2);
require $root . '/config.php';

$database = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);

if ($database->connect_errno) {
	fwrite(STDERR, $database->connect_error . "\n");
	exit(1);
}

$routes = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root . '/admin/controller', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
	if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
		continue;
	}

	$route = substr($file->getPathname(), strlen($root . '/admin/controller/'));
	$routes[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($route, 0, -4));
}

$routes = array_values(array_unique($routes));
sort($routes);

$query = $database->query(
	"SELECT `permission` FROM `" . DB_PREFIX . "user_group` WHERE `user_group_id` = '1' LIMIT 1"
);

if (!$query || !$query->num_rows) {
	fwrite(STDERR, "Administrator user group (ID 1) was not found.\n");
	exit(1);
}

$group = $query->fetch_assoc();
$permissions = json_decode($group['permission'], true);
if (!is_array($permissions)) {
	$permissions = array();
}

foreach (array('access', 'modify') as $type) {
	$current = isset($permissions[$type]) && is_array($permissions[$type]) ? $permissions[$type] : array();
	$permissions[$type] = array_values(array_unique(array_merge($current, $routes)));
	sort($permissions[$type]);
}

$permission = json_encode($permissions, JSON_UNESCAPED_SLASHES);
$statement = $database->prepare(
	"UPDATE `" . DB_PREFIX . "user_group` SET `permission` = ? WHERE `user_group_id` = '1'"
);
$statement->bind_param('s', $permission);
$statement->execute();

echo "Administrator permissions synchronized for " . count($routes) . " controller routes.\n";
