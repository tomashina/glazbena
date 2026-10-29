<?php
namespace Cache {
    // Make directory scans observable and expiry deterministic without sleeps.
    function glob($pattern) {
        $GLOBALS['file_cache_test_globs']++;
        return \glob($pattern);
    }

    function time() {
        return $GLOBALS['file_cache_test_now'];
    }
}

namespace {
    error_reporting(E_ALL);
    set_error_handler(function($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        return true;
    });

    require_once __DIR__ . '/../system/library/cache/file.php';
    define('DIR_CACHE', sys_get_temp_dir() . '/glazbena-file-cache-test-' . bin2hex(random_bytes(8)) . '/');
    mkdir(DIR_CACHE);
    register_shutdown_function(function() {
        foreach (new DirectoryIterator(DIR_CACHE) as $file) {
            if (!$file->isDot()) {
                unlink($file->getPathname());
            }
        }
        rmdir(DIR_CACHE);
    });

    $file_cache_test_globs = 0;
    $file_cache_test_now = time();
    $checks = 0;

    function fileCacheAssert($condition, $message) {
        global $checks;
        $checks++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function fileCacheSeed($key, $value, $expiry) {
        $path = DIR_CACHE . 'cache.' . $key . '.' . $expiry;
        file_put_contents($path, json_encode($value));
        return $path;
    }

    $valid = fileCacheSeed('product.query.list', array('name' => 'Guitar'), $file_cache_test_now + 300);
    fileCacheSeed('zero', 0, $file_cache_test_now + 300);
    fileCacheSeed('empty', array(), $file_cache_test_now + 300);
    $expired = fileCacheSeed('expired', 'old', $file_cache_test_now - 1);
    $cache = new Cache\File(300);
    fileCacheAssert($file_cache_test_globs === 1, 'Construction must enumerate the cache directory once.');
    fileCacheAssert($cache->get('product.query.list') === array('name' => 'Guitar'), 'Dotted cache keys must read their existing JSON payload.');
    fileCacheAssert($cache->get('zero') === 0 && $cache->get('empty') === array(), 'Zero and empty cached values must remain cache hits.');
    fileCacheAssert($cache->get('expired') === false && !is_file($expired), 'The first cleanup must remove expired entries and return a miss.');
    for ($i = 0; $i < 100; $i++) {
        $cache->get('product.query.list');
        $cache->get('missing');
    }
    fileCacheAssert($file_cache_test_globs === 1, 'Repeated cache hits and misses must not scan the directory again.');

    $cache->set('new.key', array(123));
    fileCacheAssert($cache->get('new.key') === array(123), 'A value set by this request must be available immediately.');
    $cache->set('space / key', 'sanitized');
    fileCacheAssert($cache->get('space / key') === 'sanitized', 'Key sanitization must remain consistent across reads and writes.');

    // Reopen the file each time rather than memoizing an already-read payload.
    file_put_contents($valid, json_encode(array('name' => 'Piano')));
    fileCacheAssert($cache->get('product.query.list') === array('name' => 'Piano'), 'A concurrent replacement of the indexed file must be visible.');
    unlink($valid);
    fileCacheAssert($cache->get('product.query.list') === false, 'Concurrent invalidation must turn an indexed entry into a miss.');

    $cache->set('product.query.one', 1);
    $cache->set('product.filter.two', 2);
    $concurrent = fileCacheSeed('product.query.concurrent', 3, $file_cache_test_now + 300);
    $cache->delete('product');
    fileCacheAssert($cache->get('product.query.one') === false && $cache->get('product.filter.two') === false, 'Prefix invalidation must clear matching entries from the request index.');
    fileCacheAssert(!is_file($concurrent), 'Prefix invalidation must also remove files created after the request index.');
    fileCacheAssert($cache->get('zero') === 0, 'Prefix invalidation must preserve unrelated cache keys.');

    $short = fileCacheSeed('short', 'short-lived', $file_cache_test_now + 1);
    $cache = new Cache\File();
    fileCacheAssert($cache->get('short') === 'short-lived', 'A valid short-lived entry must be readable.');
    $file_cache_test_now += 2;
    fileCacheAssert($cache->get('short') === false, 'An entry expiring after construction must become a miss in the same request.');
    $cache = new Cache\File();
    fileCacheAssert(is_file($short) && $cache->get('short') === false, 'Throttled cleanup must skip deletion without serving expired data.');
    $file_cache_test_now += 61;
    $cache = new Cache\File();
    fileCacheAssert(!is_file($short), 'Cleanup must run again after its one-minute interval.');

    // A busy cleanup lock must never hold up a catalogue request.
    $file_cache_test_now += 61;
    $locked_expired = fileCacheSeed('locked.expired', 'old', $file_cache_test_now - 1);
    $lock = fopen(DIR_CACHE . '.cache-gc', 'c+');
    flock($lock, LOCK_EX);
    $cache = new Cache\File();
    fileCacheAssert(is_file($locked_expired) && $cache->get('locked.expired') === false, 'A busy cleanup lock must skip cleanup and preserve expiry checks.');
    flock($lock, LOCK_UN);
    fclose($lock);
    $cache = new Cache\File();
    fileCacheAssert(!is_file($locked_expired), 'The next unlocked request must perform the pending cleanup.');

    $concurrent = fileCacheSeed('later.request', 5, $file_cache_test_now + 300);
    $cache->delete('*');
    fileCacheAssert($cache->get('zero') === false && $cache->get('new.key') === false, 'Full invalidation must empty the request index.');
    fileCacheAssert(!is_file($concurrent) && glob(DIR_CACHE . 'cache.*') === array(), 'Full invalidation must remove entries created after construction.');

    echo 'PASS: ' . $checks . " file cache index assertions; warnings are test failures.\n";
}
