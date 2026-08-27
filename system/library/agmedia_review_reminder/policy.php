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
}
