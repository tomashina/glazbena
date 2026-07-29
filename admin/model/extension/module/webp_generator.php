<?php
class ModelExtensionModuleWebpGenerator extends Model {
	const QUALITY = 82;

	public function getTotalProductImages() {
		$query = $this->db->query(
			"SELECT COUNT(*) AS total FROM (" .
			"SELECT image FROM `" . DB_PREFIX . "product` WHERE image <> '' " .
			"UNION " .
			"SELECT image FROM `" . DB_PREFIX . "product_image` WHERE image <> ''" .
			") product_images"
		);

		return (int)$query->row['total'];
	}

	public function getProductImages($start = 0, $limit = 5) {
		$start = max(0, (int)$start);
		$limit = max(1, min(20, (int)$limit));

		$query = $this->db->query(
			"SELECT image FROM (" .
			"SELECT image FROM `" . DB_PREFIX . "product` WHERE image <> '' " .
			"UNION " .
			"SELECT image FROM `" . DB_PREFIX . "product_image` WHERE image <> ''" .
			") product_images ORDER BY image ASC LIMIT " . $start . "," . $limit
		);

		return $query->rows;
	}

	public function getWebpCacheStats() {
		$stats = array(
			'files' => 0,
			'bytes' => 0
		);

		$cache_directory = DIR_IMAGE . 'cache';

		if (!is_dir($cache_directory)) {
			return $stats;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($cache_directory, FilesystemIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file) {
				if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
					$stats['files']++;
					$stats['bytes'] += $file->getSize();
				}
			}
		} catch (UnexpectedValueException $exception) {
			$this->log->write('WebP Generator: Cache statistics could not be read: ' . $exception->getMessage());
		}

		return $stats;
	}

	public function generateBatch($start, $limit, array $sizes, $force = false) {
		$images = $this->getProductImages($start, $limit);
		$result = array(
			'processed_sources' => count($images),
			'generated' => 0,
			'skipped' => 0,
			'failed' => 0,
			'errors' => array()
		);

		foreach ($images as $row) {
			$filename = $this->normaliseFilename($row['image']);
			$source = $this->validateSource($filename);

			if (!$source['valid']) {
				$result['failed']++;
				$result['errors'][] = array(
					'file' => $filename,
					'code' => $source['code']
				);
				continue;
			}

			foreach ($sizes as $size) {
				$generated = $this->generateImage(
					$filename,
					$source,
					(int)$size['width'],
					(int)$size['height'],
					isset($size['mode']) ? $size['mode'] : 'contain',
					(bool)$force
				);

				if ($generated['status'] === 'generated') {
					$result['generated']++;
				} elseif ($generated['status'] === 'skipped') {
					$result['skipped']++;
				} else {
					$result['failed']++;

					if (count($result['errors']) < 20) {
						$result['errors'][] = array(
							'file' => $filename,
							'code' => $generated['code'],
							'size' => (int)$size['width'] . '×' . (int)$size['height']
						);
					}
				}
			}
		}

		return $result;
	}

	private function normaliseFilename($filename) {
		return ltrim(str_replace('\\', '/', trim((string)$filename)), '/');
	}

	private function validateSource($filename) {
		if ($filename === '' || preg_match('#(^|/)\.\.(/|$)#', $filename)) {
			return array('valid' => false, 'code' => 'invalid_path');
		}

		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		if (!in_array($extension, array('jpg', 'jpeg', 'png', 'webp'), true)) {
			return array('valid' => false, 'code' => 'unsupported_type');
		}

		$image_root = realpath(DIR_IMAGE);
		$source_path = realpath(DIR_IMAGE . $filename);

		if ($image_root === false || $source_path === false || !is_file($source_path)) {
			return array('valid' => false, 'code' => 'missing_source');
		}

		$image_root = rtrim(str_replace('\\', '/', $image_root), '/') . '/';
		$normalised_source = str_replace('\\', '/', $source_path);

		if (strpos($normalised_source, $image_root) !== 0) {
			return array('valid' => false, 'code' => 'invalid_path');
		}

		$info = @getimagesize($source_path);

		if (!$info || !in_array($info[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP), true)) {
			return array('valid' => false, 'code' => 'invalid_image');
		}

		return array(
			'valid' => true,
			'path' => $source_path,
			'extension' => $extension,
			'width' => (int)$info[0],
			'height' => (int)$info[1]
		);
	}

	private function generateImage($filename, array $source, $width, $height, $mode, $force) {
		if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
			return array('status' => 'failed', 'code' => 'invalid_size');
		}

		$mode = $mode === 'cover' ? 'cover' : 'contain';
		$filename_without_extension = substr($filename, 0, strrpos($filename, '.'));
		$target_relative = 'cache/' . $filename_without_extension . '-' . $width . 'x' . $height;

		if ($mode === 'cover') {
			$target_relative .= '-cover';
		}

		$target_relative .= '-q' . self::QUALITY . '.webp';
		$target_path = DIR_IMAGE . $target_relative;

		if (
			!$force &&
			is_file($target_path) &&
			filesize($target_path) > 0 &&
			filemtime($target_path) >= filemtime($source['path'])
		) {
			return array('status' => 'skipped');
		}

		$target_directory = dirname($target_path);

		if (!is_dir($target_directory) && !@mkdir($target_directory, 0777, true) && !is_dir($target_directory)) {
			return array('status' => 'failed', 'code' => 'directory_not_writable');
		}

		if (!is_writable($target_directory)) {
			return array('status' => 'failed', 'code' => 'directory_not_writable');
		}

		try {
			if ($source['width'] === $width && $source['height'] === $height && $source['extension'] === 'webp') {
				$success = @copy($source['path'], $target_path);
			} else {
				$image = new Image($source['path']);

				if ($source['width'] !== $width || $source['height'] !== $height) {
					if ($mode === 'cover') {
						$source_ratio = $source['width'] / $source['height'];
						$target_ratio = $width / $height;
						$image->resize($width, $height, $source_ratio > $target_ratio ? 'h' : 'w');
					} else {
						$image->resize($width, $height);
					}
				}

				$image->save($target_path, self::QUALITY);
				$success = is_file($target_path) && filesize($target_path) > 0;
				unset($image);
			}
		} catch (Throwable $exception) {
			$this->log->write('WebP Generator: ' . $filename . ': ' . $exception->getMessage());
			return array('status' => 'failed', 'code' => 'conversion_failed');
		}

		if (!$success) {
			return array('status' => 'failed', 'code' => 'conversion_failed');
		}

		return array('status' => 'generated');
	}
}
