<?php
class ModelExtensionForm extends Model {
 	
	public function addForm($data) {
		
		if(isset($data['product_id'])){
			$product_id = (int)$data['product_id'];
		} else {
			$product_id = '';
		}
		
		$customer_id = $this->customer->getId();
		$customer_name = $this->customer->getFirstName().' '.$this->customer->getLastName();
		
		$sql="INSERT INTO " . DB_PREFIX . "tmdform_submit set
			form_id='".(int)$data['form_id']."',
			product_id='".$product_id."',
			customer_id='".$customer_id."',
			customer_name='".$customer_name."',
			ip='".$this->request->server['REMOTE_ADDR']."',
			user_agent='".$this->request->server['HTTP_USER_AGENT']."',
			language_id = '" . (int)$this->config->get('config_language_id') . "',
			store_id = '" . (int)$this->config->get('config_store_id') . "',
			date_added=now()";
		$this->db->query($sql);
		$fs_id = $this->db->getLastId();
		if (isset($data['formfields'])) {
			$email='';
			foreach ($data['formfields'] as $key => $fields) {
				
				$form_field_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field tf LEFT JOIN " . DB_PREFIX . "tmdform_field_description tfd ON (tf.field_id = tfd.field_id) WHERE tf.form_id = '" .(int)$data['form_id']. "' AND tfd.language_id = '" . (int)$this->config->get('config_language_id') . "' and tf.field_id='".$key."' ORDER BY tf.sort_order");
				
				if(isset($form_field_query->row['type']) && $form_field_query->row['type']=='email') {
				$email=$fields;
				}
				
				$serialize=0;
				if (is_array($fields)) {
				$fields=serialize($fields);
				$serialize=1;
				}
				$this->db->query("INSERT INTO " . DB_PREFIX . "tmdfield_submit SET 
				fs_id = '" . (int)$fs_id . "',
				form_id='".(int)$data['form_id']."',
				label='".$form_field_query->row['field_name']."',
				field_id = '" .$key . "',
				sort_order = '" .$form_field_query->row['sort_order'] . "',
				serialize='".$serialize."',
				value='".$this->db->escape($fields)."'");
				
			}
		}
		
		/* Admin Mail */
			$this->language->load('extension/popupform');
			
			$mail_infos = $this->getForm($data['form_id']);
			$mailnotification_adminstatus = $mail_infos['admin_notification'];
			
			if($mailnotification_adminstatus==1){
				
				$mailnotificationget = $this->getMailNotification($data['form_id']);
				$subject = sprintf($this->language->get('text_subject'). ' '. $mailnotificationget['admin_subject']);
					 
				$message =  html_entity_decode($mailnotificationget['admin_message']);
				/* new code */
				$this->session->data['form_id']=$fs_id;
				
				$formdata = $this->load->controller('extension/tmdemailtemplate');
			
				$find = array(
				'{formrecord}'	
			    );
			
				$replace = array(				
					'formrecord' =>$formdata
					);
				
				
				
				$subject = str_replace(array("\r\n", "\r", "\n"), '', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '', trim(str_replace($find, $replace, $subject))));

				$message = 'IP:'.$this->request->server['REMOTE_ADDR'].'<br/>'. str_replace(array("\r\n", "\r", "\n"), '', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '', trim(str_replace($find, $replace, $message))));

			
				/* new code */	
				$mail = new Mail($this->config->get('config_mail_engine'));
				$mail->parameter = $this->config->get('config_mail_parameter');
				$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
				$mail->smtp_username = $this->config->get('config_mail_smtp_username');
				$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
				$mail->smtp_port = $this->config->get('config_mail_smtp_port');
				$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
			
				$mail->setTo($this->config->get('config_email'));	
					
				$mail->setFrom($this->config->get('config_email'));	
					$mail->setReplyTo($email);		
				$mail->setSender($this->config->get('config_name'));
				$mail->setSubject($subject);
				if(!empty($this->session->data['emailfiles']))
				{
					foreach($this->session->data['emailfiles'] as $emailfiles)
					{
						$mail->addAttachment($emailfiles);
						
					}
					
				}
				$mail->setHtml(html_entity_decode($message));
				
				$mail->send();
				
			}
			/* Admin Mail */
			
