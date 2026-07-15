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
			else {$parameters['ch1s'] = '';}
			
if ((!isset($_GET['gkey'])) || ($_GET['gkey'] != $parameters['gkey'])) 	{

		header('Location: ' . HTTP_SERVER);
	}
	else {

		if (!isset($parameters['ch1s'])) {$parameters['ch1s'] = '';}
		
		$query = $db->query("insert ignore into " . DB_PREFIX . "product_description_seo (product_id, language_id) select product_id, language_id from " . DB_PREFIX . "product_description;");
		$query = $db->query("select pd.name as pname, p.price as price, cd.name as cname, pd.description as pdescription, pd.language_id as language_id, pd.product_id as product_id, p.model as model, p.sku as sku, p.upc as upc, m.name as brand from " . DB_PREFIX . "product_description pd
				left join " . DB_PREFIX . "product_to_category pc on pd.product_id = pc.product_id
				inner join " . DB_PREFIX . "product p on pd.product_id = p.product_id
				left join " . DB_PREFIX . "category_description cd on cd.category_id = pc.category_id and cd.language_id = pd.language_id
				left join " . DB_PREFIX . "manufacturer m on m.manufacturer_id = p.manufacturer_id;");

		foreach ($query->rows as $product) {
			echo 'Generating Custom h1s for <b>'.$product['pname'].' (from '.$product['cname'].')</b>: ';
			
			$bef = array("%", "_","\"","'","\\", "\r", "\n");
			$aft = array("", " ", " ", " ", "", "", "");
			
			$ncategory = trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['cname']), ENT_COMPAT, "UTF-8")));
			$nproduct = trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['pname']), ENT_COMPAT, "UTF-8")));
			$model = trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['model']), ENT_COMPAT, "UTF-8")));
			$sku = trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['sku']), ENT_COMPAT, "UTF-8")));
			$upc = trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['upc']), ENT_COMPAT, "UTF-8")));
			$brand = trim($db->escape(html_entity_decode(str_replace($bef, $aft,$product['brand']), ENT_COMPAT, "UTF-8")));
			$price = trim($db->escape(html_entity_decode(str_replace($bef, $aft, number_format($product['price'], 2)), ENT_COMPAT, "UTF-8")));
			
			$bef = array("%c", "%p", "%m", "%s", "%u", "%b", "%$");
			$aft = array($ncategory, $nproduct, $model, $sku, $upc, $brand, $price);
						
			$ch1s = str_replace($bef, $aft,  $parameters['ch1s']);
			$db->query("update " . DB_PREFIX . "product_description_seo set custom_h1 = '". htmlspecialchars($ch1s) ."' where product_id = ".$product['product_id']." and language_id = ".$product['language_id'].";");			
			
			echo " - ".( (!$parameters['ch1s']) ?" No ":"<span style=\"color:red;\">$ch1s</span> ")." custom h1 was set;<br>";
			}
	}
	
?>

</body>
</html>


