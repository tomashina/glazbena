<?php 

/*******************************************************************************
*                                 Opencart SEO Pack                            *
*                             © Copyright Ovidiu Fechete                       *
*                              email: ovife21@gmail.com                        *
*                Below source-code or any part of the source-code              *
*                          cannot be resold or distributed.                    *
*******************************************************************************/

class ControllerExtensionExtensionClickFix extends Controller { 
	private $error = array();
 
	public function index() {
	
		$this->load->language('extension/extension/clickfix');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('clickfix', $this->request->post);		
					
			$this->session->data['success'] = $this->language->get('text_success');
						
			$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
		}
	
		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL'),
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'),
      		'separator' => ' :: '
   		);
	
		$data['heading_title'] = $this->language->get('heading_title');
		$data['user_token'] = $this->session->data['user_token'];
		$data['action'] = $this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		
		$meter = array();
		
		$data['aboutseourls'] = '';
		$data['fixseourls'] = false;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "seo_url where store_id = 0 and `query` like 'product_id=%';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "product_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutseourls'] .= $q.'/'.$queryt->row['total'].' SEO URLs for products '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixseourls'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "seo_url where store_id = 0 and `query` like 'category_id=%';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "category_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutseourls'] .= $q.'/'.$queryt->row['total'].' SEO URLs for categories '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixseourls'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "seo_url where store_id = 0 and `query` like 'manufacturer_id=%';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "manufacturer;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutseourls'] .= $q.'/'.$queryt->row['total'].' SEO URLs for manufacturers '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixseourls'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "seo_url where store_id = 0 and `query` like 'information_id=%';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "information_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutseourls'] .= $q.'/'.$queryt->row['total'].' SEO URLs for information '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixseourls'] = true;
		
		if ($data['fixseourls']) $data['fixseourls'] = $this->url->link('extension/extension/clickfix/fixseourls', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutmetadescription'] = '';
		$data['fixmetadescription'] = false;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "product_description where meta_description <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "product_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetadescription'] .= $q.'/'.$queryt->row['total'].' meta descriptions for product pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetadescription'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "category_description where meta_description <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "category_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetadescription'] .= $q.'/'.$queryt->row['total'].' meta descriptions for category pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetadescription'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "information_description where meta_description <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "information_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetadescription'] .= $q.'/'.$queryt->row['total'].' meta descriptions for info pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetadescription'] = true;
		
		if ($data['fixmetadescription']) $data['fixmetadescription'] = $this->url->link('extension/extension/clickfix/fixmetadescription', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutmetakeyword'] = '';
		$data['fixmetakeyword'] = false;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "product_description where meta_keyword <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "product_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetakeyword'] .= $q.'/'.$queryt->row['total'].' meta keywords for product pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetakeyword'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "category_description where meta_keyword <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "category_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetakeyword'] .= $q.'/'.$queryt->row['total'].' meta keywords for category pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetakeyword'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "information_description where meta_keyword <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "information_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetakeyword'] .= $q.'/'.$queryt->row['total'].' meta keywords for info pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetakeyword'] = true;
		
		if ($data['fixmetakeyword']) $data['fixmetakeyword'] = $this->url->link('extension/extension/clickfix/fixmetakeyword', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutmetatitle'] = '';
		$data['fixmetatitle'] = false;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "product_description where meta_title <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "product_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetatitle'] .= $q.'/'.$queryt->row['total'].' meta titles for product pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetatitle'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "category_description where meta_title <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "category_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetatitle'] .= $q.'/'.$queryt->row['total'].' meta titles for category pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetatitle'] = true;
		
		$query = $this->db->query("select count(*) as total from " . DB_PREFIX . "information_description where meta_title <> '';");
		$queryt = $this->db->query("select count(*) as total from " . DB_PREFIX . "information_description pd inner join " . DB_PREFIX . "language l on pd.language_id = l.language_id;");
		$q = (($query->row['total'] >= $queryt->row['total'])? $queryt->row['total'] : $query->row['total']);
		$meter[] .= ($queryt->row['total']>0)?($q/$queryt->row['total']):1;
		$data['aboutmetatitle'] .= $q.'/'.$queryt->row['total'].' meta titles for info pages '.(($query->row['total'] >= $queryt->row['total'])? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').'</br>';
		if ($query->row['total'] < $queryt->row['total']) $data['fixmetatitle'] = true;
		
		if ($data['fixmetatitle']) $data['fixmetatitle'] = $this->url->link('extension/extension/clickfix/fixmetatitle', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutmlseo'] = '';
		$data['fixmlseo'] = false;
				
		$mlseo = $this->config->get('mlseo');
		$tmlseo = 6;
		if (!$mlseo) {$mlseo = array();}
		$q = ((count($mlseo) > $tmlseo)? $tmlseo : count($mlseo));
		$meter[] .= $q/$tmlseo;
		$data['aboutmlseo'] .= $q.'/'.$tmlseo.' features for Multi-Language enabled '.(($q >= $tmlseo)? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($q < $tmlseo) $data['fixmlseo'] = true;
		if ($data['fixmlseo']) $data['fixmlseo'] = $this->url->link('extension/extension/clickfix/fixmlseo', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutcanonicals'] = '';
		$data['fixcanonicals'] = false;
				
		$canonicals = $this->config->get('canonicals');
		$tcanonicals = 6;
		if (!$canonicals) {$canonicals = array();}
		$q = ((count($canonicals) > $tcanonicals)? $tcanonicals : count($canonicals));
		$meter[] .= $q/$tcanonicals;
		$data['aboutcanonicals'] .= $q.'/'.$tcanonicals.' features for Canonical Links enabled '.(($q >= $tcanonicals)? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($q < $tcanonicals) $data['fixcanonicals'] = true;
		if ($data['fixcanonicals']) $data['fixcanonicals'] = $this->url->link('extension/extension/clickfix/fixcanonicals', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutrichsnippets'] = '';
		$data['fixrichsnippets'] = false;
				
		$richsnippets = $this->config->get('richsnippets');
		$cnt = 0;
		
		if (isset($richsnippets)) {foreach ($richsnippets as $element => $value) if ($value == 1) {$cnt++;}}
		$trichsnippets = 10;
		//if (!$richsnippets) {$richsnippets = array();}
		$q = (($cnt > $trichsnippets)? $trichsnippets : $cnt);
		$meter[] .= $q/$trichsnippets;
		$data['aboutrichsnippets'] .= $q.'/'.$trichsnippets.' features for Rich Snippets and Social SEO enabled '.(($q >= $trichsnippets)? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($q < $trichsnippets) $data['fixrichsnippets'] = true;
		if ($data['fixrichsnippets']) $data['fixrichsnippets'] = $this->url->link('extension/extension/clickfix/fixrichsnippets', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutseopagination'] = '';
		$data['fixseopagination'] = false;
				
		$seopagination = $this->config->get('seopagination');
		$tseopagination = 2;
		if (!$seopagination) {$seopagination = array();}
		$q = ((count($seopagination) > $tseopagination)? $tseopagination : count($seopagination));
		$meter[] .= $q/$tseopagination;
		$data['aboutseopagination'] .= $q.'/'.$tseopagination.' features for SEO Pagination enabled '.(($q >= $tseopagination)? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($q < $tseopagination) $data['fixseopagination'] = true;
		if ($data['fixseopagination']) $data['fixseopagination'] = $this->url->link('extension/extension/clickfix/fixseopagination', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$data['aboutextendedseo'] = '';
		$data['fixextendedseo'] = false;
				
		$extendedseo = $this->config->get('extendedseo');
		$textendedseo = 8;
		if (!$extendedseo) {$extendedseo = array();}
		$q = ((count($extendedseo) > $textendedseo)? $textendedseo : count($extendedseo));
		$meter[] .= $q/$textendedseo;
		$data['aboutextendedseo'] .= $q.'/'.$textendedseo.' features for Canonical Links enabled '.(($q >= $textendedseo)? '<b style="color:green">- OK</b>':'<b style="color:red">- FIX</b>').' </br>';
		if ($q < $textendedseo) $data['fixextendedseo'] = true;
		if ($data['fixextendedseo']) $data['fixextendedseo'] = $this->url->link('extension/extension/clickfix/fixextendedseo', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		
		
		$data['seometer'] = array_sum($meter) / count($meter);
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
		
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}
	
	
	$data['header'] = $this->load->controller('common/header');
						$data['column_left'] = $this->load->controller('common/column_left');
						$data['footer'] = $this->load->controller('common/footer');

						$this->response->setOutput($this->load->view('extension/extension/clickfix', $data));
	
		 
	}
	
	public function fixseourls() {
	
		require_once(DIR_APPLICATION . 'controller/extension/extension/seopack.php');
		$seo = new ControllerExtensionExtensionSeoPack($this->registry);
		$seo-> seourls(1);
		$this->session->data['success'] = "SEO URLs were fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	public function fixmetakeyword() {
	
		$parameters = $this->config->get('seopack_parameters');
		
		$this->db->query("update " . DB_PREFIX . "category_description set meta_keyword = lower(name) where meta_keyword = '';");
		$this->db->query("update " . DB_PREFIX . "information_description set meta_keyword = lower(title) where meta_keyword = '';");
		
		$query = $this->db->query("select pd.name as pname, cd.name as cname, pd.language_id as language_id, pd.product_id as product_id, p.sku as sku, p.model as model, p.upc as upc, m.name as brand  from " . DB_PREFIX . "product_description pd
				left join " . DB_PREFIX . "product_to_category pc on pd.product_id = pc.product_id
				inner join " . DB_PREFIX . "product p on pd.product_id = p.product_id
				left join " . DB_PREFIX . "category_description cd on cd.category_id = pc.category_id and cd.language_id = pd.language_id
				left join " . DB_PREFIX . "manufacturer m on m.manufacturer_id = p.manufacturer_id
				where pd.meta_keyword = '';");

		foreach ($query->rows as $product) {
					
			$bef = array("%", "_","\"","'","\\");
			$aft = array("", " ", " ", " ", "");
			
			$included = explode('%', str_replace(array(' ',','), '', (($parameters['keywords'])?$parameters['keywords']:'%p%c')));
			
			$tags = array();
			
			if (in_array("p", $included)) {$tags = array_merge($tags, explode(' ',trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['pname']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("c", $included)) {$tags = array_merge($tags, explode(' ',trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['cname']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("s", $included)) {$tags = array_merge($tags, explode(' ',trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['sku']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("m", $included)) {$tags = array_merge($tags, explode(' ',trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['model']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("u", $included)) {$tags = array_merge($tags, explode(' ',trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['upc']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("b", $included)) {$tags = array_merge($tags, explode(' ',trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['brand']), ENT_COMPAT, "UTF-8")))));}
			
			$keywords = '';
			
			foreach ($tags as $tag)
				{
				if (strlen($tag) > 2) 
					{
					
					$keywords = $keywords.' '.strtolower($tag);
					
					}
				}
				
			
			$exists = $this->db->query("select count(*) as times from " . DB_PREFIX . "product_description where product_id = ".$product['product_id']." and language_id = ".$product['language_id']." and meta_keyword like '%". htmlspecialchars($keywords) ."%';");
			
					foreach ($exists->rows as $exist)
						{
						$count = $exist['times'];
						}
			$exists = $this->db->query("select length(meta_keyword) as leng from " . DB_PREFIX . "product_description where product_id = ".$product['product_id']." and language_id = ".$product['language_id'].";");
			
					foreach ($exists->rows as $exist)
						{
						$leng = $exist['leng'];
						}

			if (($count == 0) && ($leng < 255)) {$this->db->query("update " . DB_PREFIX . "product_description set meta_keyword = concat(meta_keyword, '". htmlspecialchars($keywords) ."') where product_id = ".$product['product_id']." and language_id = ".$product['language_id'].";");}			
	
			}
	
		$this->session->data['success'] = "Meta Keywords were fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	private function first_sentence($content) {

		$content = strip_tags(html_entity_decode($content));
		$pos = strpos($content, '.');
		   
		if($pos === false) {
			return $content;
		}
		else {
			return substr($content, 0, $pos+1);
		}
	   
	}
	
	public function fixmetadescription() {
	
		$parameters = $this->config->get('seopack_parameters');
		
		$this->db->query("update " . DB_PREFIX . "category_description set meta_description = concat(name, case description when '' then '' else ' - ' end, substring_index(description, '.', 1)) where meta_description = '';");
		$this->db->query("update " . DB_PREFIX . "information_description set meta_description = concat(title, case description when '' then '' else ' - ' end, substring_index(description, '.', 1)) where meta_description = '';");

		$query = $this->db->query("select pd.name as pname, p.price as price, cd.name as cname, pd.description as pdescription, pd.language_id as language_id, pd.product_id as product_id, p.model as model, p.sku as sku, p.upc as upc, m.name as brand from " . DB_PREFIX . "product_description pd
				left join " . DB_PREFIX . "product_to_category pc on pd.product_id = pc.product_id
				inner join " . DB_PREFIX . "product p on pd.product_id = p.product_id
				left join " . DB_PREFIX . "category_description cd on cd.category_id = pc.category_id and cd.language_id = pd.language_id
				left join " . DB_PREFIX . "manufacturer m on m.manufacturer_id = p.manufacturer_id
				where pd.meta_description = '';");

		foreach ($query->rows as $product) {
						
			$bef = array("%", "_","\"","'","\\", "\r", "\n");
			$aft = array("", " ", " ", " ", "", "", "");
			
			$ncategory = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['cname']), ENT_COMPAT, "UTF-8")));
			$nproduct = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['pname']), ENT_COMPAT, "UTF-8")));
			$model = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['model']), ENT_COMPAT, "UTF-8")));
			$sku = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['sku']), ENT_COMPAT, "UTF-8")));
			$upc = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['upc']), ENT_COMPAT, "UTF-8")));
			$sentence = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$this->first_sentence($product['pdescription'])), ENT_COMPAT, "UTF-8")));
			$brand = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['brand']), ENT_COMPAT, "UTF-8")));
			$price = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft, number_format($product['price'], 2)), ENT_COMPAT, "UTF-8")));
			
			$bef = array("%c", "%p", "%m", "%s", "%u", "%f", "%b", "%$");
			$aft = array($ncategory, $nproduct, $model, $sku, $upc, $sentence, $brand, $price);
			
			$meta_description = str_replace($bef, $aft,  (($parameters['metas'])?$parameters['metas']:'%p - %f'));
			
			$exists = $this->db->query("select count(*) as times from " . DB_PREFIX . "product_description where product_id = ".$product['product_id']." and language_id = ".$product['language_id']." and meta_description not like '%".htmlspecialchars($meta_description)."%';");
			
					foreach ($exists->rows as $exist)
						{
						$count = $exist['times'];
						}
			
			if ($count) {$this->db->query("update " . DB_PREFIX . "product_description set meta_description = concat(meta_description, '". htmlspecialchars($meta_description) ."') where product_id = ".$product['product_id']." and language_id = ".$product['language_id'].";");}			
				
			}
	
		$this->session->data['success'] = "Meta Descriptions were fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	public function fixmetatitle() {
	
		$parameters = $this->config->get('seopack_parameters');
		
		$this->db->query("update " . DB_PREFIX . "category_description set meta_title = name where meta_title = '';");
		$this->db->query("update " . DB_PREFIX . "information_description set meta_title = title where meta_title = '';");
		
		
		$query = $this->db->query("select pd.name as pname, p.price as price, p.tax_class_id as tax_class_id, cd.name as cname, pd.description as pdescription, pd.language_id as language_id, pd.product_id as product_id, p.model as model, p.sku as sku, p.upc as upc, m.name as brand from " . DB_PREFIX . "product_description pd
				left join " . DB_PREFIX . "product_to_category pc on pd.product_id = pc.product_id
				inner join " . DB_PREFIX . "product p on pd.product_id = p.product_id
				left join " . DB_PREFIX . "category_description cd on cd.category_id = pc.category_id and cd.language_id = pd.language_id
				left join " . DB_PREFIX . "manufacturer m on m.manufacturer_id = p.manufacturer_id
				where pd.meta_title = '';");

		foreach ($query->rows as $product) {
						
			$bef = array("%", "_","\"","'","\\", "\r", "\n");
			$aft = array("", " ", " ", " ", "", "", "");
			
			$ncategory = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['cname']), ENT_COMPAT, "UTF-8")));
			$nproduct = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['pname']), ENT_COMPAT, "UTF-8")));
			$model = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['model']), ENT_COMPAT, "UTF-8")));
			$sku = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['sku']), ENT_COMPAT, "UTF-8")));
			$upc = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['upc']), ENT_COMPAT, "UTF-8")));
			$brand = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft,$product['brand']), ENT_COMPAT, "UTF-8")));
			$price = trim($this->db->escape(html_entity_decode(str_replace($bef, $aft, number_format($product['price'] * (($product['tax_class_id'] == 9)?1.21:1.06), 2)), ENT_COMPAT, "UTF-8")));
			
			$bef = array("%c", "%p", "%m", "%s", "%u", "%b", "%$");
			$aft = array($ncategory, $nproduct, $model, $sku, $upc, $brand, $price);
			
			$ctitles = str_replace($bef, $aft, (($parameters['ctitles'])?$parameters['ctitles']:'%p'));
			$this->db->query("update " . DB_PREFIX . "product_description set meta_title = '". htmlspecialchars($ctitles) ."' where product_id = ".$product['product_id']." and language_id = ".$product['language_id'].";");			
					
			}
	
		$this->session->data['success'] = "Meta Titles were fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	public function fixmlseo() {
		
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('mlseo', array('mlseo'=> array( 'subfolder' => 1 ,'hreflang' => 1, 'hreflangproduct' => 1, 'hreflangcategory' => 1, 'hreflangbrand' => 1, 'hreflanginfo' => 1 )));
		$this->session->data['success'] = "Multi Language SEO was fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	public function fixcanonicals() {
		
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('canonicals', array('canonicals'=> array(  'canonicals_categories' => 1, 'canonicals_brands' => 1, 'canonicals_info' => 1, 'canonicals_home' => 1, 'canonicals_specials' => 1, 'canonicals_extended' => 1  )));
		$this->session->data['success'] = "Canonical Links were fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	public function fixrichsnippets() {
		
		$this->load->model('setting/setting');
		$richsnippets = $this->config->get('richsnippets');
		$this->model_setting_setting->editSetting('richsnippets',  array('richsnippets'=> array('breadcrumbs' => 1, 'product' => 1, 'offer' => 1, 'rating' => 1, 'store' => 1, 'twittersite' => 1, 'twitterproduct' => 1, 'ogsite' => 1, 'ogproduct' => 1, 'googlepublisher' => 1, 'googleid' =>((isset($richsnippets['googleid']))?$richsnippets['googleid']:''), 'twitteruser' =>((isset($richsnippets['twitteruser']))?$richsnippets['twitteruser']:''))));
		$this->session->data['success'] = "Rich Snippets and Social SEO were fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	public function fixseopagination() {
		
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('seopagination', array('seopagination'=> array( 'pagination' => 1, 'prevnext' => 1 )));
		$this->session->data['success'] = "SEO Pagination was fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	public function fixextendedseo() {
		
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('extendedseo', array('extendedseo'=> array( 'seotags' => 1, 'productseo' => 1, 'categoryintitle' => 1, 'link' => 1, 'slash' => 1, 'robots' => 1, 'trim_titles' => 1, 'trim_descriptions' => 1 )));
		$this->session->data['success'] = "Extened SEO was fixed.";
		
		$this->response->redirect($this->url->link('extension/extension/clickfix', 'user_token=' . $this->session->data['user_token'], 'SSL'));
	
	}
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/extension/clickfix')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}
	

	
}
?>