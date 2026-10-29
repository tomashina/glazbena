<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Cache class
*/
class Cache {
	private $adaptor;
	
	/**
	 * Constructor
	 *
	 * @param	string	$adaptor	The type of storage for the cache.
	 * @param	int		$expire		Optional parameters
	 *
 	*/
	public function __construct($adaptor, $expire = 3600) {
		$class = 'Cache\\' . $adaptor;

		if (class_exists($class)) {
			$this->adaptor = new $class($expire);
		} else {
			throw new \Exception('Error: Could not load cache adaptor ' . $adaptor . ' cache!');
		}
	}
	
    /**
     * Gets a cache by key name.
     *
     * @param	string $key	The cache key name
     *
     * @return	string
     */
	public function get($key) {
		return $this->adaptor->get($key);
	}
	
    /**
     * 
     *
     * @param	string	$key	The cache key
	 * @param	string	$value	The cache value
	 * 
	 * @return	string
     */
	public function set($key, $value) {
		return $this->adaptor->set($key, $value);
	}

	/**
	 * Read an explicitly timed entry without changing ordinary cache payloads.
	 */
	public function getTimed($key) {
		$entry = $this->get($key);

		if (!is_array($entry) || !isset($entry['expires_at']) || !is_numeric($entry['expires_at']) || !array_key_exists('value', $entry) || (int)$entry['expires_at'] <= $this->timedCacheNow()) {
			return false;
		}

		return $entry['value'];
	}

	/**
	 * Store a deadline with the value so every adaptor enforces the same TTL.
	 */
	public function setTimed($key, $value, $ttl = 300) {
		return $this->set($key, array(
			'expires_at' => $this->timedCacheNow() + max(0, (int)$ttl),
			'value'      => $value
		));
	}

	protected function timedCacheNow() {
		return time();
	}
   
    /**
     * 
     *
     * @param	string	$key	The cache key
     */
	public function delete($key) {
		return $this->adaptor->delete($key);
	}
}
