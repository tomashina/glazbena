<?php

require_once(__DIR__ . '/product_meta.php');

class BlogMeta {
	public static function autofillDescriptions(array $descriptions, $tag_limit = 5, $short_description_length = 220, $meta_description_length = 160) {
		foreach ($descriptions as $language_id => $description) {
			if (!is_array($description)) {
				continue;
			}

			$title = self::plainText(isset($description['title']) ? $description['title'] : '');
			$body = self::plainText(isset($description['description']) ? $description['description'] : '');
			$short_description = self::plainText(isset($description['short_description']) ? $description['short_description'] : '');
			$meta_description = self::plainText(isset($description['meta_description']) ? $description['meta_description'] : '');
			$meta_keyword = self::plainText(isset($description['meta_keyword']) ? $description['meta_keyword'] : '');
			$tags = implode(', ', ProductMeta::generateTags($title, '', $tag_limit));

			if (!isset($description['page_title']) || trim((string)$description['page_title']) === '') {
				$description['page_title'] = $title;
			}

			if (($short_description === '' || $short_description === $title) && self::length($body) > self::length($short_description)) {
				$description['short_description'] = self::excerpt($body, $short_description_length);
				$short_description = $description['short_description'];
			}

			if ($meta_description === '' || $meta_description === $title) {
				$meta_source = $short_description !== '' ? $short_description : ($body !== '' ? $body : $title);
				$description['meta_description'] = self::excerpt($meta_source, $meta_description_length);
			}

			if ($meta_keyword === '' || $meta_keyword === $title) {
				$description['meta_keyword'] = $tags;
			}

			if (!isset($description['tags']) || trim((string)$description['tags']) === '') {
				$description['tags'] = $tags;
			}

			$descriptions[$language_id] = $description;
		}

		return $descriptions;
	}

	public static function plainText($html) {
		$text = (string)$html;

		for ($decode = 0; $decode < 2; $decode++) {
			$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		}

		$text = preg_replace('/<\s*(?:br|\/p|\/div|\/li|\/h[1-6])\s*\/?>/iu', ' ', $text);
		$text = strip_tags($text);
		$text = str_replace(array('&nbsp;', '&amp;nbsp;', "\xC2\xA0"), ' ', $text);
		$text = preg_replace('/\s+/u', ' ', $text);

		return trim($text);
	}

	public static function excerpt($text, $limit) {
		$text = self::plainText($text);
		$limit = max(1, (int)$limit);

		if (self::length($text) <= $limit) {
			return $text;
		}

		$excerpt = self::substring($text, 0, $limit + 1);
		$word_boundary = preg_replace('/\s+\S*$/u', '', $excerpt);

		if ($word_boundary !== '') {
			$excerpt = $word_boundary;
		} else {
			$excerpt = self::substring($text, 0, $limit);
		}

		return rtrim($excerpt, " \t\n\r\0\x0B,.;:-");
	}

	private static function length($text) {
		if (function_exists('mb_strlen')) {
			return mb_strlen($text, 'UTF-8');
		}

		if (function_exists('iconv_strlen')) {
			return iconv_strlen($text, 'UTF-8');
		}

		return strlen($text);
	}

	private static function substring($text, $start, $length) {
		if (function_exists('mb_substr')) {
			return mb_substr($text, $start, $length, 'UTF-8');
		}

		if (function_exists('iconv_substr')) {
			return iconv_substr($text, $start, $length, 'UTF-8');
		}

		return substr($text, $start, $length);
	}
}
