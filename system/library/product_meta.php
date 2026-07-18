<?php

class ProductMeta {
	private static $stop_words = array(
		'a', 'ali', 'ako', 'bez', 'bi', 'biti', 'da', 'do', 'ga', 'i', 'ili', 'iz', 'je', 'još',
		'kao', 'koja', 'koje', 'koji', 'kroz', 'na', 'ne', 'nije', 'od', 'po', 'pod', 'pri', 'sa',
		'se', 'si', 'smo', 'su', 'što', 'ta', 'te', 'to', 'u', 'uz', 'već', 'za', 'zbog',
		'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'in', 'is', 'it', 'its',
		'of', 'on', 'or', 'that', 'the', 'this', 'to', 'with', 'your',
		'der', 'die', 'das', 'den', 'des', 'ein', 'eine', 'für', 'ist', 'mit', 'und', 'von', 'zu'
	);

	public static function autofillDescriptions(array $descriptions, $limit = 5) {
		$limit = max(1, (int)$limit);

		foreach ($descriptions as $language_id => $description) {
			if (!is_array($description)) {
				continue;
			}

			$name = isset($description['name']) ? trim((string)$description['name']) : '';
			$html = isset($description['description']) ? (string)$description['description'] : '';
			$tags = self::generateTags($name, $html, $limit);
			$tag_list = implode(', ', $tags);

			$description['meta_title'] = $name;
			$description['meta_keyword'] = $tag_list;
			$description['tag'] = $tag_list;
			$descriptions[$language_id] = $description;
		}

		return $descriptions;
	}

	public static function generateTags($name, $html, $limit = 5) {
		$limit = max(1, (int)$limit);
		$title_tokens = self::tokenize($name);
		$description_text = strip_tags(
			str_replace(array('</p>', '</li>', '<br>', '<br/>', '<br />'), ' ', (string)$html)
		);

		for ($decode = 0; $decode < 2; $decode++) {
			$description_text = html_entity_decode($description_text, ENT_QUOTES, 'UTF-8');
		}

		$description_text = str_replace(array('&nbsp;', '&amp;nbsp;', "\xC2\xA0"), ' ', $description_text);
		$description_tokens = self::tokenize($description_text);
		$stop_words = array_fill_keys(self::$stop_words, true);
		$candidates = array();
		$position = 0;

		foreach ($title_tokens as $token) {
			$token = self::normalizeToken($token);

			if (!self::isUsefulToken($token, true, $stop_words)) {
				continue;
			}

			if (!isset($candidates[$token])) {
				$candidates[$token] = array('score' => 0, 'position' => $position++);
			}

			$candidates[$token]['score'] += 100;
		}

		foreach ($description_tokens as $token) {
			$token = self::normalizeToken($token);

			if (!self::isUsefulToken($token, false, $stop_words)) {
				continue;
			}

			if (!isset($candidates[$token])) {
				$candidates[$token] = array('score' => 0, 'position' => $position++);
			}

			$candidates[$token]['score'] += 1;
		}

		uksort($candidates, function($left, $right) use ($candidates) {
			if ($candidates[$left]['score'] === $candidates[$right]['score']) {
				return $candidates[$left]['position'] <=> $candidates[$right]['position'];
			}

			return $candidates[$right]['score'] <=> $candidates[$left]['score'];
		});

		return array_slice(array_keys($candidates), 0, $limit);
	}

	private static function tokenize($text) {
		if (!preg_match_all('/[\p{L}\p{N}]+(?:[-\'][\p{L}\p{N}]+)*/u', (string)$text, $matches)) {
			return array();
		}

		return $matches[0];
	}

	private static function normalizeToken($token) {
		$token = trim((string)$token, "-' \t\n\r\0\x0B");

		if (function_exists('mb_strtolower')) {
			return mb_strtolower($token, 'UTF-8');
		}

		return strtolower($token);
	}

	private static function isUsefulToken($token, $from_title, array $stop_words) {
		if ($token === '' || isset($stop_words[$token])) {
			return false;
		}

		$length = function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token);

		if ($length < ($from_title ? 2 : 3)) {
			return false;
		}

		if (!$from_title && preg_match('/^\p{N}+$/u', $token)) {
			return false;
		}

		return true;
	}
}
