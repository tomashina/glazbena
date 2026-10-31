<?php
// Offline checks only: php tests/catalog_request_guard_test.php
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
	if (error_reporting() & $severity) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	}
	return true;
});
require_once __DIR__ . '/../system/library/catalog_request_guard.php';

$directory = sys_get_temp_dir() . '/glazbena-request-guard-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
register_shutdown_function(function() use ($directory) {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) {
		if ($file->isDir()) {
			rmdir($file->getPathname());
		} else {
			unlink($file->getPathname());
		}
	}
	rmdir($directory);
});
$checks = 0;
function guardAssert($condition, $message) {
	global $checks;
	$checks++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
function guardServer($address = '192.0.2.1', $method = 'GET') {
	return array('REQUEST_METHOD' => $method, 'REMOTE_ADDR' => $address);
}
function guardRequest($guard, $query, $server, $now) {
	$result = $guard->evaluate($query, $server, $now);
	$guard->release();
	return array($result, $query);
}

$guard = new CatalogRequestGuard($directory . '/main');
$variant = array('route' => 'product/category', 'sort' => 'p.price', 'page' => '2');
foreach (array('product/product', 'product/category', 'product/search', 'product/manufacturer', 'product/manufacturer/info', 'product/special', 'extension/module/digitalElephantFilter', 'extension/module/digital_elephant_filter/show_more', 'product/category/index') as $route) {
	guardAssert($guard->isCatalogVariantRequest(array('route' => $route, 'page' => '2'), guardServer()), $route . ' variants must be guarded.');
}
guardAssert($guard->isCatalogVariantRequest(array('_route_' => 'gitare/akusticne-gitare', 'sort' => 'pd.name'), guardServer()), 'SEO catalogue variants must be guarded before route decoding.');
guardAssert($guard->isCatalogVariantRequest(array('sort' => 'pd.name'), guardServer()), 'Clean URLs with no decoded route must be guarded.');
guardAssert($guard->isCatalogVariantRequest($variant, guardServer('192.0.2.1', 'HEAD')), 'HEAD variants must share the limit.');
foreach (array('product/category', 'product/product', 'checkout/cart', 'account/login', 'api/order', 'extension/feed/google_base', 'information/price_list') as $route) {
	$query = array('route' => $route);
	guardAssert(!$guard->isCatalogVariantRequest($query, guardServer()), 'Ordinary ' . $route . ' visits must bypass the guard.');
}
foreach (array('checkout/cart', 'checkout/confirm', 'account/order', 'api/order', 'extension/feed/google_base', 'information/price_list', 'extension/payment/wspay/callback') as $route) {
	$query = array('route' => $route, 'page' => '100001', 'limit' => '99999');
	$result = guardRequest($guard, $query, guardServer(), 1000);
	guardAssert($result === array(0, $query), 'Explicit ' . $route . ' parameters must remain unchanged.');
}
foreach (array('POST', 'PUT', 'OPTIONS') as $method) {
	$query = array_merge($variant, array('limit' => '99999'));
	guardAssert(guardRequest($guard, $query, guardServer('192.0.2.1', $method), 1000) === array(0, $query), $method . ' must bypass both normalization and throttling.');
}
guardAssert(!is_dir($directory . '/main'), 'Unscoped requests must perform no limiter storage work.');

$query = array('route' => 'product/category', 'limit' => '999999999999999999999999', 'page' => '-2', 'sort' => 'arbitrary', 'order' => 'desc', 'path' => '1_2');
$result = guardRequest($guard, $query, guardServer(), 1000);
guardAssert($result === array(0, array('route' => 'product/category', 'limit' => 100, 'page' => 1, 'order' => 'DESC', 'path' => '1_2')), 'Bounds, sorting and direction must normalize without changing unrelated input.');
$query = array('limit' => array('100000'), 'page' => array('2'), 'sort' => array('p.price'), 'order' => array('ASC'));
guardAssert(guardRequest($guard, $query, guardServer('192.0.2.2'), 1000) === array(0, array()), 'Array-valued catalogue parameters must not reach controllers expecting scalars.');
$query = array('limit' => '0', 'page' => '10001', 'sort' => 'ps.price', 'order' => 'asc');
guardAssert(guardRequest($guard, $query, guardServer('192.0.2.3'), 1000) === array(0, array('limit' => 1, 'page' => 10000, 'sort' => 'ps.price', 'order' => 'ASC')), 'Valid special-price sorting must survive normalization.');

$bucket_guard = new CatalogRequestGuard($directory . '/bucket');
for ($i = 0; $i < CatalogRequestGuard::BURST; $i++) {
	guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 2000)[0] === 0, 'A normal browsing burst must be allowed.');
}
guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 2000)[0] === 1, 'The next request in a spent burst must receive a retry delay.');
guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 2000.5)[0] === 1, 'Fractional refill must not allow an early request.');
guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 2001)[0] === 0, 'One second must replenish one request.');
guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 2001)[0] === 1, 'The replenished token must only be spent once.');
guardAssert(guardRequest($bucket_guard, $variant, guardServer('192.0.2.99'), 2001)[0] === 0, 'A different IP must retain its own allowance.');
$spoofed = guardServer();
$spoofed['HTTP_X_FORWARDED_FOR'] = '192.0.2.100';
$spoofed['HTTP_CF_CONNECTING_IP'] = '192.0.2.101';
$spoofed['HTTP_USER_AGENT'] = 'Googlebot';
guardAssert(guardRequest($bucket_guard, $variant, $spoofed, 2001)[0] === 1, 'Forwarded headers and a bot name must not bypass the real client allowance.');
guardAssert(guardRequest($bucket_guard, array('route' => 'checkout/cart', 'page' => '1'), guardServer(), 2001)[0] === 0, 'Exhausting catalogue tokens must not block shopping routes.');
guardAssert(guardRequest($bucket_guard, array('route' => 'product/category'), guardServer(), 2001)[0] === 0, 'Exhausting catalogue tokens must not block an ordinary category visit.');
guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 2062)[0] === 0, 'Idle buckets must reset after expiry.');
guardAssert(guardRequest($bucket_guard, $variant, guardServer('2001:db8::1'), 2062)[0] === 0, 'IPv6 clients must receive an allowance.');

