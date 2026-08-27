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

		// Repair one or more encoding layers saved by the first admin template
		// revisions. Only accept the decoded candidate when it produces a real
		// supported HTML tag, so ordinary text entities remain untouched.
		if (strpos($value, '<') === false) {
			$candidate = $value;

			for ($layer = 0; $layer < 5; $layer++) {
				$decoded = html_entity_decode($candidate, ENT_QUOTES, 'UTF-8');

				if ($decoded === $candidate) {
					break;
				}

				$candidate = $decoded;

				if (preg_match('/<\/?(?:p|div|table|tbody|tr|td|a|strong|em|br|ul|ol|li|h[1-6])(?:\s|>)/i', $candidate)) {
					$value = $candidate;
					break;
				}
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
