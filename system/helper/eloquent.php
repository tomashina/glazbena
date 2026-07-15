<?php

if (defined('DIR_STORAGE')) {
    $autoload = rtrim(DIR_STORAGE, '/').'/vendor/autoload.php';
} else {
    // fallback ako DIR_STORAGE nije definiran ovdje
    $autoload = __DIR__ . '/../storage/vendor/autoload.php';
}

if (is_file($autoload)) {
    require_once $autoload;
} else {
    // Ako nema vendor-a, nemoj rušiti site — samo odustani od Eloquent bootstrapa
    return;
}

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    "driver"    => "mysql",
    "host"      => DB_HOSTNAME,
    "database"  => DB_DATABASE,
    "username"  => DB_USERNAME,
    "password"  => DB_PASSWORD,
    'charset'   => 'utf8',
    'collation' => 'utf8_general_ci',
    'prefix'    => DB_PREFIX,
    'strict'    => false,
]);

$capsule->bootEloquent();