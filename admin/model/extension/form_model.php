<?php
class ModelExtensionForm extends Model {
 	public function addForm($data) {
		$sql="INSERT INTO " . DB_PREFIX . "tmdform set
			header='".(int)$data['header']."',
			footer='".(int)$data['footer']."',
			button_name='".$this->db->escape($data['button_name'])."',
			custome_style='".$this->db->escape($data['custome_style'])."',
			captcha='".(int)$data['captcha']."',
			display_type='".$data['display_type']."',
			assign_product='".$data['assign_product']."',
			status='".(int)$data['status']."',
			customer_notification='".(int)$data['customer_notification']."',
			admin_notification='".(int)$data['admin_notification']."',
			date_added=now()";
		$this->db->query($sql);
		$form_id = $this->db->getLastId();
		
		if (isset($data['form_description'])) {
			foreach ($data['form_description'] as $language_id => $value) {
				$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_description SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',title='".$this->db->escape($value['title'])."',meta_title='".$this->db->escape($value['meta_title'])."',meta_description='".$this->db->escape($value['meta_description'])."',meta_keyword='".$this->db->escape($value['meta_keyword'])."',top_description='".$this->db->escape($value['top_description'])."',bottom_description='".$this->db->escape($value['bottom_description'])."'"); 
			}
		}
		
		if (isset($data['form_store'])) {
			foreach ($data['form_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_store SET form_id = '" . (int)$form_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		if (isset($data['form_customer'])) {
			foreach ($data['form_customer'] as $customer_group_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_customergroup SET form_id = '" . (int)$form_id . "', customer_group_id = '" . (int)$customer_group_id . "'");
			}
		}
		
		if(isset($data['succes_form'])) {
			foreach ($data['succes_form'] as $language_id => $success) {
				$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_success SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',success_title='".$this->db->escape($success['success_title'])."',success_meta_title='".$this->db->escape($success['success_meta_title'])."',success_description='".$this->db->escape($success['success_description'])."'"); 
			}
		}
		
		if(isset($data['form_notification'])) {
			foreach ($data['form_notification'] as $language_id => $notification) {
				$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_notification SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',customer_subject='".$this->db->escape($notification['customer_subject'])."',customer_message='".$this->db->escape($notification['customer_message'])."',admin_subject='".$this->db->escape($notification['admin_subject'])."',admin_message='".$this->db->escape($notification['admin_message'])."'"); 
			}
		}
		
		if (isset($data['product'])){
		   foreach ($data['product'] as $product_id){
			 	$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_product SET  form_id = '" . (int) $form_id . "',product_id = '" . $this->db->escape($product_id). "'");
		   }
		}
		
		if (isset($data['option_fields'])){
		   foreach ($data['option_fields'] as $fields){
			   if(!empty($fields['type'])) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_field SET  
					form_id='".(int)$form_id."',
					form_status = '" .$fields['form_status']. "',
					required = '".$fields['required']. "',
					type='".$fields['type']."'");

					$field_id = $this->db->getLastId();

				   if(isset($fields['form_fields'])) {
						foreach ($fields['form_fields'] as $language_id => $form) {
							$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_field_description SET field_id ='" . (int)$field_id . "',form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',field_name='".$this->db->escape($form['field_name'])."',help_text='".$this->db->escape($form['help_text'])."',placeholder='".$this->db->escape($form['placeholder'])."',error_message='".$this->db->escape($form['error_message'])."'"); 
						}
					}
					if(isset($fields['option_type'])) {
					foreach ($fields['option_type'] as $option_description) {
					
						if(isset($option_description['option_value_description'])) {
							foreach ($option_description['option_value_description'] as $language_id => $option_value_description) {

								$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_field_option SET field_id ='" . (int)$field_id . "',form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',
								name='".$option_value_description['name']."',sort_order='".$option_description['sort_order']."',image='".$option_description['images']."'"); 
							}
						}
					}
				}
			}	   
					
			  
		   	}
		}
				
		return $form_id;
	}   
 	public function editForm($form_id,$data) {
		$sql="update " . DB_PREFIX . "tmdform set
			header='".(int)$data['header']."',
			footer='".(int)$data['footer']."',
			button_name='".$this->db->escape($data['button_name'])."',
			custome_style='".$this->db->escape($data['custome_style'])."',
			captcha='".(int)$data['captcha']."',
			display_type='".$data['display_type']."',
			assign_product='".$data['assign_product']."',
			status='".(int)$data['status']."',
			customer_notification='".(int)$data['customer_notification']."',
			admin_notification='".(int)$data['admin_notification']."',
			date_modified=now()where form_id='".$form_id."'";
	 	$this->db->query($sql);
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_description where  form_id ='" . (int)$form_id."'");
		foreach ($data['form_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_description SET form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',title='".$this->db->escape($value['title'])."',meta_title='".$this->db->escape($value['meta_title'])."',meta_description='".$this->db->escape($value['meta_description'])."',meta_keyword='".$this->db->escape($value['meta_keyword'])."',top_description='".$this->db->escape($value['top_description'])."',bottom_description='".$this->db->escape($value['bottom_description'])."'"); 
		}
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_store where  form_id ='" . (int)$form_id."'");
		if (isset($data['form_store'])) {
			foreach ($data['form_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_store SET form_id = '" . (int)$form_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		$this->db->query("delete from " . DB_PREFIX . "tmdform_customergroup where  form_id ='" . (int)$form_id."'");
		if (isset($data['form_customer'])) {
			foreach ($data['form_customer'] as $store_id) {
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
		if (isset($data['product'])){
		   foreach ($data['product'] as $product_id){
			 $sql = $this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_product SET  form_id = '" . (int) $form_id . "',product_id = '" . $this->db->escape($product_id). "'");
		   }
		}
		
		if (isset($data['option_fields'])){
		   foreach ($data['option_fields'] as $fields){
			   if(!empty($fields['type'])) {
				    if(empty($fields['field_id'])) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "tmdform_field SET  
					form_id='".(int)$form_id."',
					form_status = '" .$fields['form_status']. "',
					required = '".$fields['required']. "',
					type='".$fields['type']."'");
					$field_id = $this->db->getLastId();
					}
					else
					{
					$this->db->query("update  " . DB_PREFIX . "tmdform_field SET  
					form_id='".(int)$form_id."',
					form_status = '" .$fields['form_status']. "',
					required = '".$fields['required']. "',
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
			   	if(isset($fields['option_type'])) {
					foreach ($fields['option_type'] as $option_description) {
					
						if(isset($option_description['option_value_description'])) {
							foreach ($option_description['option_value_description'] as $language_id => $option_value_description) {

								$this->db->query("INSERT INTO " .DB_PREFIX . "tmdform_field_option SET field_id ='" . (int)$field_id . "',form_id ='" . (int)$form_id . "',language_id = '" . (int)$language_id ."',
								name='".$option_value_description['name']."',sort_order='".$option_description['sort_order']."',image='".$option_description['images']."'"); 
							}
						}
					}
				}
			}	   
				
		   	}
		}
		
 	}
	
 	public function deleteForm($form_id){		
		$sql="delete  from " . DB_PREFIX . "tmdform where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_customergroup where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_description where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_field where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_field_description where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_field_option where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_notification where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_product where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_store where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		$sql="delete  from " . DB_PREFIX . "tmdform_success where form_id='".$form_id."'";
		$query=$this->db->query($sql);
 	} 
	
	public function getForm($form_id){
		$sql="select * from " . DB_PREFIX . "tmdform where form_id='".$form_id."'";
		$query=$this->db->query($sql);
		return $query->row;
	}
	
 	public function getForms($data){
		$sql = "SELECT * FROM `" . DB_PREFIX . "tmdform` f LEFT JOIN " . DB_PREFIX . "tmdform_description fd ON (f.form_id = fd.form_id) WHERE fd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
			
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
			$form_descriptio_data[$result['language_id']] = array(
				'title'=> $result['title'],
				'meta_title'=> $result['meta_title'],
				'meta_keyword'=> $result['meta_keyword'],
				'meta_description'=> $result['meta_description'],
				'top_description'=> $result['top_description'],
				'bottom_description'=> $result['bottom_description'],
			);
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
	
	public function getFormFieldById($form_id) {
		$form_field_data = array();

		$form_field_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field WHERE form_id = '" . (int)$form_id . "'");

		foreach ($form_field_query->rows as $form_field) {
			$form_field_description_data = array();

			$form_field_description_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field_description WHERE form_id = '" . (int)$form_id . "' and field_id='".$form_field['field_id']."'");

			foreach ($form_field_description_query->rows as $form_field_description) {
				$form_field_description_data[$form_field_description['language_id']] = array(
					'form_id' => $form_field_description['form_id'],
					'field_id' => $form_field_description['field_id'],
					'field_name' => $form_field_description['field_name'],
					'help_text' => $form_field_description['help_text'],
					'placeholder' => $form_field_description['placeholder'],
					'error_message' => $form_field_description['error_message']
				);
				
			}
			$form_field_options=array();
			
			$form_field_options_query= $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field_option WHERE form_id = '" . (int)$form_id . "' and field_id='".$form_field['field_id']."' order by sort_order asc");
			if(isset($form_field_options_query->row))
			{
				foreach ($form_field_options_query->rows as $form_field_option) {
				$option_name[$form_field_option['language_id']]=$form_field_option['name'];
				$thumb='';
				$form_field_options[] = array(
					'name' => $option_name,
					'sort_order' => $form_field_option['sort_order'],
					'image' => $form_field_option['image'],
					'thumb' => $thumb,
				);
			}
			}
			
			$form_field_data[] = array(
				'field_id' => $form_field['field_id'],
				'type' => $form_field['type'],
				'form_status' => $form_field['form_status'],
				'required' => $form_field['required'],
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
		
}
?>