			/* Customer Mail */
			
			 $mailnotification_customerstatus = $mail_infos['customer_notification'];
			
			 if($mailnotification_customerstatus==1 && !empty($email)){
				
				$mailnotification = $this->getMailNotification($data['form_id']);
				$subject = sprintf($this->language->get('text_subject'). ' '. $mailnotification['customer_subject']);
					 
				$message =  html_entity_decode($mailnotification['customer_message']);
                 /* new code */
				$this->session->data['form_id']=$fs_id;
				
				$formdata = $this->load->controller('extension/tmdemailtemplate');
			
				$find = array(
				'{formrecord}'	
			    );
			
				$replace = array(				
					'formrecord' =>$formdata
					);
				
				
				
				$subject = str_replace(array("\r\n", "\r", "\n"), '', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '', trim(str_replace($find, $replace, $subject))));

				$message = str_replace(array("\r\n", "\r", "\n"), '', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '', trim(str_replace($find, $replace, $message))));

			
				/* new code */	   
				$mail = new Mail($this->config->get('config_mail_engine'));
				$mail->parameter = $this->config->get('config_mail_parameter');
				$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
				$mail->smtp_username = $this->config->get('config_mail_smtp_username');
				$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
				$mail->smtp_port = $this->config->get('config_mail_smtp_port');
				$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');				
				$mail->setTo($email);			
				$mail->setFrom($this->config->get('config_email'));			
				$mail->setSender($this->config->get('config_name'));
				$mail->setSubject($subject);
				if(!empty($this->session->data['emailfiles']))
				{
					foreach($this->session->data['emailfiles'] as $emailfiles)
					{
						$mail->addAttachment($emailfiles);
						
					}
					
				}
				$mail->setHtml(html_entity_decode($message));
				
				$mail->send();
				
				if(!empty($this->session->data['emailfiles']))
				{
						foreach($this->session->data['emailfiles'] as $emailfiles)
					{
					unlink($emailfiles);
					}
					unset($this->session->data['emailfiles']);
				}
				
			} 
			/* Customer Mail */
			
		
		return $fs_id;
	}
	public function getForm($form_id){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform t LEFT JOIN " . DB_PREFIX . "tmdform_description td ON (t.form_id = td.form_id) WHERE  t.form_id='".(int)$form_id."' and td.language_id = '" . (int)$this->config->get('config_language_id') . "' AND t.status='1'";
		$query=$this->db->query($sql);
		return $query->row;
	}
	
	
	public function getFormFieldById($form_id) {
		$form_field_data = array();
		
		$form_field_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field tf LEFT JOIN " . DB_PREFIX . "tmdform_field_description tfd ON (tf.field_id = tfd.field_id) WHERE tf.form_id = '" .(int)$form_id . "' AND tfd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY tf.sort_order");
		
		foreach ($form_field_query->rows as $form_field) {
			$form_field_option_data = array();
			
			$form_field_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tmdform_field_option WHERE field_id = '".(int)$form_field['field_id'] ."' AND language_id = '" . (int)$this->config->get('config_language_id') . "' order by sort_order asc");
			
			foreach ($form_field_option_query->rows as $form_field_option) {
				$form_field_option_data[] = array(
					'field_id' 		=> $form_field_option['field_id'],
					'form_id' 		=> $form_field_option['form_id'],
					'name' 			=> $form_field_option['name'],
					'image' 		=> $form_field_option['image'],
					'sort_order' 	=> $form_field_option['sort_order']
				);
			}

			$form_field_data[] = array(
				'field_id' 		=> $form_field['field_id'],
				'form_id' 		=> $form_field['form_id'],
				'type' 			=> $form_field['type'],
				'form_status' 	=> $form_field['form_status'],
				'required' 		=> $form_field['required'],
				'field_name' 	=> $form_field['field_name'],
				'help_text' 	=> $form_field['help_text'],
				'placeholder' 	=> $form_field['placeholder'],
				'error_message' => $form_field['error_message'],
				'form_field_option' => $form_field_option_data
			);
			
		}
		return $form_field_data;
	}
	
	public function getMailNotification($form_id){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_notification WHERE form_id = '" .(int)$form_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'";
		$query=$this->db->query($sql);
		
		return $query->row;
	}
	
	public function getSuccesstext($form_id){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_success WHERE form_id = '" .(int)$form_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'";
		$query=$this->db->query($sql);
		
		return $query->row;
	}
	
	public function getForms($data){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform t LEFT JOIN " . DB_PREFIX . "tmdform_description td ON (t.form_id = td.form_id) WHERE td.language_id = '" . (int)$this->config->get('config_language_id') . "' AND t.status='1'";
		$query=$this->db->query($sql);
		
		return $query->rows;
	}
	
	public function getEmailExist($value,$field_id) {
		
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tmdfield_submit WHERE LOWER(value) = '" . $this->db->escape(utf8_strtolower($value)) . "' and field_id='".$field_id."'");
			return $query->row['total'];
		
	}
	
	public function getHeaderForms(){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform t LEFT JOIN " . DB_PREFIX . "tmdform_description td ON (t.form_id = td.form_id) WHERE td.language_id = '" . (int)$this->config->get('config_language_id') . "' AND t.status='1'";
		$query=$this->db->query($sql);
		
		return $query->rows;
	}
	
	public function getProductForm($product_id){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform t LEFT JOIN " . DB_PREFIX . "tmdform_description td on(t.form_id= td.form_id) LEFT JOIN " . DB_PREFIX . "tmdform_product tp ON (t.form_id = tp.form_id) WHERE tp.product_id='".(int)$product_id."' and td.language_id = '" . (int)$this->config->get('config_language_id') . "' AND t.status='1'";
		$query=$this->db->query($sql);
		return $query->row;
	}
	
	public function getAssignProduct($product_id,$manufacturer_id){
		$formdata=array();
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_product tp left join " . DB_PREFIX . "tmdform t on tp.form_id=t.form_id left join " . DB_PREFIX . "tmdform_description td on td.form_id=t.form_id   WHERE tp.product_id='".(int)$product_id."' and t.status='1' and td.language_id='".$this->config->get('config_language_id')."' AND t.status='1'";
		
		$query=$this->db->query($sql);
		if(isset($query->rows)){
			foreach($query->rows as $row)
			{
				$formdata[$row['form_id']]=array('form_id'=>$row['form_id'],'display_type'=>$row['display_type'],'popuplinkname'=>$row['popuplinkname'],'formlink' => $this->url->link('extension/popupform/popupformpage', 'form_id=' . (int)$row['form_id'] . '&product_id=' . (int)$product_id, true));
			}
		
		}
			
			$query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id='".(int)$product_id."'");
			if(!empty($query->row['category_id']))
			{
				foreach($query->rows as $row)
				{
			
				$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_category tc left join `" . DB_PREFIX . "tmdform` t on tc.form_id=t.form_id left join " . DB_PREFIX . "tmdform_description td on td.form_id=t.form_id WHERE t.status='1' and tc.category_id='".$row['category_id']."'  and td.language_id='".$this->config->get('config_language_id')."'";
				$query1=$this->db->query($sql);
				if(isset($query1->row['form_id'])){
					foreach($query1->rows as $row)
					{
						
						$formdata[$row['form_id']]=array('form_id'=>$row['form_id'],'display_type'=>$row['display_type'],'popuplinkname'=>$row['popuplinkname'],'formlink' => $this->url->link('extension/popupform/popupformpage', 'form_id=' . (int)$row['form_id'] . '&product_id=' . (int)$product_id, true));
					}
				}
				
			} 
			}
			if(!empty($manufacturer_id)) {
				$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_manufacturer tm left join `" . DB_PREFIX . "tmdform` t on tm.form_id=t.form_id  left join " . DB_PREFIX . "tmdform_description td on td.form_id=t.form_id  WHERE t.status='1' and tm.manufacturer_id='".$manufacturer_id."'  and td.language_id='".$this->config->get('config_language_id')."'";
				
				$query1=$this->db->query($sql);
				if(isset($query1->row['form_id'])){
					foreach($query1->rows as $row)
					{
				
					$formdata[$row['form_id']]=array('form_id'=>$row['form_id'],'display_type'=>$row['display_type'],'popuplinkname'=>$row['popuplinkname'],'formlink' => $this->url->link('extension/popupform/popupformpage', 'form_id=' . (int)$row['form_id'] . '&product_id=' . (int)$product_id, true));
					}
				}
			}
		
		
			if(!empty($product_id)) {
				$sql = "SELECT * FROM " . DB_PREFIX . "tmdform t left join " . DB_PREFIX . "tmdform_description td on td.form_id=t.form_id  WHERE t.assign_product='2' and t.status='1'  and td.language_id='".$this->config->get('config_language_id')."'";
				$query1=$this->db->query($sql);
				if(isset($query1->rows)){
					foreach($query1->rows as $row)
					{
						$formdata[$row['form_id']]=array('form_id'=>$row['form_id'],'display_type'=>$row['display_type'],'popuplinkname'=>$row['popuplinkname'],'formlink' => $this->url->link('extension/popupform/popupformpage', 'form_id=' . (int)$row['form_id'] . '&product_id=' . (int)$product_id, true));
					}
			
				}
			}
		
		return $formdata;
	}
	public function getTotalFormProduct($product_id,$manufacturer_id=0) {
		$total=0;
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tmdform_product WHERE product_id='".(int)$product_id."'");
		if(!empty($query->row['total']))
		{
			$total=$query->row['total'];
		}
		
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tmdform_manufacturer WHERE manufacturer_id='".(int)$manufacturer_id."'");
		if(!empty($query->row['total']))
		{
			$total=$query->row['total'];
		}
		
		$query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id='".(int)$product_id."'");
		if(!empty($query->row['category_id']))
		{
			foreach($query->rows as $row)
			{
				$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tmdform_category WHERE category_id='".(int)$row['category_id']."'");
				if(!empty($query->row['total']))
				{
					$total=$query->row['total'];
				}
			}
		
		}
		return $total;
	} 
	
	/* new code start */
	public function getTmdRecord($fs_id){
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_submit ts LEFT JOIN " . DB_PREFIX . "tmdform_description td ON (ts.form_id = td.form_id) where ts.fs_id='".$fs_id."'";
		$query = $this->db->query($sql);
		return $query->row;
	}
	
	public function getStore($store_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "store WHERE store_id = '" . (int)$store_id . "'");

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
	
	public function getfieldtype($field_id){
		$sql="select type from " . DB_PREFIX . "tmdform_field where field_id='".$field_id."'";
		$query = $this->db->query($sql);
		return $query->row['type'];

	}
	/* new code end */
	

	// 11 03 2019 //
	public function getInformationform($information_id){
		$informdata=array();
		$sql = "SELECT * FROM " . DB_PREFIX . "tmdform_information tp left join " . DB_PREFIX . "tmdform t on tp.form_id=t.form_id left join " . DB_PREFIX . "tmdform_description td on td.form_id=t.form_id   WHERE tp.information_id='".(int)$information_id."' and td.language_id='".$this->config->get('config_language_id')."'";
		$query=$this->db->query($sql);
	
		if(isset($query->rows)){
			foreach($query->rows as $row)
			{
				$informdata[$row['form_id']]=array('form_id'=>$row['form_id'],'display_type'=>$row['display_type'],'popuplinkname'=>$row['popuplinkname'],'formlink' => $this->url->link('tmdform/popupform/popupformpage&form_id='.$row['form_id'].'&information_id=' . $information_id));
			}
		
		}
		return $informdata;
	} 
	// 11 03 2019 //

		
}
?>
