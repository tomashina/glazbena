<?php

/**
 * Mega Filter
 * 
 * @license Commercial
 * @author info@ocdemo.eu
 */
		
if( class_exists( 'VQMod' ) ) {
	require_once VQMod::modCheck( modification( DIR_SYSTEM . 'library/mfilter_helper.php' ) );
	require_once VQMod::modCheck( modification( DIR_SYSTEM . 'library/mfilter_core.php' ) );
	require_once VQMod::modCheck( modification( DIR_SYSTEM . 'library/mfilter_module.php' ) );
} else {
	require_once modification( DIR_SYSTEM . 'library/mfilter_helper.php' );
	require_once modification( DIR_SYSTEM . 'library/mfilter_core.php' );
	require_once modification( DIR_SYSTEM . 'library/mfilter_module.php' );
}

class ModelExtensionModuleMegaFilter extends Model {	
	
	private static $_tmp_sort_parameters = NULL;
	
	private $_settings = array();
	
	private static $_seo = false;
	
	private static $_cache = array();
	
	private $_smp_langauge = null;
	
	////////////////////////////////////////////////////////////////////////////
	//
	// You can edit this section if you want to modify queries to database
	//
	
	/**
	 * Use this function if you want to modify raw query
	 * 
	 * {__mfp_select__}
	 * {__mfp_join__}
	 * {__mfp_conditions__}
	 * {__mfp_having_conditions__}
	 * {__mfp_group_by__}
	 * {__mfp_order_by__}
	 * {__mfp_limit__}
	 * 
	 * @param string $sql
	 * @param array $data
	 * @param string $type
	 * @return string
	 */	
	public function createQuery( $sql, $data, $type = '' ) {
		foreach( $data as $k => $v ) {
			if( is_array( $v ) ) {
				switch( $k ) {
					case '{__mfp_having_conditions__}' :
					case '{__mfp_conditions__}' : $v = implode( ' AND ', $v ); break;
					case '{__mfp_join__}' : $v = implode( ' ', $v ); break;
					default : $v = implode( ', ', $v );
				}
			}
			
			$sql = str_replace( $k, $v, $sql );
		}
		
		//str_replace( '`p`.`price`', "`p`.`price` * ( SELECT `value` FROM `" . DB_PREFIX . "currency` WHERE `p`.`currency_id` = `currency`.`currency_id` )", $sql );
		
		return $sql;
	}
	
	/**
	 * Use this function if you want to change the list of base conditions
	 * 
	 * @param array $conditions - list of base conditions in the current query
	 *		@possibility keys:
	 *			- status
	 *			- date_available
	 *			- manufacturer_id
	 *			- cat_id
	 *			- filter_id
	 *			- product_id
	 *			- search
	 * @return array
	 */
	public function prepareBaseConditions( $conditions ) {
		// example:
		// $conditions[] = "`p`.`product_id` > 0";
		
		return $conditions;
	}
	
	/**
	 * Use this function if you want to change the list of base join tables
	 * 
	 * @param array $join - list of tables which will be joinen to the current query
	 *		@possibility keys:
	 *			- p2s -> product_to_store
	 *			- pd -> product_description
	 *			- p2c -> product_to_category
	 *			- cp -> category_path
	 *			- pf -> product_filter
	 *			- p2mfv -> product_to_mfv (only if you have installed Mega Vehicle Filter)
	 *			- p2mfl -> product_to_mfl (only if you have installed Mega Level Filter)
	 * @param array $skip - list of tables which must be skipped
	 * @param bool $mainQuery - true only for main query
	 * @return array
	 */
	public function prepareBaseJoin( $join, $skip, $mainQuery ) {		
		return $join;
	}
	
	////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////
	
	public function om() {
		return null;
	}
	
	public function iom() {
		return false;
	}
	
	////////////////////////////////////////////////////////////////////////////

