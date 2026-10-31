<?php
namespace Cache;
class File {
	private const FORMAT = 'glazbena-file-cache-v1';
	private $expire;
	private $files = array();

	public function __construct($expire = 3600) {
		$this->expire = $expire;

		$now = time();
		$gc_handle = @fopen(DIR_CACHE . '.cache-gc', 'c+');
		$run_gc = false;

		// Only one request per minute needs to remove expired entries. Reads
		// still check expiry themselves, so delayed cleanup cannot serve old data.
		if ($gc_handle && flock($gc_handle, LOCK_EX | LOCK_NB)) {
			$last_gc = (int)stream_get_contents($gc_handle);

			if ($last_gc < $now - 60) {
				$run_gc = true;
				rewind($gc_handle);
				ftruncate($gc_handle, 0);
				fwrite($gc_handle, (string)$now);
				fflush($gc_handle);
			}
		}

		// Enumerate the full directory only for the elected cleanup request.
		// Building every key's index on every request becomes costly as bots
		// generate thousands of distinct catalogue query cache entries.
		if ($run_gc) {
			$files = glob(DIR_CACHE . 'cache.*');

			if ($files) {
				// Remove legacy copies before expired stable entries, so an old
				// value cannot reappear after its replacement has expired.
				foreach ($files as $file) {
					$expiry = substr(strrchr($file, '.'), 1);

					if (ctype_digit($expiry) && ((int)$expiry < $now || is_file(substr($file, 0, strrpos($file, '.')) . '.data'))) {
						@unlink($file);
					}
				}

				foreach ($files as $file) {
					if (substr($file, -5) === '.data') {
						$entry = json_decode($this->read($file), true);

						if (!$this->isEntry($entry) || (int)$entry['expires_at'] < $now) {
							@unlink($file);
						}
					}
				}
			}
		}

		if ($gc_handle) {
			flock($gc_handle, LOCK_UN);
			fclose($gc_handle);
		}
	}

	public function get($key) {
		$key = preg_replace('/[^A-Z0-9\._-]/i', '', $key);
		$data = $this->read(DIR_CACHE . 'cache.' . $key . '.data');

		if ($data !== false) {
			$entry = json_decode($data, true);

			// An expired replacement must never fall back to an older file.
			if (!$this->isEntry($entry) || (int)$entry['expires_at'] < time()) {
				return false;
			}

			return $entry['value'];
		}

		// Legacy entries remain readable during the gradual cache warmup.
		// New entries use a deterministic path and never need glob().
		if (!isset($this->files[$key])) {
			$this->files[$key] = array();
			$files = glob(DIR_CACHE . 'cache.' . $key . '.*');

			if ($files) {
				$prefix = DIR_CACHE . 'cache.' . $key . '.';

				foreach ($files as $file) {
					// A parent key must not read a dotted descendant's payload.
					// Only the numeric expiry may follow the exact requested key.
					if (ctype_digit(substr($file, strlen($prefix)))) {
						$this->files[$key][] = $file;
					}
				}
			}
		}

		$files = $this->files[$key];

		foreach ($files as $file) {
			if ((int)substr(strrchr($file, '.'), 1) < time()) {
				continue;
			}

			$data = $this->read($file);

			if ($data === false) {
				continue;
			}

			if ($data === '') {
				return false;
			}

			return json_decode($data, true);
		}

		return false;
	}

	public function set($key, $value) {
		$key = preg_replace('/[^A-Z0-9\._-]/i', '', $key);
		$data = json_encode(array('marker' => self::FORMAT, 'expires_at' => time() + $this->expire, 'value' => $value));

		if ($data === false) {
			return false;
		}

		$temporary = tempnam(DIR_CACHE, '.cache-write-');

		if ($temporary === false) {
			return false;
		}

		$written = file_put_contents($temporary, $data, LOCK_EX);
		$gc_handle = @fopen(DIR_CACHE . '.cache-gc', 'c+');
		$published = false;

		// Coordinate publication with cleanup so it cannot unlink a fresh
		// replacement using an expiry read from the previous file.
		if ($written === strlen($data) && $gc_handle && flock($gc_handle, LOCK_SH)) {
			$published = rename($temporary, DIR_CACHE . 'cache.' . $key . '.data');
			flock($gc_handle, LOCK_UN);
		}

		if ($gc_handle) {
			fclose($gc_handle);
		}

		if (!$published) {
			@unlink($temporary);
			return false;
		}

		$this->files[$key] = array();
		return true;
	}

	private function isEntry($entry) {
		return is_array($entry) && isset($entry['marker'], $entry['expires_at']) && $entry['marker'] === self::FORMAT && is_numeric($entry['expires_at']) && array_key_exists('value', $entry);
	}

	private function read($file) {
		$handle = @fopen($file, 'r');

		if (!$handle) {
			return false;
		}

		if (!flock($handle, LOCK_SH)) {
			fclose($handle);
			return false;
		}

		// Read the stream rather than statting a path another request may
		// unlink or atomically replace while this handle remains valid.
		$data = stream_get_contents($handle);
		flock($handle, LOCK_UN);
		fclose($handle);
		return $data;
	}

	public function delete($key) {
		if ($key == '*') {
			$files = glob(DIR_CACHE . 'cache.*.*');
			$this->files = array();
		} else {
			$key = preg_replace('/[^A-Z0-9\._-]/i', '', $key);
			// Mutations need a fresh listing, including entries written by other
			// requests after our first lookup. Keep prefix invalidation intact.
			$files = glob(DIR_CACHE . 'cache.' . $key . '.*');

			foreach (array_keys($this->files) as $indexed_key) {
				if ($indexed_key === $key || strpos($indexed_key, $key . '.') === 0) {
					unset($this->files[$indexed_key]);
				}
			}
		}

		if ($files) {
			foreach ($files as $file) {
				if (file_exists($file)) {
					@unlink($file);
				}
			}
		}
	}
}
