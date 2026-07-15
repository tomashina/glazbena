<?php

/*******************************************************************************
*                                 Opencart SEO Pack                            *
*                             © Copyright Ovidiu Fechete                       *
*                              email: ovife21@gmail.com                        *
*                Below source-code or any part of the source-code              *
*                          cannot be resold or distributed.                    *
*******************************************************************************/
require_once('../../../config.php');
// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Config
$config = new Config();
$config->load('default');

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);


echo '<html><head><meta charset="UTF-8" /></head>
<body>
<FORM><INPUT TYPE="BUTTON" VALUE="Go Back" 
ONCLICK="history.go(-1)"></FORM>';

$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` like 'seopack%'");

		foreach ($query->rows as $result) {
					if (!$result['serialized']) {
						$data[$result['key']] = $result['value'];
					} else {
						if ($result['value'][0] == '{') {$data[$result['key']] = json_decode($result['value'], true);} else {$data[$result['key']] = unserialize($result['value']);}
					}
				}
				
		if (isset($data)) {$parameters = $data['seopack_parameters'];}
			else {$parameters['keywords'] = '%c%p';}

if ((!isset($_GET['gkey'])) || ($_GET['gkey'] != $parameters['gkey'])) 	{

		header('Location: ' . HTTP_SERVER);
	}
	else {
		
		$db->query("update " . DB_PREFIX . "category_description set meta_keyword = lower(name) where meta_keyword = '';");
		$db->query("update " . DB_PREFIX . "information_description set meta_keyword = lower(title) where meta_keyword = '';");
		
		$query = $db->query("select pd.name as pname, cd.name as cname, pd.language_id as language_id, pd.product_id as product_id, p.sku as sku, p.model as model, p.upc as upc, m.name as brand  from " . DB_PREFIX . "product_description pd
				left join " . DB_PREFIX . "product_to_category pc on pd.product_id = pc.product_id
				inner join " . DB_PREFIX . "product p on pd.product_id = p.product_id
				left join " . DB_PREFIX . "category_description cd on cd.category_id = pc.category_id and cd.language_id = pd.language_id
				left join " . DB_PREFIX . "manufacturer m on m.manufacturer_id = p.manufacturer_id;");

		foreach ($query->rows as $product) {
			echo 'Generating keywords for <b>'.$product['pname'].' (from '.$product['cname'].')</b>: ';
			
			$bef = array("%", "_","\"","'","\\");
			$aft = array("", " ", " ", " ", "");
			
			$included = explode('%', str_replace(array(' ',','), '', $parameters['keywords']));
			
			$tags = array();
			
			if (in_array("p", $included)) {$tags = array_merge($tags, explode(' ',trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['pname']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("c", $included)) {$tags = array_merge($tags, explode(' ',trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['cname']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("s", $included)) {$tags = array_merge($tags, explode(' ',trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['sku']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("m", $included)) {$tags = array_merge($tags, explode(' ',trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['model']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("u", $included)) {$tags = array_merge($tags, explode(' ',trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['upc']), ENT_COMPAT, "UTF-8")))));}
			if (in_array("b", $included)) {$tags = array_merge($tags, explode(' ',trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['brand']), ENT_COMPAT, "UTF-8")))));}
			
			$keywords = '';
			
			foreach ($tags as $tag)
				{
				if (strlen($tag) > 2) 
					{
					
					$keywords = $keywords.' '.strtolower($tag);
					
					}
				}
				
			
			$exists = $db->query("select count(*) as times from " . DB_PREFIX . "product_description where product_id = ".$product['product_id']." and language_id = ".$product['language_id']." and meta_keyword like '%". htmlspecialchars($keywords) ."%';");
			
					foreach ($exists->rows as $exist)
						{
						$count = $exist['times'];
						}
			$exists = $db->query("select length(meta_keyword) as leng from " . DB_PREFIX . "product_description where product_id = ".$product['product_id']." and language_id = ".$product['language_id'].";");
			
					foreach ($exists->rows as $exist)
						{
						$leng = $exist['leng'];
						}

			if (($count == 0) && ($leng < 255)) {$db->query("update " . DB_PREFIX . "product_description set meta_keyword = concat(meta_keyword, '". htmlspecialchars($keywords) ."') where product_id = ".$product['product_id']." and language_id = ".$product['language_id'].";");}			
			
			echo " - ".((($count) || ($leng == 255))?"No new keywords ":"<span style=\"color:red;\">>$keywords</span> ")." were inserted;<br>";
			}
	}
	
?>

</body>
</html>


