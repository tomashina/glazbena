<?php

/**
 * Mega Filter
 * 
 * @author info@ocdemo.eu <info@ocdemo.eu> 
 */
class ModelExtensionModuleMegaFilter extends Model {
	
	private $_hasFilters = NULL;
	
	public function hasFilters() {
		if( $this->_hasFilters === NULL ) {
			$this->_hasFilters = version_compare( VERSION, '1.5.5', '>=' );
		}
		
		return $this->_hasFilters;
	}
	
	public function uninstall() {
		$this->db->query("
			DELETE FROM
				`" . DB_PREFIX . "setting`
			WHERE
				`key` IN(
					'mega_filter_module',
					'mega_filter_status',
					'mfilter_version',
					'mfilter_latest_ver',
					'mfilter_license',
					'mega_filter_attribs',
					'mega_filter_settings', 
					'mega_filter_seo',
					'mega_filter_filters',
					'mega_filter_options',
					'mfilter_plus_version',
					'mfilter_mijoshop',
					'mfilter_url_param',
					'mfilter_seo_sep'
				) OR
				`key` REGEXP '^mega_filter_at_img_[0-9]+_[0-9]+$' OR 
				`key` REGEXP '^mega_filter_fi_img_[0-9]+_[0-9]+$' OR 
				`key` REGEXP '^mega_filter_at_sort_[0-9]+_[0-9]+$'
		");
			
		$this->removeColumn( 'attribute_description', 'mf_tooltip' );
		$this->removeColumn( 'option_description', 'mf_tooltip' );
		$this->removeColumn( 'filter_group_description', 'mf_tooltip' );
			
		// @since 2.0.3.2
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "mfilter_url_alias`");
		
		// @since 2.0.5.3
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "mfilter_url_replacement`");
	}
	
	/**
	 * Add column to table
	 * 
	 * @param string $table
	 * @param string $column
	 * @param string $type
	 * @return boolean 
	 */
	public function addColumn( $table, $column, $type ) {		
		$query = $this->db->query('SHOW COLUMNS FROM `' . DB_PREFIX . $table . '` LIKE "' . $column . '"');
		
		if( ! $query->num_rows ) {
			$this->db->query( 'ALTER TABLE `' . DB_PREFIX . $table . '` ADD `' . $column . '` ' . $type );
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Remove column from table
	 * 
	 * @param string $table
	 * @param string $column
	 * @return bool 
	 */
	public function removeColumn( $table, $column ) {		
		$query = $this->db->query('SHOW COLUMNS FROM `' . DB_PREFIX . $table . '` LIKE "' . $column . '"');
		
		if( $query->num_rows ) {
			$this->db->query('ALTER TABLE `' . DB_PREFIX . $table . '` DROP `' . $column . '`');
			
			return true;
		}
		
		return false;
	}
	
	public function updateDB( $install = false ) {
		if( $install || version_compare( $this->config->get( 'mfilter_version' ), '2.0.4.4.5.2', '<' ) ) {
			$this->addColumn( 'attribute_description', 'mf_tooltip', 'TEXT NULL' );
			$this->addColumn( 'option_description', 'mf_tooltip', 'TEXT NULL' );
			$this->addColumn( 'filter_group_description', 'mf_tooltip', 'TEXT NULL' );
		}
		
		if( $install || version_compare( $this->config->get( 'mfilter_version' ), '2.0.3.2', '<' ) ) {
			$this->createUrlAliasTable();
		}
		
		if( version_compare( $this->config->get( 'mfilter_version' ), '2.0.4.6', '<' ) ) {
			$this->addColumn( 'mfilter_url_alias', 'meta_title', 'VARCHAR(255) NULL' );
			$this->addColumn( 'mfilter_url_alias', 'meta_description', 'VARCHAR(255) NULL' );
			$this->addColumn( 'mfilter_url_alias', 'meta_keyword', 'VARCHAR(255) NULL' );
		}
		
		if( version_compare( $this->config->get( 'mfilter_version' ), '2.0.4.7', '<' ) ) {
			$this->addColumn( 'mfilter_url_alias', 'description', 'TEXT NULL' );
			$this->addColumn( 'mfilter_url_alias', 'h1', 'VARCHAR(255) NULL' );
		}
		
		if( version_compare( $this->config->get( 'mfilter_version' ), '2.0.5.3', '<' ) ) {
			$this->createMfilterUrlReplacementTable();
		}
		
		if( $install || version_compare( $this->config->get( 'mfilter_version' ), '2.0.5.3.5', '<' ) || ( $this->config->get( 'mfilter_version' ) == '2.0.5.3.5' && ! $this->config->get('mega_filter_themes') ) ) {
			$this->load->model('setting/store');
			$this->load->model('setting/setting');

			$stores = array(0);

			foreach( $this->model_setting_store->getStores() as $row ) {
				$stores[] = $row['store_id'];
			}
			
			$css = "/* // HORIZONTAL TOP //////////////////////////////////////////////////////////*/


.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li {
	border-top: none !important;
}

.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li .mfilter-heading {
	border-left: 1px solid #dbdee1;
}
.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li .mfilter-content-opts {
	border-right: 1px solid #dbdee1;
}

.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li .mfilter-heading .mfilter-heading-content{
	margin-left: 10px;
	border: none;
}

.mfilter-content_top.mfilter-light-theme .mfilter-category-cat_checkbox .mfilter-tb > .mfilter-tb-as-tr, 
.mfilter-content_top.mfilter-light-theme .mfilter-tb .mfilter-tb {
	background: #f5f5f5;
	background-image: -moz-linear-gradient(center top , #f5f5f5, #f1f1f1);
	border: 1px solid #dedede;
}

.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li:first-child .mfilter-heading,
.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li:first-child .mfilter-content-opts {
	border-top: 1px solid #dbdee1;
}
.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li:last-child .mfilter-heading,
.mfilter-content_top.mfilter-light-theme .mfilter-content > ul > li:last-child .mfilter-content-opts {
	border-bottom: 1px solid #dbdee1;
}

.mfilter-content_top.mfilter-light-theme .mfilter-tb .mfilter-tb-as-tr .mfilter-tb-as-td{
	border-top: none;
}

.mfilter-content_top.mfilter-light-theme .mfilter-category-cat_checkbox .mfilter-tb > .mfilter-tb-as-tr:hover, 
.mfilter-content_top.mfilter-light-theme .mfilter-tb .mfilter-tb:hover {
	background-color: #f8f8f8;
    background-image: -moz-linear-gradient(center top , #f8f8f8, #f1f1f1);
    border: 1px solid #c6c6c6;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
}

.mfilter-content_top.mfilter-light-theme .mfilter-price-inputs{
	margin-left: 5px;
}

.mfilter-content_top.mfilter-light-theme .mfilter-price-inputs input{
	margin: 2px 0;
}

.mfilter-content_top.mfilter-light-theme .mfilter-price-slider {
	margin: 6px 0 0 10px;
}


.mfilter-content_top.mfilter-light-theme .mfilter-search{
	margin: 0 16px;
}

.mfilter-content_top.mfilter-light-theme .mfilter-search #mfilter-opts-search{
	margin-top: 2px;
}

.mfilter-content_top.mfilter-light-theme .mfilter-category-tree{
	margin: 6px 0 0 10px;
}

.mfilter-content_top.mfilter-light-theme .mfilter-content .mfilter-button-top {
	border-bottom: none !important;
}

.mfilter-content_top.mfilter-light-theme .mfilter-content .mfilter-button-bottom {
	border-top: none !important;
}

.mfilter-button-top.mfilter-light-theme {
	border-bottom: 1px solid #EEEEEE;
}

.mfilter-content_top.mfilter-light-theme .mfilter-slider-inputs .mfilter-opts-slider-min{
	margin-left: 13px;
}

.mfilter-content_top.mfilter-light-theme .mfilter-text .mfilter-slider-inputs input{
	margin-left: 13px;
	width: 96%;
	margin-left: auto;
	margin-right: auto;
}

.mfilter-light-theme .mfilter-inline-horizontal .mfilter-scroll-left,
.mfilter-light-theme .mfilter-inline-horizontal .mfilter-scroll-right {
	vertical-align: middle;
}

/* // VERTICAL ///////////////////////////////////////////////////////////////*/

.mfilter-light-theme .mfilter-content {
	border: none;
}

.mfilter-light-theme .mfilter-heading {
	font-weight: bold;
	color: black;
	background: none;
}


.mfilter-light-theme .mfilter-heading-text > i {
	background: #ecd8d8;
	margin-bottom: 3px;
}

.mfilter-light-theme .mfilter-heading-content {
	padding: 16px 8px 1px 8px;
	border-bottom: 1px solid #c1c6c6;
}

.mfilter-light-theme .mfilter-category a{
	color: black;
	margin-left: 3px;
}

.mfilter-light-theme .mfilter-tb-as-tr:hover{
	background-color: #f8f8f8;
	background-image: -moz-linear-gradient(center top , #f8f8f8, #f1f1f1);
}

.mfilter-light-theme .mfilter-tb-as-td {
	border-top: 1px solid #dcdcdc;
}

.mfilter-light-theme label.mfilter-tb-as-td {
	color: black;
}

.mfilter-light-theme .mfilter-disabled label.mfilter-tb-as-td {
	color: gray;
}

.mfilter-light-theme .mfilter-heading-text > span {
	padding-left: 12px;
}

.mfilter-light-theme .mfilter-button-more {
	color: black;
}

.mfilter-light-theme .mfilter-opts-container {
	border:none;
}
 
.mfilter-light-theme .mfilter-price-slider {
	padding: 0 0 5px 4px !important;
	margin-right: 0px !important;
	background: none !important;
}

.mfilter-light-theme .mfilter-slider-slider .ui-slider-handle,
.mfilter-light-theme #mfilter-price-slider .ui-slider-handle {
	width: 18px !important;
	height: 12px !important;
	background: white !important;
	margin-top: -1px;
	background-size: cover;
	background-repeat: no-repeat;
	background-position: center center;
	-webkit-border-radius: 99em;
	-moz-border-radius: 99em;
	border-radius: 99em;
	border: 1px solid #eee;
	box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.4); 
}

.mfilter-light-theme .mfilter-slider-slider,
.mfilter-light-theme #mfilter-price-slider {
	background: lightgrey !important;
	height: 3px !important;
	margin: 10px 6px 0 12px !important;
}

.mfilter-light-theme .mfilter-slider-slider .ui-slider-range,
.mfilter-light-theme #mfilter-price-slider .ui-slider-range {
	background: #428bca !important;
}

.mfilter-light-theme .mfilter-search{
	margin: 0 5px;
}

.mfilter-light-theme .mfilter-search #mfilter-opts-search {
	margin-top: 2px;
	border: #dcdcdc solid 1px;
	border-radius: 2px !important;
	color: black;
	box-shadow: none !important;
          -webkit-box-shadow: none !important;
          -moz-box-shadow: none !important;
}

.mfilter-light-theme .mfilter-col-input {
	padding-right: 4px !important;
	padding-left: 2px !important;
}

.mfilter-light-theme .mfilter-category-tree ul li.mfilter-to-parent {
	margin-top: 3px;
}

.mfilter-light-theme .mfilter-selected-filters-cnt {
	margin-bottom: 20px;
}

.mfilter-light-theme .mfilter-content .mfilter-selected-filters .mfilter-selected-filters-cnt a > span {
	border-bottom: 1px dotted #c1c6c6;
}

.mfilter-light-theme .mfilter-select select {
	width: 96%;
	margin-left: auto;
	margin-right: auto;
	border: #dcdcdc solid 1px;
	border-radius: 2px !important;
	box-shadow: none !important;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
}

.mfilter-light-theme .mfilter-text .mfilter-slider-inputs input{
	width: 94%;
	margin-left: auto;
	margin-right: auto;
}

.mfilter-light-theme .mfilter-price-inputs > input{
	border: #dcdcdc solid 1px;
	border-radius: 2px !important;
	padding: 0 0 0 10px;
	margin: 8px 0 8px 0;
	height: 30px;
	color: black;
	box-shadow: none !important;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
}
 
.mfilter-light-theme .mfilter-slider-inputs input{
	border: #dcdcdc solid 1px;
	border-radius: 2px !important;
	height: 30px;
	color: black;
	box-shadow: none !important;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
 }
.mfilter-light-theme .mfilter-col-count{
	 padding-right: 2px;
 }";
			
			$themes = (array) $this->config->get('mega_filter_themes');
			$themes['.mfilter-light-theme'] = array(
				'code' => $css,
				'name' => 'Light Theme'
			);
		
			foreach( $stores as $store_id ) {
				$this->model_setting_setting->editSetting('mega_filter_themes', array( 'mega_filter_themes' => $themes ), $store_id);
			}
		}
	}
	
	protected function optionsConditions( $data, $prefix = ' AND ' ) {
		/* @var $conditions array */
		$conditions = array();
		
		if (!empty($data['filter_name'])) {
			$conditions[] = "`od`.`name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}
		
		if( ! empty( $data['except_ids'] ) ) {
			$conditions[] = "`o`.`option_id` NOT IN(" . implode( ',', $data['except_ids'] ) . ")";
		}
		
		if( ! empty( $data['only_ids'] ) ) {
			$conditions[] = "`o`.`option_id` IN(" . implode( ',', $data['only_ids'] ) . ")";
		}
		
		return $conditions ? $prefix . implode( ' AND ', $conditions ) : '';
	}
	
	protected function sqlOptions( $data, $select = '*' ) {
		$sql = "
			SELECT 
				" . $select . "
			FROM 
				`" . DB_PREFIX . "option` AS `o` 
			LEFT JOIN 
				`" . DB_PREFIX . "option_description` AS `od` 
			ON 
				`o`.`option_id` = `od`.`option_id` 
			WHERE 
				`od`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND
				`o`.`type` IN( 'select', 'radio', 'checkbox', 'image' )
		";
		
		$sql .= $this->optionsConditions( $data );
		
		return $sql;
	}
	
	protected function sqlLimit( $data ) {
		$sql = '';
		
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}
		
		return $sql;
	}

	public function getTotalOptions( $data = array() ) {		
		$query = $this->db->query( $this->sqlOptions( $data, 'COUNT(*) AS `total`' ) );

		return $query->row['total'];
	}
	
	public function getOptions( $data = array() ) {
		$sql = $this->sqlOptions( $data );

		$sql .= " ORDER BY `od`.`name` ASC";
		$sql .= $this->sqlLimit( $data );

		$query = $this->db->query($sql);
		
		/* @var $options array */
		$options = array();
		
		foreach( $query->rows as $row ) {
			$options[$row['option_id']] = $row;
		}
		
		return $options;
	}
	
	public function getFiltersValues( $filter_id, $lang_id ) {
		$sql = "
			SELECT
				`fd`.`name`,
				`f`.`filter_id`
			FROM
				`" . DB_PREFIX . "filter_description` AS `fd`
			INNER JOIN
				`" . DB_PREFIX . "filter` AS `f`
			ON
				`fd`.`filter_id` = `f`.`filter_id`
			WHERE
				`f`.`filter_group_id` = " . (int) $filter_id . " AND
				`fd`.`language_id` = '" . (int)$lang_id . "'
			ORDER BY
				`fd`.`name`
		";
			
		return $this->db->query( $sql )->rows;
	}
	
	public function getAttribsValues( $attribute_id, $lang_id ) {
		$sql = "
			SELECT
				REPLACE(REPLACE(TRIM(`pa`.`text`), '\r', ''), '\n', '') AS `text`,
				`pa`.`attribute_id`
			FROM
				`" . DB_PREFIX . "product_attribute` AS `pa`
			INNER JOIN
				`" . DB_PREFIX . "product` AS `p`
			ON
				`pa`.`product_id` = `p`.`product_id` AND `p`.`status` = '1'
			WHERE
				`pa`.`attribute_id` = " . (int) $attribute_id . " AND
				`pa`.`language_id` = '" . (int)$lang_id . "'
			GROUP BY
				`text`, `pa`.`attribute_id`
		";
		
		return $this->db->query( $sql )->rows;
	}
	
	protected function filtersConditions( $data, $prefix = ' AND ' ) {
		/* @var $conditions array */
		$conditions = array();
		
		if (!empty($data['filter_name'])) {
			$conditions[] = "`fgd`.`name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}
		
		if( ! empty( $data['except_ids'] ) ) {
			$conditions[] = "`fg`.`filter_group_id` NOT IN(" . implode( ',', $data['except_ids'] ) . ")";
		}
		
		if( ! empty( $data['only_ids'] ) ) {
			$conditions[] = "`fg`.`filter_group_id` IN(" . implode( ',', $data['only_ids'] ) . ")";
		}
		
		return $conditions ? $prefix . implode( ' AND ', $conditions ) : '';
	}
	
	protected function sqlFilters( $data, $select = '*' ) {
		$sql = "
			SELECT 
				" . $select . "
			FROM 
				`" . DB_PREFIX . "filter_group` AS `fg` 
			INNER JOIN 
				`" . DB_PREFIX . "filter_group_description` AS `fgd` 
			ON 
				`fg`.`filter_group_id` = `fgd`.`filter_group_id` AND `fgd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'
		";
		
		$sql .= $this->filtersConditions( $data );
		
		return $sql;
	}
	
	public function getFilters( $data = array() ) {
		$items = array();
		$sql = $this->sqlFilters( $data ) . $this->sqlLimit( $data );
		
		foreach( $this->db->query( $sql )->rows as $filter ) {
			$items[$filter['filter_group_id']] = $filter;
		}
		
		return $items;
	}
	
	public function getTotalFilters( $data = array() ) {
		$sql = $this->sqlFilters( $data, 'COUNT(*) AS `c`' );
		
		return $this->db->query( $sql )->row['c'];
	}
	
	public function getAttributesIds() {
		$ids = array();
		
		foreach( $this->getAttributes(array()) as $attribute ) {
			$ids[$attribute['attribute_id']] = $attribute['attribute_id'];
		}
		
		return $ids;
	}
	
	protected function attributesConditions( $data, $prefix = ' AND ' ) {
		/* @var $conditions array */
		$conditions = array();
		
		if (!empty($data['filter_name'])) {
			$conditions[] = "ad.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_attribute_group_id'])) {
			$conditions[] = "a.attribute_group_id = '" . $this->db->escape($data['filter_attribute_group_id']) . "'";
		}
		
		if( ! empty( $data['except_ids'] ) ) {
			$conditions[] = "a.attribute_id NOT IN(" . implode( ',', $data['except_ids'] ) . ")";
		}
		
		if( ! empty( $data['only_ids'] ) ) {
			$conditions[] = "a.attribute_id IN(" . implode( ',', $data['only_ids'] ) . ")";
		}
		
		return $conditions ? $prefix . implode( ' AND ', $conditions ) : '';
	}
	
	protected function attributesGroupConditions( $data, $prefix = ' AND ' ) {
		/* @var $conditions array */
		$conditions = array();
		
		if (!empty($data['filter_name'])) {
			$conditions[] = "agd.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_attribute_group_id'])) {
			$conditions[] = "ag.attribute_group_id = '" . $this->db->escape($data['filter_attribute_group_id']) . "'";
		}
		
		if( ! empty( $data['except_ids'] ) ) {
			$conditions[] = "ag.attribute_group_id NOT IN(" . implode( ',', $data['except_ids'] ) . ")";
		}
		
		if( ! empty( $data['only_ids'] ) ) {
			$conditions[] = "ag.attribute_group_id IN(" . implode( ',', $data['only_ids'] ) . ")";
		}
		
		return $conditions ? $prefix . implode( ' AND ', $conditions ) : '';
	}

	public function getTotalAttributes( $data = array() ) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute AS a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		
		$sql .= $this->attributesConditions( $data );
		
		$query = $this->db->query( $sql );

		return $query->row['total'];
	}
	
	public function getAttributes( $data = array() ) {
		$sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$sql .= $this->attributesConditions( $data );

		$sql .= " ORDER BY attribute_group, ad.name ASC";
		$sql .= $this->sqlLimit( $data );

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalAttributesGroup( $data = array() ) {
		$sql = "
			SELECT
				COUNT(*) AS total
			FROM
				" . DB_PREFIX . "attribute_group AS ag
			INNER JOIN
				" . DB_PREFIX . "attribute_group_description AS agd
			ON
				agd.attribute_group_id = ag.attribute_group_id
			WHERE
				agd.language_id = '" . (int)$this->config->get('config_language_id') . "'
		";
		
		$sql .= $this->attributesGroupConditions( $data );
		
		$query = $this->db->query( $sql );

		return $query->row['total'];
	}
	
	public function getAttributesGroup( $data = array() ) {
		$sql = "
			SELECT
				*
			FROM
				" . DB_PREFIX . "attribute_group AS ag
			INNER JOIN
				" . DB_PREFIX . "attribute_group_description AS agd
			ON
				agd.attribute_group_id = ag.attribute_group_id
			WHERE
				agd.language_id = '" . (int)$this->config->get('config_language_id') . "'
		";
		
		$sql .= $this->attributesGroupConditions( $data );

		$sql .= " ORDER BY agd.name ASC";
		$sql .= $this->sqlLimit( $data );

		$query = $this->db->query($sql);

		return $query->rows;
	}
	
	public function getCategoriesByIds( $categories_ids ) {
		return $this->db->query( "
			SELECT 
				`c`.`category_id`, `cd`.`name`,
				" . ( $this->hasFilters() ? "
				(
					SELECT 
						GROUP_CONCAT(`cd1`.`name` ORDER BY `level` SEPARATOR ' &gt; ') 
					FROM 
						`" . DB_PREFIX . "category_path` AS `cp` 
					LEFT JOIN 
						`" . DB_PREFIX . "category_description` AS `cd1` 
					ON 
						`cp`.`path_id` = `cd1`.`category_id` AND `cp`.`category_id` != `cp`.`path_id` 
					WHERE 
						`cp`.`category_id` = `c`.`category_id` AND `cd1`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' 
					GROUP BY 
						`cp`.`category_id`
				) AS `path`" : "CONCAT( '', '' ) AS `path`" ) . "
			FROM 
				`" . DB_PREFIX . "category` AS `c`
			LEFT JOIN
				`" . DB_PREFIX . "category_description` AS `cd`
			ON
				`c`.`category_id` = `cd`.`category_id` AND `cd`.`language_id` = " . $this->config->get( 'config_language_id' ) . "
			WHERE
				`c`.`category_id` IN(" . implode( ',', array_unique( $categories_ids ) ) . ")
		" )->rows;
	}
	
	public function getAllSettings() {
		$settings = array();
		
		foreach( $this->db->query( "SELECT * FROM `" . DB_PREFIX . "module` WHERE `code` = 'mega_filter' ORDER BY `module_id` ASC" )->rows as $row ) {
			$settings[$row['module_id']] = json_decode( $row['setting'], true );
		}
		
		return $settings;
	}
	
	public function getSettings( $idx ) {
		$query = $this->db->query( "SELECT * FROM `" . DB_PREFIX . "module` WHERE `code` = 'mega_filter' AND `module_id` = " . (int) $idx );
		
		if( $query->num_rows ) {
			return json_decode( $query->row['setting'], true );
		}
		
		return array();
	}
	
	public function removeSettings( $idx ) {
		$this->db->query( "DELETE FROM `" . DB_PREFIX . "module` WHERE `code` = 'mega_filter' AND `module_id` = " . (int) $idx );
	}
	
	public function saveSettings( $idx, $settings ) {
		if( empty( $settings['name'] ) ) {
			$settings['name'] = 'Mega Filter #' . ( $this->db->query( "SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "module` WHERE `code` = 'mega_filter'" )->row['total'] + 1 );
		}
		
		$idx = (int) $idx;
		
		if( $idx ) {
			$settings['_idx'] = $idx;
		}
		
		$json = $this->db->escape( json_encode( $settings ) );
		$config = $this->getSettings( $idx );
		
		if( $config || $config === null ) {
			$sql = "
				UPDATE
					`" . DB_PREFIX . "module`
				SET
					`name` = '" . $this->db->escape( $settings['name'] ) . "',
					`setting` = '" . $json . "'
				WHERE
					`module_id` = " . $idx . "
			";
			
			return $this->db->query( $sql );
		} else {
			$sql = "
				INSERT INTO
					`" . DB_PREFIX . "module`
				SET
					`name` = '" . $this->db->escape( $settings['name'] ) . "',
					`code` = 'mega_filter',
					`setting` = '" . $json . "'
			";
			
			$this->db->query( $sql );
			
			return $this->saveSettings($this->db->getLastId(), $settings);
		}
	}
	
	public function createUrlAliasTable() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mfilter_url_alias` (
				`mfilter_url_alias_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`path` TEXT NOT NULL,
				`mfp` TEXT NOT NULL,
				`alias` TEXT NOT NULL,
				`language_id` INT(11) NOT NULL,
				`store_id` INT(11) NOT NULL DEFAULT '0',
				`meta_title` VARCHAR(255) NULL,
				`meta_description` VARCHAR(255) NULL,
				`meta_keyword` VARCHAR(255) NULL,
				`description` TEXT NULL,
				`h1` VARCHAR(255) NULL,
				PRIMARY KEY(`mfilter_url_alias_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1
		");
	}
	
	public function createMfilterUrlReplacementTable() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mfilter_url_replacement` (
				`mfilter_url_replacement_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`type` VARCHAR(100) NOT NULL,
				`search` VARCHAR(100) NOT NULL,
				`replace` VARCHAR(100) NOT NULL,
				PRIMARY KEY(`mfilter_url_replacement_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1
		");
		
		$this->db->query( "DELETE FROM `" . DB_PREFIX . "mfilter_url_replacement` WHERE `type` = 'special_character'" );

		/* @var $no_latin_to_latin array */
		$no_latin_to_latin = array(
			'A' => array(
				'À','Á','Â','Ã','Ä','Å','Ā','Ă','Ą','Ǟ','Ǻ','Α','А',
			),
			'a' => array(
				'à','á','â','ã','ä','å','ā','ă','ą','ǟ','ǻ','α','а'
			),
			'B' => array(
				'Ḃ','Б'
			),
			'b' => array(
				'ḃ','б'
			),
			'C' => array(
				'Ć','Ç','Č','Ĉ','Ċ'
			),
			'c' => array(
				'ć','ç','č','ĉ','ċ'
			),
			'CH' => array(
				'Ч','Χ'
			),
			'ch' => array(
				'ч','χ'
			),
			'D' => array(
				'Ḑ','Ď','Ḋ','Đ','Ð','Д','Δ'
			),
			'd' => array(
				'ḑ','ď','ḋ','đ','ð','д','δ'
			),
			'DZ' => array(
				'Ǳ','Ǆ'
			),
			'Dz' => array(
				'ǲ','ǅ'
			),
			'dz' => array(
				'ǳ','ǆ'
			),
			'E' => array(
				'È','É','Ě','Ê','Ë','Ē','Ĕ','Ę','Ė','Ʒ','Ǯ','Е','Э','Ε', 'Ё'
			),
			'e' => array(
				'è','é','ě','ê','ë','ē','ĕ','ę','ė','ʒ','ǯ','е','э','ε', 'ё'
			),
			'F' => array(
				'Ḟ','Ф','Φ'
			),
			'f' => array(
				'ḟ','ƒ','ф','φ'
			),
			'fi' => array(
				'ﬁ'
			),
			'fl' => array(
				'ﬂ'
			),
			'G' => array(
				'Ǵ','Ģ','Ǧ','Ĝ','Ğ','Ġ','Ǥ','Г','Γ'
			),
			'g' => array(
				'ǵ','ģ','ǧ','ĝ','ğ','ġ','ǥ','г','γ'
			),
			'H' => array(
				'Ĥ','Ħ','Х'
			),
			'h' => array(
				'ĥ','ħ','х'
			),
			'ZH' => array(
				'Ж'
			),
			'zh' => array(
				'ж'
			),
			'I' => array(
				'Ì','Í','Î','Ĩ','Ï','Ī','Ĭ','Į','İ','И','Η','Ι'
			),
			'i' => array(
				'ì','í','î','ĩ','ï','ī','ĭ','į','ı','и','η','ι'
			),
			'IJ' => array(
				'Ĳ'
			),
			'ij' => array(
				'ĳ'
			),
			'J' => array(
				'Ĵ'
			),
			'j' => array(
				'ĵ'
			),
			'K' => array(
				'Ḱ','Ķ','Ǩ','К','Κ'
			),
			'k' => array(
				'ḱ','ķ','ǩ','к','κ'
			),
			'L' => array(
				'Ĺ','Ļ','Ľ','Ŀ','Ł','Л','Λ'
			),
			'l' => array(
				'ĺ','ļ','ľ','ŀ','ł','л','λ'
			),
			'LJ' => array(
				'Ǉ'
			),
			'Lj' => array(
				'ǈ'
			),
			'lj' => array(
				'ǉ'
			),
			'M' => array(
				'Ṁ','М','Μ'
			),
			'm' => array(
				'ṁ','м','μ'
			),
			'N' => array(
				'Ń','Ņ','Ň','Ñ','Ŋ','Н','Ν'
			),
			'n' => array(
				'ń','ņ','ň','ñ','ŉ','ŋ','н','ν'
			),
			'NJ' => array(
				'Ǌ'
			),
			'Nj' => array(
				'ǋ'
			),
			'nj' => array(
				'ǌ'
			),
			'O' => array(
				'Ò','Ó','Ô','Õ','Ö','Ō','Ŏ','Ø','Ő','Ǿ','О','Ο','Ω'
			),
			'o' => array(
				'ò','ó','ô','õ','ö','ō','ŏ','ø','ő','ǿ','о','ο','ω'
			),
			'OE' => array(
				'Œ'
			),
			'oe' => array(
				'œ'
			),
			'P' => array(
				'Ṗ','П','Π'
			),
			'p' => array(
				'ṗ','п','π'
			),
			'PS' => array(
				'Ŕ'
			),
			'ps' => array(
				'ŕ'
			),
			'R' => array(
				'Ŗ','Ř','Р','Ρ','Ψ'
			),
			'r' => array(
				'ŗ','ř','р','ρ','ψ'
			),
			'S' => array(
				'Ś','Ş','Š','Ŝ','Ṡ','С','Σ'
			),
			's' => array(
				'ś','ş','š','ŝ','ṡ','ſ','с','σ','ς'
			),
			'ss' => array(
				'ß'
			),
			'SH' => array(
				'Ш'
			),
			'sh' => array(
				'ш'
			),
			'SHCH' => array(
				'Щ'
			),
			'shch' => array(
				'щ'
			),
			'T' => array(
				'Ţ','Ť','Ṫ','Ŧ','Þ','Т','Τ'
			),
			't' => array(
				'ţ','ť','ṫ','ŧ','þ','т','τ'
			),
			'TS' => array(
				'Ц'
			),
			'ts' => array(
				'ц'
			),
			'TH' => array(
				'Θ'
			),
			'th' => array(
				'θ'
			),
			'U' => array(
				'Ù','Ú','Û','Ũ','Ü','Ů','Ū','Ŭ','Ų','Ű','У'
			),
			'u' => array(
				'ù','ú','û','ũ','ü','ů','ū','ŭ','ų','ű','у'
			),
			'V' => array(
				'В','Β'
			),
			'v' => array(
				'в','β'
			),
			'W' => array(
				'Ẁ','Ẃ','Ŵ','Ẅ'
			),
			'w' => array(
				'ẁ','ẃ','ŵ','ẅ'
			),
			'X' => array(
				'Ξ'
			),
			'x' => array(
				'ξ'
			),
			'Y' => array(
				'Ỳ','Ý','Ŷ','Ÿ','Й','Ы','Υ'
			),
			'y' => array(
				'ỳ','ý','ŷ','ÿ','й','ы','υ'
			),
			'YU' => array(
				'Ю'
			),
			'yu' => array(
				'ю'
			),
			'YA' => array(
				'Я'
			),
			'ya' => array(
				'я'
			),
			'Z' => array(
				'Ź','Ž','Ż','З','Ζ'
			),
			'z' => array(
				'ź','ž','ż','з','ζ'
			),
			'AE' => array(
				'Æ','Ǽ'
			),
			'ae' => array(
				'æ','ǽ'
			),
			'' => array(
				'ь', 'ъ', 'Ъ', 'Ь'
			)
		);
		
		foreach( $no_latin_to_latin as $replace => $no_latin ) {
			foreach( $no_latin as $search ) {
				$this->db->query( "INSERT INTO `" . DB_PREFIX . "mfilter_url_replacement` SET `type` = 'special_character', `search` = '" . $this->db->escape( $search ) . "', `replace` = '" . $this->db->escape( $replace ) . "'");
			}
		}
	}

	public function changeTypeOfValueFieldInSettingTable() {			
		// Change column 'value' in table 'setting' from 'text' to 'longtext'
		$query = $this->db->query( "
			SELECT 
				* 
			FROM 
				INFORMATION_SCHEMA.COLUMNS 
			WHERE 
				TABLE_SCHEMA LIKE '" . $this->db->escape( DB_DATABASE ) . "' AND
				TABLE_NAME LIKE '" . $this->db->escape( DB_PREFIX . 'setting' ) . "' AND 
				COLUMN_NAME LIKE 'value'
		");
			
		if( $query->num_rows && isset( $query->row['COLUMN_TYPE'] ) && strtolower( $query->row['COLUMN_TYPE'] ) != 'longtext' ) {
			$sql = array();
			$sql[] = 'ALTER TABLE `' . DB_PREFIX . 'setting` CHANGE `value` `value` LONGTEXT';
				
			if( ! empty( $query->row['CHARACTER_SET_NAME'] ) ) {
				$sql[] = 'CHARACTER SET ' . $query->row['CHARACTER_SET_NAME'];
			}
				
			if( ! empty( $query->row['COLLATION_NAME'] ) ) {
				$sql[] = 'COLLATE ' . $query->row['COLLATION_NAME'];
			}
				
			if( ! empty( $query->row['IS_NULLABLE'] ) && strtolower( $query->row['IS_NULLABLE'] ) == 'no' ) {
				$sql[] = 'NOT';
			}
				
			$sql[] = 'NULL';
			
			$this->db->query( implode( ' ', $sql ) );
		}
	}
	
	public function getCategories($data) {
		if( version_compare( VERSION, '1.5.5', '>=' ) ) {
			$sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR ' &gt; ') AS name, c.parent_id, c.sort_order FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c ON (cp.path_id = c.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (c.category_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";

			if( ! empty( $data['filter_name'] ) ) {
				$sql .= " AND cd2.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
			} 
				
			$sql .= " GROUP BY cp.category_id ORDER BY name";
		} else {
			$sql = "SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
				
			if( ! empty( $data['filter_name'] ) ) {
				$sql .= " AND LOWER(cd.name) LIKE '" . $this->db->escape( function_exists( 'mb_strtolower' ) ? mb_strtolower( $data['filter_name'], 'utf-8' ) : $data['filter_name'] ) . "%'";
			}
				
			$sql .= " GROUP BY c.category_id ORDER BY name";
		}

		$sql .= $this->sqlLimit( $data );
				
		$query = $this->db->query($sql);

		return $query->rows;
	}
}