<?php
class ControllerCronCurrencyUpdate extends Controller {
	public function index() {
	
		// Run currency update
		if ($this->config->get('config_currency_auto')) {
			if ($this->refresh()) echo "Updated"; else echo "Not updated";
		}

	}

	private function refresh($force = false) {
		if (extension_loaded('curl')) {
			$data = array();

			if ($force) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "currency WHERE code != '" . $this->db->escape($this->config->get('config_currency')) . "'");
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "currency WHERE code != '" . $this->db->escape($this->config->get('config_currency')) . "' AND date_modified < '" .  $this->db->escape(date('Y-m-d H:i:s', strtotime('-1 day'))) . "'");
			}

			if ($query->num_rows) {
			
				foreach ($query->rows as $result) {
					$data[] = $result['code'];
				}

				$curl = curl_init();

				curl_setopt($curl, CURLOPT_URL, 'https://api.exchangeratesapi.io/latest?base='.$this->config->get('config_currency').'&symbols='.implode(',', $data));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curl, CURLOPT_HEADER, false);
				curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
				curl_setopt($curl, CURLOPT_TIMEOUT, 30);

				$content = curl_exec($curl);

				curl_close($curl);

				if (!$content) $content = @file_get_contents('https://api.exchangeratesapi.io/latest?base='.$this->config->get('config_currency').'&symbols='.implode(',', $data)); 

				if ($content) {

					$currencies = json_decode($content, true);

					foreach ($currencies['rates'] as $currency => $value) {
						if ((float)$value) {
							$this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '" . (float)$value . "', date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE code = '" . $this->db->escape($currency) . "'");
						}
					}

					$this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '1.00000', date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE code = '" . $this->db->escape($this->config->get('config_currency')) . "'");

					$this->cache->delete('currency');

				}

			}

			return $data;

		}
	}
	
}