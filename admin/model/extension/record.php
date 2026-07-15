<?php
class ModelExtensionRecord extends Model {
 	
	public function getTmdRecord($fs_id){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_submit ts LEFT JOIN " . DB_PREFIX . "tmdform_description td ON (ts.form_id = td.form_id) where ts.fs_id='".$fs_id."'";
		$query = $this->db->query($sql);
		return $query->row;
	}
		
	public function getTmdFieldRecord($data){
	
		$sql="select * from " . DB_PREFIX . "tmdfield_submit where fs_id='".$data['fs_id']."'";	
		$sort_data = array(
			'label'
		);
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
		 	$sql .= " ORDER BY " . $data['sort'];
		} else {
		 	$sql .= " ORDER BY label";
		}
		if (isset($data['order']) && ($data['order'] == 'DESC')) {
		 	$sql .= " DESC";
		} else {
		 	$sql .= " ASC";
		}
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}
			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}
		}
		$query = $this->db->query($sql);
		return $query->rows;
	}
	
	public function getFormRecords($data = array()){
		
		$sql = "SELECT *, p.name  FROM " . DB_PREFIX . "tmdform_submit ts LEFT JOIN " . DB_PREFIX . "tmdform_description td ON (ts.form_id = td.form_id) LEFT JOIN ".DB_PREFIX."product_description p ON(ts.product_id=p.product_id) where td.language_id = '" . (int)$this->config->get('config_language_id') . "' and ts.fs_id<>0 ";
			
		if (!empty($data['filter_title'])){
			$sql .=" and td.form_id like '".$this->db->escape($data['filter_title'])."%'";
		}
		
		if (!empty($data['filter_name'])){
			$sql .=" and ts.customer_name like '".$this->db->escape($data['filter_name'])."%'";
		}
		
		if (!empty($data['filter_ip'])){
			$sql .=" and ts.ip like '".$this->db->escape($data['filter_ip'])."%'";
		}
		
		if (!empty($data['filter_date'])){
			$sql .=" and ts.date_added like '".$this->db->escape($data['filter_date'])."%'";
		}
			
		$sort_data = array(
			'td.title',
			'ts.customer_name',
			'ts.ip',
			'ts.date_added',
		);
		
		$sql .="GROUP BY ts.fs_id";
		
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
		 	$sql .= " ORDER BY " . $data['sort'];
		} else {
		 	$sql .= " ORDER BY ts.date_added";
		}
		if (isset($data['order']) && ($data['order'] == 'DESC')) {
		 	$sql .= " ASC";
		} else {
		 	$sql .= " DESC";
		}
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}
			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}
		}
		
		$query = $this->db->query($sql);
		return $query->rows;
 	}
		
	public function getTotalRecord($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tmdform_submit where fs_id<>0 ";
		
		if (isset($data['filter_title'])){
			$sql .=" and form_id like '".$this->db->escape($data['filter_title'])."%'";
		}
		
		if (!empty($data['filter_name'])){
			$sql .=" and customer_name like '".$this->db->escape($data['filter_name'])."%'";
		}
		
		if (!empty($data['filter_ip'])){
			$sql .=" and ip like '".$this->db->escape($data['filter_ip'])."%'";
		}
		
		if (!empty($data['filter_date'])){
			$sql .=" and date_added like '".$this->db->escape($data['filter_date'])."%'";
		}
		
		$query = $this->db->query($sql);
		return $query->row['total'];
	}
	
	public function deleteRecord($fs_id) {
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "tmdfield_submit WHERE fs_id = '" . (int)$fs_id . "'");
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "tmdform_submit WHERE fs_id = '" . (int)$fs_id . "'");
		
	}
	
 	
}
?>
