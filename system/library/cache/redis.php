<?php
namespace Cache;

class Redis {
    private $expire = 3600;
    private $prefix = 'oc_';
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
        $this->prefix = defined('CACHE_PREFIX') ? (string)CACHE_PREFIX : 'oc_';
        $timeout  = defined('CACHE_TIMEOUT')  ? (float)CACHE_TIMEOUT : 1.0;

        // 2) Always reference the GLOBAL class \Redis (not namespaced)
        $this->redis = new \Redis();

        if (!$this->redis->pconnect($host, $port, $timeout)) {
            throw new \RuntimeException('Could not connect to Redis at ' . $host . ':' . $port);
        }

        if ($password !== '') {
            if (!$this->redis->auth($password)) {
                throw new \RuntimeException('Redis authentication failed');
            }
        }
    }

    public function get($key) {
        $value = $this->redis->get($this->prefix . $key);

        return $value === false ? false : unserialize($value);
    }

    public function set($key, $value) {
        $payload = serialize($value);

        return $this->expire > 0
            ? $this->redis->setEx($this->prefix . $key, $this->expire, $payload)
            : $this->redis->set($this->prefix . $key, $payload);
    }

    public function delete($key) {
        if ($key === '*') {
            $pattern = $this->prefix . '*';
        } else {
            $this->redis->del($this->prefix . $key);
            $pattern = $this->prefix . $key . '.*';
        }

        $iterator = null;

        do {
            $keys = $this->redis->scan($iterator, $pattern, 1000);

            if ($keys) {
                $this->redis->del($keys);
            }
        } while ($iterator > 0);

        return true;
    }
}
