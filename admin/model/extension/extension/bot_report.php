<?php

/*******************************************************************************
*                                   Opencart Cache                             *
*                             Copyright : Ovidiu Fechete                       *
*                              email: ovife21@gmail.com                        *
*                Below source-code or any part of the source-code              *
*                          cannot be resold or distributed.                    *
*******************************************************************************/


class ModelExtensionExtensionBotReport extends Model {
	
	public function getTotalPages() {
			
			$sql = "select count(distinct link) as total from " . DB_PREFIX . "bots_report";
					
			$query = $this->db->query($sql);
			
			return $query->row['total'];
		 
	}
	
	public function getPages($data) {
	
			$sql = "select link, count(*) as cnt, max(`date`) as lastdate from " . DB_PREFIX . "bots_report group by link order by cnt desc";
			
			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}				

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}	
			
				$sql .= " limit " . (int)$data['start'] . "," . (int)$data['limit'];
			}	
			
			$query = $this->db->query($sql);						
		
			return $query->rows;
		 
	}
			
}
?>