<?php
namespace Cache {
    // Make directory scans observable and expiry deterministic without sleeps.
    function glob($pattern) {
        $GLOBALS['file_cache_test_globs']++;
        $GLOBALS['file_cache_test_patterns'][] = $pattern;
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
    $file_cache_test_patterns = array();
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

    function fileCacheFullScans() {
        return count(array_filter($GLOBALS['file_cache_test_patterns'], function($pattern) {
            return $pattern === DIR_CACHE . 'cache.*';
        }));
    }

    $valid = fileCacheSeed('product.query.list', array('name' => 'Guitar'), $file_cache_test_now + 300);
    fileCacheSeed('zero', 0, $file_cache_test_now + 300);
    fileCacheSeed('empty', array(), $file_cache_test_now + 300);
    $expired = fileCacheSeed('expired', 'old', $file_cache_test_now - 1);
    $cache = new Cache\File(300);
    fileCacheAssert($file_cache_test_globs === 1, 'The first cleanup must enumerate the cache directory once.');
    fileCacheAssert($cache->get('product.query.list') === array('name' => 'Guitar'), 'Dotted cache keys must read their existing JSON payload.');
    fileCacheAssert($cache->get('zero') === 0 && $cache->get('empty') === array(), 'Zero and empty cached values must remain cache hits.');
    fileCacheAssert($cache->get('expired') === false && !is_file($expired), 'The first cleanup must remove expired entries and return a miss.');
    $cache->get('missing');
    $lookup_scans = $file_cache_test_globs;
    for ($i = 0; $i < 100; $i++) {
        $cache->get('product.query.list');
        $cache->get('missing');
    }
    fileCacheAssert($file_cache_test_globs === $lookup_scans, 'Repeated cache hits and misses must reuse the exact key lookup.');

    // Thousands of bot-generated keys must not be enumerated and indexed by
    // every new catalogue request. Only the elected cleanup does a full scan.
    for ($i = 0; $i < 1000; $i++) {
        fileCacheSeed('catalogue.query.' . $i, $i, $file_cache_test_now + 1);
    }
    $before_requests = $file_cache_test_globs;
    for ($i = 0; $i < 100; $i++) {
        $next_request = new Cache\File();
    }
    fileCacheAssert($file_cache_test_globs === $before_requests, 'Ordinary request construction must perform no directory enumeration, even with many cached queries.');
    fileCacheAssert($next_request->get('product.query.list') === array('name' => 'Guitar'), 'A new request must lazily read an existing legacy cache filename.');
    fileCacheAssert($file_cache_test_patterns[count($file_cache_test_patterns) - 1] === DIR_CACHE . 'cache.product.query.list.*', 'Reads must list only the requested key, rather than every cache entry.');
    fileCacheAssert(fileCacheFullScans() === 1, 'Repeated request construction and reads must leave the full scan count unchanged.');
    fileCacheAssert($cache->get('catalogue') === false, 'A parent key must never return the payload of a dotted descendant.');

    $before_set = $file_cache_test_globs;
    $cache->set('new.key', array(123));
    fileCacheAssert($file_cache_test_globs === $before_set, 'Writing a stable cache entry must not enumerate any cache files.');
    fileCacheAssert($cache->get('new.key') === array(123), 'A value set by this request must be available immediately.');
    fileCacheAssert(is_file(DIR_CACHE . 'cache.new.key.data'), 'New entries must use the deterministic cache path.');
    $cache->set('space / key', 'sanitized');
    fileCacheAssert($cache->get('space / key') === 'sanitized', 'Key sanitization must remain consistent across reads and writes.');
    $cache->set('stable.zero', 0);
    $cache->set('stable.empty', array());
    $cache->set('stable.null', null);
    $cache->set('stable.parent.child', 'child');
    $cache->set('stable.parent', 'parent');
    $before_stable = $file_cache_test_globs;
    for ($i = 0; $i < 20; $i++) {
        $stable_reader = new Cache\File();
        fileCacheAssert($stable_reader->get('new.key') === array(123) && $stable_reader->get('stable.zero') === 0 && $stable_reader->get('stable.empty') === array() && $stable_reader->get('stable.null') === null, 'Stable cache values must be readable across requests without changing falsey payloads.');
    }
    fileCacheAssert($file_cache_test_globs === $before_stable, 'Warm requests reading multiple stable keys must perform zero directory enumerations.');
    fileCacheAssert($cache->get('stable.parent.child') === 'child', 'Setting an exact key must preserve a separately cached dotted descendant.');

    $cache->set('stable.concurrent', 'before');
    fileCacheAssert($stable_reader->get('stable.concurrent') === 'before', 'A stable entry must be readable by another request.');
    $cache->set('stable.concurrent', 'after');
    fileCacheAssert($stable_reader->get('stable.concurrent') === 'after', 'Atomic replacement must be visible to an existing reader.');
    $cache->delete('stable.concurrent');
    fileCacheAssert($stable_reader->get('stable.concurrent') === false, 'Concurrent stable invalidation must produce a cache miss.');
    fileCacheAssert($stable_reader->get('stable.later') === false, 'A stable entry may initially be absent.');
    $cache->set('stable.later', 'new');
    fileCacheAssert($stable_reader->get('stable.later') === 'new', 'A memoized legacy miss must not hide a new stable entry.');

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
    fileCacheAssert(!is_file(DIR_CACHE . 'cache.product.query.one.data') && !is_file(DIR_CACHE . 'cache.product.filter.two.data'), 'Prefix invalidation must delete stable and legacy descendants together.');

    $short = fileCacheSeed('short', 'short-lived', $file_cache_test_now + 1);
    $stable_short = new Cache\File(1);
    $stable_short->set('stable.short', 'replacement');
    $replaced_legacy = fileCacheSeed('stable.short', 'superseded legacy value', $file_cache_test_now + 300);
    $cache = new Cache\File();
    fileCacheAssert($cache->get('short') === 'short-lived', 'A valid short-lived entry must be readable.');
    fileCacheAssert($cache->get('stable.short') === 'replacement', 'A stable replacement must take precedence over a still-valid legacy file.');
    $file_cache_test_now += 2;
    fileCacheAssert($cache->get('short') === false, 'An entry expiring after construction must become a miss in the same request.');
    fileCacheAssert($cache->get('stable.short') === false, 'Expired stable entries must miss without falling back to a superseded legacy value.');
    $cache = new Cache\File();
    fileCacheAssert(is_file($short) && $cache->get('short') === false, 'Throttled cleanup must skip deletion without serving expired data.');
    $file_cache_test_now += 61;
    $cache = new Cache\File();
    fileCacheAssert(!is_file($short), 'Cleanup must run again after its one-minute interval.');
    fileCacheAssert(fileCacheFullScans() === 2, 'Only the first request after the cleanup interval may scan the full directory.');
    fileCacheAssert(glob(DIR_CACHE . 'cache.catalogue.query.*') === array(), 'Cleanup must reclaim abandoned query entries after expiry so they do not accumulate indefinitely.');
    fileCacheAssert(!is_file(DIR_CACHE . 'cache.stable.short.data') && !is_file($replaced_legacy) && $cache->get('stable.short') === false, 'Cleanup must reclaim expired stable entries and their superseded legacy copies without resurrecting old data.');

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
