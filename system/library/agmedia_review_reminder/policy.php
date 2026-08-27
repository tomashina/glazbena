<?php

final class AgmediaReviewReminderPolicy {
	const MIN_ORDER_DATE = '2026-08-27';

	public static function isValidOrderDate($value) {
		$value = trim((string)$value);

		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $matches)) {
			return false;
		}

		return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
	}

	public static function normaliseMinOrderDate($value) {
		$value = trim((string)$value);

		if (!self::isValidOrderDate($value) || $value < self::MIN_ORDER_DATE) {
			return self::MIN_ORDER_DATE;
		}

		return $value;
	}

	public static function minOrderDateTime() {
		return self::MIN_ORDER_DATE . ' 00:00:00';
	}

	public static function normaliseBodyHtml($value) {
		$value = (string)$value;

		// Repair content saved by the first admin template revision, which
		// encoded the complete HTML document before the WYSIWYG editor loaded it.
		if (strpos($value, '<') === false
			&& preg_match('/&lt;\/?(?:p|div|table|tbody|tr|td|a|strong|em|br|ul|ol|li|h[1-6])(?:\s|&gt;)/i', $value)) {
			$decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

			if (strpos($decoded, '<') !== false) {
				$value = $decoded;
			}
		}

		return $value;
	}

	public static function prepareBodyForTextarea($value) {
		$value = self::normaliseBodyHtml($value);

		// Keep raw email HTML compatible with OpenCart's editor without allowing
		// stored content to close the surrounding admin textarea element.
		return preg_replace('/<\/textarea/i', '&lt;/textarea', $value);
	}
}
