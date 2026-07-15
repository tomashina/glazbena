<?php
class ModelExtensionStartup extends Model {
	

	public function getCountrycode($country_code) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "country WHERE 	iso_code_2 = '" . $country_code . "'");

		return $query->row;
	}
	public function getcurrencycode($currency_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "currency WHERE 	currency_id = '" . $currency_id . "'");

		return $query->row;
	}
	public function getlanguagecode($language_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "language WHERE 	language_id = '" . $language_id . "'");

		return $query->row;
	}
	
}