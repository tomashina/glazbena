<?php
class ModelExtensionTmdFormBulider extends Model {
	public function install() {
	$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdfield_submit` (
  `fs_id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `value` text NOT NULL,
  `label` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL,
  `serialize` int(11) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform` (
  `form_id` int(11) NOT NULL AUTO_INCREMENT,
  `headerlink` int(11) NOT NULL,
  `footerlink` int(11) NOT NULL,
  `productwidth` varchar(255) NOT NULL,
  `productheight` varchar(255) NOT NULL,
  `captcha` int(11) NOT NULL,
  `resetbutton` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `display_type` text NOT NULL,
  `assign_product` text NOT NULL,
  `custome_style` text NOT NULL,
  `customer_notification` int(11) NOT NULL,
  `admin_notification` int(11) NOT NULL,
  `date_added` date NOT NULL,
  `date_modified` date NOT NULL,
  PRIMARY KEY (`form_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_category` (
  `form_id` int(11) NOT NULL,
  `category_id` int(200) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_customergroup` (
  `form_id` int(11) NOT NULL,
  `customer_group_id` int(111) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_description` (
  `form_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `meta_title` varchar(200) NOT NULL,
  `meta_description` text NOT NULL,
  `meta_keyword` text NOT NULL,
  `top_description` text NOT NULL,
  `bottom_description` text NOT NULL,
  `button_name` varchar(255) NOT NULL,
  `resetbuttonname` varchar(255) NOT NULL,
  `popuplinkname` varchar(255) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_field` (
  `field_id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `form_status` int(11) NOT NULL,
  `required` int(11) NOT NULL,
  `type` text NOT NULL,
  `sort` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL,
  PRIMARY KEY (`field_id`),
  UNIQUE KEY `field_id` (`field_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_field_description` (
  `field_id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `field_name` varchar(200) NOT NULL,
  `help_text` varchar(200) NOT NULL,
  `placeholder` varchar(200) NOT NULL,
  `error_message` varchar(200) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_field_option` (
  `field_option_id` int(11) NOT NULL AUTO_INCREMENT,
  `optiobaseid` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `image` text NOT NULL,
  `sort_order` text NOT NULL,
  PRIMARY KEY (`field_option_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_field_option_base` (
  `optiobaseid` int(11) NOT NULL AUTO_INCREMENT,
  `field_id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  PRIMARY KEY (`optiobaseid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_information` (
  `form_id` int(11) NOT NULL,
  `information_id` int(11) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_manufacturer` (
  `form_id` int(11) NOT NULL,
  `manufacturer_id` int(200) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_notification` (
  `form_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `customer_subject` varchar(200) NOT NULL,
  `customer_message` text NOT NULL,
  `admin_subject` varchar(200) NOT NULL,
  `admin_message` text NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_product` (
  `form_id` int(11) NOT NULL,
  `product_id` int(111) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_store` (
  `form_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_submit` (
  `fs_id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `customer_id` int(111) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `language_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `user_agent` varchar(100) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`fs_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."tmdform_success` (
  `form_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `success_title` varchar(200) NOT NULL,
  `success_meta_title` text NOT NULL,
  `success_description` text NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

	}
	public function uninstall() {
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdfield_submit`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_category`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_customergroup`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_description`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_field`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_field_description`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_field_option`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_field_option_base`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_manufacturer`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_information`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_notification`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_product`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_store`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_submit`");
	$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."tmdform_success`");
	}
}
