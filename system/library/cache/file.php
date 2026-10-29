<?php
namespace Cache;
class File {
	private $expire;
	private $files = array();

	public function __construct($expire = 3600) {
		$this->expire = $expire;

		$files = glob(DIR_CACHE . 'cache.*');
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

		if ($files) {
			foreach ($files as $file) {
				$separator = strrpos($file, '.');
				$time = substr($file, $separator + 1);

				if ($time < $now) {
					if ($run_gc) {
						@unlink($file);
					}
					continue;
				}

				$key = substr($file, strlen(DIR_CACHE . 'cache.'), $separator - strlen(DIR_CACHE . 'cache.'));
				$this->files[$key][] = $file;
			}
		}

		if ($gc_handle) {
			flock($gc_handle, LOCK_UN);
			fclose($gc_handle);
		}
	}

	public function get($key) {
		$key = preg_replace('/[^A-Z0-9\._-]/i', '', $key);
		$files = isset($this->files[$key]) ? $this->files[$key] : array();

		foreach ($files as $file) {
			if ((int)substr(strrchr($file, '.'), 1) < time()) {
				continue;
			}

			// Open the indexed path on every read: another request may have
			// invalidated it since this request built its directory index.
			$handle = @fopen($file, 'r');

			if (!$handle) {
				continue;
			}

			if (!flock($handle, LOCK_SH)) {
				fclose($handle);
				return false;
			}

			// Read from the already opened stream. The cache file can be
			// unlinked by another request after glob(), so asking the path
			// for its size here causes a warning even though the handle is
			// still valid.
			$data = stream_get_contents($handle);

			flock($handle, LOCK_UN);
			fclose($handle);

			if ($data === false || $data === '') {
				return false;
			}

			return json_decode($data, true);
		}

		return false;
	}

	public function set($key, $value) {
		$this->delete($key);

		$file = DIR_CACHE . 'cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) . '.' . (time() + $this->expire);

		$handle = fopen($file, 'w');

		flock($handle, LOCK_EX);

		fwrite($handle, json_encode($value));

		fflush($handle);

		flock($handle, LOCK_UN);

		fclose($handle);

		$key = preg_replace('/[^A-Z0-9\._-]/i', '', $key);
		$this->files[$key] = array($file);
	}

	public function delete($key) {
		if ($key == '*') {
			$files = glob(DIR_CACHE . 'cache.*.*');
			$this->files = array();
		} else {
			$key = preg_replace('/[^A-Z0-9\._-]/i', '', $key);
			// Mutations need a fresh listing, including entries written by other
			// requests after our index was built. Keep prefix invalidation intact.
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