$unavailable = $directory . '/not-a-directory';
file_put_contents($unavailable, 'fixture');
guardAssert(guardRequest(new CatalogRequestGuard($unavailable . '/guard'), $variant, guardServer(), 3000)[0] === 0, 'Unavailable local storage must fail open.');
guardAssert(guardRequest($bucket_guard, $variant, array('REQUEST_METHOD' => 'GET', 'HTTP_X_FORWARDED_FOR' => '192.0.2.1'), 3000)[0] === 0, 'Missing REMOTE_ADDR must not trust forwarded input.');
$key = hash('sha256', inet_pton('192.0.2.1'));
$bucket_path = $directory . '/bucket/bucket-' . substr($key, 0, 2) . '.json';
$lock = fopen($bucket_path, 'c+b');
flock($lock, LOCK_EX);
guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 3000)[0] === 0, 'A contended bucket lock must fail open without waiting.');
flock($lock, LOCK_UN);
fclose($lock);

$records = array();
for ($i = 0; $i < CatalogRequestGuard::MAX_SHARD_ENTRIES; $i++) {
	$records[hash('sha256', 'fixture-' . $i)] = array('time' => 4000, 'tokens' => 29);
}
file_put_contents($bucket_path, json_encode($records));
guardAssert(guardRequest($bucket_guard, $variant, guardServer(), 4000)[0] === 0, 'A full shard must still accept a new client.');
$records = json_decode(file_get_contents($bucket_path), true);
guardAssert(count($records) === CatalogRequestGuard::MAX_SHARD_ENTRIES && isset($records[$key]), 'A full shard must evict an old entry and remain bounded.');
guardAssert(filesize($bucket_path) <= CatalogRequestGuard::MAX_SHARD_BYTES, 'Limiter state must stay below the fixed shard size bound.');
file_put_contents($bucket_path, str_repeat('x', CatalogRequestGuard::MAX_SHARD_BYTES + 1));
$recovered = guardRequest($bucket_guard, $variant, guardServer(), 4001);
clearstatcache(true, $bucket_path);
guardAssert($recovered[0] === 0 && filesize($bucket_path) <= CatalogRequestGuard::MAX_SHARD_BYTES, 'Oversized or corrupt state must recover within the storage bound.');

