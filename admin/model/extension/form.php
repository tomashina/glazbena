<?php
class ModelExtensionForm extends Model {
	
	private function tablesname()
	{
		$tablesname=array();
		$tablesname[]="tmdform_customergroup";
		$tablesname[]="tmdform";
		$tablesname[]="tmdform_information";
		$tablesname[]="tmdform_success";
		$tablesname[]="tmdform_store";
		$tablesname[]="tmdform_manufacturer";
		$tablesname[]="tmdform_category";
		$tablesname[]="tmdform_product";
		$tablesname[]="tmdform_notification";
		$tablesname[]="tmdform_field_option";
		$tablesname[]="tmdform_field_option_base";
		$tablesname[]="tmdform_field_description";
		$tablesname[]="tmdform_field";
		$tablesname[]="tmdform_description";
		return $tablesname;
	}
	
 	public function addForm($data) {
		
		$sql="INSERT INTO " . DB_PREFIX . "tmdform set headerlink = '" . (isset($data['headerlink']) ? (int)$data['headerlink'] : 0) . "',footerlink = '" . (isset($data['footerlink']) ? (int)$data['footerlink'] : 0) . "', productwidth='".$this->db->escape($data['productwidth'])."', productheight='".$this->db->escape($data['productheight'])."', custome_style='".$this->db->escape($data['custome_style'])."', captcha='".(int)$data['captcha']."', resetbutton='".(int)$data['resetbutton']."', display_type='".$data['display_type']."',assign_product='".$data['assign_product']."',status='".(int)$data['status']."',		customer_notification='".(int)$data['customer_notification']."', admin_notification='".(int)$data['admin_notification']."', date_added=now()";
		$this->db->query($sql);
		$form_id = $this->db->getLastId();
		
		if (isset($data['form_description'])) {
			foreach ($data['form_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_description SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',title='".$this->db->escape($value['title'])."', button_name='".$this->db->escape($value['button_name'])."', resetbuttonname='".$this->db->escape($value['resetbuttonname'])."', popuplinkname='".$this->db->escape($value['popuplinkname'])."',  meta_title='".$this->db->escape($value['meta_title'])."', meta_description='".$this->db->escape($value['meta_description'])."',meta_keyword='".$this->db->escape($value['meta_keyword'])."',top_description='".$this->db->escape($value['top_description'])."',bottom_description='".$this->db->escape($value['bottom_description'])."'"); 
			}
		}
		
		if (!empty($data['form_store'])) {
			foreach ($data['form_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_store SET form_id = '" . (int)$form_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		if (!empty($data['form_customer'])) {
			foreach ($data['form_customer'] as $customer_group_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_customergroup SET form_id = '" . (int)$form_id . "', customer_group_id = '" . (int)$customer_group_id . "'");
			}
		}
		
		if(!empty($data['succes_form'])) {
			foreach ($data['succes_form'] as $language_id => $success) {
				$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_success SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."', success_title='".$this->db->escape($success['success_title'])."', success_meta_title='".$this->db->escape($success['success_meta_title'])."', success_description='".$this->db->escape($success['success_description'])."'"); 
			}
		}
		
		if(!empty($data['form_notification'])) {
			foreach ($data['form_notification'] as $language_id => $notification) {
				$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_notification SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',customer_subject='".$this->db->escape($notification['customer_subject'])."',customer_message='".$this->db->escape($notification['customer_message'])."', admin_subject='".$this->db->escape($notification['admin_subject'])."', admin_message='".$this->db->escape($notification['admin_message'])."'"); 
			}
		}
		
		if (!empty($data['product'])){
		   foreach ($data['product'] as $product_id){
			 	$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_product SET  form_id = '" . (int) $form_id . "',product_id = '" . $this->db->escape($product_id). "'");
		   }
		}
		
		if (!empty($data['category'])){
		   foreach ($data['category'] as $category_id){
			 	$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_category SET  form_id = '" . (int) $form_id . "', category_id = '" . $this->db->escape($category_id). "'");
		   }
		}
		
		if (!empty($data['manufacturer'])){
		   foreach ($data['manufacturer'] as $manufacturer_id){
			 	$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_manufacturer SET  form_id = '" . (int) $form_id . "', manufacturer_id = '" . $this->db->escape($manufacturer_id). "'");
		   }
		}

		//09 03 2019 //
		if (!empty($data['information'])){

		   foreach ($data['information'] as $information_id){

			 	$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_information SET  form_id = '" . (int) $form_id . "', information_id = '" . $this->db->escape($information_id). "'");

		   }

		}		
//09 03 2019 //
		
		if (isset($data['option_fields'])){
		   foreach ($data['option_fields'] as $fields){
			   if(!empty($fields['type'])) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_field SET  
					form_id='".(int)$form_id."',form_status = '" .$fields['form_status']. "', required = '".$fields['required']. "', sort_order='".(int)$fields['sort_order']."', type='".$fields['type']."'");

					$field_id = $this->db->getLastId();

				   if(isset($fields['form_fields'])) {
						foreach ($fields['form_fields'] as $language_id => $form) {
							$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_field_description SET field_id ='" . (int)$field_id . "',form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',field_name='".$this->db->escape($form['field_name'])."',help_text='".$this->db->escape($form['help_text'])."',placeholder='".$this->db->escape($form['placeholder'])."',error_message='".$this->db->escape($form['error_message'])."'"); 
						}
					}
					if(isset($fields['option_type'])) {
					foreach ($fields['option_type'] as $option_description) {
						
						$baseoption=$this->db->query("INSERT INTO  " .DB_PREFIX . "tmdform_field_option_base set field_id ='" . (int)$field_id . "' , form_id ='" . (int)$form_id . "'");
						$optiobaseid=$this->db->getLastId();
						
						if(isset($option_description['option_value_description'])) {
							foreach ($option_description['option_value_description'] as $language_id => $option_value_description) {
								
								$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_field_option SET field_id ='" . (int)$field_id . "',form_id ='" . (int)$form_id . "',optiobaseid='".$optiobaseid."',language_id = '" . (int)$language_id ."',
								name='".$option_value_description['name']."',sort_order='".$option_description['sort_order']."',image='".$option_description['image']."'"); 
							}
						}
					}
				}
			}	   
					
			  
		   	}
		}
		
		// SEO URL
		if (!empty($data['form_seo_url'])) {
			foreach ($data['form_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'form_id=" . (int)$form_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}
		
				
		return $form_id;
	}   
 	public function editForm($form_id,$data) {
		
		$sql="update " . DB_PREFIX . "tmdform set `headerlink` = '" . (isset($data['headerlink']) ? (int)$data['headerlink'] : 0) . "', `footerlink` = '" . (isset($data['footerlink']) ? (int)$data['footerlink'] : 0) . "', productwidth='".$this->db->escape($data['productwidth'])."', productheight='".$this->db->escape($data['productheight'])."', custome_style='".$this->db->escape($data['custome_style'])."', captcha='".(int)$data['captcha']."', resetbutton='".(int)$data['resetbutton']."', display_type='".$data['display_type']."',
			assign_product='".$data['assign_product']."',
			status='".(int)$data['status']."',
			customer_notification='".(int)$data['customer_notification']."',
			admin_notification='".(int)$data['admin_notification']."',
			date_modified=now()where form_id='".$form_id."'";
	 	$this->db->query($sql);
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_description where  form_id ='" . (int)$form_id."'");
		foreach ($data['form_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_description SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',title='".$this->db->escape($value['title'])."', button_name='".$this->db->escape($value['button_name'])."', resetbuttonname='".$this->db->escape($value['resetbuttonname'])."', popuplinkname='".$this->db->escape($value['popuplinkname'])."', meta_title='".$this->db->escape($value['meta_title'])."',meta_description='".$this->db->escape($value['meta_description'])."',meta_keyword='".$this->db->escape($value['meta_keyword'])."',top_description='".$this->db->escape($value['top_description'])."',bottom_description='".$this->db->escape($value['bottom_description'])."'"); 
		}
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_store where  form_id ='" . (int)$form_id."'");
		if (!empty($data['form_store'])) {
			foreach ($data['form_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_store SET form_id = '" . (int)$form_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_customergroup where  form_id ='" . (int)$form_id."'");
		if (!empty($data['form_customer'])) {
			foreach ($data['form_customer'] as $customer_group_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_customergroup SET form_id = '" . (int)$form_id . "', customer_group_id = '" . (int)$customer_group_id . "'");
			}
		}
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_success where  form_id ='" . (int)$form_id."'");
		foreach ($data['succes_form'] as $language_id => $success) {
			$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_success SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',success_title='".$this->db->escape($success['success_title'])."',success_meta_title='".$this->db->escape($success['success_meta_title'])."',success_description='".$this->db->escape($success['success_description'])."'"); 
		}
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_notification where  form_id ='" . (int)$form_id."'");
		foreach ($data['form_notification'] as $language_id => $notification) {
			$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_notification SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',customer_subject='".$this->db->escape($notification['customer_subject'])."',customer_message='".$this->db->escape($notification['customer_message'])."',admin_subject='".$this->db->escape($notification['admin_subject'])."',admin_message='".$this->db->escape($notification['admin_message'])."'"); 
		}
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_product where  form_id ='" . (int)$form_id."'");
		if (!empty($data['product'])){
		   foreach ($data['product'] as $product_id){
			 $sql = $this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_product SET  form_id = '" . (int) $form_id . "',product_id = '" . $this->db->escape($product_id). "'");
		   }
		}
		
		$this->db->query("DELETE from " . DB_PREFIX . "tmdform_category where  form_id ='" . (int)$form_id."'");
		
		if (!empty($data['category'])){
		   foreach ($data['category'] as $category_id){
			 $sql = $this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_category SET  form_id = '" . (int) $form_id . "', category_id = '" . $this->db->escape($category_id). "'");
		   }
		}
		$this->db->query("DELETE from " . DB_PREFIX . "tmdform_manufacturer where  form_id ='" . (int)$form_id."'");
		
		if (!empty($data['manufacturer'])){
		   foreach ($data['manufacturer'] as $manufacturer_id){
			 $sql = $this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_manufacturer SET  form_id = '" . (int) $form_id . "', manufacturer_id = '" . $this->db->escape($manufacturer_id). "'");
		   }
		}

		//09 03 2019 //
		$this->db->query("DELETE from " . DB_PREFIX . "tmdform_information where  form_id ='" . (int)$form_id."'");
		if (!empty($data['information'])){

		   foreach ($data['information'] as $information_id){

			 	$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_information SET  form_id = '" . (int) $form_id . "', information_id = '" . $this->db->escape($information_id). "'");

		   }

		}		
//09 03 2019 //
		
		if (isset($data['option_fields'])){
		   foreach ($data['option_fields'] as $fields){
			   if(!empty($fields['type'])) {
				    if(empty($fields['field_id'])) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_field SET  
					form_id='".(int)$form_id."',
					form_status = '" .$fields['form_status']. "',
					required = '".$fields['required']. "',
					sort_order = '".$fields['sort_order']. "',
					type='".$fields['type']."'");
					$field_id = $this->db->getLastId();
					}
					else
					{
					$this->db->query("update  " . DB_PREFIX . "tmdform_field SET  
					form_id='".(int)$form_id."',
					form_status = '" .$fields['form_status']. "',
					required = '".$fields['required']. "',
					sort_order = '".$fields['sort_order']. "',
					type='".$fields['type']."' where field_id='".$fields['field_id']."'");
					$field_id = $fields['field_id'];		
					}
					
				   	$this->db->query("delete from " . DB_PREFIX . "tmdform_field_description where  field_id ='" . (int)$field_id."'");
					
				   	if(isset($fields['form_fields'])) {
						foreach ($fields['form_fields'] as $language_id => $form) {
							$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_field_description SET field_id ='" . (int)$field_id . "',form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',field_name='".$this->db->escape($form['field_name'])."',help_text='".$this->db->escape($form['help_text'])."',placeholder='".$this->db->escape($form['placeholder'])."',error_message='".$this->db->escape($form['error_message'])."'"); 
						}
					}
					
				$this->db->query("delete from " . DB_PREFIX . "tmdform_field_option where  field_id ='" . (int)$field_id."'");	
				$this->db->query("delete from " . DB_PREFIX . "tmdform_field_option_base where  field_id ='" . (int)$field_id."'");	
			   	if(isset($fields['option_type'])) {
					foreach ($fields['option_type'] as $option_description) {
						
						$baseoption=$this->db->query("INSERT INTO  " .DB_PREFIX . "tmdform_field_option_base set field_id ='" . (int)$field_id . "' , form_id ='" . (int)$form_id . "'");
						$optiobaseid=$this->db->getLastId();
						
						if(isset($option_description['option_value_description'])) {
							foreach ($option_description['option_value_description'] as $language_id => $option_value_description) {
								
								$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_field_option SET field_id ='" . (int)$field_id . "',form_id ='" . (int)$form_id . "',optiobaseid='".$optiobaseid."',language_id = '" . (int)$language_id ."',
								name='".$option_value_description['name']."',sort_order='".$option_description['sort_order']."',image='".$option_description['image']."'"); 
							}
						}
					}
				}
			}	   
				
		   	}
		}
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'form_id=" . (int)$form_id . "'");

		if (!empty($data['form_seo_url'])) {
			foreach ($data['form_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (trim($keyword)) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'form_id=" . (int)$form_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		
		
		
 	}
	
 	public function deleteForm($form_id){	
	
		$tables=$this->tablesname();
		foreach($tables as $table)
		{
		$sql=$this->db->query("DELETE  FROM `" . DB_PREFIX . $table."` where form_id='".$form_id."'");
		}
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'form_id=" . (int)$form_id . "'");
		
	} 
	
	public function getForm($form_id){
		
		$sql="SELECT DISTINCT * FROM " . DB_PREFIX . "tmdform WHERE form_id='".$form_id."'";
		
		$query=$this->db->query($sql);
		return $query->row;
	}
	
 	public function getForms($data){
		$sql = "SELECT * FROM `" . DB_PREFIX . "tmdform` f LEFT JOIN " . DB_PREFIX . "tmdform_description fd ON (f.form_id = fd.form_id) WHERE fd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		
		if (!empty($data['filter_title'])){
			$sql .=" and fd.title like '".$this->db->escape($data['filter_title'])."%'";
		}
		
		$sort_data = array(
			'fd.title',
			'f.status'
		);
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
		 	$sql .= " ORDER BY " . $data['sort'];
		} else {
		 	$sql .= " ORDER BY fd.title";
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
	
	public function getFormDescriptions($form_id) {
		$form_descriptio_data = array();
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX ."tmdform_description WHERE form_id = '" . (int)$form_id . "'");
		foreach ($query->rows as $result) {
			
			$form_descriptio_data[$result['language_id']] =$result;
			
	 	}
		return $form_descriptio_data;
	}
	
	public function getFormSuccess($form_id) {
		$form_success_data = array();
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX ."tmdform_success WHERE form_id = '" . (int)$form_id . "'");
		foreach ($query->rows as $result) {
			$form_success_data[$result['language_id']] = array(
				'success_title'=> $result['success_title'],
				'success_meta_title'=> $result['success_meta_title'],
				'success_description'=> $result['success_description'],
			);
	 	}
		return $form_success_data;
	}
	
	public function getFormNotification($form_id) {
		$form_notification_data = array();
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX ."tmdform_notification WHERE form_id = '" . (int)$form_id . "'");
		foreach ($query->rows as $result) {
			$form_notification_data[$result['language_id']] = array(
				'customer_subject'=> $result['customer_subject'],
				'customer_message'=> $result['customer_message'],
				'admin_subject'=> $result['admin_subject'],
				'admin_message'=> $result['admin_message'],
			);
	 	}
		return $form_notification_data;
	}
			
 	public function getTotalForm($data) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tmdform where form_id<>0 ";
		
		$query = $this->db->query($sql);
		return $query->row['total'];
	}
	
	 public function getCustomerCheckbox($form_id){
		$form_to_customer=array();
		$sql="select  *  from " . DB_PREFIX . "tmdform_customergroup where form_id='".$form_id."'";
		$query=$this->db->query($sql);	
		foreach ($query->rows as $result) {
			$form_to_customer[] = $result['customer_group_id'];
		}
		return $form_to_customer;
	}
	
	public function getFormByStoreId($form_id) {
		$form_store_data = array();
		$sql="select  *  from " . DB_PREFIX . "tmdform_store where form_id='".$form_id."'";
		$query=$this->db->query($sql);	
		foreach ($query->rows as $result) {
			$form_store_data[] = $result['store_id'];
		}
		
		return $form_store_data;
	}
	
	public function getFormProductbyid($form_id){
		$product_ids=array();
		$sql="select  *  from " . DB_PREFIX . "tmdform_product where form_id='".$form_id."'";
		$query=$this->db->query($sql);	
		foreach ($query->rows as $result) {
			$product_ids[] = $result['product_id'];
		}
		return $product_ids;
 	}
	
	public function getFormCategorybyid($form_id){
		$category_ids=array();
		$sql="SELECT  *  FROM " . DB_PREFIX . "tmdform_category where form_id='".$form_id."'";
		$query=$this->db->query($sql);	
		foreach ($query->rows as $result) {
			$category_ids[] = $result['category_id'];
		}
		return $category_ids;
 	}
	
	public function getFormManufacturerbyid($form_id){
		$manufacturer_ids=array();
		$sql="SELECT  *  FROM " . DB_PREFIX . "tmdform_manufacturer where form_id='".$form_id."'";
		$query=$this->db->query($sql);	
		foreach ($query->rows as $result) {
			$manufacturer_ids[] = $result['manufacturer_id'];
		}
		return $manufacturer_ids;
 	}
	
	// 09 03 2019 //
 	public function getFormInformation($form_id){

		$information_data=array();

		$sql="SELECT  *  FROM " . DB_PREFIX . "tmdform_information where form_id='".$form_id."'";

		$query=$this->db->query($sql);	

		foreach ($query->rows as $result) {
			$information_data[] = $result['information_id'];
		}

		return $information_data;
 	}
// 09 03 2019 //
 	
	public function getFormFieldById($form_id) {
		$form_field_data = array();

		$form_field_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field WHERE form_id = '" . (int)$form_id . "'");

		foreach ($form_field_query->rows as $form_field) {
			$form_field_description_data = array();

			$form_field_description_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field_description WHERE form_id = '" . (int)$form_id . "' and field_id='".$form_field['field_id']."'");
			foreach ($form_field_description_query->rows as $form_field_description) {
				
				$form_field_description_data[$form_field_description['language_id']] =$form_field_description;
				
			}
			$form_field_options=array();
			
			$form_field_options_query= $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field_option WHERE form_id = '" . (int)$form_id . "' and field_id='".$form_field['field_id']."' and language_id='".$this->config->get('config_language_id')."' order by sort_order asc");
			
			if(isset($form_field_options_query->row))
			{
				foreach ($form_field_options_query->rows as $form_field_option) {
				
				$form_field_options_query1= $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field_option WHERE form_id = '" . (int)$form_id . "' and field_id='".$form_field['field_id']."' and optiobaseid='".$form_field_option['optiobaseid']."'");
				foreach ($form_field_options_query1->rows as $form_field_option1) {
				$option_name[$form_field_option1['language_id']]=$form_field_option1['name'];
				
				}
				$this->load->model('tool/image');
				if (is_file(DIR_IMAGE . $form_field_option['image'])) {
				$thumb = $this->model_tool_image->resize($form_field_option['image'], 40, 40);
				} else {
					$thumb = $this->model_tool_image->resize('no_image.png', 40, 40);
				}
				
				$form_field_options[] = array(
					'name' => $option_name,
					'sort_order' => $form_field_option['sort_order'],
					'image' => $form_field_option['image'],
					'field_id' => $form_field_option['field_id'],
					'thumb' => $thumb,
				);
			}
			}
			
			$form_field_data[] = array(
				'field_id' => $form_field['field_id'],
				'type' => $form_field['type'],
				'form_status' => $form_field['form_status'],
				'required' => $form_field['required'],
				'sort_order' => $form_field['sort_order'],
				'form_field_description' => $form_field_description_data,
				'form_field_options' => $form_field_options
			);
		}
		
		
		return $form_field_data;
		
	}
	
	public function getFormfields($form_id) {
		$submit_data = array();
		$submit_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_submit WHERE form_id = '" . (int)$form_id . "'");
		foreach($submit_query->rows as $submit) {
			$field_data=array();
			$data_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdfield_submit WHERE fs_id = '" . (int)$submit['fs_id'] . "'");
			foreach($data_query->rows as $fieldsubmit) {
				$field_data=array(
					'field_id' => $fieldsubmit['field_id'],
					'value' => $fieldsubmit['value']
				);
			}
			
			$submit_data = array(
				'form_id' => $submit['form_id'],
				'fieldsubmit' => $field_data
			);
			
		}
		
		return $submit_data;
	}
	
	public function getTotalFormfield($data) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tmdform_field where form_id<>0 ";
		
		$query = $this->db->query($sql);
		return $query->row['total'];
	}
	
	public function getexportheading($form_id){
		$sql="select * from " . DB_PREFIX . "tmdfield_submit where form_id='".$form_id."'  group by field_id ORDER by sort_order asc ";
		$query = $this->db->query($sql);
		return $query->rows;
	}
	public function getexportsubmit($form_id){
		$sql="select * from " . DB_PREFIX . "tmdform_submit where form_id='".$form_id."' ORDER by date_added desc ";
		$query = $this->db->query($sql);
		return $query->rows;
	}
	
	public function getFieldType($field_id){
		$sql="select type from " . DB_PREFIX . "tmdform_field where field_id='".$field_id."'";
		$query = $this->db->query($sql);
		if(isset($query->row['type'])){
		return $query->row['type'];
		}

	}
	
	public function getFieldExport($fs_id,$form_id, $field_id){
		$sql="select * from " . DB_PREFIX . "tmdfield_submit where fs_id='".$fs_id."'  and form_id='".$form_id."' and field_id='".$field_id."'";		
		$query = $this->db->query($sql);
		
		if(isset($query->row['value'])) {
				if($query->row['serialize'])
				{
					$data='';
					$values=unserialize($query->row['value']);
                    if(!empty($values))
                    {
					foreach($values as $value)
					{
						$data .=$value.',';
					}
					}
					return $data;
				}
				else
				{
					return $query->row['value'];	
				}
		}
		
	}
	
	public function deleteAllFieldById($field_id){	
		$sql="delete  from " . DB_PREFIX . "tmdform_field where field_id='".$field_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_field_description where field_id='".$field_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_field_option where field_id='".$field_id."'";
		$sql="delete  from " . DB_PREFIX . "tmdform_field_option_base where field_id='".$field_id."'";
		$query=$this->db->query($sql);
		
 	} 
	public function getHeaderlinks(){
		$sql = "show table status where name='".DB_PREFIX . "tmdform'";
$query = $this->db->query($sql);
if(empty($query->row))
{
return array();
}
		
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform t LEFT JOIN " . DB_PREFIX . "tmdform_description td ON (t.form_id = td.form_id) WHERE td.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		$query=$this->db->query($sql);
		
		return $query->rows;
	}
	
	public function getFormStores($form_id) {
		$store_data = array();
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_store WHERE form_id = '" . (int)$form_id . "'");
		foreach ($query->rows as $result) {
			$store_data[] = $result['store_id'];
		}
		return $store_data;
	}
	
	public function getFormSeoUrls($form_id) {
		$seourldata = array();
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'form_id=" . (int)$form_id . "'");
		foreach ($query->rows as $result) {
			$seourldata[$result['store_id']][$result['language_id']] = $result['keyword'];
		}
		return $seourldata;
	}
		
}
?>
