<?php
namespace Cache;

class Redis {
    private $expire = 3600;
    /** @var \Redis */
    private $redis;

    public function __construct($expire = 3600) {
        $this->expire = (int)$expire ?: 3600;

        // 1) Ensure the PHP redis extension is loaded in THIS SAPIs (php-fpm)
        if (!class_exists('\\Redis')) {
            // Throwing a \RuntimeException will end up in error.log with a clear message
            throw new \RuntimeException('phpredis extension not loaded for FPM');
        }

        $host     = defined('CACHE_HOSTNAME') ? CACHE_HOSTNAME : '127.0.0.1';
        $port     = defined('CACHE_PORT')     ? (int)CACHE_PORT : 6379;
        $password = defined('CACHE_PASSWORD') ? CACHE_PASSWORD  : '';
        $prefix   = defined('CACHE_PREFIX')   ? CACHE_PREFIX    : 'oc_';
        $timeout  = defined('CACHE_TIMEOUT')  ? (float)CACHE_TIMEOUT : 1.0;

        // 2) Always reference the GLOBAL class \Redis (not namespaced)
        $this->redis = new \Redis();
        $this->redis->pconnect($host, $port, $timeout);
        if ($password !== '') {
            $this->redis->auth($password);
        }
        $this->redis->setOption(\Redis::OPT_PREFIX, $prefix);
    }

    public function get($key) {
        $v = $this->redis->get($key);
        return $v === false ? null : unserialize($v);
    }

    public function set($key, $value) {
        $payload = serialize($value);
        return $this->expire > 0
            ? $this->redis->setEx($key, $this->expire, $payload)
            : $this->redis->set($key, $payload);
    }

    public function delete($key) {
        $this->redis->del($key);
    }
}