$active_directory = $directory . '/active';
$active = array();
for ($i = 0; $i < CatalogRequestGuard::CONCURRENT_REQUESTS; $i++) {
	$active[$i] = new CatalogRequestGuard($active_directory);
	$query = $variant;
	guardAssert($active[$i]->evaluate($query, guardServer('192.0.2.' . ($i + 10)), 5000) === 0, 'Each free concurrent slot must admit one request.');
}
$waiting = new CatalogRequestGuard($active_directory);
$query = $variant;
guardAssert($waiting->evaluate($query, guardServer('192.0.2.20'), 5000) === 2, 'Distributed clients must receive 429 when all expensive request slots are occupied.');
$query = array('route' => 'product/product', 'product_id' => 1);
guardAssert($waiting->evaluate($query, guardServer('192.0.2.20'), 5000) === 0, 'A full concurrent pool must not block ordinary product visits.');
$active[1]->release();
$query = $variant;
guardAssert($waiting->evaluate($query, guardServer('192.0.2.20'), 5000) === 0, 'Completing one request must immediately free its slot.');
$waiting->release();
foreach ($active as $request_guard) {
	$request_guard->release();
}
guardAssert(count(glob($active_directory . '/active-*.lock')) === CatalogRequestGuard::CONCURRENT_REQUESTS, 'Concurrent state must use only the fixed number of lock files.');

// Exercise the real front controller with a local bootstrap fixture. A blocked
// request must exit before startup can connect to a database or create a session.
$front = $directory . '/front';
mkdir($front . '/system/library', 0700, true);
mkdir($front . '/cache', 0700);
file_put_contents($front . '/config.php', '<?php define("DIR_APPLICATION", __DIR__ . "/catalog/"); define("DIR_SYSTEM", __DIR__ . "/system/"); define("DIR_CACHE", __DIR__ . "/cache/");');
file_put_contents($front . '/system/library/catalog_request_guard.php', '<?php require ' . var_export(realpath(__DIR__ . '/../system/library/catalog_request_guard.php'), true) . ';');
file_put_contents($front . '/system/startup.php', '<?php file_put_contents(__DIR__ . "/started", "unexpected startup"); exit;');
$front_guards = array();
for ($i = 0; $i < CatalogRequestGuard::CONCURRENT_REQUESTS; $i++) {
	$front_guards[$i] = new CatalogRequestGuard($front . '/cache/catalog-request-guard');
	$query = $variant;
	$front_guards[$i]->evaluate($query, guardServer('192.0.2.' . ($i + 30)), 6000);
}
$entry = realpath(__DIR__ . '/../index.php');
$code = '$_SERVER = array("REQUEST_METHOD" => "GET", "REMOTE_ADDR" => "192.0.2.40"); $_GET = array("route" => "product/category", "page" => "2"); register_shutdown_function(function() { echo "STATUS:" . http_response_code(); }); require ' . var_export($entry, true) . ';';
$process = proc_open(array(PHP_BINARY, '-r', $code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $front);
$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit_code = proc_close($process);
guardAssert($exit_code === 0 && $errors === '' && strpos($output, 'STATUS:429') !== false, 'The real front controller must issue a clean HTTP 429 for a saturated pool.');
guardAssert(!is_file($front . '/system/started') && strpos($output, 'Previše zahtjeva') !== false, 'A blocked request must return the Croatian response before OpenCart startup.');
$head_code = str_replace('"REQUEST_METHOD" => "GET"', '"REQUEST_METHOD" => "HEAD"', $code);
$process = proc_open(array(PHP_BINARY, '-r', $head_code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $front);
$head_output = stream_get_contents($pipes[1]);
$head_errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
guardAssert(proc_close($process) === 0 && $head_errors === '' && $head_output === 'STATUS:429', 'A rejected HEAD request must return 429 without a response body.');
foreach ($front_guards as $front_guard) {
	$front_guard->release();
}

$child_directory = $directory . '/shutdown';
$guard_path = realpath(__DIR__ . '/../system/library/catalog_request_guard.php');
$child_code = 'require ' . var_export($guard_path, true) . '; $guard = new CatalogRequestGuard(' . var_export($child_directory, true) . '); $query = array("page" => "2"); echo $guard->evaluate($query, array("REQUEST_METHOD" => "GET", "REMOTE_ADDR" => "192.0.2.70"));';
$process = proc_open(array(PHP_BINARY, '-r', $child_code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
$child_output = stream_get_contents($pipes[1]);
$child_errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
guardAssert(proc_close($process) === 0 && $child_errors === '' && $child_output === '0', 'A standalone request must acquire a slot successfully.');
$slot_handle = fopen($child_directory . '/active-0.lock', 'c+b');
guardAssert(flock($slot_handle, LOCK_EX | LOCK_NB), 'Request shutdown must release its active slot without an explicit release call.');
flock($slot_handle, LOCK_UN);
fclose($slot_handle);

echo 'PASS: ' . $checks . " catalogue request guard assertions.\n";
