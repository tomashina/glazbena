<?php
namespace Cache;
class File {
	private $expire;

	public function __construct($expire = 3600) {
		$this->expire = $expire;

		$files = glob(DIR_CACHE . 'cache.*');

		if ($files) {
			foreach ($files as $file) {
				$time = substr(strrchr($file, '.'), 1);

				if ($time < time()) {
					if (file_exists($file)) {
						@unlink($file);
					}
				}
			}
		}
	}

	public function get($key) {
		$files = glob(DIR_CACHE . 'cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) . '.*');

		if ($files) {
			$handle = @fopen($files[0], 'r');

			if (!$handle) {
				return false;
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
	}

	public function delete($key) {
		if ($key == '*') {
			$files = glob(DIR_CACHE . 'cache.*.*');
		} else {
			$files = glob(DIR_CACHE . 'cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) . '.*');
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