	public function __construct($registry) {
		parent::__construct( $registry );
		
		MFilter_Helper::create( $this );

		if( $this->config->get('smp_version') ) {
			if( ! empty( $this->session->data['language'] ) ) {
				$smp_language = $this->session->data['language'];
			} else if( ! empty( $_COOKIE['language'] ) ) {
				$smp_language = htmlspecialchars($_COOKIE['language'], ENT_COMPAT, 'UTF-8');
			} else {
				$smp_language = $this->config->get('config_language');
			}

			$smp_default_language = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$this->config->get('config_store_id') . "' AND `code` = 'config' AND `key` = 'config_language'");

			if( $smp_default_language->num_rows ) {
				$smp_default_language = $smp_default_language->row['value'];
			} else {
				$smp_default_language = $this->config->get('config_language');
			}

			if( $this->config->get( 'smp_add_default_language_code_to_url' ) || $smp_default_language != $smp_language ) {				
				$smp_language_code_alias = $this->config->get( 'smp_language_code_alias' );

				if( $smp_language_code_alias && ! empty( $smp_language_code_alias[$smp_language] ) ){
					$smp_language =  $smp_language_code_alias[$smp_language];
				}

				$this->_smp_langauge = $smp_language;
			}
		}
	}
	
	protected function sitemap() {
		/* @var $store_id int */
		$store_id = (int) $this->config->get('config_store_id');
		
		/* @var $language_id int */
		$language_id = (int) $this->config->get('config_language_id');
		
		/* @var $sitemap array */
		$sitemap = array();
		
		foreach( $this->db->query( "SELECT * FROM `" . DB_PREFIX . "mfilter_url_alias` WHERE `meta_title` IS NOT NULL AND `meta_title` != '' AND `language_id` = " . $language_id . " AND `store_id` = " . $store_id )->rows as $row ) {
			$url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
			$url .= '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/.\\');
			$url .= '/' . $row['path'] . '/' . $row['alias'];
			
			$sitemap[] = array(
				'name' => $row['meta_title'],
				'href'  => $url
			);
		}
		
		return $sitemap;
	}
	
	public function createGoogleSitemap() {
		/* @var $settings array */
		$settings = $this->seoSettings();
		
		if( ! $settings || empty( $settings['google_sitemap']['enabled'] ) ) {
			return '';
		}
		
		$settings = $settings['google_sitemap'];
		
		/* @var $out string */
		$out = '';
		
		/* @var $sitemap array */
		$sitemap = $this->sitemap();
		
		foreach( $sitemap as $row ) {
			$out .= '<url>';
			$out .= '<loc>' . $row['href'] . '</loc>';
			$out .= '<changefreq>' . $settings['changefreq'] . '</changefreq>';
			$out .= '<priority>' . $settings['priority'] . '</priority>';
			$out .= '</url>';
		}

		return $out;
	}
	
	public function createSitemap( $data ) {
		/* @var $settings array */
		$settings = $this->seoSettings();
		
		if( ! $settings || empty( $settings['sitemap']['enabled'] ) ) {
			return $data;
		}
		
		/* @var $mfilter_url_aliases array */
		if( null != ( $mfilter_url_aliases = $this->sitemap() ) ) {
			foreach( $mfilter_url_aliases as $row ) {
				$row['children'] = array();
				
				$data['categories'][] = $row;
			}
		}
		
		return $data;
	}
	
	public function setSettings( $settings ) {
		$this->_settings = $settings;
	}
	
	public function rewrite( $url = null, $url_data = null ) {		
		/* @var $mfp_url_param string */
		$mfp_url_param = $this->mfpUrlParam();
		
		if( isset( $url_data[$mfp_url_param] ) ) {
			/* @var $mfp_seo_sep string */
			$mfp_seo_sep = $this->mfpSeoSep();
		
			if( isset( $url_data['route'] ) && in_array( $url_data['route'], array( 'product/product' ) ) ) {
				return array( $url, $url_data );
			}
			
			$mfilterConfig = $this->seoSettings();
			
			if( ! empty( $mfilterConfig['enabled'] ) || ! empty( $mfilterConfig['aliases_enabled'] ) ) {
				/* @var $path_alias string */
				$path_alias = parse_url( $url );
				$path_alias = trim( isset( $path_alias['path'] ) ? $path_alias['path'] : '', '/' );
				
				if( ! is_null( $this->_smp_langauge ) ) {
					$path_alias = $this->_smp_langauge . '/' . $path_alias;
				}
				
				/* @var $mfilter_path string */
				$mfilter_path = null;
				
				if( isset( $_POST['mfilterPath'] ) ) {
					$mfilter_path = $_POST['mfilterPath'];
				} else if( isset( $_GET['mfilterPath'] ) ) {
					$mfilter_path = $_GET['mfilterPath'];
				}
				
				/* @var $mfp_alias string */
				$mfp_alias = $url_data[$mfp_url_param];
				
				//if( isset( $this->request->server['REQUEST_METHOD'] ) && $this->request->server['REQUEST_METHOD'] == 'POST' ) {
					if( class_exists( 'MegaFilterCore' ) ) {
						$mfp_params = MegaFilterCore::__parseParams( $mfp_alias );

						if( ! empty( $mfp_params[1] ) && ! empty( $mfp_params[2] ) ) {
							$mfp_temp = array();

							foreach( $mfp_params[1] as $k => $v ) {
								$mfp_params[2][$k] = explode( ',', $mfp_params[2][$k] );
								$mfp_params[2][$k] = array_map( 'urldecode', $mfp_params[2][$k] );
								$mfp_params[2][$k] = implode( ',', $mfp_params[2][$k] );

								$mfp_temp[] = $mfp_params[1][$k] . '[' . $mfp_params[2][$k] . ']';
							}

							$mfp_alias = implode( ',', $mfp_temp );
						}
					}
				//}
				
				if( $mfilter_path !== null ) {
					$mfp_alias = preg_replace( '/(,?)path\['.preg_quote($mfilter_path,'/').'\](,?)/', '$1$2', $mfp_alias );
					$mfp_alias = trim( str_replace( '],,', '],', $mfp_alias ), ',' );
				}
				
				/* @var $sql_alias string */
				$sql_alias = "
					SELECT
						*
					FROM
						`" . DB_PREFIX . "mfilter_url_alias`
					WHERE
						( 
							`path` = '' OR 
							`path` IS NULL OR 
							`path` = '" . $this->db->escape( $path_alias ) . "' OR 
							`path` = '" . $this->db->escape( $this->config->get('config_language') . '/' . $path_alias ) . "'
						) 
							AND
						`mfp` = '" . $this->db->escape( $mfp_alias ) . "' AND
						`language_id` = " . (int) $this->config->get('config_language_id') . " AND
						`store_id` = " . (int) $this->config->get('config_store_id') . "
				";
				
				if( ! isset( self::$_cache[__METHOD__][$sql_alias] ) ) {
					self::$_cache[__METHOD__][$sql_alias] = $this->db->query( $sql_alias )->row;
				}
				
				if( self::$_cache[__METHOD__][$sql_alias] ) {
					$url .= '/' . self::$_cache[__METHOD__][$sql_alias]['alias'];

					if( ! empty( $mfilterConfig['add_slash_at_the_end'] ) ) {
						$url .= '/';
					}
					
					unset( $url_data[$mfp_url_param] );
				} else if( ! empty( $mfilterConfig['enabled'] ) ) {
					if( $mfilter_path !== null ) {
						$url_data[$mfp_url_param] = preg_replace( '/(,?)path\['.preg_quote($mfilter_path,'/').'\](,?)/', '$1$2', $url_data[$mfp_url_param] );
						$url_data[$mfp_url_param] = trim( str_replace( '],,', '],', $url_data[$mfp_url_param] ), ',' );
					}
				
					preg_match_all( '/([a-z0-9\-_]+)\[([^\]]*)\]/', $url_data[$mfp_url_param], $matches );

					if( isset( $matches[0] ) && isset( $matches[1] ) && isset( $matches[2] ) ) {
						$mfp = array();
							//print_r($url_data);die();
						foreach( $matches[0] as $k => $match ) {
							if( ! isset( $matches[1][$k] ) || ! isset( $matches[2][$k] ) || $matches[1][$k] === '' ) {
								continue;
							}

							$key = $matches[1][$k];
							$val = $matches[2][$k];

							$mfp[] = $key . ',' . $val;
						}

						if( $mfp ) {
							$url .= '/' . $mfp_seo_sep . '/' . implode( '/', $mfp );
							} else if( $url_data[$mfp_url_param] ) {
							$url .= '/' . $mfp_seo_sep . '/' . $url_data[$mfp_url_param];
						}

						if( ! empty( $mfilterConfig['add_slash_at_the_end'] ) ) {
							$url .= '/';
						}

						unset( $url_data[$mfp_url_param] );
					}
				}
			}
		}
		
		return array( $url, $url_data );
	}
	
	public function mfpSeoSep() {
		if( null != ( $mfilter_seo_sep = $this->config->get('mfilter_seo_sep') ) ) {
			return isset( $mfilter_seo_sep[$this->config->get('config_language_id')] ) ? $mfilter_seo_sep[$this->config->get('config_language_id')] : 'mfp';
		}
		
		return 'mfp';
	}
	
	public function mfpUrlParam() {
		return $this->config->get('mfilter_url_param')?$this->config->get('mfilter_url_param'):'mfp';
	}
	
	public function removeSeoMfpFromUrl( $url, $remove_also_no_seo = false ) {
		/* @var $u array */
		$u = parse_url( $url );
		
		/* @var $parts array */
		$parts = ! empty( $u['path'] ) ? explode( '/', $u['path'] ) : array();
		
		/* @var $last_part string */
		$last_part = array_pop( $parts );
		
		/* @var $last_part_was_empty bool */
		$last_part_was_empty = false;
		
		while( $last_part === '' ) {
			$last_part_was_empty = true;
			$last_part = array_pop( $parts );
		}
		
		/* @var $query stdClass */
		$query = $this->db->query( "
			SELECT 
				* 
			FROM 
				`" . DB_PREFIX . "mfilter_url_alias` 
			WHERE 
				`alias` = '" . $this->db->escape( $last_part ) . "' AND
				`store_id` = " . (int) $this->config->get('config_store_id') . "
		");
		
		if( ! $query->num_rows ) {
			$parts[] = $last_part;
		}
		
		if( $last_part_was_empty ) {
			$parts[] = '';
		}
		
		/* @var $url string */
		$url = '';
		
		$url .= empty( $u['scheme'] ) ? '' : $u['scheme'];
		$url .= empty( $u['host'] ) ? '' : '://' . $u['host'];
		$url .= empty( $u['port'] ) ? '' : ':' . $u['port'];
		$url .= implode( '/', $parts );
		$url .= empty( $u['query'] ) ? '' : '?' . $u['query'];
		
		if( ! $remove_also_no_seo ) {
			return $url;
		}
		
		return $this->removeMfpFromUrl( $url );
	}
	
	public function removeMfpFromUrl( $url ) {
		/* @var $mfp_url_param string */
		$mfp_url_param = $this->mfpUrlParam();
		
		if( false !== ( $mfpPos = mb_strpos( $url, '?'.$mfp_url_param.'=', 0, 'utf8' ) ) ) {
			$before = $mfpPos ? mb_substr( $url, 0, $mfpPos, 'utf8' ) : '';
			$after	= '';
				
			if( false !== ( $pos = mb_strpos( $url, '&', $mfpPos+1, 'utf8' ) ) ) {
				$after = '?' . mb_substr( $url, $pos+1, NULL, 'utf8' );
			}
				
			$url = $before . $after;
		} else if( false !== ( $mfpPos = mb_strpos( $url, '&'.$mfp_url_param.'=', 0, 'utf8' ) ) ) {
			$before = $mfpPos ? mb_substr( $url, 0, $mfpPos, 'utf8' ) : '';
			$after	= '';
				
			if( false !== ( $pos = mb_strpos( $url, '&', $mfpPos+1, 'utf8' ) ) ) {
				$after = '?' . mb_substr( $url, $pos+1, NULL, 'utf8' );
			}
				
			$url = $before . $after;
		} else if( false !== ( $mfpPos = mb_strpos( $url, $mfp_url_param.',', 0, 'utf8' ) ) || false !== ( $mfpPos = mb_strpos( $url, '/' . $this->mfpSeoSep() . '/', 0, 'utf8' ) ) ) {
			$before = $mfpPos ? mb_substr( $url, 0, $mfpPos, 'utf8' ) : '';
			$after	= '';
			
			if( false !== ( $pos = mb_strpos( $url, '?', $mfpPos+1, 'utf8' ) ) ) {
				$after = mb_substr( $url, $pos, NULL, 'utf8' );
			} else if( false !== ( $pos = mb_strpos( $url, '&', $mfpPos+1, 'utf8' ) ) ) {
				$after = '?' . mb_substr( $url, $pos+1, NULL, 'utf8' );
			} else if( false !== ( $pos = mb_strpos( $url, '/', $mfpPos+1, 'utf8' ) ) ) {
				if( $mfpPos + mb_strlen( $this->mfpSeoSep(), 'utf8' ) + 1 > $pos ) {
					$after = mb_substr( $url, $mfpPos, $pos, 'utf8' );
				}
			}
				
			$url = $before . $after;
		}
	
		return $url;
	}
	
	public function prepareData( $data ) {
		if( ! empty( $data['breadcrumbs'] ) && $this->rget($this->mfpUrlParam()) ) {
			foreach( $data['breadcrumbs'] as $mfK => $mfBreadcrumb ) {
				$data['breadcrumbs'][$mfK]['href'] = $this->removeMfpFromUrl( $data['breadcrumbs'][$mfK]['href'] );
			}
		}
				
		if( $this->rget('mfilterAjax') !== null && class_exists( 'MegaFilterCore' ) ) {
			$calculate_number_of_products = false;
			$settings = $this->rget('mfilterIdx') !== null ? $this->getModuleSettings( $this->rget('mfilterIdx') ) : array();

			if( ! empty( $settings['configuration'] ) ) {
				$calculate_number_of_products = ! empty( $settings['configuration']['calculate_number_of_products'] );
			} else {
				$settings = $this->config->get('mega_filter_settings');
				$calculate_number_of_products = ! empty( $settings['calculate_number_of_products'] );
			}

			$seo_settings = $this->seoSettings();
			$baseTypes	= array( 'stock_status', 'manufacturers', 'rating', 'attributes', 'price', 'options', 'filters' );

			if( $this->rget('mfilterBTypes') !== null ) {
				$baseTypes = explode( ',', $this->rget('mfilterBTypes') );
			}

			if( 
				! empty( $seo_settings['enabled'] ) || 
				! empty( $seo_settings['aliases_enabled'] ) ||
				$calculate_number_of_products || 
				in_array( 'categories:tree', $baseTypes ) || 
				in_array( 'vehicles', $baseTypes ) ||
				in_array( 'levels', $baseTypes )
			) {
				if( ! $calculate_number_of_products ) {
					$baseTypesCopy = $baseTypes;
					$baseTypes = array();

					if( in_array( 'categories:tree', $baseTypesCopy ) ) {
						$baseTypes[] = 'categories:tree';
					}

					if( in_array( 'vehicles', $baseTypesCopy ) ) {
						$baseTypes[] = 'vehicles';
					}

					if( in_array( 'levels', $baseTypesCopy ) ) {
						$baseTypes[] = 'levels';
					}
				}

				$idx = 0;

				if( $this->rget('mfilterIdx') !== null ) {
					$idx = (int) $this->rget('mfilterIdx');
				}

				$data['mfilter_json'] = MegaFilterCore::newInstance( $this, NULL, array( 'mfp_overwrite_path' => true ) )->getJsonData($baseTypes, $idx);
			}
			
			if( empty( $seo_settings['enabled'] ) || empty( $seo_settings['use_post_ajax_requests'] ) || empty( $seo_settings['return_full_ajax_response'] ) ) {
				$data['header'] = $data['column_left'] = $data['column_right'] = $data['content_top'] = $data['content_bottom'] = $data['footer'] = '';
			}
			
			if( ! empty( $seo_settings['enabled'] ) && empty( $seo_settings['use_post_ajax_requests'] ) && ! empty( $seo_settings['header_noindex'] ) ) {
				$this->response->addHeader( 'X-Robots-Tag: noindex' );
			}
		}
		
		if( isset( $data['heading_title'] ) && ! isset( $data['mfilter_json']['seo_data']['meta_title'] ) ) {
			$data['mfilter_json']['seo_data']['meta_title'] = $this->document->getTitle();
		}
		
		if( class_exists( 'ControllerExtensionModuleMegaFilter' ) ) {
			if( ControllerExtensionModuleMegaFilter::seo( $this ) ) {
				if( ControllerExtensionModuleMegaFilter::$_seo['h1'] ) {
					$data['heading_title'] = ControllerExtensionModuleMegaFilter::$_seo['h1'];
				}
				
				$data['description'] = html_entity_decode((string)ControllerExtensionModuleMegaFilter::$_seo['description'], ENT_QUOTES, 'UTF-8');
			} else {
				if( isset( $data['mfilter_json']['seo_data']['h1'] ) ) {
					$data['heading_title'] = $data['mfilter_json']['seo_data']['h1'];
				}
				
				if( isset( $data['mfilter_json']['seo_data']['description'] ) ) {
					$data['description'] = $data['mfilter_json']['seo_data']['description'];
				}
			}
			
			self::$_seo = true;
		}
		
		if( isset( $data['mfilter_json'] ) ) {
			$data['header'] .= '<div id="mfilter-json" style="display:none">' . base64_encode( json_encode( $data['mfilter_json'] ) ) . '</div>';
		}
		
		$data['content_top'] .= '<div id="mfilter-content-container">';
		$data['content_bottom'] = '</div>' . $data['content_bottom'];
		
		return $data;
	}
	
	public function getModuleSettings( $idx ) {
		if( isset( self::$_cache[__FUNCTION__][$idx] ) ) {
			return self::$_cache[__FUNCTION__][$idx];
		}
		
		self::$_cache[__FUNCTION__][$idx] = array();
		
		$query = $this->db->query( "SELECT * FROM `" . DB_PREFIX . "module` WHERE `code` = 'mega_filter' AND `module_id` = " . (int) $idx );
		
		if( $query->num_rows ) {
			self::$_cache[__FUNCTION__][$idx] = json_decode( $query->row['setting'], true );
		}
		
		return self::$_cache[__FUNCTION__][$idx];
	}
	
	protected function settings() {
		if( ! $this->_settings ) {
			$this->_settings = $this->config->get('mega_filter_settings');
		}
		
		return $this->_settings;
	}
	
	protected function seoSettings() {
		return (array) $this->config->get( 'mega_filter_seo' );
	}
	
	protected function isSeoEnabled() {
		$settings = $this->seoSettings();
		
		return ! empty( $settings['enabled'] );
	}
	
	private function hasMfilterPlus() {
		if( ! file_exists( DIR_SYSTEM . 'library/mfilter_plus.php' ) )
			return false;
		
		return true;
	}
	
	/**
	 * Get list of statuses
	 * 
	 * @return array 
	 */
	public function getStockStatuses( $core ) {
		$list = array();
		
		foreach( $this->db->query("
			SELECT
				*
			FROM
				`" . DB_PREFIX . "stock_status`
			WHERE
				`language_id` = " . (int) $this->config->get('config_language_id') . "
		")->rows as $row ) {
			$list[] = $core->addParamToCurrentUrl( array(
				'key' => $row['stock_status_id'],
				'value' => $row['stock_status_id'],
				'name' => $row['name'],
			), 'stock_status', $row['stock_status_id'] );
		}
		
		return $list;
	}
	
	private function rget( $name ) {
		if( isset( $this->request->post[$name] ) ) {
			return $this->request->post[$name];
		}
		
		if( isset( $this->request->get[$name] ) ) {
			return $this->request->get[$name];
		}
		
		return null;
	}
	
	public function createBaseQuery( array $parameters, $core = null ) {
		$sql	= "
			SELECT
				{__mfp_select__}
			FROM
				`" . DB_PREFIX . "product` AS `p`
			{__mfp_join__}
			{__mfp_conditions__}
			{__mfp_group_by__}
			{__mfp_order_by__}
			{__mfp_limit__}
		";
		
		if( $core === null ) {
			$core = MegaFilterCore::newInstance( $this, NULL, array(), $this->settings() );
		}
		
		$data			= MegaFilterCore::_getData( $this );
		$join			= '';
		$conditions		= $core->_baseConditions( array(), true );
		$skipBaseJoin	= array();
		
		if( isset( $parameters['skipBaseJoin'] ) ) {
			$skipBaseJoin = $parameters['skipBaseJoin'];
			
			unset( $parameters['skipBaseJoin'] );
		}
		
		if( in_array( $core->route(), MegaFilterCore::$_specialRoute ) ) {
			$conditions[] = '(' . $core->specialCol( '' ) . ') IS NOT NULL';
		}
		
		if( $this->rget('mfp_path') || ! empty( $data['filter_name'] ) || ! empty( $data['filter_category_id'] ) || ! empty( $data['filter_manufacturer_id'] ) || ! empty( $conditions['search'] ) ) {
			$join .= ' ' . $core->_baseJoin( $skipBaseJoin );
		}
		
		if( isset( $parameters['conditions'] ) ) {
			foreach( $parameters['conditions'] as $k => $v ) {
				if( is_string( $k ) ) {
					$conditions[$k] = $v;
				} else {
					$conditions[] = $v;
				}
			}
			
			unset( $parameters['conditions'] );
		}
		
		$conditions	= $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		
		$search = array(
			'{__mfp_join__}' => $join, 
			'{__mfp_select__}' => '', 
			'{__mfp_group_by__}' => '', 
			'{__mfp_order_by__}' => '', 
			'{__mfp_conditions__}' => $conditions,
			'{__mfp_limit__}' => ''
		);
		
		foreach( $parameters as $k => $v ) {
			$search[$k] .= $v;
		}
		
		return $this->createQuery( $sql, $search );
	}
	
	public function getTags( $core = null ) {
		$list = array();
		$sql = $this->createBaseQuery(array(
			'{__mfp_select__}' => 'DISTINCT `t`.`tag`, `mfilter_tag_id`',
			'{__mfp_join__}' => "INNER JOIN `" . DB_PREFIX . "mfilter_tags` AS `t` ON FIND_IN_SET( `t`.`mfilter_tag_id`, `p`.`mfilter_tags` ) AND `t`.`language_id` = " . (int) $this->config->get( 'config_language_id' ),
			'{__mfp_order_by__}' => 'ORDER BY `t`.`tag` ASC',
			'{__mfp_group_by__}' => 'GROUP BY MD5(`t`.`tag`)',
		), $core);
		
		foreach( $this->db->query( $sql )->rows as $row ) {
			$list[$row['mfilter_tag_id']] = $core->addParamToCurrentUrl( array(
				'name' => $row['tag'],
				'value' => $row['tag'],
				'key' => $row['mfilter_tag_id'],
			), 'tags', $row['tag'] );
		}
		
		return $list;
	}
	
	public function getOptionsByType( $type, $core = null ) {
		$list	= array();
		$unit	= '';
		$ftmp	= in_array( $type, array( 'weight', 'length', 'width', 'height' ) ) ?
			"ROUND( `p`.`" . $type . "` / ( SELECT `value` FROM `" . DB_PREFIX . ( $type == 'weight' ? 'weight' : 'length' ) . "_class` WHERE `" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` = `p`.`" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` LIMIT 1 ), 10 ) AS `field`" :
			"`" . $type . "` AS `field`";		
		
		if( in_array( $type, array( 'weight', 'length', 'width', 'height' ) ) ) {
			$ftmp .= ", ROUND( `p`.`" . $type . "` / ( SELECT `value` FROM `" . DB_PREFIX . ( $type == 'weight' ? 'weight' : 'length' ) . "_class` WHERE `" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` = `p`.`" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` LIMIT 1 ), 2 ) AS `field_name`";
			
			$unit = $this->db->query( "
				SELECT 
					`unit` 
				FROM 
					`" . DB_PREFIX . ( $type == 'weight' ? 'weight' : 'length' ) . "_class` AS `c` 
				INNER JOIN 
					`" . DB_PREFIX . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_description` AS `cd` ON `c`.`" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` = `cd`.`" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` AND `cd`.`language_id` = " . (int) $this->config->get( 'config_language_id' ) . "
				WHERE
					`c`.`value` = 1
				LIMIT
					1
			");
			
			$unit = $unit->num_rows ? $unit->row['unit'] : '';
		}
		
		$sql	= $this->createBaseQuery(array(
			'{__mfp_select__}' => $ftmp,
			'{__mfp_group_by__}' => 'GROUP BY `field`',
			'{__mfp_order_by__}' => in_array( $type, array( 'width', 'height', 'length', 'weight' ) ) ? 'ORDER BY `field_name` ASC' : 'ORDER BY `field` ASC',
			'conditions' => in_array( $type, array( 'width', 'height', 'length', 'weight' ) ) ? array(
				"`p`.`" . $type . "` > 0"
			) : array()
		), $core);
		
		foreach( $this->db->query( $sql )->rows as $row ) {			
			switch( $type ) {
				case 'length' :
				case 'width' :
				case 'height' : 
				case 'weight' : {
					$row['field'] = round( $row['field'], 10 );
					
					break;
				}
			}
			
			if( $row['field'] === '' ) continue;
			
			$k = md5( $row['field'] );
			
			$list[$k] = $core->addParamToCurrentUrl( array(
				'name' => ( isset( $row['field_name'] ) ? $row['field_name'] : $row['field'] ) . ( $unit ? ' ' . $unit : '' ),
				'unit' => $unit,
				'value' => $row['field'],
				'key' => $k,
			), $type, $row['field'] );
		}
		
		return $list;
	}
	
	public function getManufacturers( $core = null ) {
		$sql = "
			SELECT 
				`m`.*
			FROM 
				`" . DB_PREFIX . "manufacturer` AS `m` 
			INNER JOIN 
				`" . DB_PREFIX . "manufacturer_to_store` AS `m2s` 
			ON 
				`m`.`manufacturer_id` = `m2s`.`manufacturer_id`
			{join}
			{conditions}
			{group}
			ORDER BY 
				`m`.`sort_order` ASC,
				`m`.`name` ASC
		";
		
		if( $core === null ) {
			$core = MegaFilterCore::newInstance( $this, NULL, array(), $this->settings() );
		}
		
		$data		= MegaFilterCore::_getData( $this );
		$group		= array(
			'`m`.`manufacturer_id`'
		);
		$conditions	= $core->_baseConditions( array(), true );
		$join		= "
			INNER JOIN `" . DB_PREFIX . "product` AS `p` ON `p`.`manufacturer_id` = `m`.`manufacturer_id`
			INNER JOIN `" . DB_PREFIX . "product_to_store` AS `p2s` ON `p`.`product_id` = `p2s`.`product_id`
		";
		
		$conditions[] = "`m2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "'";
		$conditions[] = "`p2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "'";
		
		if( in_array( $core->route(), MegaFilterCore::$_specialRoute ) ) {
			$conditions[] = '(' . $core->specialCol( '' ) . ') IS NOT NULL';
		}
		
		if( $this->rget('mfp_path') || ! empty( $data['filter_name'] ) || ! empty( $data['filter_category_id'] ) || ! empty( $data['filter_manufacturer_id'] ) || ! empty( $conditions['search'] ) ) {
			$join .= ' ' . $core->_baseJoin(array('p2s'));
		}
		
		$group		= $group ? 'GROUP BY ' . implode( ',', $group ) : '';
		$conditions	= $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		
		$sql			= str_replace( array( '{join}', '{conditions}', '{group}' ), array( $join, $conditions, $group ), $sql );
		$manufacturers	= array();
		$seo_keywords	= array();
		$query			= $this->db->query( $sql );
		
		if( $this->isSeoEnabled() ) {
			$manufacturer_queries = array();
			
			foreach( $query->rows as $row ) {
				$manufacturer_queries[] = "'manufacturer_id=" . $row['manufacturer_id'] . "'";
			}
			
			if( $manufacturer_queries ) {
				foreach( $this->db->query( "
					SELECT 
						*
					FROM 
						`" . DB_PREFIX . "seo_url` 
					WHERE 
						`query` IN(" . implode(',', $manufacturer_queries ) . ") AND
						`store_id` = " . (int) $this->config->get( 'config_store_id' ) . " AND 
						`language_id` = '" . (int) $this->config->get('config_language_id') . "'"
				)->rows as $row ) {
					$seo_keywords[$row['query']] = $row['keyword'];
				}
			}
			
			unset( $manufacturer_queries );
		}
		
		foreach( $query->rows as $row ) {
			$value = $this->isSeoEnabled() && isset( $seo_keywords['manufacturer_id='.$row['manufacturer_id']] ) ? $seo_keywords['manufacturer_id='.$row['manufacturer_id']] : $row['manufacturer_id'];
			
			$manufacturers[] = $core->addParamToCurrentUrl( array(
				'key'	=> $row['manufacturer_id'],
				'value'	=> $value,
				'name'	=> $row['name'],
				'image'	=> empty( $row['image'] ) ? '' : $row['image'],
			), 'manufacturers', $value );
		}
		
		return $manufacturers;
	}
	
	public function getDiscountsSql( $core = null ) {		
		if( $core === null ) {
			$core = MegaFilterCore::newInstance( $this, NULL, array(), $this->settings() );
		}
		
		$sql = "
			SELECT 
				ROUND( 100 - ( ( ( " . $core->priceCol('') . " ) / `p`.`price` ) * 100 ) ) AS `discount`
			FROM 
				`" . DB_PREFIX . "product` AS `p` 
			{join}
			{conditions}
			{group}
			HAVING
				`discount` > 0
			ORDER BY 
				`discount` ASC
		";
		
		$data		= MegaFilterCore::_getData( $this );
		$join		= '';
		$group		= array();
		$conditions	= $core->_baseConditions( array(
			'`p`.`price` > 0',
		), true );
		$join		= '';
		
		if( in_array( $core->route(), MegaFilterCore::$_specialRoute ) ) {
			$conditions[] = '(' . $core->specialCol( '' ) . ') IS NOT NULL';
		}
		
		if( $this->rget('mfp_path') || ! empty( $data['filter_name'] ) || ! empty( $data['filter_category_id'] ) || ! empty( $data['filter_manufacturer_id'] ) || ! empty( $conditions['search'] ) ) {
			$join .= ' ' . $core->_baseJoin();
		}
		
		if( $join ) {
			$group[] = '`p`.`product_id`';
		}
		
		$group		= $group ? 'GROUP BY ' . implode( ',', $group ) : '';
		$conditions	= $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		
		return str_replace( array( '{join}', '{conditions}', '{group}' ), array( $join, $conditions, $group ), $sql );
	}
	
	public function getDiscounts( $core = null ) {
		$sql = $this->getDiscountsSql( $core );		
		$discounts	= array();
		
		foreach( $this->db->query( $sql )->rows as $row ) {
			$discounts[] = $core->addParamToCurrentUrl( array(
				'key'	=> $row['discount'],
				'value'	=> $row['discount'],
				'name'	=> $row['discount'] . '%',
				'image'	=> '',
			), 'discounts', $row['discount'] );
		}
		
		return $discounts;
	}
	
	private function stockStatusIsEnabled( $idx ) {		
		$module		= $this->getModuleSettings( $idx );
		$attribs	= $idx !== NULL && isset( $module['base_attribs'] ) ? $module['base_attribs'] : $this->_settings['attribs'];
		
		return empty( $attribs['stock_status']['enabled'] ) ? false : true;
	}
	
	private function calculateNumberOfProducts( $idx ) {
		$module	= $this->getModuleSettings( $idx );
		$config	= $idx !== NULL && isset( $module['configuration'] ) ? $module['configuration'] : $this->_settings;
		
		return ! empty( $config['calculate_number_of_products'] );
	}
	
	private function showNumberOfProducts( $idx ) {
		$module	= $this->getModuleSettings( $idx );
		$config	= $idx !== NULL && isset( $module['configuration'] ) ? $module['configuration'] : $this->_settings;
		
		return ! empty( $config['show_number_of_products'] );
	}
	
	/**
	 * Create a list of filters
	 */
	public function createFilters( $core, $idx, array $config ) {
		/* @var $hasMfilterPlus bool */
		$hasMfilterPlus = $this->hasMfilterPlus();
		
		$sql = "
			SELECT
				`fgd`.`name` AS `gname`,
				`fgd`.`mf_tooltip` AS `tooltip`,
				`f`.`filter_group_id`,
				`f`.`filter_id`,
				`fd`.`name`" . (  $this->isSeoEnabled() && ! $hasMfilterPlus ? ",
				LOWER(REPLACE(REPLACE(REPLACE(TRIM(`fd`.`name`), '\r', ''), '\n', ''), ' ', '-')) AS `keyword`" : '' ) . "
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_to_store` AS `p2s`
			ON
				`p`.`product_id` = `p2s`.`product_id` AND `p2s`.`store_id` = " . (int) $this->config->get( 'config_store_id' ) . "
			INNER JOIN
				`" . DB_PREFIX . "product_filter` AS `pf`
			ON
				`p`.`product_id` = `pf`.`product_id`
			INNER JOIN
				`" . DB_PREFIX . "filter` AS `f`
			ON
				`pf`.`filter_id` = `f`.`filter_id`
			INNER JOIN
				`" . DB_PREFIX . "filter_description` AS `fd`
			ON
				`pf`.`filter_id` = `fd`.`filter_id` AND `fd`.`language_id` = " . (int) $this->config->get('config_language_id') . "
			INNER JOIN
				`" . DB_PREFIX . "filter_group_description` AS `fgd`
			ON
				`f`.`filter_group_id` = `fgd`.`filter_group_id` AND `fgd`.`language_id` = " . (int) $this->config->get('config_language_id') . "
			{join}
			WHERE
				{conditions}
			GROUP BY
				`f`.`filter_group_id`, `f`.`filter_id`
			ORDER BY
				`f`.`sort_order`, `fd`.`name`
		";
		
		$filters	= array();
		$conditions	= array();
		$sort		= array();
		
		if( ! empty( $this->_settings['try_to_boost_speed'] ) ) {
			if( null != ( $filter_ids = $this->boostCreateFilters( $core, $idx, $config ) ) ) {
				$conditions[] = "`f`.`filter_id` IN(" . implode(',', $filter_ids) . ")";
			} else {
				return $filters;
			}
		}
		
		$rows = $this->db->query( $this->prepareFiltersQuery( $sql, $core, $idx, $config, $conditions ) )->rows;
		
		$keywords = array();
		
		if( $hasMfilterPlus && $this->isSeoEnabled() ) {
			$ids = array();

			foreach( $rows as $filter ) {
				$ids[] = $filter['filter_id'];
			}

			if( ! $ids ) {
				return $filters;
			}
			
			foreach( $this->db->query( "SELECT * FROM `" . DB_PREFIX . "mfilter_values` WHERE `type` = 'filter' AND `value_id` IN(" . implode(',', $ids) . ") AND ( `language_id` IS NULL OR `language_id` = " . (int) $this->config->get('config_language_id') . " )" )->rows as $row ) {
				$keywords[$row['value_id']] = $row['seo_value'];
			}
		}
		
		foreach( $rows as $filter ) {
			/* @var $item array */
			$item = isset( $config['default'] ) ? $config['default'] : array();
			
			if( isset( $config[$filter['filter_group_id']] ) ) {
				$item = $config[$filter['filter_group_id']];
			}
			
			if( empty( $item['enabled'] ) ) continue;
			
			if( ! isset( $item['sort_order'] ) ) {
				$item['sort_order'] = 0;
			}
			
			$images = (array) $this->config->get('mega_filter_fi_img_' . $filter['filter_group_id'] . '_' . $this->config->get('config_language_id'));
			
			if( ! isset( $filters['f_'.$filter['filter_group_id']] ) ) {
				$filters['f_'.$filter['filter_group_id']] = array(
					'id'					=> $filter['filter_group_id'],
					'type'					=> $item['type'],
					'base_type'				=> 'filter',
					'sort_order'			=> $item['sort_order'],
					'name'					=> $filter['gname'],
					'tooltip'				=> $filter['tooltip'],
					'seo_name'				=> $filter['filter_group_id'] . 'f-' . $this->clear( $filter['gname'] ),
					'options'				=> array(),
					'collapsed'				=> empty( $item['collapsed'] ) ? false : $item['collapsed'],
					'select_deselect_all'	=> empty( $item['select_deselect_all'] ) ? false : $item['select_deselect_all'],
					'display_list_of_items'	=> empty( $item['display_list_of_items'] ) ? '' : $item['display_list_of_items'],
					'display_live_filter'	=> empty( $item['display_live_filter'] ) ? '' : $item['display_live_filter']
				);
			}
			
			if( ! empty( $item['sort_order_values'] ) ) {
				$sort['f_'.$filter['filter_group_id']] = $item['sort_order_values'];
			}
			
			$value = $filter['filter_id'];
			
			if( $this->isSeoEnabled() ) {
				if( isset( $keywords[$filter['filter_id']] ) ) {
					$value = $keywords[$filter['filter_id']];
				} else if( isset( $filter['keyword'] ) ) {
					$value = $filter['keyword'];
				}
			}
			
			$filters['f_'.$filter['filter_group_id']]['options'][$filter['filter_id']] = $core->addParamToCurrentUrl( array(
				'name' => $filter['name'],
				'value' => $value,
				'key' => $filter['filter_id'],
			), $filters['f_'.$filter['filter_group_id']]['seo_name'], $value );
				
			if( in_array( $filters['f_'.$filter['filter_group_id']]['type'], array( 'image', 'image_radio', 'image_list_radio', 'image_list_checkbox' ) ) ) {
				list( $w, $h ) = $this->_imageSize();
				
				$filters['f_'.$filter['filter_group_id']]['options'][$filter['filter_id']]['image'] = self::parseUrl( isset( $images[$filter['filter_id']] ) ? $this->model_tool_image->resize($images[$filter['filter_id']], $w, $h) : $this->model_tool_image->resize('no_image.jpg', $w, $h) );
			}
		}
		
		foreach( $sort as $filter_group_id => $type ) {
			$this->_sortOptions( $filters[$filter_group_id]['options'], $type, true );
		}
		
		return $filters;
	}
	
	private function prepareFiltersQuery( $sql, $core, $idx, array $config, array $conditions = array() ) {
		$filter_ids		= array();
		
		if( ! empty( $config['based_on_category'] ) ) {
			$category_id	= $this->rget('path') !== null ? explode( '_', (string) $this->rget('path') ) : array();
			$category_id	= end( $category_id );

			if( $category_id ) {
				foreach( $this->db->query( 'SELECT `filter_id` FROM `' . DB_PREFIX . 'category_filter` WHERE `category_id` = ' . (int) $category_id )->rows as $row ) {
					$filter_ids[] = (int) $row['filter_id'];
				}
			}
		}
		
		$conditions		= $this->prepareQuery( $conditions, $idx, $core );
		$join			= $core->_baseJoin(array('p2s','pf'));
		
		if( $filter_ids ) {
			$conditions[]	= sprintf( '`f`.`filter_id` IN(%s)', implode( ',', $filter_ids ) );
		}
		
		return str_replace( array( '{conditions}', '{join}' ), array( implode( ' AND ', $conditions ), $join ), $sql );
	}
	
	/**
	 * Create a list of filters
	 */
	public function boostCreateFilters( $core, $idx, array $config ) {
		$sql = "
			SELECT
				`f`.`filter_id`
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_to_store` AS `p2s`
			ON
				`p`.`product_id` = `p2s`.`product_id` AND `p2s`.`store_id` = " . (int) $this->config->get( 'config_store_id' ) . "
			INNER JOIN
				`" . DB_PREFIX . "product_filter` AS `pf`
			ON
				`p`.`product_id` = `pf`.`product_id`
			INNER JOIN
				`" . DB_PREFIX . "filter` AS `f`
			ON
				`pf`.`filter_id` = `f`.`filter_id`
			{join}
			WHERE
				{conditions}
			GROUP BY
				`f`.`filter_group_id`, `f`.`filter_id`
		";
		
		$ids = array();
		
		foreach( $this->db->query( $this->prepareFiltersQuery( $sql, $core, $idx, $config ) )->rows as $row ) {
			$ids[] = $row['filter_id'];
		}
		
		return $ids;
	}
	
	private static function parseUrl( $url ) {
		$scheme		= isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		$host		= isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
		$parse		= parse_url( $url );
		
		return $scheme . '://' . $host . '/' . trim( $parse['path'], '/' ) . ( empty( $parse['query'] ) ? '' : '?' . str_replace( '&amp;', '&', $parse['query'] ) );
	}
	
	/**
	 * Create a list of options
	 */
	public function createOptions( $core, $idx, array $opts ) {
		/* @var $hasMfilterPlus bool */
		$hasMfilterPlus = $this->hasMfilterPlus();
		
		$sql = "
			SELECT
				`o`.`option_id`,
				`ov`.`option_value_id`,
				`od`.`name` AS `gname`,
				`od`.`mf_tooltip` AS `tooltip`,
				`ov`.`image`,
				`ovd`.`name`" . (  $this->isSeoEnabled() && ! $hasMfilterPlus ? ",
				LOWER(REPLACE(REPLACE(REPLACE(TRIM(`ovd`.`name`), '\r', ''), '\n', ''), ' ', '-')) AS `keyword`" : '' ) . "
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_to_store` AS `p2s`
			ON
				`p`.`product_id` = `p2s`.`product_id` AND `p2s`.`store_id` = " . (int) $this->config->get( 'config_store_id' ) . "
			INNER JOIN
				`" . DB_PREFIX . "product_option_value` AS `pov`
			ON
				`p`.`product_id` = `pov`.`product_id`
			INNER JOIN
				`" . DB_PREFIX . "option_value` AS `ov`
			ON
				`ov`.`option_value_id` = `pov`.`option_value_id`
			INNER JOIN
				`" . DB_PREFIX . "option_value_description` AS `ovd`
			ON
				`ov`.`option_value_id` = `ovd`.`option_value_id` AND `ovd`.`language_id` = " . (int) $this->config->get('config_language_id') . "
			INNER JOIN
				`" . DB_PREFIX . "option` AS `o`
			ON
				`o`.`option_id` = `pov`.`option_id`
			INNER JOIN
				`" . DB_PREFIX . "option_description` AS `od`
			ON
				`od`.`option_id` = `o`.`option_id` AND `od`.`language_id` = " . (int) $this->config->get('config_language_id') . "
			{join}
			WHERE
				{conditions}
			GROUP BY
				`o`.`option_id`, `ov`.`option_value_id`
			ORDER BY
				`ov`.`sort_order`, `ovd`.`name`
		";
		
		$this->load->model('tool/image');
		
		$options	= array();
		$conditions	= array();
		$sort		= array();
		
		if( ! empty( $this->_settings['try_to_boost_speed'] ) ) {
			if( null != ( $option_ids = $this->boostCreateOptions( $core, $idx, $opts ) ) ) {
				$conditions[] = "`ov`.`option_value_id` IN(" . implode(',', $option_ids) . ")";
			} else {
				return $options;
			}
		}
		
		$rows = $this->db->query( $this->prepareOptionsQuery( $sql, $core, $idx, $conditions ) )->rows;
		
		$keywords = array();
		
		if( $hasMfilterPlus && $this->isSeoEnabled() ) {
			$ids = array();

			foreach( $rows as $option ) {
				$ids[] = $option['option_value_id'];
			}

			if( ! $ids ) {
				return $options;
			}
			
			foreach( $this->db->query( "SELECT * FROM `" . DB_PREFIX . "mfilter_values` WHERE `type` = 'option' AND `value_id` IN(" . implode(',', $ids) . ") AND ( `language_id` IS NULL OR `language_id` = " . (int) $this->config->get('config_language_id') . " )" )->rows as $row ) {
				$keywords[$row['value_id']] = $row['seo_value'];
			}
		}
		
		foreach( $rows as $option ) {
			/* @var $item array */
			$item = isset( $opts['default'] ) ? $opts['default'] : array();
			
			if( isset( $opts[$option['option_id']] ) ) {
				$item = $opts[$option['option_id']];
			}
			
			if( empty( $item['enabled'] ) ) continue;
			
			if( ! isset( $item['sort_order'] ) ) {
				$item['sort_order'] = 0;
			}
			
			if( ! isset( $options['o_'.$option['option_id']] ) ) {
				$options['o_'.$option['option_id']] = array(
					'id'					=> $option['option_id'],
					'type'					=> $item['type'],
					'base_type'				=> 'option',
					'sort_order'			=> $item['sort_order'],
					'name'					=> $option['gname'],
					'tooltip'				=> $option['tooltip'],
					'seo_name'				=> $option['option_id'] . 'o-' . $this->clear( $option['gname'] ),
					'options'				=> array(),
					'collapsed'				=> empty( $item['collapsed'] ) ? false : $item['collapsed'],
					'select_deselect_all'	=> empty( $item['select_deselect_all'] ) ? false : $item['select_deselect_all'],
					'display_list_of_items'	=> empty( $item['display_list_of_items'] ) ? '' : $item['display_list_of_items'],
					'display_live_filter'	=> empty( $item['display_live_filter'] ) ? '' : $item['display_live_filter']
				);
			}
			
			if( ! empty( $item['sort_order_values'] ) ) {
				$sort['o_'.$option['option_id']] = $item['sort_order_values'];
			}
			
			$value = $option['option_value_id'];
			
			if( $this->isSeoEnabled() ) {
				if( isset( $keywords[$option['option_value_id']] ) ) {
					$value = $keywords[$option['option_value_id']];
				} else if( isset( $option['keyword'] ) ) {
					$value = $option['keyword'];
				}
			}
			
			$value = $core->addParamToCurrentUrl( array(
				'name'	=> $option['name'],
				'value'	=> $value,
				'key' => $option['option_value_id'],
			), $options['o_'.$option['option_id']]['seo_name'], $value );
			
			if( in_array( $item['type'], array( 'image', 'image_radio', 'image_list_radio', 'image_list_checkbox' ) ) ) {
				list( $w, $h ) = $this->_imageSize();
				
				$value['image'] = self::parseUrl( empty( $option['image'] ) ? $this->model_tool_image->resize('no_image.jpg', $w, $h) : $this->model_tool_image->resize($option['image'], $w, $h) );
			}
			
			$options['o_'.$option['option_id']]['options'][$option['option_value_id']] = $value;
		}
		
		foreach( $sort as $option_id => $type ) {
			$this->_sortOptions( $options[$option_id]['options'], $type, true );
		}
		
		return $options;
	}
	
	private function prepareOptionsQuery( $sql, $core, $idx, array $conditions = array() ) {
		$conditions		= $this->prepareQuery( $conditions, $idx, $core );
		$conditions[]	= "`o`.`type` IN('radio','checkbox','select','image','image_radio')";
		$join			= $core->_baseJoin(array('p2s'));
		
		if( ! $this->stockStatusIsEnabled( $idx ) && ! empty( $core->_settings['in_stock_default_selected'] ) ) {
			$conditions[] = '`pov`.`quantity` > 0';
		}
		
		return str_replace( array( '{conditions}', '{join}' ), array( implode( ' AND ', $conditions ), $join ), $sql );
	}
	
	private function prepareQuery( array $conditions, $idx, $core ) {
		$conditions	= $core->_baseConditions( $conditions, true );
		
		if( in_array( $core->route(), MegaFilterCore::$_specialRoute ) ) {
			$conditions[] = '(' . $core->specialCol( '' ) . ') IS NOT NULL';
		}
		
		if( ! $this->stockStatusIsEnabled( $idx ) && ! empty( $core->_settings['in_stock_default_selected'] ) ) {
			$conditions[] = sprintf( '( `p`.`quantity` > 0 OR `p`.`stock_status_id` = %s )', $core->inStockStatus() );
		}
		
		return $conditions;
	}
	
	/**
	 * Create a list of options
	 */
	public function boostCreateOptions( $core, $idx ) {
		$sql = "
			SELECT
				`ov`.`option_value_id`
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_to_store` AS `p2s`
			ON
				`p`.`product_id` = `p2s`.`product_id` AND `p2s`.`store_id` = " . (int) $this->config->get( 'config_store_id' ) . "
			INNER JOIN
				`" . DB_PREFIX . "product_option_value` AS `pov`
			ON
				`p`.`product_id` = `pov`.`product_id`
			INNER JOIN
				`" . DB_PREFIX . "option_value` AS `ov`
			ON
				`ov`.`option_value_id` = `pov`.`option_value_id`
			INNER JOIN
				`" . DB_PREFIX . "option` AS `o`
			ON
				`o`.`option_id` = `pov`.`option_id`
			{join}
			WHERE
				{conditions}
			GROUP BY
				`ov`.`option_value_id`
		";
		
		$ids = array();
		
		foreach( $this->db->query( $this->prepareOptionsQuery( $sql, $core, $idx ) )->rows as $row ) {
			$ids[] = $row['option_value_id'];
		}
		
		return $ids;
	}
	
	private function _imageSize() {
		$settings	= $this->settings();
		
		$w = empty( $settings['image_size_width'] ) ? 20 : (int) $settings['image_size_width'];
		$h = empty( $settings['image_size_height'] ) ? 20 : (int) $settings['image_size_height'];
		
		return array( $w, $h );
	}
	
	/**
	 * Utwórz listę atrybutów
	 * 
	 * @param array $attribs
	 * @return type 
	 */
	public function createAttribs( $core, $idx, array $attribs ) {
		/* @var $hasMfilterPlus bool */
		$hasMfilterPlus = $this->hasMfilterPlus();
		
		$sql = "
			SELECT
				`a`.`attribute_id`,
				REPLACE(REPLACE(TRIM(pa.text), '\r', ''), '\n', '') AS `txt`,
				`ad`.`name`,
				`ad`.`mf_tooltip` AS `tooltip`,
				`agd`.`name` AS `gname`,
				`agd`.`attribute_group_id`
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_to_store` AS `pts`
			ON
				`p`.`product_id` = `pts`.`product_id` AND `pts`.`store_id` = " . (int) $this->config->get( 'config_store_id' ) . "
			INNER JOIN
				`" . DB_PREFIX . "product_attribute` AS `pa`
			ON
				`p`.`product_id` = `pa`.`product_id` AND `pa`.`language_id` = " . (int)$this->config->get('config_language_id') . "
			INNER JOIN
				`" . DB_PREFIX . "attribute` AS `a`
			ON
				`a`.`attribute_id` = `pa`.`attribute_id`
			INNER JOIN
				`" . DB_PREFIX . "attribute_description` AS `ad`
			ON
				`ad`.`attribute_id` = `a`.`attribute_id` AND `ad`.`language_id` = " . (int) $this->config->get('config_language_id') . "
			INNER JOIN
				`" . DB_PREFIX . "attribute_group` AS `ag`
			ON
				`ag`.`attribute_group_id` = `a`.`attribute_group_id`
			INNER JOIN
				`" . DB_PREFIX . "attribute_group_description` AS `agd`
			ON
				`agd`.`attribute_group_id` = `ag`.`attribute_group_id` AND `agd`.`language_id` = " . (int)$this->config->get('config_language_id') . "
			{join}
			WHERE
				{conditions}
			GROUP BY
				`txt`, `pa`.`attribute_id`
			HAVING 
				`txt` != ''
			ORDER BY
				`txt`
		";
		
		$this->load->model('tool/image');
		
		$attributes = array();
		$conditions	= array();
		$sort		= array();
		
		if( ! empty( $this->_settings['try_to_boost_speed'] ) ) {
			if( null != ( $attribs_ids = $this->boostCreateAttribs( $core, $idx ) ) ) {
				$conditions[] = "`a`.`attribute_id` IN(" . implode(',', $attribs_ids) . ")";
			} else {
				return $attributes;
			}
		}
		
		$settings	= $this->settings();
		
		$query	= $this->db->query( $this->prepareAttributesQuery( $sql, $core, $idx, $conditions ) );
		$rows	= $query && $query->rows ? $query->rows : array();
		
		$keywords = array();
		
		if( $hasMfilterPlus && $this->isSeoEnabled() ) {
			$ids = array();

			foreach( $rows as $attribute ) {
				$attribute['txt'] = htmlspecialchars_decode( $attribute['txt'] );

				if( ! empty( $settings['attribute_separator'] ) ) {
					$attribute['txt'] = array_map( 'trim', explode( $settings['attribute_separator'], $attribute['txt'] ) );
				} else {
					$attribute['txt'] = array( $attribute['txt'] );
				}

				$unique	= array();
				
				foreach( $attribute['txt'] as $text ) {
					$text = htmlspecialchars( $text );

					$k = md5( $text );

					if( isset( $unique[$text] ) ) continue;

					$unique[$text]	= true;
					
					$ids[] = "'".$k."'";
				}
			}

			if( ! $ids ) {
				return $attributes;
			}
			
			foreach( $this->db->query( "SELECT * FROM `" . DB_PREFIX . "mfilter_values` WHERE `type` = 'attribute' AND `value` IN(" . implode(',', $ids) . ") AND ( `language_id` IS NULL OR `language_id` = " . (int) $this->config->get('config_language_id') . " )" )->rows as $row ) {
				$keywords[$row['value'].'_'.$row['value_id']] = $row['seo_value'];
			}
		}
		
		foreach( $rows as $attribute ) {
			/* @var $item array */
			$item = isset( $attribs['default'] ) ? $attribs['default'] : array();
			
			if( isset( $attribs[$attribute['attribute_group_id']]['items'][$attribute['attribute_id']] ) ) {
				$item = $attribs[$attribute['attribute_group_id']]['items'][$attribute['attribute_id']];
			} else if( isset( $attribs['default_group'][$attribute['attribute_group_id']] ) ) {
				$item = $attribs['default_group'][$attribute['attribute_group_id']];
			}	
			
			if( empty( $item['enabled'] ) ) continue;
			
			/* @var $group_key string */
			$group_key = '';
			
			if( isset( $attribs['default_group'][$attribute['attribute_group_id']]['display_in_group'] ) ) {
				if( ! empty( $attribs['default_group'][$attribute['attribute_group_id']]['display_in_group'] ) ) {
					$group_key = 'ag_' . $attribute['attribute_group_id'];
				}
			} else if( ! empty( $attribs['settings']['display_in_group'] ) ) {
				$group_key = 'ag_' . $attribute['attribute_group_id'];
			}
			
			if( $group_key && ! isset( $attribs[$attribute['attribute_group_id']]['items'][$attribute['attribute_id']] ) ) {
				$item['sort_order'] = 0;
			}
			
			if( ! isset( $item['sort_order'] ) ) {
				$item['sort_order'] = 0;
			}
			
			$images = (array) $this->config->get('mega_filter_at_img_' . $attribute['attribute_id'] . '_' . $this->config->get('config_language_id'));
			
			if( $group_key ) {
				if( ! isset( $attributes[$group_key] ) ) {
					$attributes[$group_key] = array(
						'name'			=> $group_key ? $attribute['gname'] : null,
						'group_key'		=> $group_key,
						'type'			=> 'attribute_group',
						'base_type'		=> 'attribute_group',
						'sort_order'	=> isset( $attribs['default_group'][$attribute['attribute_group_id']]['sort_order'] ) ? (int) $attribs['default_group'][$attribute['attribute_group_id']]['sort_order'] : 0,
						'attributes'	=> array(),
					);
				}
				
				if( ! isset( $attributes[$group_key]['attributes']['a_'.$attribute['attribute_id']] ) ) {
					$attributes[$group_key]['attributes']['a_'.$attribute['attribute_id']] = array();
				}
				
				$attr_value = & $attributes[$group_key]['attributes']['a_'.$attribute['attribute_id']];
			} else {
				if( ! isset( $attributes['a_'.$attribute['attribute_id']] ) ) {
					$attributes['a_'.$attribute['attribute_id']] = array();
				}
				
				$attr_value = & $attributes['a_'.$attribute['attribute_id']];
			}
			
			if( ! $attr_value ) {
				$attr_value = array(
					'id'					=> $attribute['attribute_id'],
					'group_id'				=> $attribute['attribute_group_id'],
					'type'					=> $item['type'],
					'base_type'				=> 'attribute',
					'sort_order'			=> empty( $attribs[$attribute['attribute_group_id']]['sort_order'] ) ? 0 : (int) $attribs[$attribute['attribute_group_id']]['sort_order'],
					'sort_order-sec'		=> $item['sort_order'],
					'name'					=> $attribute['name'],
					'tooltip'				=> $attribute['tooltip'],
					'seo_name'				=> $attribute['attribute_id'] . '-' . $this->clear( $attribute['name'] ),
					'options'				=> array(),
					'collapsed'				=> empty( $item['collapsed'] ) ? false : $item['collapsed'],
					'select_deselect_all'	=> empty( $item['select_deselect_all'] ) ? false : $item['select_deselect_all'],
					'display_list_of_items'	=> empty( $item['display_list_of_items'] ) ? '' : $item['display_list_of_items'],
					'display_live_filter'	=> empty( $item['display_live_filter'] ) ? '' : $item['display_live_filter']
				);
			}
			
			$attribute['txt'] = htmlspecialchars_decode( $attribute['txt'] );
			
			if( ! empty( $settings['attribute_separator'] ) ) {
				$attribute['txt'] = array_map( 'trim', explode( $settings['attribute_separator'], $attribute['txt'] ) );
			} else {
				$attribute['txt'] = array( $attribute['txt'] );
			}
			
			$unique	= array();
			foreach( $attribute['txt'] as $text ) {
				$text = htmlspecialchars( $text );
				
				$k = md5( $text );
				
				if( isset( $unique[$text] ) ) continue;
				
				$unique[$text]	= true;
				$value			= $hasMfilterPlus && isset( $keywords[$k.'_'.$attribute['attribute_id']] ) ? $keywords[$k.'_'.$attribute['attribute_id']] : Mfilter_Helper::i()->convertValueToSeo( $text );
				$value			= $core->addParamToCurrentUrl( array(
					'name' => $text,
					'value' => $value,
					'key' => $k
				), $attr_value['seo_name'], $value );
				
				if( in_array( $item['type'], array( 'image', 'image_radio', 'image_list_radio', 'image_list_checkbox' ) ) ) {
					list( $w, $h ) = $this->_imageSize();
					
					$value['image'] = self::parseUrl( isset( $images[$k] ) ? $this->model_tool_image->resize($images[$k], $w, $h) : $this->model_tool_image->resize('no_image.jpg', $w, $h) );
				}
				
				$attr_value['options'][$k] = $value;
			}
			
			if( ! empty( $item['sort_order_values'] ) ) {
				$sort['a_'.$attribute['attribute_id']] = $item['sort_order_values'];
			}
		}
		
		foreach( $attributes as $attribute_group_id => $attributes_group ) {
			if( $attributes_group['type'] == 'attribute_group' ) {
				foreach( $attributes_group['attributes'] as $attribute_id => $attribute ) {
					if( ! empty( $attribute['options'] ) ) {
						$this->_sortOptions( $attributes[$attribute_group_id]['attributes'][$attribute_id]['options'], empty( $sort[$attribute_id] ) ? '' : $sort[$attribute_id], true, $attribute_id );
					}
				}
				
				uasort( $attributes[$attribute_group_id]['attributes'], array( 'ModelExtensionModuleMegaFilter', '_sortAttributes' ) );
			} else if( ! empty( $attributes[$attribute_group_id]['options'] ) ) {
				$this->_sortOptions( $attributes[$attribute_group_id]['options'], empty( $sort[$attribute_group_id] ) ? '' : $sort[$attribute_group_id], true, $attribute_group_id );
			}
		}
		
		return $attributes;
	}
	
	private function prepareAttributesQuery( $sql, $core, $idx, array $conditions = array() ) {
		$conditions = $this->prepareQuery( $conditions, $idx, $core );
		$join		= $core->_baseJoin(array('p2s'));
		
		return str_replace( array( '{conditions}', '{join}' ), array( implode( ' AND ', $conditions ), $join ), $sql );
	}
	
	/**
	 * Create a list of attributes
	 * 
	 * @param array $attribs
	 * @return type 
	 */
	public function boostCreateAttribs( $core, $idx ) {
		$sql = "
			SELECT
				`a`.`attribute_id`
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_to_store` AS `pts`
			ON
				`p`.`product_id` = `pts`.`product_id` AND `pts`.`store_id` = " . (int) $this->config->get( 'config_store_id' ) . "
			INNER JOIN
				`" . DB_PREFIX . "product_attribute` AS `pa`
			ON
				`p`.`product_id` = `pa`.`product_id` AND `pa`.`language_id` = " . (int)$this->config->get('config_language_id') . "
			INNER JOIN
				`" . DB_PREFIX . "attribute` AS `a`
			ON
				`a`.`attribute_id` = `pa`.`attribute_id`
			{join}
			WHERE
				{conditions}
			GROUP BY
				`pa`.`attribute_id`
		";
		
		$ids = array();
		
		foreach( $this->db->query( $this->prepareAttributesQuery( $sql, $core, $idx ) )->rows as $row ) {
			$ids[] = $row['attribute_id'];
		}
		
		return $ids;
	}
	
	public function _prepareVehiclesData( & $core, $data ) {
		if( ! empty( $data['makes'] ) ) {
			$this->load->model('tool/image');
			
			list( $w, $h ) = $this->_imageSize();
			
			foreach( $data['makes'] as $k => $v ) {
				$data['makes'][$k]['image'] = empty( $v['image'] ) ? '' : self::parseUrl( $this->model_tool_image->resize($v['image'], $w, $h) );
			}
		}
		
		/*foreach( $data as $k => $v ) {
			foreach( $v as $k2 => $v2 ) {
				$data[$k][$k2]['url'] = $core->addParamToCurrentUrl( 'vehicles', $v2['value'] );
			}
		}*/
		
		return $data;
	}
	
	public function _prepareLevelsData( $data ) {
		$this->load->model('tool/image');
			
		list( $w, $h ) = $this->_imageSize();
			
		foreach( $data as $k => $v ) {
			foreach( $v as $k2 => $v2 ) {
				$data[$k][$k2]['image'] = empty( $v2['image'] ) ? '' : self::parseUrl( $this->model_tool_image->resize($v2['image'], $w, $h) );
			}
		}
		
		return $data;
	}
	
	public function hasVehicles() {
		if( ! $this->config->get( 'mfilter_vehicle_version' ) || ! $this->config->get( 'mfilter_vehicle_license' ) ) {
			return false;
		}
		
		require_once DIR_SYSTEM . 'library/mfilter_vehicle.php';
		
		if( ! class_exists( 'Mfilter_Vehicle' ) ) {
			return false;
		}
		
		return true;
	}
	
	public function hasLevels() {
		if( ! $this->config->get( 'mfilter_level_version' ) || ! $this->config->get( 'mfilter_level_license' ) ) {
			return false;
		}
		
		require_once DIR_SYSTEM . 'library/mfilter_level.php';
		
		if( ! class_exists( 'Mfilter_Level' ) ) {
			return false;
		}
		
		return true;
	}
	
	public function createVehicles( $idx, $core, $config ) {
		if( ! $this->hasVehicles() ) {
			return array();
		}
		
		$data = Mfilter_Vehicle::createVehicles( $this, $core, $config, $this->calculateNumberOfProducts( $idx ) && $this->showNumberOfProducts( $idx ) );
		
		if( ! empty( $data['vehicles']['options'] ) ) {
			$data['vehicles']['options'] = $this->_prepareVehiclesData( $core, $data['vehicles']['options'] );
		}
		
		return $data;
	}
	
	public function createLevels( $idx, $core, $config ) {
		if( ! $this->hasLevels() ) {
			return array();
		}
		
		$data = Mfilter_Level::createLevels( $this, $core, $config, $this->calculateNumberOfProducts( $idx ) && $this->showNumberOfProducts( $idx ) );
		
		if( ! empty( $data['levels']['options'] ) ) {
			$data['levels']['options'] = $this->_prepareLevelsData( $data['levels']['options'] );
		}
		
		return $data;
	}
	
	public function vehiclesToJson( $idx, $core, array $data = array() ) {
		if( ! $this->hasVehicles() ) {
			return array();
		}
		
		return $this->_prepareVehiclesData( $core, Mfilter_Vehicle::toJson( $this, $core, $data, $this->calculateNumberOfProducts( $idx ) && $this->showNumberOfProducts( $idx ) ) );
	}
	
	public function levelsToJson( $idx, $core, array $data = array() ) {
		if( ! $this->hasLevels() ) {
			return array();
		}
		
		return $this->_prepareLevelsData( Mfilter_Level::toJson( $this, $core, $data, $this->calculateNumberOfProducts( $idx ) && $this->showNumberOfProducts( $idx ) ) );
	}
	
	protected function getParseParams( & $core ) {
		if( isset( $this->request->get['mfp_temp'] ) ) {
			$this->request->get[$this->mfpUrlParam()] = $this->request->get['mfp_temp'];
			$core->parseParams();
		}
		
		$params = $core->getParseParams();
		
		if( isset( $this->request->get['mfp_temp'] ) ) {
			unset( $this->request->get[$this->mfpUrlParam()] );
			$core->parseParams();
		}
		
		return $params;
	}
	
	/**
	 * Create a list of categories
	 */
	public function createCategories( & $core, $idx, $config ) {
		$categories = array();
		$params		= $this->getParseParams( $core );
		
		foreach( $config as $key => $category ) {
			$row = array(
				'sort_order'	=> isset( $category['sort_order'] ) ? $category['sort_order'] : 0,
				'type'			=> $category['type'],
				'base_type'		=> 'categories',
				'collapsed'		=> empty( $category['collapsed'] ) ? false : $category['collapsed'],
				'show_button'	=> empty( $category['show_button'] ) ? false : true,
				'name'			=> empty( $category['name'][$this->config->get('config_language_id')] ) ? current( $category['name'] ) : $category['name'][$this->config->get('config_language_id')],
			);
			
			$seo_name			= $this->clear( $row['name'] ? $row['name'] : 'cat' );
			$row['seo_name']	= 'c-' . ( $seo_name ? $seo_name : 'cat' ) . '-' . $key;
			$values				= empty( $params[$row['seo_name']] ) ? array() : $params[$row['seo_name']];
			
			switch( $category['type'] ) {
				case 'tree' : {
					if( NULL != ( $row['categories'] = $core->getTreeCategories( null, 'tree' ) ) || $this->rget('mfp_path') || ! empty( $category['show_go_back_to_top'] ) ) {
						$row['top_path'] = 0;
						
						if( $this->rget('mfp_org_path') ) {
							$row['top_path'] = explode( strpos( $this->rget('mfp_org_path'), ',' ) ? ',' : '_', $this->rget('mfp_org_path') );
						} else if( $this->rget('path') ) {
							$row['top_path'] = explode( '_', $this->rget('path') );
						}
						
						if( is_array( $row['top_path'] ) ) {
							array_pop( $row['top_path'] );
							$row['top_path'] = $row['top_path'] ? implode( '_', MegaFilterCore::_aliasesToIds( $this, 'category_id', $row['top_path'] ) ) : 0;
						}
						
						$row['top_url'] = $row['top_path'] ? $this->url->link( 'product/category', '&path=' . $row['top_path'], 'SSL' ) : '';
					} else {
						$row = NULL;
					}
					
					break;
				}
				case 'cat_checkbox' : {
					$row['seo_name'] = 'path';
					
					if( NULL == ( $row['categories'] = $core->getTreeCategories( $this->rget('mfp_org_path') ? $this->rget('mfp_org_path') : NULL, 'checkbox' ) ) ) {
						$row = NULL;
					}
					
					break;
				}
				case 'related' : {
					$row['levels']		= array();
					$row['labels']		= array();
					$row['auto_levels']	= empty( $category['auto_levels'] ) ? false : true;
					$root_category_id	= empty( $category['root_category_id'] ) ? NULL : $category['root_category_id'];
					$start_id			= 0;
					
					if( ! empty( $category['root_category_type'] ) ) {
						switch( $category['root_category_type'] ) {
							case 'by_url' : {
								if( $this->rget('path') ) {
									$path = explode( '_', $this->rget('path') );
									$start_id = count( $path ) - 1;
									$root_category_id = end( $path );
								}
								
								break;
							}
							case 'top_category' : {
								$root_category_id = 0;
								
								break;
							}
						}
					}
					
					if( ! empty( $category['levels'] ) ) {
						$levels = $category['levels'];
							
						foreach( $category['levels'] as $level ) {
							$row['labels'][] = empty( $level[$this->config->get('config_language_id')] ) ? $this->language->get('text_select') : $level[$this->config->get('config_language_id')];
						}
						
						if( $start_id ) {
							if( empty( $category['auto_levels'] ) ) {
								$levels = array_slice( $levels, $start_id );
							} else {
								$levels = array_slice( $levels, $start_id, count( $values ) ? count( $values ) : 1 );
							}
							
							//$values = array_slice( $values, $start_id );
							$row['labels'] = array_slice( $row['labels'], $start_id );
						}
						
						$level_id = 0;
						foreach( $levels as $level ) {
							$level = array(
								'name'			=> $row['labels'][$level_id],
								'options'		=> array()
							);
							$value = empty( $values[$level_id-1] ) ? $root_category_id : $values[$level_id-1];
							
							if( ! $row['levels'] || $value || $value == $root_category_id ) {
								if( $value || $value == $root_category_id ) {
									$this->load->model('catalog/category');
									
									foreach( $this->model_catalog_category->getCategories( $value ) as $cat ) {
										$level['options'][$cat['category_id']] = $cat['name'];
									}
									
									//if( isset( $values[$level_id-1] ) ) {
										if( $level_id && ! isset( $row['levels'][$level_id-1]['options'][$value] ) ) {
											if( ! empty( $category['auto_levels'] ) ) {
												break;
											} else {
												$level['options'] = array();
											}
										}
									//}
								}
								
								if( ! $level['options'] && ( ( ! $value && $value != $root_category_id ) || ! $level_id ) ) {
									$row = NULL;
									break;
								}
							}
							
							$row['levels'][] = $level;
							$level_id++;
						}
						
						if( empty( $row['levels'] ) ) {
							$row = NULL;
						}
					} else {
						$row = NULL;
					}
					
					break;
				}
			}
			
			if( $row != NULL ) {
				$categories[] = $row;
			}
		}
		
		return $categories;
	}
	
	private static function _sortItems( $a, $b ) {
		$as = isset( self::$_tmp_sort_parameters['config'][md5($a['name'])] ) ? self::$_tmp_sort_parameters['config'][md5($a['name'])] : count(self::$_tmp_sort_parameters['config']);
		$bs = isset( self::$_tmp_sort_parameters['config'][md5($b['name'])] ) ? self::$_tmp_sort_parameters['config'][md5($b['name'])] : count(self::$_tmp_sort_parameters['config']);
		
		if( $as > $bs )
			return 1;
		
		if( $as < $bs )
			return -1;
		
		return 0;
	}
	
	private function _sortOptions( & $options, $sort, $a = false, $attribute_id = NULL ) {		
		if( $sort ) {
			list( $type, $order ) = explode( '_', $sort );
		} else {
			$type = $order = '';
		}
		
		if( $attribute_id ) {
			$attribute_id = str_replace(array(
				'a_', 'o_', 'f_'
			), '', $attribute_id);
		}
		
		self::$_tmp_sort_parameters = array(
			'attribute_id'	=> $attribute_id,
			'type'			=> $type,
			'order'			=> $order,
			'a'				=> $a,
			'options'		=> $options,
			'config'		=> $this->config->get('mega_filter_at_sort_' . $attribute_id . '_' . $this->config->get('config_language_id') )
		);
		
		if( ! self::$_tmp_sort_parameters['type'] && ! self::$_tmp_sort_parameters['config'] ) {
			self::$_tmp_sort_parameters = NULL;
			
			return;
		}
		
		if( ! self::$_tmp_sort_parameters['type'] && self::$_tmp_sort_parameters['attribute_id'] !== NULL && self::$_tmp_sort_parameters['config'] ) {
			uasort( $options, array( 'ModelExtensionModuleMegaFilter', '_sortItems' ) );
		} else {
			$tmp = array();
			
			foreach( $options as $k => $v ) {
				$tmp['_'.$k] = htmlspecialchars_decode( $v['name'] );
			}
			
			switch( $type ) {
				case 'date' : {
					uasort( $tmp, function($a, $b) use($order){
						if( $order == 'desc' ) {
							return @ strtotime($b) - @ strtotime($a);
						}
						
						return @ strtotime($a) - @ strtotime($b);
					});
					
					break;
				}
				default : {
					if( $order == 'desc' ) {
						arsort( $tmp, $type == 'string' ? SORT_STRING : SORT_NUMERIC );
					} else {
						asort( $tmp, $type == 'string' ? SORT_STRING : SORT_NUMERIC );
					}
				}
			}
			
			$tmp2 = array();
			
			foreach( $tmp as $k => $v ) {
				$tmp2[trim($k,'_')] = $options[trim($k,'_')];
			}
		
			$options = $tmp2;
		}
			
		self::$_tmp_sort_parameters = NULL;
	}
		
	/**
	 * Get list of attributes
	 * 
	 * @return array 
	 */
	public function getAttributes( $core, $idx, array $base_attribs, array $attribs, array $opts, array $filters, array $categories, array $vehicles, array $levels ) {
		$attributes = 
			$this->createAttribs( $core, $idx, $attribs ) + 
			$this->createOptions( $core, $idx, $opts ) + 
			( MegaFilterCore::hasFilters() ? $this->createFilters( $core, $idx, $filters ) : array() ) + 
			$this->createCategories( $core, $idx, $categories );
		
		if( ! empty( $vehicles['enabled'] ) ) {
			$attributes += $this->createVehicles( $idx, $core, $vehicles );
		
			if( ! empty( $attributes['vehicle']['options'] ) ) {
				list( $w, $h ) = $this->_imageSize();

				foreach( $attributes['vehicle']['options'] as $k => $v ) {
					if( isset( $v['image'] ) ) {
						$attributes['vehicle']['options'][$k]['image_w'] = $w;
						$attributes['vehicle']['options'][$k]['image_h'] = $h;
						$attributes['vehicle']['options'][$k]['image'] = self::parseUrl( empty( $v['image'] ) ? $this->model_tool_image->resize('no_image.png', $w, $h) : $this->model_tool_image->resize($v['image'], $w, $h) );
					}
				}
			}
		}
		
		if( ! empty( $levels['enabled'] ) ) {
			$attributes += $this->createLevels( $idx, $core, $levels );
		
			if( ! empty( $attributes['level']['options'] ) ) {
				list( $w, $h ) = $this->_imageSize();

				foreach( $attributes['level']['options'] as $k => $v ) {
					if( isset( $v['image'] ) ) {
						$attributes['level']['options'][$k]['image_w'] = $w;
						$attributes['level']['options'][$k]['image_h'] = $h;
						$attributes['level']['options'][$k]['image'] = self::parseUrl( empty( $v['image'] ) ? $this->model_tool_image->resize('no_image.png', $w, $h) : $this->model_tool_image->resize($v['image'], $w, $h) );
					}
				}
			}
		}
		
		if( ! empty( $data['seo']['trans'] ) ) {
			foreach( $data['seo']['trans'] as $k => $v ) {
				foreach( $v as $k2 => $v2 ) {
					$data['seo']['trans'][$k][$k2] = isset( $v2[$this->_ctrl->config->get('config_language_id')] ) && $v2[$this->_ctrl->config->get('config_language_id')] !== '' ? $v2[$this->_ctrl->config->get('config_language_id')] : null;
				}
			}
		}
		
		/* @var $seo_config array */
		$seo_config = (array) $this->config->get( 'mega_filter_seo' );
		
		/**
		 * Add basic attributes
		 */
		if( ! empty( $base_attribs ) ) {
			foreach( $base_attribs as $type => $attribute ) {
				if( empty( $attribute['enabled'] ) ) continue;
				if( $attribute['enabled'] == 'logged' && ! $this->customer->isLogged() ) continue;
				
				if( ( $type == 'search' || $type == 'search_oc' ) && ( isset( $this->request->get['search'] ) || in_array( $core->route(), MegaFilterCore::$_searchRoute ) ) ) {
					continue;
				}
				
				$attribute['type']					= isset( $attribute['display_as_type'] ) ? $attribute['display_as_type'] : $type;
				$attribute['base_type']				= $type;
				$attribute['id']					= $type;
				$attribute['sort_order']			= empty( $attribute['sort_order'] ) ? 0 : (int) $attribute['sort_order'];
				$attribute['name']					= $this->language->get( 'name_' . $type );
				$attribute['seo_name']				= $type;
				$attribute['collapsed']				= empty( $attribute['collapsed'] ) ? false : $attribute['collapsed'];
				$attribute['select_deselect_all']	= empty( $attribute['select_deselect_all'] ) ? false : $attribute['select_deselect_all'];
				$attribute['display_list_of_items']	= empty( $attribute['display_list_of_items'] ) ? '' : $attribute['display_list_of_items'];
				$attribute['display_live_filter']	= empty( $attribute['display_live_filter'] ) ? '' : $attribute['display_live_filter'];
				
				if( 
					isset( $seo_config['trans'] ) 
						&& 
					isset( $seo_config['trans']['base_attribs'][$type][$this->config->get('config_language_id')] ) 
						&&
					$seo_config['trans']['base_attribs'][$type][$this->config->get('config_language_id')] !== ''
				) {
					$attribute['seo_name'] = $seo_config['trans']['base_attribs'][$type][$this->config->get('config_language_id')];
				}
				
				switch( $type ) {
					case 'manufacturers' : {
						if( NULL != ( $attribute['options'] = $this->getManufacturers( $core ) ) ) {
							if( in_array( $attribute['type'], array( 'image', 'image_radio', 'image_list_checkbox', 'image_list_radio' ) ) ) {
								list( $w, $h ) = $this->_imageSize();

								foreach( $attribute['options'] as $k => $v ) {
									$attribute['options'][$k]['image'] = self::parseUrl( empty( $v['image'] ) ? $this->model_tool_image->resize('no_image.png', $w, $h) : $this->model_tool_image->resize($v['image'], $w, $h) );

									if( empty( $attribute['options'][$k]['image'] ) ) {
										$attribute['options'][$k]['image'] = self::parseUrl( $this->model_tool_image->resize('no_image.png', $w, $h) );
									}
								}
							}
						}
						
						break;
					}
					case 'discounts' : {
						$attribute['options'] = $this->getDiscounts( $core );
						
						break;
					}
					case 'stock_status' : {
						$attribute['options'] = $this->getStockStatuses( $core );
					
						break;
					}
					case 'location' :
					case 'length' :
					case 'width' :
					case 'height' :
					case 'weight' :
					case 'mpn' : 
					case 'isbn' :
					case 'sku' :
					case 'upc' :
					case 'ean' :
					case 'jan' :
					case 'model' : {
						$attribute['options'] = $this->getOptionsByType( $type, $core );
						
						break;
					}
					case 'tags' : {
						if( $this->hasMfilterPlus() ) {
							$attribute['options'] = $this->getTags( $core );
						}
						
						break;
					}
				}
				
				if( ! empty( $attribute['options'] ) || in_array( $type, array( 'rating', 'price', 'search' ) ) ) {
					$attributes[] = $attribute;
				}
			}
		}
		
		/**
		 * sort
		 */
		usort( $attributes, array( 'ModelExtensionModuleMegaFilter', '_sortAttributes' ) );
		
		return $attributes;
	}
	
	/**
	 * Sort attributes
	 * 
	 * @param type $a
	 * @param type $b
	 * @return int 
	 */
	private static function _sortAttributes( $a, $b ) {
		$sa = (int) ( isset( $a['sort_order-sec'] ) ? $a['sort_order-sec'] : $a['sort_order'] );
		$sb = (int) ( isset( $b['sort_order-sec'] ) ? $b['sort_order-sec'] : $b['sort_order'] );
		
		if( $sa < $sb )
			return -1;
		
		if( $sa > $sb )
			return 1;
		
		return 0;
	}
	
	/**
	 * Remove special characters from URL
	 * 
	 * @param string $str
	 * @param bool $clearOn
	 * @return string 
	 */
	public function clear( $str, $clearOn = true ) {
		$str = str_replace(array(
			'`', '~', '!', '@', '#', '$', '%', '^', '*', '(', ')', '+', '=', '[', '{', ']', '}', '\\', '|', ';', ':', "'", '"', ',', '<', '.', '>', '/', '?'
		), ' ', str_replace(array(
			'&'
		), array(
			'and'
		), htmlspecialchars_decode( $str )) );		
		
		if( ! $clearOn )
			return mb_strtolower( trim( preg_replace( '/-+/', '-', preg_replace( '/ +/', '-', $str ) ), '-' ), 'utf-8' );
		
		$str = Mfilter_Helper::i()->convertValueToSeo( $str );
		$str = mb_strtolower( $str, 'utf-8' );
		$str = trim( preg_replace('/[^A-Z^a-z^0-9]+/','-', $str), '-');
		return preg_replace( '/-+/', '-', $str );
	}
}