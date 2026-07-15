<?php

/**
 * Mega Filter
 * 
 * Editing this file may result in loss of license which will be permanently blocked.
 * 
 * @license Commercial
 * @author info@ocdemo.eu
 */

class MegaFilterCore {	
	
	public static $_specialRoute	= array( 'product/special' );
	
	public static $_searchRoute		= array( 'product/search' );
	
	public static $_homeRoute		= array( 'common/home' );
	
	private static $_cache			= array();
	
	////////////////////////////////////////////////////////////////////////////
	
	private $_sql	= '';
	
	private $_data	= array();
	
	private $_ctrl	= NULL;
	
	private $_mfilterUrl	= '';
	
	private $_parseParams	= array();
	
	private $_params		= array();
	
	private $_attribs		= array();
	
	public $_settings		= array();
	
	public $_seo_settings	= array();
	
	private $_options		= array();
	
	private $_filters		= array();
	
	private $_categories	= array();
	
	private $_conditions	= array();
	
	private $_tm;
	
	private static $_hasFilters	= NULL;
	
	private static $_om = null;
	
	static public function newInstance( & $ctrl, $sql, array $data = array(), $settings = array() ) {
		return new MegaFilterCore( $ctrl, $sql, $data, $settings );
	}
	
	static public function hasFilters() {
		if( self::$_hasFilters === NULL ) {
			self::$_hasFilters = version_compare( VERSION, '1.5.5', '>=' );
		}
		
		return self::$_hasFilters;
	}
	
	static public function clearCache() {
		self::$_cache = array();
	}
	
	static private function _rget( & $ctrl, $name ) {
		if( isset( $ctrl->request->post[$name] ) ) {
			return $ctrl->request->post[$name];
		}
		
		if( isset( $ctrl->request->get[$name] ) ) {
			return $ctrl->request->get[$name];
		}
		
		return null;
	}
	
	private function rget( $name ) {
		return self::_rget( $this->_ctrl, $name );
	}
	
	static public function redirectToCorrectLang( & $ctrl, $row ) {
		$seo_settings = (array) $ctrl->config->get( 'mega_filter_seo' );
		
		if( ! empty( $seo_settings['redirect_to_correct_lang_by_seo_url'] ) && ! headers_sent() && null != ( $language = $ctrl->db->query( "SELECT * FROM `" . DB_PREFIX . "language` WHERE `language_id` = '" . (int) $row['language_id'] . "'" )->row ) ) {
			$ctrl->session->data['language'] = $language['code'];
			
			header('Refresh:0');
			
			exit;
		}
	}
	
	static public function prepareSeoParts( & $ctrl, $parts ) {
		$mfp_url_param = self::_mfpUrlParam( $ctrl );
		
		$mfp_seo_sep = self::_mfpSeoSep( $ctrl );
		
		if( null != ( $joinParts = implode( '/', $parts ) ) && preg_match( '#/?'.$mfp_seo_sep.'/([^,]+,[^/]+/?)+#', $joinParts, $matches ) ) {
			if( isset( $ctrl->request->get['route'] ) ) {
				$ctrl->request->get['route'] = preg_replace( '#/?'.$mfp_seo_sep.'/([^,]+,[^/]+/?)+#', '', $ctrl->request->get['route'] );
			}
		
			if( isset( $ctrl->request->get['_route_'] ) ) {
				$ctrl->request->get['_route_'] = preg_replace( '#/?'.$mfp_seo_sep.'/([^,]+,[^/]+/?)+#', '', $ctrl->request->get['_route_'] );
			}
			
			if( self::_rget( $ctrl, $mfp_url_param ) === null ) {
				$ctrl->request->get[$mfp_url_param] = preg_replace( '#^' . $mfp_seo_sep . '/#', '', trim( $matches[0], '/' ) );
			}
			
			$joinParts = preg_replace( '#/?'.$mfp_seo_sep.'/([^,]+,[^/]+/?)+#', '', $joinParts );
			
			if( ! $joinParts ) {
				$joinParts = 'common/home';
			}
			
			$parts = explode( '/', $joinParts );
		}
		
		$seo_settings = (array) $ctrl->config->get( 'mega_filter_seo' );

		if( ! empty( $seo_settings['auto_redirect_to_seo_aliases'] ) && ! empty( $ctrl->request->get[$mfp_url_param] ) && empty( $ctrl->request->get['mfilterAjax'] ) ) {
			$params = self::__parseParams( $ctrl->request->get[$mfp_url_param] );

			if( ! empty( $params[1] ) && ! empty( $params[2] ) ) {
				$temp = array();

				foreach( $params[1] as $k => $v ) {
					$params[2][$k] = explode( ',', $params[2][$k] );
					$params[2][$k] = array_map( 'urldecode', $params[2][$k] );
					$params[2][$k] = implode( ',', $params[2][$k] );

					$temp[] = $params[1][$k] . '[' . $params[2][$k] . ']';
				}
				
				$smp_language = null;

				if( $ctrl->config->get('smp_version') ) {
					if( ! empty( $ctrl->session->data['language'] ) ) {
						$smp_language = $ctrl->session->data['language'];
					} else if( ! empty( $_COOKIE['language'] ) ) {
						$smp_language = htmlspecialchars($_COOKIE['language'], ENT_COMPAT, 'UTF-8');
					} else {
						$smp_language = $this->config->get('config_language');
					}
					
					$smp_default_language = $ctrl->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$ctrl->config->get('config_store_id') . "' AND `code` = 'config' AND `key` = 'config_language'");

					if( $smp_default_language->num_rows ) {
						$smp_default_language = $smp_default_language->row['value'];
					} else {
						$smp_default_language = $ctrl->config->get('config_language');
					}

					if( $ctrl->config->get( 'smp_add_default_language_code_to_url' ) || $smp_language != $smp_default_language ) {
						$smp_language_code_alias = $ctrl->config->get( 'smp_language_code_alias' );

						if( $smp_language_code_alias && ! empty( $smp_language_code_alias[$smp_language] ) ){
							$smp_language =  $smp_language_code_alias[$smp_language];
						}
					} else {
						$smp_language = null;
					}
				}
				
				$query = $ctrl->db->query("
					SELECT
						*
					FROM
						`" . DB_PREFIX . "mfilter_url_alias`
					WHERE
						( 
							`path` = '" . $ctrl->db->escape( ( $smp_language ? $smp_language . '/' : '' ) . implode('/', $parts) ) . "' 
							" . ( $smp_language ? "OR `path` = '" . $ctrl->db->escape( implode('/', $parts) ) . "'" : '' ) . "
						)
							AND
						`mfp` = '" . $ctrl->db->escape( implode( ',', $temp ) ) . "' 
							AND
						`language_id` = " . (int) $ctrl->config->get('config_language_id') . " 
							AND
						`store_id` = " . (int) $ctrl->config->get('config_store_id') . "
				");
				
				if( $query->num_rows ) {						
					$ctrl->response->redirect(
						'http' . ( ! empty( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/.\\') . '/' . 
						( $smp_language ? $smp_language . '/' : '' ) . implode( '/', $parts ) . '/' . $query->row['alias'] . ( empty( $seo_settings['add_slash_at_the_end'] ) ? '' : '/' )
					);
				}
			}
		}
		
		return $parts;
	}
	
	static public function prepareSeoPart( & $ctrl, $part ) {
		$mfp_url_param = self::_mfpUrlParam( $ctrl );
		
		$mfp_seo_sep = self::_mfpSeoSep( $ctrl );
		
		if( preg_match( '/^'.$mfp_seo_sep.',([^\[]+\[[^\]]*\],?)+/', $part, $matches ) ) {
			if( isset( $ctrl->request->get['route'] ) ) {
				$ctrl->request->get['route'] = preg_replace( '/\/?'.$mfp_seo_sep.',([^\[]+\[[^\]]*\],?)+/', '', $ctrl->request->get['route'] );
			}

			if( isset( $ctrl->request->get['_route_'] ) ) {
				$ctrl->request->get['_route_'] = preg_replace( '/\/?'.$mfp_seo_sep.',([^\[]+\[[^\]]*\],?)+/', '', $ctrl->request->get['_route_'] );
			}

			if( self::_rget( $ctrl, $mfp_url_param ) === null ) {
				$ctrl->request->get[$mfp_url_param] = preg_replace( '/^'.$mfp_seo_sep.',/', '', $matches[0] );
			}
			
			return true;
		}
		
		return false;
	}
	
	static public function preparePath( & $ctrl, $path ) {
		if( $ctrl->config->get('smp_version') ) {
			if( ! empty( $ctrl->session->data['language'] ) ) {
				$smp_language = $ctrl->session->data['language'];
			} else if( ! empty( $_COOKIE['language'] ) ) {
				$smp_language = htmlspecialchars($_COOKIE['language'], ENT_COMPAT, 'UTF-8');
			} else {
				$smp_language = $ctrl->config->get('config_language');
			}
			
			$smp_default_language = $ctrl->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$ctrl->config->get('config_store_id') . "' AND `code` = 'config' AND `key` = 'config_language'");

			if( $smp_default_language->num_rows ) {
				$smp_default_language = $smp_default_language->row['value'];
			} else {
				$smp_default_language = $ctrl->config->get('config_language');
			}
			
			if( $ctrl->config->get( 'smp_add_default_language_code_to_url' ) || $smp_default_language != $smp_language ) {
				$smp_language_code_alias = $ctrl->config->get( 'smp_language_code_alias' );

				if( $smp_language_code_alias && ! empty( $smp_language_code_alias[$smp_language] ) ){
					$smp_language =  $smp_language_code_alias[$smp_language];
				}

				$path = $smp_language . '/' . $path;
			}
		}
		
		return $path;
	}
	
	public function getJsonData( array $types, $idx = NULL ) {
		$json		= array();
		
		foreach( $types as $type ) {
			if( in_array( $type, array( 'manufacturers', 'stock_status', 'rating', 'price', 'discounts' ) ) ) {
				switch( $type ) {
					case 'stock_status'		: $json[$type] = $this->getCountsByStockStatus(); break;
					case 'manufacturers'	: $json[$type] = $this->getCountsByManufacturers(); break;
					case 'rating'			: $json[$type] = $this->getCountsByRating(); break;
					case 'price'			: $json[$type] = $this->getMinMaxPrice(); break;
					case 'discounts'		: $json[$type] = $this->getCountsByDiscounts(); break;
				}
			} else if( in_array( $type, array( 'location', 'length', 'width', 'height', 'weight', 'mpn', 'isbn', 'sku', 'upc', 'ean', 'jan', 'model' ) ) ) {
				$json[$type] = $this->getCountsByBaseType( $type );
			} else {
				$url_param = self::_mfpUrlParam( $this->_ctrl );
						
				if( $url_param != 'mfp' ) {
					$this->_ctrl->request->get['mfp'] = $this->rget( $url_param ) !== null ? $this->rget( $url_param ) : null;
				}
				
				switch( $type ) {
					case 'attribute'		:
					case 'attributes'		: $json['attributes'] = $this->getCountsByAttributes(); break;
					case 'option'			:
					case 'options'			: $json['options'] = $this->getCountsByOptions(); break;
					case 'filter'			:
					case 'filters'			: if( self::hasFilters() ) { $json['filters'] = $this->getCountsByFilters(); } break;
					case 'tags'				: $json['tags'] = $this->getCountsByTags(); break;
					case 'categories:cat_checkbox' : {
						$json[$type] = $this->getTreeCategories( null, 'checkbox' );
						
						break;
					}
					case 'categories:tree'	: {
						$json[$type] = $this->getTreeCategories( null, 'tree' );
						
						break;
					}
					case 'vehicles'			: {
						$json['vehicles'] = array();
						
						foreach( $this->_ctrl->model_extension_module_mega_filter->vehiclesToJson( $idx, $this, array() ) as $k => $v ) {
							$json['vehicles'][$k] = $v;
						}
						
						break;
					}
					case 'levels'			: {
						$json['levels'] = array();
						
						foreach( $this->_ctrl->model_extension_module_mega_filter->levelsToJson( $idx, $this, array() ) as $k => $v ) {
							$json['levels'][$k] = $v;
						}
						
						break;
					}
				}
						
				if( $url_param != 'mfp' ) {
					unset( $this->_ctrl->request->get['mfp'] );
				}
			}
		}
		
		if( 
			$this->rget( $this->mfpUrlParam() ) !== null && 
			NULL != ( $mfilterConfigSeo = $this->_ctrl->config->get( 'mega_filter_seo' ) ) && 
			( ! empty( $mfilterConfigSeo['enabled'] ) || ! empty( $mfilterConfigSeo['aliases_enabled'] ) )
		) {
			$sql = "
				SELECT 
					{__mfp_select__}
				FROM 
					`" . DB_PREFIX . "mfilter_url_alias` 
				WHERE 
					{__mfp_conditions__}
				LIMIT
					1
			";
			
			$mfp = $this->rget( $this->mfpUrlParam() );
			
			if( $this->rget( 'mfilterPath' ) ) {
				$mfp = preg_replace( '/(,?)path\['.preg_quote($this->rget( 'mfilterPath' ),'/').'\](,?)/', '$1$2', $mfp );
				$mfp = trim( str_replace( '],,', '],', $mfp ), ',' );
			}
			
			$matches = self::__parseParams( $mfp );
			
			$params = array();
			
			if( ! empty( $matches[1] ) && ! empty( $matches[2] ) ) {
				foreach( $matches[1] as $k => $v ) {
					$matches[2][$k] = explode(',', $matches[2][$k]);
					$matches[2][$k] = implode(',', $matches[2][$k]);
					
					$params[] = $matches[1][$k] . '[' . $matches[2][$k] . ']';
				}
				
				$mfp = implode(',', $params);
			}
			
			$path = ! $this->rget( 'mfilterLPath' ) ? '' : trim( $this->rget( 'mfilterLPath' ), '/' );
			
			if( isset( $_SERVER['SCRIPT_NAME'] ) ) {
				if( null != ( $dir = trim(dirname($_SERVER['SCRIPT_NAME']), '/.\\') ) ) {
					$path = trim( preg_replace( '/^' . preg_quote( $dir, '/' ) . '/', '', $path ), '/' );
				}
			}
			
			$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
				'{__mfp_select__}' => array(
					'*',
				),
				'{__mfp_conditions__}' => array(
					"`mfp` = '" . $this->_ctrl->db->escape( $mfp ) . "'",
					"`language_id` = '" . (int) $this->_ctrl->config->get( 'config_language_id' ) . "'",
					"`store_id` = '" . (int) $this->_ctrl->config->get( 'config_store_id' ) . "'",
					"( `path` = '' OR `path` = '" . $this->_ctrl->db->escape( $path ) . "' )"
				)
			), 'aliases');
			
			$mfilter_query = $this->_ctrl->db->query( $sql );
			
			if( $mfilter_query->num_rows ) {
				$json['url_alias'] = $mfilter_query->row['alias'];
				$json['seo_data'] = array(
					'meta_title' => $mfilter_query->row['meta_title'],
					'meta_description' => $mfilter_query->row['meta_description'],
					'meta_keyword' => $mfilter_query->row['meta_keyword'],
					'h1' => $mfilter_query->row['h1'],
					'description' => html_entity_decode($mfilter_query->row['description'], ENT_QUOTES, 'UTF-8'),
				);
			}
		}
		
		return $json;
	}
	
	private static function _mfpSeoSep( & $ctrl ) {
		if( null != ( $mfilter_seo_sep = $ctrl->config->get('mfilter_seo_sep') ) ) {
			return isset( $mfilter_seo_sep[$ctrl->config->get('config_language_id')] ) ? $mfilter_seo_sep[$ctrl->config->get('config_language_id')] : 'mfp';
		}
		
		return 'mfp';
	}
	
	private static function _mfpUrlParam( & $ctrl ) {
		return $ctrl->config->get('mfilter_url_param')?$ctrl->config->get('mfilter_url_param'):'mfp';
	}
	
	private function mfpSeoSep() {
		return self::_mfpSeoSep( $this->_ctrl );
	}
	
	private function mfpUrlParam() {
		return self::_mfpUrlParam( $this->_ctrl );
	}
	
	private function __construct( & $ctrl, $sql, array $data = array(), array $settings = array() ) {		
		$this->_ctrl	= & $ctrl;
		$this->_sql		= $sql;
		$this->_data	= self::_getData( $ctrl );
		$this->_tm		= time();
		
		foreach( $data as $k => $v ) {
			$this->_data[$k] = $v;
		}
		
		$this->_settings		= $this->findSettings( $settings );
		$this->_seo_settings	= (array) $this->_ctrl->config->get( 'mega_filter_seo' );
		
		$this->parseParams();
		
		if( self::$_om === null ) {
			self::$_om = $this->_ctrl->model_extension_module_mega_filter->iom() ? true : false;
		}
		
		if( 
			! empty( $this->_seo_settings['enabled'] ) &&
			( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] != 'POST' ) && 
			empty( $ctrl->request->post['mfilterIdx'] ) && 
			empty( $ctrl->request->get['mfilterIdx'] ) && 
			! empty( $ctrl->request->get[(isset($this->_seo_settings['url_parameter'])?$this->_seo_settings['url_parameter']:'mfp')] ) 
		) {
			if( isset( $_SERVER['REQUEST_URI'] ) ) {
				$uri = $_SERVER['REQUEST_URI'];
				$query = '';
				
				if( false !== ( $pos = mb_strpos( $uri, '?', 0, 'utf8' ) ) ) {
					$query = mb_substr( $uri, $pos, mb_strlen( $uri, 'utf8' ), 'utf8' );
					$uri = mb_substr( $uri, 0, $pos, 'utf8' );
				}
				
				if( 
					(
						( ! empty( $this->_seo_settings['add_slash_at_the_end'] ) && ! preg_match( '/[^\/]\/$/', $uri ) ) ||
						( empty( $this->_seo_settings['add_slash_at_the_end'] ) && preg_match( '/\/$/', $uri ) )
					) && 
					! preg_match( '/index.php$/', $uri ) 
				) {
					$url = 'http';
					$url .= ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) || ( isset( $_SERVER['PORT'] ) && $_SERVER['PORT'] == 443 )  ? 's' : '';
					$url .= '://';
					$url .= $_SERVER['HTTP_HOST'];
					$url .= '/' . trim( $uri, '/' );
					$url .= empty( $this->_seo_settings['add_slash_at_the_end'] ) ? '' : '/';

					if( $query ) {
						$url .= $query;
					}
					
					header('HTTP/1.1 301 Moved Permanently');
					header('Location: ' . $url);
					exit();
				}
			}
		}
	}
	
	private function _parseUrl() {
		$this->_mfilterUrl	= $this->rget( $this->mfpUrlParam() ) !== null ? $this->rget( $this->mfpUrlParam() ) : '';
		
		if( ! empty( $this->_settings['in_stock_default_selected'] ) ) {
			if( false === mb_strpos( $this->_mfilterUrl, 'stock_status', 0, 'utf-8' ) ) {
				if( ! empty( $this->_seo_settings['enabled'] ) ) {
					$this->_mfilterUrl .= $this->_mfilterUrl ? '/' : '';
					$this->_mfilterUrl .= 'stock_status,' . implode( ',', $this->inStockStatusSelected() );
				} else {
					$this->_mfilterUrl .= $this->_mfilterUrl ? ',' : '';
					$this->_mfilterUrl .= 'stock_status[' . implode( ',', $this->inStockStatusSelected() ) . ']';
				}
			}
		}
	}
	
	protected function findSettings( $settings ) {
		if( $settings ) {	
			if( isset( $settings['configuration'] ) ) {
				foreach( $settings['configuration'] as $k => $v ) {
					$settings[$k] = $v;
				}
			}
		
			return $settings;
		}
		
		$cname = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : __METHOD__;
		
		if( isset( self::$_cache[__METHOD__][$cname] ) ) {
			return self::$_cache[__METHOD__][$cname];
		}
		
		$route = isset( $this->_ctrl->request->get['route'] ) ? (string) $this->_ctrl->request->get['route'] : 'common/home';
		
		$layout_id = 0;

		if( $route == 'product/category' && isset( $this->_ctrl->request->get['path'] ) ) {
			$path = explode('_', (string)$this->_ctrl->request->get['path']);
			
			if( NULL != ( $query = $this->_ctrl->db->query("SELECT * FROM `" . DB_PREFIX . "category_to_layout` WHERE `category_id` = '" . (int) end( $path ) . "' AND `store_id` = '" . (int) $this->_ctrl->config->get('config_store_id') . "'")->row ) ) {
				$layout_id = $query['layout_id'];
			}
		} else if( $route == 'product/product' && isset( $this->_ctrl->request->get['product_id'] ) ) {
			if( NULL != ( $query = $this->_ctrl->db->query("SELECT * FROM `" . DB_PREFIX . "product_to_layout` WHERE `product_id` = '" . (int) $this->_ctrl->request->get['product_id'] . "' AND `store_id` = '" . (int)$this->_ctrl->config->get('config_store_id') . "'")->row ) ) {
				$layout_id = $query['layout_id'];
			}	
		} else if( $route == 'information/information' && isset( $this->_ctrl->request->get['information_id'] ) ) {
			if( NULL != ( $query = $this->_ctrl->db->query("SELECT * FROM `" . DB_PREFIX . "information_to_layout` WHERE `information_id` = '" . (int) $this->_ctrl->request->get['information_id'] . "' AND `store_id` = '" . (int)$this->_ctrl->config->get('config_store_id') . "'")->row ) ) {
				$layout_id = $query['layout_id'];
			}
		}

		if( ! $layout_id ) {
			if( NULL != ( $query = $this->_ctrl->db->query("SELECT * FROM `" . DB_PREFIX . "layout_route` WHERE '" . $this->_ctrl->db->escape($route) . "' LIKE `route` AND `store_id` = '" . (int)$this->_ctrl->config->get('config_store_id') . "' ORDER BY `route` DESC LIMIT 1")->row ) ) {
				$layout_id = $query['layout_id'];
			}

			if( ! $layout_id ) {
				$layout_id = $this->_ctrl->config->get('config_layout_id');
			}
		}
		
		$settings = $this->_ctrl->config->get('mega_filter_settings');
		
		$config = null;
		
		if( isset( $this->_ctrl->request->post['mfilterIdx'] ) ) {
			$config = $this->_ctrl->model_extension_module_mega_filter->getModuleSettings( $this->_ctrl->request->post['mfilterIdx'] );
		} else if( isset( $this->_ctrl->request->get['mfilterIdx'] ) ) {
			$config = $this->_ctrl->model_extension_module_mega_filter->getModuleSettings( $this->_ctrl->request->get['mfilterIdx'] );
		} else {
			foreach( $this->_ctrl->db->query("SELECT * FROM `" . DB_PREFIX . "layout_module` WHERE `layout_id` = '" . (int)$layout_id . "' AND `code` LIKE 'mega_filter%' ORDER BY `sort_order`")->rows as $module ) {
				$part = explode( '.', $module['code'] );
				
				if( isset( $part[1] ) ) {
					$config = $this->_ctrl->model_extension_module_mega_filter->getModuleSettings( $part[1] );
					
					if( empty( $config['status'] ) ) {
						$config = null;
						
						continue;
					}
		
					if( ! in_array( $this->_ctrl->config->get( 'config_store_id' ), $config['store_id'] ) ) {
						$config = null;
						
						continue;
					}
					
					if( isset( $config['layout_id'] ) && is_array( $config['layout_id'] ) ) {
						if( in_array( $settings['layout_c'], $config['layout_id'] ) && isset( $this->_ctrl->request->get['path'] ) ) {		
							if( ! empty( $config['category_id'] ) ) {
								$categories	= explode( '_', $this->_ctrl->request->get['path'] );

								if( ! empty( $config['category_id_with_childs'] ) ) {
									$is = false;

									foreach( $categories as $category_id ) {
										if( in_array( $category_id, $config['category_id'] ) ) {
											$is = true; break;
										}
									}

									if( ! $is ) {
										$config = null;
										
										continue;
									}
								} else {
									$category_id	= end( $categories );

									if( ! in_array( $category_id, $config['category_id'] ) ) {
										$config = null;
										
										continue;
									}
								}
							}
							
							if( ! empty( $config['hide_category_id'] ) ) {
								$categories	= explode( '_', $this->_ctrl->request->get['path'] );

								if( ! empty( $config['hide_category_id_with_childs'] ) ) {						
									foreach( $categories as $category_id ) {
										if( in_array( $category_id, $config['hide_category_id'] ) ) {
											$config = null;
											
											break;
										}
									}
								} else {
									$category_id = array_pop( $categories );

									if( in_array( $category_id, $config['hide_category_id'] ) ) {
										$config = null;
									}
								}
							}
						}
					}
					
					if( ! empty( $config['customer_groups'] ) ) {
						$customer_group_id = $this->_ctrl->customer->isLogged() ? $this->_ctrl->customer->getGroupId() : $this->_ctrl->config->get( 'config_customer_group_id' );

						if( ! in_array( $customer_group_id, $config['customer_groups'] ) ) {
							$config = null;
						}
					}
					
					if( $config ) {
						break;
					}
				}
			}
		}
		
		if( $config !== null ) {
			if( isset( $config['configuration'] ) ) {
				foreach( $config['configuration'] as $k => $v ) {
					$settings[$k] = $v;
				}
			}
			
			if( isset( $settings['base_attribs'] ) ) {
				foreach( $settings['base_attribs'] as $k => $v ) {
					$settings['base_attribs'][$k] = $v;
				}
			}
		}
		
		self::$_cache[__METHOD__][$cname] = $settings;
		
		return self::$_cache[__METHOD__][$cname];
	}
	
	protected function seoSettings() {
		return (array) $this->_ctrl->config->get( 'mega_filter_seo' );
	}
	
	protected function isSeoEnabled() {
		$settings = (array) $this->seoSettings();
		
		return ! empty( $settings['enabled'] );
	}
	
	protected function valuesAreLinks() {
		$settings = $this->seoSettings();
		
		return ! empty( $settings['values_are_links'] );
	}
	
	public function addParamToCurrentUrl( array $data, $param_name, $param_value ) {
		if( ! $this->valuesAreLinks() ) {
			return $data;
		}
		
		$route = 'common/home';
		
		if( isset( $this->_ctrl->request->get['route'] ) ) {
			$route = $this->_ctrl->request->get['route'];
		} else if( isset( $this->_ctrl->request->get['_route_'] ) ) {
			$route = $this->_ctrl->request->get['_route_'];
		}
		
		if( $route == 'common/home' ) {
			$route = 'extension/module/mega_filter/results';
		}
		
		$params = array_replace( $this->_ctrl->request->get, $this->_ctrl->request->post );
		
		$url_param = $this->mfpUrlParam();
		
		$to_remove = array( 'mfp_temp', 'route', '_route_', $url_param );
		
		foreach( $to_remove as $name ) {
			if( isset( $params[$name] ) ) {
				unset( $params[$name] );
			}
		}
		
		$mfp_params = $this->_params;
		
		if( ! isset( $mfp_params[$param_name] ) ) {
			$mfp_params[$param_name] = array();
		}
		
		if( false !== ( $idx = array_search( $param_value, $mfp_params[$param_name] ) ) ) {
			unset( $mfp_params[$param_name][$idx] );
		} else {
			$mfp_params[$param_name][] = $param_value;
		}
		
		foreach( $mfp_params as $name => $value ) {
			foreach( $value as $k2 => $v2 ) {
				$mfp_params[$name][$k2] = urlencode( $this->encodeUrl( $v2 ) );
			}
		}
		
		$mfp_alias = array();
		
		if( $this->isSeoEnabled() ) {
			$mfp = array();
			
			foreach( $mfp_params as $name => $value ) {
				if( $value ) {
					$mfp[] = $name . ',' . implode( ',', $value );
					$mfp_alias[] = $name . '[' . implode( ',', $value ) . ']';
				}
			}
			
			$mfp = implode( '/', $mfp );
			$mfp_alias = implode( ',', $mfp_alias );
		} else {
			$mfp = array();
			
			foreach( $mfp_params as $name => $value ) {
				if( $value ) {
					$mfp[] = $name . '[' . implode( ',', $value ) . ']';
				}
			}			
			
			if( $mfp ) {
				$params[$url_param] = implode( ',', $mfp );
			}
		}
		
		$data['url'] = $this->_ctrl->url->link( $route, http_build_query( $params ) );
		$data['url_alias'] = $data['url_alias_params'] = $data['url_alias_path'] = '';
		
		if( $this->isSeoEnabled() && $mfp ) {
			$aliases = $this->getCurrentPathAliases();
			
			$alias = isset( $aliases[$mfp_alias] ) ? $aliases[$mfp_alias] : null;
			
			$parsed = parse_url( $data['url'] );
			
			$data['url'] = '';
			$data['url'] .= empty( $parsed['host'] ) ? '' : '//' . $parsed['host'];
			$data['url'] .= empty( $parsed['port'] ) ? '' : ':' . $parsed['port'];
			$data['url'] .= rtrim( empty( $parsed['path'] ) ? '/' : $parsed['path'], '/' ) . '/';
			
			if( $alias ) {				
				$data['url'] .= $alias;
			} else {
				$data['url'] .= $this->mfpSeoSep() . '/' . $mfp;
			}
			
			$data['url'] .= empty( $parsed['query'] ) ? '' : '?' . $parsed['query'];
		}
		
		return $data;
	}
	
	public function getCurrentPath() {
		$path = '';

		if( $this->rget('path') !== null ) {
			if( ! isset( self::$_cache['paths'][$this->rget('path')] ) ) {
				$categories = explode( '_', $this->rget('path') );

				self::$_cache['paths'][$this->rget('path')] = '';
				
				foreach ($categories as $category_id) {
					$query = $this->_ctrl->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'category_id=" . (int)$category_id . "' AND `language_id` = " . (int) $this->_ctrl->config->get('config_language_id') );

					if ($query->num_rows && $query->row['keyword']) {
						self::$_cache['paths'][$this->rget('path')] .= self::$_cache['paths'][$this->rget('path')] ? '/' : '';
						self::$_cache['paths'][$this->rget('path')] .= $query->row['keyword'];
					} else {
						self::$_cache['paths'][$this->rget('path')] = '';

						break;
					}
				}
			}

			$path = self::$_cache['paths'][$this->rget('path')];
		}
		
		return $path;
	}
	
	public function getCurrentPathAliases() {
		$path = $this->getCurrentPath();

		if( $this->_ctrl->config->get('smp_version') ) {
			if( ! empty( $this->_ctrl->session->data['language'] ) ) {
				$smp_language = $this->_ctrl->session->data['language'];
			} else if( ! empty( $_COOKIE['language'] ) ) {
				$smp_language = htmlspecialchars($_COOKIE['language'], ENT_COMPAT, 'UTF-8');
			} else {
				$smp_language = $this->_ctrl->config->get('config_language');
			}

			$smp_default_language = $this->_ctrl->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$this->_ctrl->config->get('config_store_id') . "' AND `code` = 'config' AND `key` = 'config_language'");

			if( $smp_default_language->num_rows ) {
				$smp_default_language = $smp_default_language->row['value'];
			} else {
				$smp_default_language = $this->config->get('config_language');
			}

			if( $this->_ctrl->config->get( 'smp_add_default_language_code_to_url' ) || $smp_default_language != $smp_language ) {				
				$smp_language_code_alias = $this->_ctrl->config->get( 'smp_language_code_alias' );

				if( $smp_language_code_alias && ! empty( $smp_language_code_alias[$smp_language] ) ){
					$smp_language =  $smp_language_code_alias[$smp_language];
				}

				$path = $smp_language . '/' . $path;
			}
		}
		
		if( ! isset( self::$_cache['aliases'][$path] ) ) {
			self::$_cache['aliases'][$path] = array();
			
			foreach( $alias = $this->_ctrl->db->query( "
				SELECT 
					* 
				FROM 
					`" . DB_PREFIX . "mfilter_url_alias` 
				WHERE
					`language_id` = " . (int) $this->_ctrl->config->get('config_language_id') . " AND
					`store_id` = " . (int) $this->_ctrl->config->get('config_store_id') . " AND
					( `path` = '' OR `path` = '" . $this->_ctrl->db->escape( $path ) . "' )
			")->rows as $row ) {
				self::$_cache['aliases'][$path][$row['mfp']] = $row['alias'];
			}
		}
		
		if( isset( self::$_cache['aliases'][''] ) && isset( self::$_cache['aliases'][$path] ) ) {
			return array_replace( self::$_cache['aliases'][''], self::$_cache['aliases'][$path] );
		} else if( isset( self::$_cache['aliases'][$path] ) ) {
			return self::$_cache['aliases'][$path];
		}
		
		return self::$_cache['aliases'][''];
	}
	
	public function cacheName() {
		return md5( 
			$this->_mfilterUrl . 
			( ! $this->rget('mfp_temp') ? '' : $this->rget('mfp_temp') ) . 
			( ! $this->rget('mfilterAjax') ? '0' : '1' ) . 
			serialize( $this->_data ) . 
			$this->_ctrl->config->get( 'config_language_id' ) .
			$this->_ctrl->config->get( 'config_store_id' ) .
			$this->getCurrencyId() .
			$this->_ctrl->customer->isLogged() .
			( isset( $this->_ctrl->request->get['route'] ) ? $this->_ctrl->request->get['route'] : '' ) . 
			serialize( $this->_ctrl->model_extension_module_mega_filter->prepareBaseConditions(array()) ) .
			serialize( $this->_ctrl->config->get( 'mfp_cache_name' ) )
		);
	}
	
	static public function _parsePath( $path ) {
		$path = explode( ',', $path );
		$vals = array();
		
		foreach( $path as $v ) {
			$v = explode( '_', $v );
			$vals[] = array_pop( $v );
		}
		
		return implode( ',', $vals );
	}
	
	static public function _getData( & $ctrl ) {
		$data = array();
		
		if( ! empty( $ctrl->request->get['category_id'] ) ) {
			$data['filter_category_id'] = (int) $ctrl->request->get['category_id'];
		} else if( self::_rget($ctrl, 'path') ) {
			$data['filter_category_id'] = self::_parsePath( (string) self::_rget($ctrl, 'path') );
		}
		
		if( ! empty( $ctrl->request->get['sub_category'] ) ) {
			$data['filter_sub_category'] = $ctrl->request->get['sub_category'];
		} else if( ! in_array( self::_route( $ctrl ), array( 'common/home' ) ) ) {
			if( self::_showProductsFromSubcategories( $ctrl ) ) {
				$data['filter_sub_category'] = '1';
			}
		}
		
		if( ! empty( $ctrl->request->get['filter'] ) ) {
			$data['filter_filter'] = $ctrl->request->get['filter'];
		}
		
		if( ! empty( $ctrl->request->get['description'] ) ) {
			$data['filter_description'] = $ctrl->request->get['description'];
		}
		
		if( ! empty( $ctrl->request->get['filter_tag'] ) ) {
			$data['filter_tag'] = $ctrl->request->get['filter_tag'];
		} else if( ! empty( $ctrl->request->get['tag'] ) ) {
			$data['filter_tag'] = $ctrl->request->get['tag'];
		} else if( ! empty( $ctrl->request->get['search'] ) ) {
			$data['filter_tag'] = $ctrl->request->get['search'];
		}
		
		if( ! empty( $ctrl->request->get['manufacturer_id'] ) ) {
			$data['filter_manufacturer_id'] = (int) $ctrl->request->get['manufacturer_id'];
		}
		
		if( ! empty( $ctrl->request->get['search'] ) ) {
			$data['filter_name'] = (string) $ctrl->request->get['search'];
		}
		
		return $data;
	}
	
	private static function _showProductsFromSubcategories( & $ctrl ) {
		$settings = $ctrl->config->get('mega_filter_settings');
		
		if( empty( $settings['show_products_from_subcategories'] ) ) {
			return false;
		}
		
		if( ! empty( $settings['level_products_from_subcategories'] ) ) {
			$level = (int) $settings['level_products_from_subcategories'];
			$path = explode( '_', ! self::_rget( $ctrl, 'path' ) ? '' : self::_rget( $ctrl, 'path' ) );
			
			if( $path && count( $path ) < $level ) {
				return false;
			}
		}
		
		return true;
	}
	
	public function getParseParams() {
		return $this->_parseParams;
	}
	
	public function getData() {
		return $this->_data;
	}
	
	public function inStockStatus() {
		$inStockStatus = empty( $this->_settings['in_stock_status'] ) ? 7 : $this->_settings['in_stock_status'];
		
		return (int) is_array( $inStockStatus ) ? current( $inStockStatus ) : $inStockStatus;
	}
	
	public function inStockStatusSelected() {
		return empty( $this->_settings['in_stock_status_selected'] ) ? array( $this->inStockStatus() ) : $this->_settings['in_stock_status_selected'];
	}
	
	protected function encodeUrl( $url ) {
		return str_replace(array(
			',', '[', ']', '&quot;', "'", '&amp;', '/', '+', '%', ';', ':', '&lt;', '&gt;', '#', '?',
		), array(
			'LA==', 'Ww==', 'XQ==', 'Ig==', 'Jw==', 'Jg==', 'Lw==', 'Kw==', 'JQ==', 'Ow==', 'Og==', 'PA==', 'Pg==', 'Iw==', 'Pw==',
		), $url);
	}
	
	protected function decodeUrl( $url ) {
		return str_replace(array(
			'LA==', 'Ww==', 'XQ==', 'Ig==', 'Jw==', 'Jg==', 'Lw==', 'Kw==', 'JQ==', 'Ow==', 'Og==', 'PA==', 'Pg==', 'Iw==', 'Pw==',
		), array(
			',', '[', ']', '&quot;', "'", '&amp;', '/', '+', '%', ';', ':', '&lt;', '&gt;', '#', '?',
		), $url);
	}
	
	public static function __parseParams( $mfp ) {
		preg_match_all( '/([^,]+)\[([^\]]*)\]/', $mfp, $matches );
			
		if( empty( $matches[0] ) ) {
			$matches = array();
			$parts = explode( '/', $mfp );

			foreach( $parts as $part ) {
				$part = explode( ',', $part );

				$matches[0][] = true;
				$matches[1][] = array_shift( $part );
				$matches[2][] = implode( ',', $part );
			}
		}
		
		return $matches;
	}
	
	public function parseParams() {
		$this->_parseUrl();
		
		$this->_parseParams = array();
		$this->_attribs		= array();
		$this->_options		= array();
		$this->_filters		= array();
		$this->_categories	= array();
		$this->_conditions	= array(
			'out' => array(),
			'in' => array()
		);
		
		$trans = isset( $this->_seo_settings['trans'] ) ? $this->_seo_settings['trans'] : array();
		
		foreach( $trans as $k => $v ) {
			foreach( $v as $k1 => $v1 ) {
				if( isset( $v1[$this->_ctrl->config->get('config_language_id')] ) && $v1[$this->_ctrl->config->get('config_language_id')] !== '' ) {
					$trans[$k][$k1] = $v1[$this->_ctrl->config->get('config_language_id')];
				} else {
					unset( $trans[$k][$k1] );
				}
			}
			
			$trans[$k] = array_combine( array_values( $trans[$k] ), array_keys( $trans[$k] ) );
		}
		
		if( $this->_mfilterUrl ) {
			$matches = self::__parseParams( $this->_mfilterUrl );
			
			if( ! empty( $matches[0] ) ) {
				foreach( $matches[0] as $k => $match ) {
					if( ! isset( $matches[1][$k] ) || $matches[1][$k] === '' ) {			
						continue;
					}
					
					$key = $matches[1][$k];
					
					if( isset( $trans['base_attribs'][$key] ) ) {
						$key = $trans['base_attribs'][$key];
					}
					
					if( ! isset( $matches[2][$k] ) ) {
						if( $key == 'stock_status' && ! empty( $this->_settings['in_stock_default_selected'] ) ) {
							$this->_params[$key] = $this->_parseParams[$key] = array();
						}
						
						continue;
					}
					
					$value	= explode( ',', $matches[2][$k] );
					
					foreach( $value as $kk => $vv ) {
						$value[$kk]	= $this->decodeUrl( $vv );
					}
					
					$this->_params[$key] = $value;
					
					switch( $key ) {
						case 'width' :
						case 'height' :
						case 'weight' :
						case 'length' : {
							$ftmp = "( `p`.`" . $key . "` / ( SELECT `value` FROM `" . DB_PREFIX . ( $key == 'weight' ? 'weight' : 'length' ) . "_class` WHERE `" . ( $key == 'weight' ? 'weight' : 'length' ) . "_class_id` = `p`.`" . ( $key == 'weight' ? 'weight' : 'length' ) . "_class_id` LIMIT 1 ) )";
							
							if( isset( $value[0] ) && isset( $value[1] ) ) {
								sort( $value, SORT_NUMERIC );
								
								$this->_params[$key] = $value;
								
								$this->_conditions['in'][$key] = "( " . $ftmp . " >= " . (float) $value[0] . " AND " . $ftmp . " <= " . (float) $value[count($value)-1] . ')';
							} else {
								$this->_conditions['in'][$key] = "( " . $ftmp . " >= " . (float) $value[0] . " AND " . $ftmp . " <= " . (float) $value[0] . ')';
							}
							
							break;
						}
						case 'model' :
						case 'sku' :
						case 'upc' :
						case 'ean' :
						case 'jan' :
						case 'isbn' :
						case 'mpn' :
						case 'location' : {
							$ftmp = $value;
							
							if( isset( $this->_settings['base_attribs'][$key]['display_as_type'] ) && $this->_settings['base_attribs'][$key]['display_as_type'] == 'text' ) {
								foreach( $value as $k => $v ) {
									$ftmp[$k] = '%' . $v . '%';
								}
							}
							
							$this->_conditions['in'][$key] = "( `p`.`" . $key . "` LIKE " . implode( " OR `p`.`" . $key . "` LIKE ", $this->parseArrayToStr( $ftmp ) ) . ' )';
							
							break;
						}
						case 'search_oc' :
						case 'search' : {
							if( isset( $value[0] ) ) {
								$this->_data['filter_name'] = $value[0];
								$this->_data['filter_mf_name'] = $value[0];
							} else {
								$value = NULL;
							}
							
							break;
						}
						case 'price' : {
							if( isset( $value[0] ) && isset( $value[1] ) ) {
								$this->_conditions['out']['mf_price'] = '( `mf_price` > ' . ( (int) $value[0] - 1 ) . ' AND `mf_price` < ' . ( (int) $value[1] + 1 ) . ')';
							} else {
								$value = NULL;
							}
							
							break;
						}
						case 'manufacturers' : {
							$this->_conditions['in']['manufacturers'] = '`p`.`manufacturer_id` IN(' . implode( ',', $this->aliasesToIds( 'manufacturer_id', $value ) ) . ')';
							
							break;
						}
						case 'discounts' : {
							$this->_conditions['in']['discounts'] = "ROUND( 100 - ( ( ( " . $this->priceCol('') . " ) / `p`.`price` ) * 100 ) ) IN(" . implode( ',', $this->parseArrayToInt( $value ) ) . ')';
							
							break;
						}
						case 'tags' : {
							if( $this->hasMfilterPlus() ) {
								$sql = "SELECT {__mfp_select__} FROM `" . DB_PREFIX . "mfilter_tags` WHERE {__mfp_conditions__}";
								$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
									'{__mfp_select__}' => array(
										'`mfilter_tag_id`',
									),
									'{__mfp_conditions__}' => array(
										"`tag` IN(" . implode( ',', $this->parseArrayToStr( $value ) ) . ") AND `language_id` = " . (int) $this->_ctrl->config->get('config_language_id')
									)
								), 'tags');
								$rows = $this->_ctrl->db->query( $sql )->rows;
								$tags = array();
								
								foreach( $rows as $row ) {
									$tags[] = 'FIND_IN_SET( ' . $row['mfilter_tag_id'] . ', `p`.`mfilter_tags` )';
								}
								
								if( $tags ) {
									$this->_conditions['in']['tags'] = '( ' . implode( ' OR ', $tags ) . ' )';
								}
							}
							
							break;
						}
						case 'rating' : {
							$sql = array();
							
							foreach( $this->parseArrayToInt( $value ) as $rating ) {
								switch( $rating ) {
									case '1' :
									case '2' :
									case '3' :
									case '4' : {
										$sql[] = '( `mf_rating` >= ' . $rating . ' AND `mf_rating` < ' . ( $rating + 1 ) . ')'; 
										
										break;
									}
									case '5' : {
										$sql[] = '`mf_rating` = 5';
									}
								}
							}
							
							if( $sql )
								$this->_conditions['out']['mf_rating'] = '(' . implode( ' OR ', $sql ) . ')';
							
							break;
						}
						case 'stock_status' : {
							$value = $this->parseArrayToInt( $value );
							
							if( $value && $value[0] != '0' ) {									
								$this->_conditions['in']['stock_status'] = '(' . sprintf( 'IF( `p`.`quantity` > 0, %s, `p`.`stock_status_id` ) IN(%s)', 
									$this->inStockStatus(),
									implode( ',', $value )
								) . ')';
							}
							
							break;
						}
						case 'path' : {
							if( isset( $value[0] ) ) {
								if( ! empty( $this->_data['mfp_overwrite_path'] ) || ! isset( $this->_data['filter_category_id'] ) ) {
									$this->_data['path'] = $this->parsePath( $value );
									$this->_data['filter_category_id'] = self::_parsePath( $this->_data['path'] );
								}
								
								if( isset( $this->_ctrl->request->get['category_id'] ) ) {									
									$this->_ctrl->request->get['category_id'] = $this->_data['filter_category_id'];
								}						
							}
							
							break;
						}
						case 'level' : {
							$this->_parseParams['levels'] = $this->parseArrayToInt( $value );
							
							break;
						}
						case 'vehicle' : {
							if( ! empty( $value[0] ) ) {
								$this->_parseParams['vehicle_make_id'] = $value[0];
							}
							
							if( ! empty( $value[1] ) ) {
								$this->_parseParams['vehicle_model_id'] = $value[1];
							}
							
							if( ! empty( $value[2] ) ) {
								$this->_parseParams['vehicle_engine_id'] = $value[2];
							}
							
							if( ! empty( $value[3] ) ) {
								$this->_parseParams['vehicle_year'] = $value[3];
							}
							
							break;
						}
						case 'force-path' : {
							$this->_data['filter_category_id'] = $value[0];
							$this->_ctrl->request->get['path'] = $value[0];
							
							break;
						}
						default : {
							if( preg_match( '/^c-.+-[0-9]+$/', $key ) ) {
								$this->_categories[$key][] = explode( ',', $this->parsePath( $value ) );
							} else {
								$k = explode( '-', $key );

								if( isset( $k[0] ) && isset( $k[1] ) && 'o' == mb_substr( $k[0], -1, 1, 'utf-8' ) ) {
									if( null != ( $value = $this->findIds( 'option', $value, trim( $k[0], 'o') ) ) ) {
										$this->_options[trim( $k[0], 'o').'-'.$k[1]][] = implode( ',', $value );
									}
								} else if( isset( $k[0] ) && isset( $k[1] ) && 'f' == mb_substr( $k[0], -1, 1, 'utf-8' ) ) {
									if( self::hasFilters() ) {
										if( null != ( $value = $this->findIds( 'filter', $value, trim( $k[0], 'f' ) ) ) ) {
											$this->_filters[trim( $k[0], 'f').'-'.$k[1]][] = implode( ',', $value );
										}
									}
								} else {
									if( empty( $this->_settings['attribute_separator'] ) ) {
										$this->_attribs[$key][] = $this->parseArrayToStr( $value );
									} else {
										$this->_attribs[$key][] = $this->parseArrayToStr( $value, $this->_settings['attribute_separator'] );
									}
								}
							}
						}
					}
					
					if( $value !== NULL )
						$this->_parseParams[$key] = $value;
				}
			}
		}
		
		return $this->_parseParams;
	}
	
	private function findIds( $type, $values, $item_id = null ) {
		if( empty( $this->_seo_settings['enabled'] ) ) {
			return $this->parseArrayToInt( $values );
		}
		
		$ids = array();
		
		foreach( $values as $k => $value ) {
			if( isset( self::$_cache[__METHOD__][$type][$value] ) ) {
				$ids[self::$_cache[__METHOD__][$type][$value]] = self::$_cache[__METHOD__][$type][$value];
				unset( $values[$k] );
			}
		}
		
		if( ! $values ) {
			return $ids;
		}
		
		if( null == ( $values = $this->parseArrayToStr( $values ) ) ) {
			return $ids;
		}
		
		switch( $type ) {
			case 'filter' : {
				if( $this->hasMfilterPlus() ) {
					$sql = "
						SELECT
							{__mfp_select__}
						FROM
							`" . DB_PREFIX . "mfilter_values`
						WHERE
							{__mfp_conditions__}
					";
					$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
						'{__mfp_select__}' => array(
							"`seo_value` AS `name`",
							"`value_id` AS `id`"
						),
						'{__mfp_conditions__}' => array(
							"( `language_id` IS NULL OR `language_id` = '" . $this->_ctrl->config->get('config_language_id') . "' )",
							"`seo_value` IN(" . implode( ',', $values ) . ")",
							"`type` = 'filter'",
							"`value_group_id` = '" . (int) $item_id . "'"
						)
					), 'findIds_plus_' . $type );
				} else {
					$sql = "
						SELECT 
							{__mfp_select__}
						FROM 
							`" . DB_PREFIX . "filter_description` 
						WHERE
							{__mfp_conditions__}
						HAVING
							{__mfp_having_conditions__}
					";
					$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
						'{__mfp_select__}' => array(
							"`filter_id` AS `id`",
							"LOWER(REPLACE(REPLACE(REPLACE(TRIM(`name`), '\r', ''), '\n', ''), ' ', '-')) AS `name`"
						),
						'{__mfp_conditions__}' => array(
							"`language_id` = '" . $this->_ctrl->config->get('config_language_id') . "'",
							"`filter_group_id` = '" . (int) $item_id . "'"
						),
						'{__mfp_having_conditions__}' => array(
							"`name` IN(" . implode( ',', $values ) . ")"
						)
					), 'findIds_' . $type );
				}
				
				break;
			}
			case 'option' : {
				if( $this->hasMfilterPlus() ) {
					$sql = "
						SELECT
							{__mfp_select__}
						FROM
							`" . DB_PREFIX . "mfilter_values`
						WHERE
							{__mfp_conditions__}
					";
					$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
						'{__mfp_select__}' => array(
							"`seo_value` AS `name`",
							"`value_id` AS `id`"
						),
						'{__mfp_conditions__}' => array(
							"( `language_id` IS NULL OR `language_id` = '" . $this->_ctrl->config->get('config_language_id') . "' )",
							"`seo_value` IN(" . implode( ',', $values ) . ")",
							"`type` = 'option'",
							"`value_group_id` = '" . (int) $item_id . "'"
						)
					), 'findIds_plus_' . $type );
				} else {
					$sql = "
						SELECT
							{__mfp_select__}
						FROM
							`" . DB_PREFIX . "option_value_description`
						WHERE
							{__mfp_conditions__}
						HAVING
							{__mfp_having_conditions__}
					";
					$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
						'{__mfp_select__}' => array(
							"`option_value_id` AS `id`",
							"LOWER(REPLACE(REPLACE(REPLACE(TRIM(`name`), '\r', ''), '\n', ''), ' ', '-')) AS `name`"
						),
						'{__mfp_conditions__}' => array(
							"`language_id` = '" . $this->_ctrl->config->get('config_language_id') . "'",
							"`option_id` = '" . (int)$item_id . "'"
						),
						'{__mfp_having_conditions__}' => array(
							"`name` IN(" . implode( ',', $values ) . ")"
						)
					), 'findIds_' . $type );
				}
				
				break;
			}
		}
		
		foreach( $this->_ctrl->db->query( $sql )->rows as $row ) {
			$ids[$row['id']] = $row['id'];
			self::$_cache[__METHOD__][$type][$row['name']] = $row['id'];
		}
		
		return $ids;
	}
	
	static public function __parsePath( & $ctrl, $path ) {
		if( ! is_array( $path ) ) {
			$path = explode( ',', $path );
		}
		
		$values = array();
		
		foreach( $path as $val ) {
			$val = explode( '_', $val );
		
			foreach( $val as $v ) {
				$values[] = $v;
			}
		}
		
		self::_aliasesToIds( $ctrl, 'category_id', $values );

		$val = array();

		foreach( $path as $k => $v ) {
			$v = explode( '_', $v );

			$val[$k] = '';

			foreach( self::_aliasesToIds( $ctrl, 'category_id', $v ) as $v2 ) {
				if( ! isset( $val[$k] ) ) {
					$val[$k] = '';
				}

				$val[$k] .= $val[$k] ? '_' : '';
				$val[$k] .= $v2;
			}
		}
		
		return implode( ',', $val );
	}
	
	protected function parsePath( $path ) {
		return self::__parsePath( $this->_ctrl, $path );
	}
	
	private static function createAliases( & $ctrl, $type, $column, $values ) {
		$ids = array();
		$keywords = array();
		
		if( $values ) {
			$sql = "SELECT * FROM `" . DB_PREFIX . "seo_url` AS `ua` WHERE `" . $column . "` IN(" . implode( ',', $values ) . ") AND `ua`.`language_id` = '" . (int) $ctrl->config->get('config_language_id') . "'";
			
			$t1 = $column;
			$t2 = $column == 'query' ? 'keyword' : 'query';

			foreach( $ctrl->db->query( $sql )->rows as $row ) {
				$row['query'] = explode( '=', $row['query'] );
				$row['query'] = $row['query'][1];

				self::$_cache['aliasesToIds'][$type][$row['keyword']] = $row['query'];
				self::$_cache['idsToAliases'][$type][$row['query']] = $row['keyword'];
			}
			
			foreach( $values as $v ) {
				$v = preg_replace( '/^\'|\'$/', '', $v );
				
				if( isset( self::$_cache['aliasesToIds'][$type][$v] ) ) {
					$ids[] = self::$_cache['aliasesToIds'][$type][$v];
					$keywords[] = $v;
				}
			}
		}
		
		return array( $ids, $keywords );
	}
	
	public static function _aliasesToIds( & $ctrl, $type, $aliases ) {		
		$ids = array();
		
		foreach( $aliases as $k => $alias ) {
			if( preg_match( '/^[0-9]+$/', $alias ) ) {
				$ids[] = $alias;
				unset( $aliases[$k] );
			} else if( isset( self::$_cache['aliasesToIds'][$type][$alias] ) ) {
				$ids[] = self::$_cache['aliasesToIds'][$type][$alias];
				unset( $aliases[$k] );
			}
		}
		
		if( $aliases ) {
			list( $ids2, $keywords ) = self::createAliases( $ctrl, $type, 'keyword', self::_parseArrayToStr( $ctrl, $aliases ) );
			
			$ids = $ids + $ids2;
		}
		
		return $ids ? $ids : array( 0 );
	}
	
	static public function pathToAliases( & $ctrl, $path ) {
		$aliases = array();
		$ids = $path = explode( '_', $path );
		$parts = array();
		
		foreach( $path as $k => $id ) {
			if( ! preg_match( '/^[0-9]+$/', $id ) ) {
				$aliases[$id] = $id;
				unset( $path[$k] );
			} else if( isset( self::$_cache['idsToAliases']['category_id'][$id] ) ) {
				$aliases[$id] = self::$_cache['idsToAliases']['category_id'][$id];
				unset( $path[$k] );
			}
		}
		
		if( $path ) {
			foreach( $path as $k => $v ) {
				$path[$k] = 'category_id=' . $v;
			}
			
			list( $keys, $keywords ) = self::createAliases( $ctrl, 'category_id', 'query', self::_parseArrayToStr( $ctrl, $path ) );
			
			$data = $keys && $keywords ? array_combine( $keys, $keywords ) : array();
			
			foreach( $data as $k => $v ) {
				$aliases[$k] = $v;
			}
		}
		
		foreach( $ids as $id ) {
			if( isset( $aliases[$id] ) ) {
				$parts[] = $aliases[$id];
			}
		}
		
		return $parts;
	}
	
	private function aliasesToIds( $type, $aliases ) {
		return self::_aliasesToIds($this->_ctrl, $type, $aliases);
	}
	
	private function _conditionsOutConvertToFullWhere( $conditionsOut ) {
		foreach( $conditionsOut as $k => $v ) {
			switch( $k ) {
				case 'mf_rating' : {
					$conditionsOut[$k] = str_replace( '`' . $k . '`', $this->_ratingCol(''), $v );
					
					break;
				}
				case 'mf_price' : {
					$conditionsOut[$k] = str_replace( '`' . $k . '`', $this->_mfPriceCol(''), $v );
					
					break;
				}
			}
		}
		
		return $conditionsOut;
	}
	
	private function _mfPriceCol( $alias = 'mf_price' ) {
		if( self::$_om && $this->_ctrl->model_extension_module_mega_filter->iom( 'mfPriceCol' ) ) {
			return $this->_ctrl->model_extension_module_mega_filter->om( 'mfPriceCol', $this, func_get_args() );
		}
		
		if( $this->_ctrl->config->get( 'config_tax' ) ) {
			return '( ( ' . $this->priceCol( NULL ) . ' * ( 1 + IFNULL(' . $this->percentTaxCol( NULL ) . ', 0) / 100 ) + IFNULL(' . $this->fixedTaxCol( NULL ) . ', 0) ) * ' . (float) $this->getCurrencyValue() . ')' . ( $alias ? ' AS `' . $alias . '`' : '' );
		} else {
			return '(' . $this->priceCol( NULL ) . '* ' . (float) $this->getCurrencyValue() . ')' . ( $alias ? ' AS `' . $alias . '`' : '' );
		}
	}
	
	public function _baseColumns() {
		$columns = func_get_args();
		
		if( ! empty( $this->_conditions['out']['mf_price'] ) ) {
			$columns['mf_price'] = $this->_mfPriceCol();
		}
		
		if( ! empty( $this->_conditions['out']['mf_rating'] ) ) {
			$columns['mf_rating'] = $this->_ratingCol();
		}
		
		return $columns;
	}
	
	private function _findMainWhere( $sql ) {
		$start = 0;
		
		while( false !== ( $pos = mb_strpos( mb_strtolower( $sql, 'utf8' ), 'where', $start, 'utf8' ) ) ) {
			$pre = mb_substr( $sql, 0, $pos, 'utf8' );
			
			if( mb_substr_count( $pre, '(', 'utf8' ) == mb_substr_count( $pre, ')', 'utf8' ) ) {
				$start = $pos;
				
				break;
			} else {
				$start = $pos + 5;
			}
		}
		
		return $pos === false ? 0 : $start;
	}
	
	private function _findMainOrderBy( $sql ) {
		$start = 0;
		
		while( false !== ( $pos = mb_strpos( mb_strtolower( $sql, 'utf8' ), 'order by', $start, 'utf8' ) ) ) {
			$pre = mb_substr( $sql, 0, $pos, 'utf8' );
			
			if( mb_substr_count( $pre, '(', 'utf8' ) == mb_substr_count( $pre, ')', 'utf8' ) ) {
				$start = $pos;
				
				break;
			} else {
				$start = $pos + 5;
			}
		}
		
		return $pos === false ? 0 : $start;
	}
	
	private function _explodeCols( $sql ) {
		$cols = array();
		
		$len = mb_strlen( $sql, 'utf8' );
		
		$level = 0;
		
		$last = 0;
		
		for( $i = 0; $i < $len; $i++ ) {
			$char = mb_substr( $sql, $i, 1, 'utf8' );
			
			if( $char == '(' ) {
				$level++;
			} else if( $char == ')' ) {
				$level--;
			} else if( $char == ',' && $level <= 0 ) {
				$cols[] = mb_substr( $sql, $last, $i, 'utf8' );
				$last = $i + 1;
			}
		}
		
		if( $last < $len ) {
			$cols[] = mb_substr( $sql, $last, $len, 'utf8' );
		}
		
		return array_map( 'trim', $cols );
	}
	
	private function _replaceMainWhere( $sql, $conditions ) {		
		if( 0 != ( $whereStart = $this->_findMainWhere( $sql ) ) ) {
			$sql = mb_substr( $sql, 0, $whereStart, 'utf8' ) . $this->_conditionsToSQL( $conditions ) . ' AND ' . mb_substr( $sql, $whereStart + 5, mb_strlen( $sql, 'utf8' ), 'utf8' );
		} else {
			$sql = preg_replace('~(.*)WHERE~ms', '$1' . $this->_conditionsToSQL( $conditions ) . ' AND ', $sql, 1);
		}
		
		return $sql;
	}
	
	private function _beforeMainWhere( $sql, $insert ) {
		if( 0 != ( $whereStart = $this->_findMainWhere( $sql ) ) ) {
			$sql = mb_substr( $sql, 0, $whereStart, 'utf8' ) . ' ' . $insert . ' ' . mb_substr( $sql, $whereStart, mb_strlen( $sql, 'utf8' ), 'utf8' );
		} else {
			$sql = preg_replace('~(.*)WHERE~ms', ' ' . $insert . ' $1', $sql, 1);
		}
		
		return $sql;
	}
	
	public function getColumns() {
		return $this->_baseColumns();
	}
	
	public function getConditions( $conditions = array() ) {
		if( ! $conditions ) {
			$conditions = $this->_conditions;
		}
		
		$conditionsOut = array();
		
		if( ! isset( $conditions['in'] ) ) {
			$conditions['in'] = array();
		}
		
		if( ! isset( $conditions['out'] ) ) {
			$conditions['out'] = array();
		}
		
		if( isset( $this->_data['filter_mf_name'] ) && NULL != ( $baseConditions = $this->_baseConditions() ) ) {
			if( isset( $baseConditions['search'] ) ) {
				$conditions['in']['search'] = $baseConditions['search'];
			}
			
			if( isset( $baseConditions['product_id'] ) ) {
				$conditions['in']['product_id'] = $baseConditions['product_id'];
			}
		}
		
		if( NULL != ( $conditionsToSQL = $this->_conditionsToSQL( $conditions['out'], '' ) ) ) {
			$conditionsOut[] = $conditionsToSQL;
		}
		
		$this->_attribsToSQL( '', NULL, $conditions['in'], $conditionsOut );
		
		$this->_optionsToSQL( '', NULL, $conditions['in'], $conditionsOut );
		
		$this->_filtersToSQL( '', NULL, $conditions['in'], $conditionsOut );
		
		return array( $conditions, $conditionsOut );
	}
	
	public function getSQL( $fn, $sql = NULL, $template = NULL, array $conditions = array() ) {
		if( $sql === NULL ) {
			$sql = $this->_sql;
		}
		
		$sql 		= trim( $sql );
		$limit		= '';
		$limitReg	= '/LIMIT\s+[0-9]+(\s*,\s*[0-9]+)?$/i';
		$sort		= '';
		$sortCol	= '';
		$sortReg	= '/ORDER BY[\s"\[](.*)(ASC|DESC)?$/ims';
		
		if( preg_match( $limitReg, $sql, $matches ) ) {
			if( ! empty( $matches[0] ) ) {
				$limit 	= $matches[0];
				$sql	= trim( preg_replace( $limitReg, '', $sql ) );
			}
		}
		
		if( ! empty( $this->_settings['enable_to_sort_subqueries'] ) && false !== ( $pos = $this->_findMainOrderBy( $sql ) ) ) {
			$tmp = mb_substr( $sql, $pos, mb_strlen( $sql, 'utf8' ), 'utf8' );
			
			if( preg_match( $sortReg, $tmp, $matches ) ) {
				$sql		= trim( str_replace( $tmp, trim( str_replace( $matches[0], '', $tmp ) ), $sql ) );
				
				$sort		= array();
				$sortCol	= array();
				
				foreach( $this->_explodeCols( $matches[1] ) as $col ) {
					$sort[] = '`mfp_sort_col_' . count( $sortCol ) . '` ' . ( preg_match( '/DESC$/i', $col ) ? 'DESC' : 'ASC' );
					$sortCol[] = '( ' . preg_replace( '/\s*(ASC|DESC)$/i', '', $col ) . ' ) AS `mfp_sort_col_' . count( $sortCol ) . '`';
				}
				
				$sort		= implode( ', ', $sort );
				$sortCol	= implode( ', ', $sortCol );
			}
		}
		
		list( $conditions, $conditionsOut ) = $this->getConditions( $conditions );
		
		if( ! $conditions['out'] && ! $conditions['in'] && ! $this->_attribs && ! $this->_options && ! $this->_filters && ! $this->_categories && ! $template && ! $this->_data ) {
			return $sql . ( $limit ? ' ' . $limit : '' );
		}
		
		$columns = implode( ',', $this->_baseColumns() );
		
		if( $columns ) {
			$columns = ',' . $columns;
		}
		
		if( self::_showProductsFromSubcategories( $this->_ctrl ) || $this->_categories ) {			
			if( preg_match( '/FROM\s+`?' . DB_PREFIX . 'product_to_category`?\s+(AS)?`?p2c`?/ims', $sql ) ) {
				$sql = preg_replace( '/(LEFT|INNER)\s+JOIN\s+`?' . DB_PREFIX . '(product_to_category|category_path)`?\s+(AS)?`?(p2c|cp)`?\s+ON\s*\(?\s*`?(cp|p2c|p)`?\.`?(category_id|product_id)`?\s*=\s*`?(p2c|cp|p)`?\.`?(category_id|product_id)`?\s*\)?/ims', '', $sql );
				$sql = preg_replace( 
					'/FROM\s+`?' . DB_PREFIX . 'product_to_category`?\s+(AS)?`?p2c`?/ims', 
					'
						FROM 
							`' . DB_PREFIX . 'category_path` AS `cp`
						INNER JOIN
							`' . DB_PREFIX . 'product_to_category` AS `p2c`
						ON
							`p2c`.`category_id` = `cp`.`category_id`
					', 
					$sql 
				);
				$sql = preg_replace( '/AND\s+`?p2c`?\.`?category_id`?\s*=/ims', 'AND `cp`.`path_id` =', $sql );
			}			
		}
		
		if( ! empty( $this->_parseParams['vehicle_make_id'] ) || ! empty( $this->_parseParams['levels'] ) || ! empty( $this->_data['filter_category_id'] ) || ! empty( $conditions['in']['search'] ) ) {
			$skip = array();
			$sql2 = explode( '###MFP_BEFORE_MAIN_WHERE###', $this->_beforeMainWhere( $sql, '###MFP_BEFORE_MAIN_WHERE###' ) );
			$sql2 = $sql2[0];
			
			if( strpos( $sql2, DB_PREFIX . 'product_to_store' ) !== false ) {
				$skip[] = 'p2s';
			}
			
			if( strpos( $sql2, DB_PREFIX . 'product_description' ) !== false ) {
				$skip[] = 'pd';
			}
			
			if( strpos( $sql2, DB_PREFIX . 'product_to_category' ) !== false ) {
				$skip[] = 'p2c';
			}
			
			if( strpos( $sql2, DB_PREFIX . 'category_path' ) !== false ) {
				$skip[] = 'cp';
			}
			
			if( strpos( $sql2, DB_PREFIX . 'product_filter' ) !== false ) {
				$skip[] = 'pf';
			}
			
			$sql = $this->_beforeMainWhere( $sql, $this->_baseJoin( $skip, true ) );
			$sql = $this->_replaceMainWhere( $sql, $this->_baseConditions() );
		}
		
		if( ! empty( $this->_data['filter_category_id'] ) ) {
			$vals = implode( ',', $this->parseArrayToInt( explode( ',', $this->_data['filter_category_id'] ) ) );
			
			$sql = preg_replace( '/AND\s+`?p2c`?\.`?category_id`?\s*=\s*(\'|")[0-9]+(\'|")/ims', 'AND `p2c`.`category_id` IN(' . $vals . ')', $sql );
			$sql = preg_replace( '/AND\s+`?cp`?\.`?path_id`?\s*=\s*(\'|")[0-9]+(\'|")/ims', 'AND `cp`.`path_id` IN(' . $vals . ')', $sql );
		}
		
		if( $conditions['in'] ) {
			$sql = $this->_replaceMainWhere( $sql, $conditions['in'] );
		}
		
		switch( $fn ) {
			case 'getTotalProductSpecials' :
			case 'getTotalProducts' : {
				$sql = preg_replace( '/COUNT\(\s*(DISTINCT)?\s*(`?[^.]+`?)\.`?product_id`?\s*\)\s*(AS\s*)?total/', 'DISTINCT `$2`.`product_id`' . $columns, $sql );
				$sql = sprintf( $template ? $template : 'SELECT COUNT(DISTINCT `product_id`) AS `total` FROM(%s) AS `tmp`', $this->_createSQLByCategories( $sql ) );
				
				break;
			}
			case 'getProductSpecials' :
			case 'getProducts' : {
				$cols = '*';
			
				if( false !== mb_strpos( $sql, 'SQL_CALC_FOUND_ROWS', 0, 'utf-8' ) ) {
					$sql = str_replace( 'SQL_CALC_FOUND_ROWS', '', $sql );
					$cols = 'SQL_CALC_FOUND_ROWS *';
				}
				
				$get_sort = isset( $this->_ctrl->request->get['sort'] ) ? $this->_ctrl->request->get['sort'] : '';
				
				if( $sortCol ) {
					if( $get_sort == 'p.price' ) {
						$columns .= ', p.price';
					} else if( $get_sort == 'rating' ) {
						// nothing todo
					} else {
						$columns .= ', ' . $sortCol;
					}
				}
				
				$sql = str_replace( 'SELECT p.model, p.product_id,', 'SELECT p.product_id, p.model,', $sql );
				$sql = preg_replace( '/^(\s?SELECT\s)(DISTINCT\s)?([^.]+\.product_id)/', '$1$2$3' . $columns, $sql );
				$sql = sprintf( $template ? $template : 'SELECT ' . $cols . ' FROM(%s) AS `tmp`', $this->_createSQLByCategories( $sql ) );
				
				break;
			}
		}
		
		if( $conditionsOut ) {
			$sql .= ' WHERE ' . implode( ' AND ', $conditionsOut );
		}
		
		if( in_array( $fn, array( 'getProductSpecials', 'getProducts' ) ) && $sort ) {
			if( in_array( $get_sort, array( 'p.price', 'rating' ) ) ) {
				if( $get_sort == 'p.price' ) {
					$sql .= ' ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE price END)';
				} else if( $get_sort == 'rating' ) {
					$sql .= ' ORDER BY rating';
				}

				$sql .= ' ' . ( isset( $this->_ctrl->request->get['order'] ) && in_array( strtolower( $this->_ctrl->request->get['order'] ), array( 'asc', 'desc' ) ) ? $this->_ctrl->request->get['order'] : 'ASC' );
			} else {
				$sql .= ' ORDER BY ' . $sort;
			}
		}
		
		if( $limit ) {
			$sql .= ' ' . $limit;
		}
		
		return $sql;
	}
	
	private function _optionsToSQL( $join = ' WHERE ', array $options = NULL, & $conditionsIn = NULL, & $conditionsOut = NULL, $field_id = '`product_id`' ) {
		if( $options === NULL )
			$options = $this->_options;
		
		if( false != ( $mFilterPlus = $this->mfilterPlus() ) ) {
			$sql = $mFilterPlus->optionsToSQL( $join, $options, $conditionsIn, $conditionsOut );
			
			if( $conditionsIn !== NULL && $sql ) {
				$conditionsIn[] = $sql;
			}
			
			return $sql;
		}
		
		if( $options ) {
			$sql		= array();
			$quantity	= '';
			$inStock	= false;
			
			if( ! empty( $this->_parseParams['stock_status'] ) ) {
				if( in_array( $this->inStockStatus(), $this->_parseParams['stock_status'] ) ) {
					$inStock = true;
				}
			}
		
			if( 
				( 
					empty( $this->_parseParams['stock_status'] ) && ! empty( $this->_settings['in_stock_default_selected'] ) 
				) 
					|| 
				( 
					$inStock
				) 
			) {
				$quantity .= ' AND `quantity` > 0';
			}
			
			foreach( $options as $opt ) {
				if( ! empty( $this->_settings['type_of_condition'] ) && $this->_settings['type_of_condition'] == 'and' ) {
					$opt	= implode( ',', $opt );
					$opt	= explode( ',', $opt );
					
					foreach( $opt as $opt2 ) {
						$sql[] = sprintf( $field_id . " IN(
							SELECT
								`product_id`
							FROM
								`" . DB_PREFIX . "product_option_value`
							WHERE
								`option_value_id` = %s %s
						)", $opt2, $quantity );
					}
				} else {				
					$sql[] = sprintf( $field_id . " IN( 
						SELECT 
							`product_id` 
						FROM 
							`" . DB_PREFIX . "product_option_value` 
						WHERE 
							`option_value_id` IN(%s) %s
					)", $opt ? implode( ',', $opt ) : '0', $quantity );
				}
			}
			
			$sql = $join . implode( ' AND ', $sql );
		} else {
			$sql = '';
		}
		
		if( $conditionsOut !== NULL && $sql )
			$conditionsOut[] = $sql;
		
		return $sql;
	}
	
	private function _categoriesToSQL( $join = ' WHERE ', array $categories = NULL ) {
		if( $categories === NULL )
			$categories = $this->_categories;
		
		if( $categories ) {
			$ids = array();
			$sql = array();
			
			foreach( $categories as $cat1 ) {
				foreach( $cat1 as $cat2 ) {
					$ids[] = end( $cat2 );
				}
			}
			
			$ids = implode( ',', $ids );
			
			$sql[] = '`mf_cp`.`path_id` IN(' . $ids . ')';
			
			$sql = $join . implode( ' AND ', $sql );
		} else {
			$sql = '';
		}
		
		return $sql;
	}
	
	private function _filtersToSQL( $join = ' WHERE ', array $filters = NULL, & $conditionsIn = NULL, & $conditionsOut = NULL, $field_id = '`product_id`' ) {
		if( ! self::hasFilters() )
			return '';
		
		if( $filters === NULL )
			$filters = $this->_filters;
		
		if( false != ( $mFilterPlus = $this->mfilterPlus() ) ) {
			$sql = $mFilterPlus->filtersToSQL( $join, $filters );
			
			if( $conditionsIn !== NULL && $sql )
				$conditionsIn[] = $sql;
			
			return $sql;
		}
		
		if( $filters ) {
			$sql		= array();
			
			foreach( $filters as $opt ) {
				if( ! empty( $this->_settings['type_of_condition'] ) && $this->_settings['type_of_condition'] == 'and' ) {
					$opt	= implode( ',', $opt );
					$opt	= explode( ',', $opt );
					
					foreach( $opt as $opt2 ) {
						$sql[] = sprintf( $field_id . " IN(
							SELECT
								`product_id`
							FROM
								`" . DB_PREFIX . "product_filter`
							WHERE
								`filter_id` = %s
						)", $opt2 );
					}
				} else {				
					$sql[] = sprintf( $field_id . " IN( 
						SELECT 
							`product_id` 
						FROM 
							`" . DB_PREFIX . "product_filter` 
						WHERE 
							`filter_id` IN(%s)
					)", implode( ',', $opt ) );
				}
			}
			
			$sql = $join . implode( ' AND ', $sql );
		} else {
			$sql = '';
		}
		
		if( $conditionsOut !== NULL && $sql )
			$conditionsOut[] = $sql;
		
		return $sql;
	}
	
	private function _convertAttribs( $attribs, $field = 'text' ) {
		$tmp		= array();
		
		foreach( $attribs as $attr ) {
			foreach( $attr as $att ) {
				if( ! empty( $this->_settings['attribute_separator'] ) && $this->_settings['attribute_separator'] == ',' ) {
					$tmp[] = sprintf( "FIND_IN_SET( REPLACE(REPLACE(REPLACE(%s, ' ', ''), '\r', ''), '\n', ''), REPLACE(REPLACE(REPLACE(`%s`, ' ', ''), '\r', ''), '\n', '') )", $att, $field );
				} else if( ! is_array( $att ) ) {
					$tmp[] = sprintf( "REPLACE(REPLACE(REPLACE(`%s`, ' ', ''), '\r', ''), '\n', '') LIKE REPLACE(REPLACE(REPLACE(%s, ' ', ''), '\r', ''), '\n', '')", $field, $att );
				} else {
					foreach( $att as $at ) {
						$tmp[] = sprintf( "REPLACE(REPLACE(REPLACE(`%s`, ' ', ''), '\r', ''), '\n', '') LIKE REPLACE(REPLACE(REPLACE(%s, ' ', ''), '\r', ''), '\n', '')", $field, $at );
					}
				}
			}
		}
		
		return $tmp;
	}
	
	private function hasMfilterPlus() {
		if( ! file_exists( DIR_SYSTEM . 'library/mfilter_plus.php' ) )
			return false;
		
		return true;
	}
	
	private function mfilterPlus() {
		if( ! $this->hasMfilterPlus() ) {
			return false;
		}
		
		if( class_exists( 'VQMod' ) ) {
			require_once VQMod::modCheck( modification( DIR_SYSTEM . 'library/mfilter_plus.php' ) );
		} else {
			require_once modification( DIR_SYSTEM . 'library/mfilter_plus.php' );
		}
		
		$mfilterPlus = Mfilter_Plus::getInstance( $this->_ctrl, $this->_settings );
		
		return $mfilterPlus->setValues( $this->_attribs, $this->_options, $this->_filters );
	}
	
	private function _attribsToSQL( $join = ' WHERE ', array $attribs = NULL, & $conditionsIn = NULL, & $conditionsOut = NULL, $field_id = '`product_id`' ) {
		if( $attribs === NULL )
			$attribs = $this->_attribs;
		
		if( false != ( $mFilterPlus = $this->mfilterPlus() ) ) {
			$sql = $mFilterPlus->attribsToSQL( $join, $attribs );
			
			if( $conditionsIn !== NULL && $sql )
				$conditionsIn[] = $sql;
			
			return $sql;
		}
		
		if( $attribs ) {
			$sql		= array();
			
			foreach( $attribs as $key => $attr ) {
				list( $attrib_id ) 	= explode( '-', $key );
				
				$sql[]	= sprintf( $field_id . " IN( 
					SELECT 
						`product_id` 
					FROM 
						`" . DB_PREFIX . "product_attribute`
					WHERE 
						( %s ) AND
						`language_id` = " . (int) $this->_ctrl->config->get( 'config_language_id' ) . " AND
						`attribute_id` = " . (int) $attrib_id . " 
				)", implode( 
						! empty( $this->_settings['type_of_condition'] ) && $this->_settings['type_of_condition'] == 'and' ? ' AND ' : ' OR ', 
						$this->_convertAttribs( $attr ) 
					)
				);
			}
			
			$sql = $join . implode( ' AND ', $sql );
		} else {
			$sql = '';
		}
		
		if( $conditionsOut !== NULL && $sql )
			$conditionsOut[] = $sql;
		
		return $sql;
	}
	
	private function _ratingCol( $alias = 'mf_rating' ) {
		if( self::$_om && $this->_ctrl->model_extension_module_mega_filter->iom( 'ratingCol' ) ) {
			return $this->_ctrl->model_extension_module_mega_filter->om( 'ratingCol', $this, func_get_args() );
		}
		
		$sql = "
			(
				SELECT 
					{__mfp_select__}
				FROM 
					`" . DB_PREFIX . "review` AS `r1` 
				WHERE 
					{__mfp_conditions__}
				GROUP BY 
					{__mfp_group_by__}
			)" . ( $alias ? " AS `" . $alias . '`' : '' );
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
			'{__mfp_select__}' => array(
				'ROUND(AVG(`rating`)) AS `total`'
			),
			'{__mfp_group_by__}' => array(
				'`r1`.`product_id`'
			),
			'{__mfp_conditions__}' => array(
				"`r1`.`product_id` = `p`.`product_id`",
				"`r1`.`status` = '1'"
			)
		), 'ratingCol');
		
		return $sql;
	}
	
	private function _customerGroupId() {
		return $this->_ctrl->customer->isLogged() ? (int) $this->_ctrl->customer->getGroupId() : (int) $this->_ctrl->config->get( 'config_customer_group_id' );
	}
	
	public function discountCol( $alias = 'discount' ) {
		if( self::$_om && $this->_ctrl->model_extension_module_mega_filter->iom( 'discountCol' ) ) {
			return $this->_ctrl->model_extension_module_mega_filter->om( 'discountCol', $this, func_get_args() );
		}
		
		$sql = "
			SELECT 
				{__mfp_select__}
			FROM 
				`" . DB_PREFIX . "product_discount` AS `pd2` 
			WHERE 
				{__mfp_conditions__}
			ORDER BY 
				{__mfp_order_by__}
			LIMIT 1
		";
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
			'{__mfp_select__}' => array(
				'`price`'
			),
			'{__mfp_order_by__}' => array(
				'`pd2`.`priority` ASC',
				'`pd2`.`price` ASC'
			),
			'{__mfp_conditions__}' => array(
				"`pd2`.`product_id` = `p`.`product_id`",
				"`pd2`.`customer_group_id` = '" . (int) $this->_customerGroupId() . "'",
				"`pd2`.`quantity` >= '1'",
				"((`pd2`.`date_start` = '0000-00-00' OR `pd2`.`date_start` < NOW())",
				"(`pd2`.`date_end` = '0000-00-00' OR `pd2`.`date_end` > NOW()))"
			)
		), 'discountCol');
		
		return $alias ? sprintf( "(%s) AS %s", $sql, $alias ) : $sql;
	}
	
	public function specialCol( $alias = 'special', $main_condition = '`ps`.`product_id` = `p`.`product_id`' ) {
		if( self::$_om && $this->_ctrl->model_extension_module_mega_filter->iom( 'specialCol' ) ) {
			return $this->_ctrl->model_extension_module_mega_filter->om( 'specialCol', $this, func_get_args() );
		}
		
		$sql = "
			SELECT 
				{__mfp_select__}
			FROM 
				`" . DB_PREFIX . "product_special` AS `ps` 
			WHERE 
				{__mfp_conditions__}
			ORDER BY 
				{__mfp_order_by__}
			LIMIT 1
		";
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
			'{__mfp_select__}' => array(
				'`price`'
			),
			'{__mfp_order_by__}' => array(
				'`ps`.`priority` ASC',
				'`ps`.`price` ASC'
			),
			'{__mfp_conditions__}' => array(
				$main_condition,
				"`ps`.`customer_group_id` = '" . (int) $this->_customerGroupId() . "'",
				"((`ps`.`date_start` = '0000-00-00' OR `ps`.`date_start` < NOW())",
				"(`ps`.`date_end` = '0000-00-00' OR `ps`.`date_end` > NOW()))"
			)
		), 'specialCol');
		
		return $alias ? sprintf( "(%s) AS %s", $sql, $alias ) : $sql;
	}
	
	private function _taxConditions() {
		$conditions	= array();
		
		$country_id	= $p_country_id = $s_country_id = (int) $this->_ctrl->config->get('config_country_id');
		$zone_id = $p_zone_id = $s_zone_id = (int) $this->_ctrl->config->get('config_zone_id');
		
		if( ! empty( $this->_ctrl->session->data['payment_country_id'] ) && ! empty( $this->_ctrl->session->data['payment_zone_id'] ) ) {
			$p_country_id = (int) $this->_ctrl->session->data['payment_country_id'];
			$p_zone_id = (int) $this->_ctrl->session->data['payment_zone_id'];
		}
		
		if( ! empty( $this->_ctrl->session->data['shipping_country_id'] ) && ! empty( $this->_ctrl->session->data['shipping_zone_id'] ) ) {
			$s_country_id = (int) $this->_ctrl->session->data['shipping_country_id'];
			$s_zone_id = (int) $this->_ctrl->session->data['shipping_zone_id'];
		}
		
		$conditions[] = "(
			`tr1`.`based` = 'store' AND 
			`z2gz`.`country_id` = " . $country_id . " AND (
				`z2gz`.`zone_id` = '0' OR `z2gz`.`zone_id` = '" . $zone_id . "'
			)
		)";
		
		$conditions[] = "(
			`tr1`.`based` = 'payment' AND 
			`z2gz`.`country_id` = " . $p_country_id . " AND (
				`z2gz`.`zone_id` = '0' OR `z2gz`.`zone_id` = '" . $p_zone_id . "'
			)
		)";
		
		$conditions[] = "(
			`tr1`.`based` = 'shipping' AND 
			`z2gz`.`country_id` = " . $s_country_id . " AND (
				`z2gz`.`zone_id` = '0' OR `z2gz`.`zone_id` = '" . $s_zone_id . "'
			)
		)";	
		
		return implode( ' OR ', $conditions );
	}
	
	private function _taxCol( $type, $alias ) {
		if( self::$_om && $this->_ctrl->model_extension_module_mega_filter->iom( 'taxCol' ) ) {
			return $this->_ctrl->model_extension_module_mega_filter->om( 'taxCol', $this, func_get_args() );
		}
		
		return "
			(
				SELECT
					`tr2`.`rate`
				FROM
					`" . DB_PREFIX . "tax_rule` AS `tr1`
				LEFT JOIN
					`" . DB_PREFIX . "tax_rate` AS `tr2`
				ON
					`tr1`.`tax_rate_id` = `tr2`.`tax_rate_id`
				INNER JOIN
					`" . DB_PREFIX . "tax_rate_to_customer_group` AS `tr2cg`
				ON
					`tr2`.`tax_rate_id` = `tr2cg`.`tax_rate_id`
				LEFT JOIN
					`" . DB_PREFIX . "zone_to_geo_zone` AS `z2gz`
				ON
					`tr2`.`geo_zone_id` = `z2gz`.`geo_zone_id`
				WHERE
					`tr1`.`tax_class_id` = `p`.`tax_class_id` AND
					`tr2`.`type` = '" . $type . "' AND
					( " . $this->_taxConditions() . " ) AND
					`tr2cg`.`customer_group_id` = " . $this->_customerGroupId() . " LIMIT 1
			)" . ( $alias ? ' AS ' . $alias : '' ) . "
		";
	}
	
	public function priceCol( $alias = 'price' ) {
		if( self::$_om && $this->_ctrl->model_extension_module_mega_filter->iom( 'priceCol' ) ) {
			return $this->_ctrl->model_extension_module_mega_filter->om( 'priceCol', $this, func_get_args() );
		}
		
		return "
			IFNULL( (" . $this->specialCol( NULL ) . "), IFNULL( (" . $this->discountCol( NULL ) . "), `p`.`price` ) )" . ( $alias ? " AS `" . $alias . '`' : '' ) . "
		";
	}
	
	public function fixedTaxCol( $alias = 'f_tax' ) {
		return $this->_taxCol( 'F', $alias );
	}
	
	public function percentTaxCol( $alias = 'p_tax' ) {
		return $this->_taxCol( 'P', $alias );
	}
	
	public function _baseConditions( array $conditions = array(), $internal = false ) {
		$conditions = array( 'status' => "`p`.`status` = '1'", 'date_available' => "`p`.`date_available` <= NOW()" ) + $conditions;
		
		$data = $this->_data;
		
		if( $internal ) {
			if( $this->rget( 'path') ) {
				$data['path'] = $this->rget('path');
				
				$data['filter_category_id'] = explode( '_', $data['path'] );
				$data['filter_category_id'] = end( $data['filter_category_id'] );
			}
		}
		
		if( ! empty( $data['filter_manufacturer_id'] ) ) {
			$conditions['manufacturer_id'] = '`p`.`manufacturer_id` = ' . (int) $data['filter_manufacturer_id'];
		}
		
		$mfilter_search = false;
		
		if( ! empty( $data['filter_name'] ) && $this->_ctrl->config->get( 'mfilter_search_enabled' ) ) {
			if( ! class_exists( 'Mfilter_Search' ) ) {
				if( class_exists( '\VQMod' ) ) {
					require_once \VQMod::modCheck(modification(DIR_SYSTEM . 'library/mfilter_search.php'));
				} else {
					require_once modification(DIR_SYSTEM . 'library/mfilter_search.php');
				}
			}
					
			if( class_exists( 'Mfilter_Search' ) && $this->_ctrl->config->get( 'mfilter_search_version' ) ) {
				$mfilter_search = true;
			}
		}
		
		if( ! empty( $data['filter_category_id'] ) ) {
			if( ! empty( $data['filter_sub_category'] ) || $this->_categories ) {
				if( ! empty( $this->_settings['try_to_boost_subcategories_speed'] ) ) {
					$conditions['cat_id'] = "`p2c`.`category_id` IN(SELECT `category_id` FROM `" . DB_PREFIX . "category_path` WHERE `path_id` IN(" . implode( ',', $this->parseArrayToInt( explode( ',', $data['filter_category_id'] ) ) ) . "))";
				} else {
					$conditions['cat_id'] = "`cp`.`path_id` IN(" . implode( ',', $this->parseArrayToInt( explode( ',', $data['filter_category_id'] ) ) ) . ")";
				}
			} else {
				$conditions['cat_id'] = "`p2c`.`category_id` IN(" . implode( ',', $this->parseArrayToInt( explode( ',', $data['filter_category_id'] ) ) ) . ")";
			}
			
			if( self::hasFilters() && ! empty( $data['filter_filter'] ) && ! empty( $data['filter_category_id'] ) ) {
				$filters = explode( ',', $data['filter_filter'] );
				
				$conditions['filter_id'] = '`pf`.`filter_id` IN(' . implode( ',', $this->parseArrayToInt( $filters ) ) . ')';
			}
		}
		
		if( ! empty( $data['filter_name'] ) || ! empty( $data['filter_tag'] ) ) {
			$sql = array();
			
			if( ! empty( $data['filter_name'] ) ) {
				if( $mfilter_search ) {
					$filterData = $data;
					
					unset( $filterData['filter_category_id'] );
					
					$ids = Mfilter_Search::make( $this->_ctrl )->filterData( $filterData )->mfp();
					
					$conditions['product_id'] = "`p`.`product_id` IN(" . ( $ids ? implode( ',', $ids ) : '0' ) . ")";
				} else {
					$implode	= array();
					$words		= explode( ' ', trim( preg_replace( '/\s\s+/', ' ', $data['filter_name'] ) ) );

					foreach( $words as $word ) {
						$implode[] = "LCASE(`pd`.`name`) LIKE '%" . $this->_ctrl->db->escape( mb_strtolower( $word, 'utf-8' ) ) . "%'";
					}

					if( $implode ) {
						$sql[] = '(' . implode( ' AND ', $implode ) . ')';
					}

					if( ! empty( $data['filter_description'] ) || ! empty( $this->_settings['search_keywords_via_search_field_also_in_product_descriptions'] ) ) {
						$sql[] = "LCASE(`pd`.`description`) LIKE '%" . $this->_ctrl->db->escape( mb_strtolower( $data['filter_name'], 'utf-8' ) ) . "%'";
					}
					
					$tmp = array( '`p`.`model`', '`p`.`sku`', '`p`.`upc`', '`p`.`ean`', '`p`.`jan`', '`p`.`isbn`', '`p`.`mpn`' );

					foreach( $tmp as $tm ) {
						$sql[] = "LCASE(" . $tm . ") = '" . $this->_ctrl->db->escape( utf8_strtolower( $data['filter_name'] ) ) . "'";
					}
				}
			}
			
			if( ! $mfilter_search ) {
				if( ! empty( $data['filter_tag'] ) ) {
					$sql[] = "LCASE(`pd`.`tag`) LIKE '%" . $this->_ctrl->db->escape( mb_strtolower( $data['filter_tag'], 'utf-8' ) ) . "%'";
				} else if( ! empty( $this->_parseParams['search'][0] ) ) {
					$sql[] = "LCASE(`pd`.`tag`) LIKE '%" . $this->_ctrl->db->escape( mb_strtolower( $this->_parseParams['search'][0], 'utf-8' ) ) . "%'";
				}
			}
			
			if( $sql ) {
				$conditions['search'] = '(' . implode( ' OR ', $sql ) . ')';
			}
		}
		
		if( false != ( $mFilterPlus = $this->mfilterPlus() ) ) {
			$mFilterPlus->baseConditions( $conditions );
		}
		
		return $this->_ctrl->model_extension_module_mega_filter->prepareBaseConditions( $conditions );
	}
	
	public function _baseJoin( array $skip = array(), $mainQuery = false ) {
		$sql = array();
		
		if( ! in_array( 'p2s', $skip ) ) {
			$sql['p2s'] = "
				INNER JOIN
					`" . DB_PREFIX . "product_to_store` AS `p2s`
				ON
					`p2s`.`product_id` = `p`.`product_id` AND `p2s`.`store_id` = " . (int) $this->_ctrl->config->get( 'config_store_id' ) . "
			";
		}
		
		if( ( ! empty( $this->_data['filter_name'] ) || ! empty( $this->_data['filter_tag'] ) ) && ! in_array( 'pd', $skip ) ) {
			$sql['pd'] = "
				INNER JOIN
					`" . DB_PREFIX . "product_description` AS `pd`
				ON
					`pd`.`product_id` = `p`.`product_id` AND `pd`.`language_id` = " . (int) $this->_ctrl->config->get( 'config_language_id' ) . "
			";
		}
		
		if( ! empty( $this->_data['filter_category_id'] ) || $this->_categories ) {
			if( ! in_array( 'p2c', $skip ) ) {
				$sql['p2c'] = $this->_joinProductToCategory( 'p2c' );
			}
			
			if( ( ! empty( $this->_data['filter_sub_category'] ) || $this->_categories ) && ! in_array( 'cp', $skip ) ) {
				if( empty( $this->_settings['try_to_boost_subcategories_speed'] ) ) {
					$sql['cp'] = $this->_joinCategoryPath( 'cp', 'p2c' );
				}
			}
		
			if( ! empty( $this->_data['filter_filter'] ) && ! in_array( 'pf', $skip ) ) {
				$sql['pf'] = "
					INNER JOIN
						`" . DB_PREFIX . "product_filter` AS `pf`
					ON
						`p2c`.`product_id` = `pf`.`product_id`
				";
			}
		}
		
		if( ! empty( $this->_parseParams['vehicle_make_id'] ) || ! empty( $this->_parseParams['vehicle_model_id'] ) || ! empty( $this->_parseParams['vehicle_engine_id'] ) || ! empty( $this->_parseParams['vehicle_year'] ) ) {
			if( ! in_array( 'p2mfv', $skip ) ) {
				$sql['p2mfv'] = $this->_joinVehicle( false, $mainQuery );
			}
		}
		
		if( ! empty( $this->_parseParams['levels'] ) ) {
			if( ! in_array( 'p2mfl', $skip ) ) {
				$sql['p2mfl'] = $this->_joinLevel( false, $mainQuery );
			}
		}
		
		if( false != ( $mFilterPlus = $this->mfilterPlus() ) ) {
			if( null != ( $mf_plus_sql = $mFilterPlus->baseJoin( $skip ) ) ) {
				$sql['mf_plus'] = $mf_plus_sql;
			}
		}
		
		return implode( '', $this->_ctrl->model_extension_module_mega_filter->prepareBaseJoin( $sql, $skip, $mainQuery ) );
	}
	
	public function _joinVehicle( $skipData = false, $mainQuery = false ) {
		if( ! $this->_ctrl->model_extension_module_mega_filter->hasVehicles() ) {
			return '';
		}
		
		$sql = "
				INNER JOIN
				`" . DB_PREFIX . "product_to_mfv` AS `p2mfv`
			ON
				`p2mfv`.`product_id` = `p`.`product_id`
		";
		
		if( ! $skipData && ! empty( $this->_parseParams['vehicle_make_id'] ) ) {
			$sql .= " AND `p2mfv`.`mfilter_vehicle_make_id` = " . (int) $this->_parseParams['vehicle_make_id'] . " ";
		}
		
		if( ! $skipData && ! empty( $this->_parseParams['vehicle_model_id'] ) ) {
			$sql .= " AND ( `p2mfv`.`mfilter_vehicle_model_id` = " . (int) $this->_parseParams['vehicle_model_id'] . ( $mainQuery ? "" : " OR `p2mfv`.`mfilter_vehicle_model_id` IS NULL" ) . " ) ";
		}
		
		if( ! $skipData && ! empty( $this->_parseParams['vehicle_engine_id'] ) ) {
			$sql .= " AND ( `p2mfv`.`mfilter_vehicle_engine_id` = " . (int) $this->_parseParams['vehicle_engine_id'] . ( $mainQuery ? "" : " OR `p2mfv`.`mfilter_vehicle_engine_id` IS NULL" ) . " ) ";
		}
		
		if( ! $skipData && ! empty( $this->_parseParams['vehicle_year'] ) ) {
			$sql .= " AND ( `p2mfv`.`year` = " . (int) $this->_parseParams['vehicle_year'] . " ) ";
		}
		
		return $sql;
	}
	
	public function _joinLevel( $skipData = false ) {
		if( ! $this->_ctrl->model_extension_module_mega_filter->hasLevels() ) {
			return '';
		}
		
		$sql = "
			INNER JOIN
				`" . DB_PREFIX . "product_to_mfl` AS `p2mfl`
			ON
				`p2mfl`.`product_id` = `p`.`product_id`
			INNER JOIN
				`" . DB_PREFIX . "mfilter_level_values_path` AS `mlvp`
			ON
				`p2mfl`.`mfilter_level_value_id` = `mlvp`.`mfilter_level_value_id`
		";
		
		if( ! $skipData && ! empty( $this->_parseParams['levels'] ) ) {
			$last_level_id = end( $this->_parseParams['levels'] );
			$sql .= " AND `mlvp`.`path_id` = " . $last_level_id . " ";
		}
		
		return $sql;
	}
	
	private function _joinProductToCategory( $alias = 'mf_p2c', $on = 'p' ) {
		return "
			INNER JOIN
				`" . DB_PREFIX . "product_to_category` AS `" . $alias . "`
			ON
				`" . $alias . "`.`product_id` = `" . $on . "`.`product_id`
		";
	}
	
	private function _joinCategoryPath( $alias = 'mf_cp', $on = 'mf_p2c' ) {
		return "
			INNER JOIN
				`" . DB_PREFIX . "category_path` AS `" . $alias . "`
			ON
				`" . $alias . "`.`category_id` = `" . $on . "`.`category_id`
		";
	}
	
	public function _createSQL( array $columns, array $conditions = array(), array $group_by = array( '`p`.`product_id`' ), array $joins = array() ) {
		$conditions	= $this->_baseConditions( $conditions );
		$group_by	= $group_by ? ' GROUP BY ' . implode( ',', $group_by ) : '';
		$joins		= $joins ? implode( ' ', $joins ) : '';
		
		return $this->_createSQLByCategories( 
			str_replace( 
				array( '{COLUMNS}', '{CONDITIONS}', '{GROUP_BY}', '{JOINS}' ), 
				array( implode( ',', $columns ), implode( ' AND ', $conditions ), $group_by, $joins ), 
				sprintf("
					SELECT
						{COLUMNS}
					FROM
						`" . DB_PREFIX . "product` AS `p`
					INNER JOIN
						`" . DB_PREFIX . "product_description` AS `pd`
					ON
						`pd`.`product_id` = `p`.`product_id` AND `pd`.`language_id` = " . (int) $this->_ctrl->config->get( 'config_language_id' ) . "
					%s
					{JOINS}
					WHERE
						{CONDITIONS}
					{GROUP_BY}
				", $this->_baseJoin( array( 'pd' ) ) ) 
			) 
		);
	}
	
	public function _createSQLByCategories( $sql, $group_by = 'product_id' ) {
		if( ! $this->_categories )
			return $sql;
		
		return sprintf("
			SELECT
				`tmp`.*
			FROM
				( %s ) AS `tmp`
			%s %s %s
			GROUP BY
				`tmp`.`" . $group_by . "`
		",$sql, $this->_joinProductToCategory( 'mf_p2c', 'tmp' ), $this->_joinCategoryPath(), $this->_categoriesToSQL() );
	}
	
	private static function _route( & $ctrl ) {
		if( self::_rget($ctrl, 'mfilterRoute') !== null )
			return base64_decode( self::_rget( $ctrl, 'mfilterRoute' ) );
		
		if( isset( $ctrl->request->get['route'] ) )
			return $ctrl->request->get['route'];
		
		return 'common/home';
	}
	
	public function route() {
		return self::_route( $this->_ctrl );
	}
	
	public function _conditions() {
		return $this->_conditions;
	}
	
	public function _setCache( $name, $value ) {
		if( ! is_dir( DIR_SYSTEM . 'cache_mfp' ) || ! is_writable( DIR_SYSTEM . 'cache_mfp' ) ) return false;
		
		$name .=  '.' . $this->_ctrl->config->get('config_language_id');
		
		$lifetime = isset( $this->_settings['cache_lifetime'] ) ? (int) $this->_settings['cache_lifetime'] : 24;
		
		file_put_contents( DIR_SYSTEM . 'cache_mfp/' . $name, serialize( $value ) );
		file_put_contents( DIR_SYSTEM . 'cache_mfp/' . $name . '.time', time() + 60 * 60 * $lifetime );
		
		return true;
	}
	
	public function _getCache( $name ) {
		$dir		= DIR_SYSTEM . 'cache_mfp/';
		$file		= $dir . $name . '.' . $this->_ctrl->config->get('config_language_id');
		$file_time	= $file . '.time';
		
		if( ! file_exists( $file ) ) {
			return NULL;
		}
		
		if( ! file_exists( $file_time ) ) {
			return NULL;
		}
		
		$time = (float) file_get_contents( $file_time );
		
		if( $time < time() ) {
			@ unlink( $file );
			@ unlink( $file_time );
			
			return false;
		}
		
		return unserialize( file_get_contents( $file ) );
	}
	
	public function getMinMaxPrice( $check_is_empty = false ) {
		$mfp_overwrite_path = ! empty( $this->_data['mfp_overwrite_path'] );
		
		if( ! $check_is_empty && isset( $this->_ctrl->request->get['mfp_temp'] ) ) {
			$this->_ctrl->request->get[$this->mfpUrlParam()] = $this->_ctrl->request->get['mfp_temp'];
			$this->_data['mfp_overwrite_path'] = true;
			$this->parseParams();
		}
		
		$sel			= '`price_tmp`';
		$columns		= array( $this->priceCol( 'price_tmp' ) );
		$baseColumns	= $this->_baseColumns();
		
		if( isset( $baseColumns['mf_rating'] ) )
			$columns[] = $baseColumns['mf_rating'];
		
		if( $this->_ctrl->config->get( 'config_tax' ) ) {
			$sel = '( ' . $sel . ' * ( 1 + IFNULL(`p_tax`, 0) / 100 ) + IFNULL(`f_tax`, 0) )';
			$columns[] = $this->fixedTaxCol();
			$columns[] = $this->percentTaxCol();
		}
		
		$conditionsOut	= $this->_conditions['out'];
		$conditionsIn	= $this->_conditions['in'];
		
		if( isset( $conditionsOut['mf_price'] ) )
			unset( $conditionsOut['mf_price'] );
		
		if( $this->_attribs || $this->_options || $this->_filters || $this->_categories )
			$columns[] = '`p`.`product_id`';
		
		$conditions		= array();
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );
		
		if( isset( $conditionsOut['mf_rating'] ) ) {
			$conditions[] = $conditionsOut['mf_rating'];
			unset( $conditionsOut['mf_rating'] );
		}
		
		if( in_array( $this->route(), self::$_specialRoute ) ) {
			$columns[] = $this->specialCol();
			$conditions[] = '`special` IS NOT NULL';
		}
		
		$conditions = $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '';
		
		$sql = sprintf( 
			'SELECT {__mfp_select__} FROM( SELECT ' . $sel . ' AS `price` FROM( %s ) AS `tmp` %s ) AS `tmp` ' . $this->_conditionsToSQL( $conditionsOut ),
			$this->_createSQL( $columns, $conditionsIn, array() ), $conditions
		);
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
			'{__mfp_select__}' => array(
				"MIN(`price`) AS `p_min`",
				"MAX(`price`) AS `p_max`"
			)
		), 'getMinMaxPrice_' . ( $check_is_empty ? 'empty' : 'notEmpty'));
		
		if( ! empty( $this->_settings['cache_enabled'] ) ) {
			$cache = 'idx.price.' . md5( $sql );

			if( null != ( $cached = $this->_getCache( $cache ) ) ) {
				return array(
					'min' => floor( $cached['min'] * $this->getCurrencyValue() ),
					'max' => ceil( $cached['max'] * $this->getCurrencyValue() ),
					'empty' => $cached['empty']
				);
			}
		}
		
		$query = $this->_ctrl->db->query( $sql );
		
		if( ! $check_is_empty && isset( $this->_ctrl->request->get['mfp_temp'] ) ) {
			unset( $this->_ctrl->request->get[$this->mfpUrlParam()] );
			$this->parseParams();
			
			if( ! $mfp_overwrite_path ) {
				unset( $this->_data['mfp_overwrite_path'] );
			}
		}
		
		if( $check_is_empty ) {
			return ! $query->num_rows || ( $query->row['p_min'] == 0 && $query->row['p_max'] == 0 ) ? true : false;
		}
		
		if( ! $query->num_rows ) {
			return array( 'min' => 0, 'max' => 0, 'empty' => true );
		}
		
		$response = array(
			'min'	=> floor( $query->row['p_min'] * $this->getCurrencyValue() ),
			'max'	=> ceil( $query->row['p_max'] * $this->getCurrencyValue() ),
			'empty'	=> $this->getMinMaxPrice( true )
		);
		
		if( ! empty( $this->_settings['cache_enabled'] ) ) {
			$this->_setCache( $cache, array(
				'min' => $query->row['p_min'],
				'max' => $query->row['p_max'],
				'empty' => $response['empty']
			));
		}
		
		return $response;
	}
	
	public function getCurrencyId() {
		if( version_compare( VERSION, '2.2.0.0', '>=' ) ) {
			return $this->_ctrl->currency->getId( $this->_ctrl->session->data['currency'] );
		}
		
		return $this->_ctrl->currency->getId();
	}
	
	public function getCurrencyValue() {
		if( self::$_om && $this->_ctrl->model_extension_module_mega_filter->iom( 'getCurrencyValue' ) ) {
			return $this->_ctrl->model_extension_module_mega_filter->om( 'getCurrencyValue', $this, func_get_args() );
		}
		
		if( version_compare( VERSION, '2.2.0.0', '>=' ) ) {
			return $this->_ctrl->currency->getValue( $this->_ctrl->session->data['currency'] );
		}
		
		return $this->_ctrl->currency->getValue();
	}
	
	public function getTreeCategories( $root_cat = NULL, $type = null ) {
		if( $root_cat === NULL ) {
			if( $type == 'checkbox' && $this->rget('mfilterPath' ) !== null && $this->rget('path' ) !== null ) {
				$paths = explode( strpos( $this->rget('mfilterPath'), ',' ) ? ',' : '_', $this->rget('mfilterPath') );
				
				$root_cat = $this->rget('mfilterPath') ? self::_aliasesToIds( $this->_ctrl, 'category_id', $paths ) : array( 0 );
			} else if( $type == 'tree' && $this->rget('mfp_path' ) ) {
				$paths = explode( strpos( $this->rget('mfp_path'), ',' ) ? ',' : '_', $this->rget('mfp_path') );
				
				$root_cat = $this->rget('mfp_path') ? self::_aliasesToIds( $this->_ctrl, 'category_id', $paths ) : array( 0 );
			} else if( $this->rget('path' ) ) {
				$root_cat = explode( '_', $this->rget('path') );
			} else {
				$root_cat = array( 0 );
			}

			$root_cat = (int) end( $root_cat );
		} else {
			$root_cat = explode( '_', $root_cat );
			$root_cat = (int) end( $root_cat );
		}
		
		if( isset( self::$_cache[__METHOD__][$root_cat] ) ) {
			return self::$_cache[__METHOD__][$root_cat];
		}
		
		if( isset( $this->_ctrl->request->get['mfp_temp'] ) ) {
			$this->_ctrl->request->get[$this->mfpUrlParam()] = $this->_ctrl->request->get['mfp_temp'];
			$this->parseParams();
		}
		
		self::$_cache[__METHOD__][$root_cat] = array();
		
		$path = array( $root_cat => $root_cat );
		
		$sql = "SELECT {__mfp_select__} FROM `" . DB_PREFIX . "category_path` WHERE {__mfp_conditions__}";
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(
			'{__mfp_conditions__}' => array(
				"`path_id` = " . (int) $root_cat
			),
			'{__mfp_select__}' => array(
				'category_id'
			)
		), 'getTreeCategories_path');
		
		foreach( $this->_ctrl->db->query( $sql )->rows as $row ) {
			$path[$row['category_id']] = (int) $row['category_id'];
		}
		
		$sql		= "
			SELECT
				`c`.`parent_id`,
				`c`.`category_id`," . (
					empty( $this->_seo_settings['enabled'] ) ? '' :
					"(
						SELECT `keyword` FROM `" . DB_PREFIX . "seo_url` AS `ua` WHERE `query` = CONCAT( 'category_id=', `c`.`category_id` ) AND `ua`.`language_id` = '" . (int) $this->_ctrl->config->get('config_language_id') . "' LIMIT 1
					) AS `keyword`,"
				) . "`cd`.`name`
			FROM
				`" . DB_PREFIX . "category` AS `c`
			INNER JOIN
				`" . DB_PREFIX . "category_description` AS `cd`
			ON
				`cd`.`category_id` = `c`.`category_id` AND `cd`.`language_id` = '" . (int) $this->_ctrl->config->get('config_language_id') . "'
			INNER JOIN
				`" . DB_PREFIX . "category_to_store` AS `c2s`
			ON
				`c`.`category_id` = `c2s`.`category_id` AND `c2s`.`store_id` = '" . (int) $this->_ctrl->config->get('config_store_id') . "'
			WHERE
				`c`.`status` = '1' AND `c`.`parent_id` = " . $root_cat . "
			GROUP BY
				`c`.`category_id`
			ORDER BY
				`c`.`sort_order` ASC, `cd`.`name` ASC
		";
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), 'getTreeCategories_main');
		
		$cat_ids = array();
		
		$cat_map = array();
		
		$i = 0;
		foreach( $this->_ctrl->db->query( $sql )->rows as $cat ) {
			$cat_ids[] = $cat['category_id'];
			
			$cat_map[$cat['category_id']] = $i++;
			
			self::$_cache[__METHOD__][$root_cat][] = array(
				'name' => $cat['name'],
				'id' => ! empty( $this->_seo_settings['enabled'] ) && $cat['keyword'] ? $cat['keyword'] : $cat['category_id'],
				'cid' => $cat['category_id'],
				'pid' => $cat['parent_id']
			);
		}
		
		if( $cat_ids ) {
			$conditionsIn	= $this->_baseConditions( $this->_conditions['in'] );
			$conditionsOut	= $this->_conditions['out'];
			$columns		= array( '`cp`.`path_id`' );
			
			if( ! empty( $this->_settings['calculate_number_of_products'] ) ) {
				$columns[] = 'COUNT(DISTINCT `p`.`product_id`) AS total';
			}

			if( isset( $conditionsIn['cat_id'] ) ) {
				unset( $conditionsIn['cat_id'] );
			}
			
			$conditionsIn[] = '`cp`.`path_id` IN(' . implode(',', $cat_ids ) . ')';
			
			$this->_attribsToSQL( '', NULL, $conditionsIn, $conditionsOut, '`p`.`product_id`' );
			
			$this->_optionsToSQL( '', NULL, $conditionsIn, $conditionsOut, '`p`.`product_id`' );
			
			$this->_filtersToSQL( '', NULL, $conditionsIn, $conditionsOut, '`p`.`product_id`' );

			if( in_array( $this->route(), self::$_specialRoute ) ) {
				$conditionsIn[] = '('.$this->specialCol('') . ') IS NOT NULL';
			}

			$sql = sprintf("
				SELECT
					%s
				FROM
					`" . DB_PREFIX . "product_to_category` AS `p2c`
				INNER JOIN
					`" . DB_PREFIX . "product` AS `p`
				ON
					`p`.`product_id` = `p2c`.`product_id`
				INNER JOIN
					`" . DB_PREFIX . "category_path` AS `cp`
				ON
					`cp`.`category_id` = `p2c`.`category_id`
					%s
					%s
				GROUP BY
					`cp`.`path_id`
				",
				implode( ',', $columns ),
				$this->_baseJoin(array('p2c','cp')),
				$this->_conditionsToSQL( array_merge( $conditionsIn, $this->_conditionsOutConvertToFullWhere( $conditionsOut ) ) )
			);
			
			$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), 'getTreeCategories_counts');
			
			$cat_cnt = array();
			foreach( $this->_ctrl->db->query( $sql )->rows as $cat ) {
				$cat_cnt[$cat['path_id']] = isset( $cat['total'] ) ? $cat['total'] : -1;
			}
			
			$removed = false;
			foreach( self::$_cache[__METHOD__][$root_cat] as $k => $v ) {
				if( ! isset( $cat_cnt[$v['cid']] ) ) {
					unset( self::$_cache[__METHOD__][$root_cat][$k] );
					$removed = true;
				} else {
					self::$_cache[__METHOD__][$root_cat][$k]['cnt'] = $cat_cnt[$v['cid']];
				}
			}
			
			if( $removed ) {
				$tmp = array();
				
				foreach( self::$_cache[__METHOD__][$root_cat] as $v ) {
					$tmp[] = $v;
				}
				
				self::$_cache[__METHOD__][$root_cat] = $tmp;
			}
		}
		
		if( isset( $this->_ctrl->request->get['mfp_temp'] ) ) {
			unset( $this->_ctrl->request->get[$this->mfpUrlParam()] );
			$this->parseParams();
		}
		
		return self::$_cache[__METHOD__][$root_cat];
	}
	
	public function _conditionsToSQL( $conditions, $join = ' WHERE ' ) {
		return $conditions ? $join . implode( ' AND ', $conditions ) : '';
	}
	
	public function getCountsByTags() {
		$conditionsIn	= $this->_conditions['in'];
		$conditionsOut	= $this->_conditions['out'];
		$columns		= $this->_baseColumns();
		
		$columns[] = '`p`.`product_id`';
		$columns[] = '`t`.`mfilter_tag_id`';
		
		if( isset( $conditionsIn['tags'] ) ) {
			unset( $conditionsIn['tags'] );
		}
		
		$sql = sprintf(
			'SELECT COUNT(DISTINCT `product_id`) AS `total`, `mfilter_tag_id` FROM( %s ) AS `tmp` %s GROUP BY `mfilter_tag_id`',
			$this->_createSQL( $columns, $conditionsIn, array(), array( "INNER JOIN `" . DB_PREFIX . "mfilter_tags` AS `t` ON FIND_IN_SET( `t`.`mfilter_tag_id`, `p`.`mfilter_tags` ) AND `t`.`language_id` = " . (int) $this->_ctrl->config->get('config_language_id') )  ), $this->_conditionsToSQL( $conditionsOut )
		);
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), __FUNCTION__ );
		
		$query = $this->_ctrl->db->query( $sql );
		$counts = array();
		
		foreach( $query->rows as $row ) {
			$counts[$row['mfilter_tag_id']] = $row['total'];
		}
		
		return $counts;
	}
	
	public function getCountsByType( $type, array $baseColumns, $field, array $conditionsInAdd = array(), array $conditionsOutAdd = array() ) {
		$conditionsIn	= $this->_conditions['in'];
		$conditionsOut	= $this->_conditions['out'];
		$columns		= $baseColumns;
		
		foreach( $this->_baseColumns() as $k => $v ) {
			$columns[$k] = $v;
		}
		
		if( isset( $conditionsIn[$type] ) ) {
			unset( $conditionsIn[$type] );
		}
		
		$columns[] = '`p`.`product_id`';
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $conditionsOut );
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $conditionsOut );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $conditionsOut );
		
		if( in_array( $this->route(), self::$_specialRoute ) ) {
			$columns[] = $this->specialCol();
			$conditionsOut[] = '`special` IS NOT NULL';
		}
		
		foreach( $conditionsInAdd as $c ) {
			$conditionsIn[] = $c;
		}
		
		foreach( $conditionsOutAdd as $c ) {
			$conditionsOut[] = $c;
		}
		
		$sql = sprintf(
			'SELECT COUNT(DISTINCT `product_id`) AS `total`, `' . $field . '` FROM( %s ) AS `tmp` %s GROUP BY `' . $field . '`',
			$this->_createSQL( $columns, $conditionsIn, array() ), $this->_conditionsToSQL( $conditionsOut )
		);
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), __FUNCTION__ );
		
		$query = $this->_ctrl->db->query( $sql );		
		$counts = array();
		
		foreach( $query->rows as $row ) {
			$val = $row[$field];
			
			if( $field == 'stock_status_id' ) {
				$val = explode( '_', $val );
				
				foreach( $val as $v ) {
					$counts[$v] = $row['total'];
				}
			} else {
				$counts[$val] = $row['total'];
			}
		}
		
		return $counts;
	}
	
	public function getCountsByBaseType( $type ) {		
		$list			= array();
		$columns		= call_user_func_array( array( $this, '_baseColumns' ), array( 
			in_array( $type, array( 'length', 'weight', 'width', 'height' ) ) ? 
				"ROUND( `p`.`" . $type . "` / ( SELECT `value` FROM `" . DB_PREFIX . ( $type == 'weight' ? 'weight' : 'length' ) . "_class` WHERE `" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` = `p`.`" . ( $type == 'weight' ? 'weight' : 'length' ) . "_class_id` LIMIT 1 ), 10 ) AS `field`" :
				"`" . $type . "` AS `field`",
			'`p`.`product_id`'
		));
		$conditionsIn	= $this->_conditions['in'];
		$conditionsOut	= $this->_conditions['out'];
		
		if( isset( $conditionsIn[$type] ) ) {
			unset( $conditionsIn[$type] );
		}
		
		if( in_array( $type, array( 'width', 'height', 'length', 'weight' ) ) ) {
			$conditionsIn[] = "`p`.`" . $type . "` > 0";
		}
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $conditionsOut );
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $conditionsOut );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $conditionsOut );
		
		if( in_array( $this->route(), MegaFilterCore::$_specialRoute ) ) {
			$conditionsOut[] = '(' . $this->specialCol( '', "`ps`.`product_id` = `tmp`.`product_id`" ) . ') IS NOT NULL';
		}
		
		$sql = sprintf(
			'SELECT COUNT(DISTINCT `product_id`) AS `total`, `field` FROM( %s ) AS `tmp` %s GROUP BY `field`',
			$this->_createSQL( $columns, $conditionsIn, array() ), $this->_conditionsToSQL( $conditionsOut )
		);
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), __FUNCTION__ );
		
		foreach( $this->_ctrl->db->query( $sql )->rows as $row ) {
			switch( $type ) {
				case 'length' :
				case 'width' :
				case 'height' : 
				case 'weight' : {
					$row['field'] = round( $row['field'], 10 );
					
					break;
				}
			}
			
			$k = md5( $row['field'] );
			
			$list[$k] = $row['total'];
		}
		
		return $list;
	}
	
	public function getCountsByStockStatus() {
		return $this->getCountsByType( 'stock_status', array(
				sprintf(
					'IF( `p`.`quantity` > 0, \'%s\', `p`.`stock_status_id` ) AS `stock_status_id`', $this->inStockStatus()
				)
			),
			'stock_status_id'
		);
	}
	
	public function getCountsByRating() {
		return $this->getCountsByType( 'mf_rating', array(
				'mf_rating' => $this->_ratingCol()
			),
			'mf_rating',
			array(),
			array(
				'`mf_rating` IS NOT NULL'
			)
		);
	}
	
	public function getCountsByDiscounts() {
		return $this->getCountsByType('discounts', array(
				'discount' => "ROUND( 100 - ( ( ( " . $this->priceCol('') . " ) / `p`.`price` ) * 100 ) ) AS `discount`"
			),
			'discount',
			array(),
			array(
				'`discount` > 0'
			)
		);
	}
	
	public function getCountsByManufacturers() {
		return $this->getCountsByType( 'manufacturers', array(
				'`p`.`manufacturer_id`'
			),
			'manufacturer_id'
		);
	}
	
	private function _replaceCounts( array $counts1, array $counts2 ) {
		foreach( $counts2 as $k1 => $v1 ) {
			foreach( $v1 as $k2 => $v2 ) {				
				$counts1[$k1][$k2] = $v2;
			}
		}
		
		return $counts1;
	}
	
	private function _getCountsByAttributes( array $conditions, array $conditionsIn ) {
		$counts	= array();
		
		$conditionsOut		= $this->_conditions['out'];
		$columns			= $this->_baseColumns( '`pa`.`attribute_id`', '`p`.`product_id`', '`pa`.`text`' );
		
		if( in_array( $this->route(), self::$_specialRoute ) ) {
			$columns[] = $this->specialCol();
			$conditions[] = '`special` IS NOT NULL';
		}

		$sql = $this->_createSQLByCategories(sprintf( "
			SELECT
				%s
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_attribute` AS `pa`
			ON
				`pa`.`product_id` = `p`.`product_id` AND `pa`.`language_id` = '" . (int) $this->_ctrl->config->get('config_language_id') . "'
			%s
			WHERE
				%s
		", implode( ',', $columns ), $this->_baseJoin(), implode( ' AND ', $this->_baseConditions( $conditionsIn ) ) ), 'attribute_id`,`product_id');

		if( $conditionsOut )
			$sql = sprintf( "SELECT * FROM( %s ) AS `tmp` WHERE %s", $sql, implode( ' AND ', $conditionsOut ) );

		$sql = sprintf( "
			SELECT 
				REPLACE(REPLACE(`text`, '\r', ''), '\n', '') AS `text`, `attribute_id`, COUNT( DISTINCT `tmp`.`product_id` ) AS `total`
			FROM( %s ) AS `tmp` 
				%s 
			GROUP BY 
				`text`, `attribute_id`
		", $sql, $this->_conditionsToSQL( $conditions ) );
		$cName = __FUNCTION__ . md5( $sql );
		
		if( isset( self::$_cache[$cName] ) ) {
			return self::$_cache[$cName];
		}
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), 'attrCount' );
		
		$query = $this->_ctrl->db->query( $sql );
		
		foreach( $query->rows as $row ) {
			if( ! empty( $this->_settings['attribute_separator'] ) ) {
				$row['text'] = htmlspecialchars_decode( $row['text'] );
				
				$texts = array_map( 'trim', explode( $this->_settings['attribute_separator'], $row['text'] ) );
				$texts = array_map( 'htmlspecialchars', $texts );
				
				foreach( $texts as $txt ) {
					$md5 = md5($txt);
					
					if( ! isset( $counts[$row['attribute_id']][$md5] ) ) {
						$counts[$row['attribute_id']][$md5] = 0;
					}
					
					$counts[$row['attribute_id']][$md5] += $row['total'];
				}
			} else {
				$md5 = md5($row['text']);
				
				$counts[$row['attribute_id']][$md5] = $row['total'];
			}
		}
		
		self::$_cache[$cName] = $counts;
		
		return $counts;
	}
	
	public function getCountsByAttributes() {
		$attribs	= array_keys( $this->_attribs );
		$_attribs	= array();
		$ids		= array();
		$counts		= array();
		
		foreach( $attribs as $attrib ) {
			list( $id ) = explode( '-', $attrib );
			
			$id = (int) $id;
			
			if( $id ) {
				$ids[] = $id;
			}
			
			if( ! isset( $_attribs[$id] ) ) {
				$_attribs[$id] = array();
			}
			
			foreach( $this->_attribs[$attrib] as $v ) {
				$_attribs[$id] = array_merge( $_attribs[$id], $v );
			}
		}
		
		$conditions = array();
		$conditionsIn = $this->_conditions['in'];
		$typeOfCondition = ! empty( $this->_settings['type_of_condition'] ) ? $this->_settings['type_of_condition'] : 'or';
		
		if( $ids ) {
			$conditions[] = sprintf( '`tmp`.`attribute_id` NOT IN(%s)', implode( ',', $ids ) );
		}
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );
		
		$counts = $this->_getCountsByAttributes( $conditions, $conditionsIn );
		
		$clearConditions	= array();
		$conditionsIn		= $this->_conditions['in'];
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $clearConditions );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $clearConditions );
		
		if( $typeOfCondition == 'and' ) {
			$values = array();
			
			if( $ids ) {
				foreach( $this->_ctrl->db->query( "SELECT * FROM `" . DB_PREFIX . "product_attribute` WHERE `language_id` = " . (int) $this->_ctrl->config->get('config_language_id') . " AND `attribute_id` IN(" . implode( ',', $this->parseArrayToInt( $ids) ) . ")" )->rows as $row ) {
					if( ! isset( $values[$row['attribute_id']] ) ) {
						$values[$row['attribute_id']] = array();
					}
					
					if( empty( $this->_settings['attribute_separator'] ) ) {
						$values[$row['attribute_id']] = array_merge( $values[$row['attribute_id']], $this->parseArrayToStr( array( $row['text'] ) ) );
					} else {
						$values[$row['attribute_id']] = array_merge( $values[$row['attribute_id']], $this->parseArrayToStr( explode( $this->_settings['attribute_separator'], $row['text'] ), $this->_settings['attribute_separator'] ) );
					}
				}
			}
			
			foreach( $values as $attribute_id => $attribute_vals ) {
				foreach( $attribute_vals as $attribute_val ) {
					$conditions = array();
					$conditionsIn = $this->_conditions['in'];
					$a = $_attribs;
					$a[$attribute_id][] = $attribute_val;
					
					$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );
					
					$this->_attribsToSQL( '', array( $attribute_id => $a ), $conditionsIn, $conditions );
					
					$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );
					
					$tmp = $this->_getCountsByAttributes( $conditions, $conditionsIn );
					$val = md5( trim( $attribute_val, "'" ) );
					
					if( isset( $tmp[$attribute_id][$val] ) ) {
						$counts[$attribute_id][$val] = $tmp[$attribute_id][$val];
					}
				}
			}
		} else {
			$clearCounts = $conditions ? $this->_getCountsByAttributes( $clearConditions, $conditionsIn ) : array();

			foreach( $attribs as $key ) {
				$copy			= $this->_attribs;
				$conditions		= array();
				$conditionsIn	= $this->_conditions['in'];

				list( $k ) = explode( '-', $key );
				
				unset( $copy[$key] );

				if( $copy ) {
					$this->_attribsToSQL( '', $copy, $conditionsIn, $conditions );
					
					$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );
					
					$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );

					$tmp = $this->_getCountsByAttributes( $conditions, $conditionsIn );

					if( isset( $tmp[$k] ) ) {
						$counts = $this->_replaceCounts( $counts, array( $k => $tmp[$k] ) );
					}
				} else {				
					if( isset( $clearCounts[$k] ) ) {
						$counts = $this->_replaceCounts( $counts, array( $k => $clearCounts[$k] ) );
					}
				}
			}
		}
		
		return $counts;
	}
	
	private function _getCountsByOptions( array $conditions, array $conditionsIn ) {
		$counts	= array();
		
		$conditionsOut		= $this->_conditions['out'];
		$columns			= $this->_baseColumns( '`pov`.`option_value_id`', '`pov`.`option_id`', '`p`.`product_id`' );
		$inStock			= false;
		
		if( in_array( $this->route(), self::$_specialRoute ) ) {
			$columns[] = $this->specialCol();
			$conditions[] = '`special` IS NOT NULL';
		}

		if( ! empty( $this->_parseParams['stock_status'] ) ) {
			if( in_array( $this->inStockStatus(), $this->_parseParams['stock_status'] ) ) {
				$inStock = true;
			}
		}
		
		if( 
			( 
				empty( $this->_parseParams['stock_status'] ) && ! empty( $this->_settings['in_stock_default_selected'] ) 
			) 
				|| 
			( 
				$inStock
			) 
		) {
			if( ! empty( $this->_settings['stock_for_options_plus'] ) || ! $this->mfilterPlus() ) {
				$conditionsIn[] = '`pov`.`quantity` > 0';
			}
		}

		$sql = $this->_createSQLByCategories(sprintf( "
			SELECT
				%s
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_option_value` AS `pov`
			ON
				`pov`.`product_id` = `p`.`product_id`
			%s
			WHERE
				%s
		", implode( ',', $columns ), $this->_baseJoin(), implode( ' AND ', $this->_baseConditions( $conditionsIn ) ) ), 'option_value_id`,`product_id');
		
		if( $conditionsOut )
			$sql = sprintf( "SELECT * FROM( %s ) AS `tmp` WHERE %s", $sql, implode( ' AND ', $conditionsOut ) );

		$sql = sprintf( "
			SELECT 
				`option_value_id`, `option_id`, COUNT( DISTINCT `tmp`.`product_id` ) AS `total`
			FROM( %s ) AS `tmp` 
				%s 
			GROUP BY 
				`option_id`, `option_value_id`
		", $sql, $this->_conditionsToSQL( $conditions ) );
		
		$cName = __FUNCTION__ . md5( $sql );
		
		if( isset( self::$_cache[$cName] ) ) {
			return self::$_cache[$cName];
		}
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), 'optsCount' );
		
		$query = $this->_ctrl->db->query( $sql );

		foreach( $query->rows as $row ) {
			$counts[$row['option_id']][$row['option_value_id']] = $row['total'];
		}
		
		self::$_cache[$cName] = $counts;
		
		return $counts;
	}
	
	function get_client_ip() {
		$ipaddress = '';
		if (getenv('HTTP_CLIENT_IP'))
			$ipaddress = getenv('HTTP_CLIENT_IP');
		else if(getenv('HTTP_X_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		else if(getenv('HTTP_X_FORWARDED'))
			$ipaddress = getenv('HTTP_X_FORWARDED');
		else if(getenv('HTTP_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_FORWARDED_FOR');
		else if(getenv('HTTP_FORWARDED'))
		   $ipaddress = getenv('HTTP_FORWARDED');
		else if(getenv('REMOTE_ADDR'))
			$ipaddress = getenv('REMOTE_ADDR');
		else
			$ipaddress = 'UNKNOWN';
		return $ipaddress;
	}
	
	public function getCountsByOptions() {
		$options	= array_keys( $this->_options );
		$_options	= array();
		$ids		= array();
		$counts		= array();
		
		foreach( $options as $attrib ) {
			list( $id ) = explode( '-', $attrib );
			
			$id = (int) $id;
			
			if( $id ) {
				$ids[] = $id;
			}
			
			$_options[$id] = $this->_options[$attrib];
		}
		
		$conditions = array();
		$conditionsIn = $this->_conditions['in'];
		$typeOfCondition = ! empty( $this->_settings['type_of_condition'] ) ? $this->_settings['type_of_condition'] : 'or';
		
		if( $ids ) {
			$conditions[] = sprintf( '`tmp`.`option_value_id` NOT IN(%s)', implode( ',', $ids ) );
		}
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );
		
		$counts = $this->_getCountsByOptions( $conditions, $conditionsIn );
		
		$clearConditions	= array();
		$conditionsIn		= $this->_conditions['in'];
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $clearConditions );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $clearConditions );
		
		if( $typeOfCondition == 'and' ) {
			$values = array();
			
			if( $ids ) {
				foreach( $this->_ctrl->db->query( "SELECT * FROM `" . DB_PREFIX . "option_value` WHERE `option_id` IN(" . implode(',', $this->parseArrayToInt($ids)) . ")" )->rows as $row ) {
					$values[$row['option_id']][] = $row['option_value_id'];
				}
			}
			
			foreach( $values as $option_id => $option_value_ids ) {
				foreach( $option_value_ids as $option_value_id ) {
					$conditions = array();
					$conditionsIn = $this->_conditions['in'];
					$o = $_options;
					$o[$option_id][] = $option_value_id;
					
					$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );
					
					$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
					
					$this->_optionsToSQL( '', $o, $conditionsIn, $conditions );

					$tmp = $this->_getCountsByOptions( $conditions, $conditionsIn );

					if( isset( $tmp[$option_id][$option_value_id] ) ) {
						$counts[$option_id][$option_value_id] = $tmp[$option_id][$option_value_id];
					}
				}
			}
		} else {
			$clearCounts = $conditions ? $this->_getCountsByOptions( $clearConditions, $conditionsIn ) : array();

			foreach( $options as $key ) {
				$copy			= $this->_options;
				$conditions		= array();
				$conditionsIn	= $this->_conditions['in'];

				list( $k ) = explode( '-', $key );

				unset( $copy[$key] );

				if( $copy ) {
					$this->_optionsToSQL( '', $copy, $conditionsIn, $conditions );
					
					$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
					
					$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );

					$tmp = $this->_getCountsByOptions( $conditions, $conditionsIn );

					if( isset( $tmp[$k] ) ) {
						$counts = $this->_replaceCounts( $counts, array( $k => $tmp[$k] ) );
					}
				} else {				
					if( isset( $clearCounts[$k] ) ) {
						$counts = $this->_replaceCounts( $counts, array( $k => $clearCounts[$k] ) );
					}
				}
			}
		}
		
		return $counts;
	}
	
	private function _getCountsByFilters( array $conditions, array $conditionsIn ) {
		$counts	= array();
		
		$conditionsOut		= $this->_conditions['out'];
		$columns			= $this->_baseColumns( '`f`.`filter_group_id`', '`pf`.`filter_id`', '`p`.`product_id`' );
		
		if( in_array( $this->route(), self::$_specialRoute ) ) {
			$columns[] = $this->specialCol();
			$conditions[] = '`special` IS NOT NULL';
		}

		$sql = $this->_createSQLByCategories(sprintf( "
			SELECT
				%s
			FROM
				`" . DB_PREFIX . "product` AS `p`
			INNER JOIN
				`" . DB_PREFIX . "product_filter` AS `pf`
			ON
				`pf`.`product_id` = `p`.`product_id`
			INNER JOIN
				`" . DB_PREFIX . "filter` AS `f`
			ON
				`f`.`filter_id` = `pf`.`filter_id`
			%s
			WHERE
				%s
		", implode( ',', $columns ), $this->_baseJoin(array('pf')), implode( ' AND ', $this->_baseConditions( $conditionsIn ) ) ), 'filter_id`,`product_id');

		if( $conditionsOut )
			$sql = sprintf( "SELECT * FROM( %s ) AS `tmp` WHERE %s", $sql, implode( ' AND ', $conditionsOut ) );

		$sql = sprintf( "
			SELECT 
				`filter_id`, `filter_group_id`, COUNT( DISTINCT `tmp`.`product_id` ) AS `total`
			FROM( %s ) AS `tmp` 
				%s 
			GROUP BY 
				`filter_group_id`, `filter_id`
		", $sql, $this->_conditionsToSQL( $conditions ) );
		$cName = __FUNCTION__ . md5( $sql );
		
		if( isset( self::$_cache[$cName] ) ) {
			return self::$_cache[$cName];
		}
		
		$sql = $this->_ctrl->model_extension_module_mega_filter->createQuery( $sql, array(), 'filterCount' );
		
		$query = $this->_ctrl->db->query( $sql );

		foreach( $query->rows as $row ) {
			$counts[$row['filter_group_id']][$row['filter_id']] = $row['total'];
		}
		
		self::$_cache[$cName] = $counts;
		
		return $counts;
	}
	
	public function getCountsByFilters() {
		$filters	= array_keys( $this->_filters );
		$_filters	= array();
		$ids		= array();
		$counts		= array();
		
		foreach( $filters as $attrib ) {
			list( $id ) = explode( '-', $attrib );
			
			$id = (int) $id;
			
			if( $id ) {
				$ids[] = $id;
			}
			
			$_filters[$id] = $this->_filters[$attrib];
		}
		
		$conditions = array();
		$conditionsIn = $this->_conditions['in'];
		$typeOfCondition = ! empty( $this->_settings['type_of_condition'] ) ? $this->_settings['type_of_condition'] : 'or';
		
		if( $ids ) {
			$conditions[] = sprintf( '`tmp`.`filter_group_id` NOT IN(%s)', implode( ',', $ids ) );
		}
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );
		
		$this->_filtersToSQL( '', NULL, $conditionsIn, $conditions );
		
		$counts = $this->_getCountsByFilters( $conditions, $conditionsIn );
		
		$clearConditions	= array();
		$conditionsIn		= $this->_conditions['in'];
		
		$this->_attribsToSQL( '', NULL, $conditionsIn, $clearConditions );
		
		$this->_optionsToSQL( '', NULL, $conditionsIn, $clearConditions );
		
		$values = array();
		
		if( $typeOfCondition == 'and' && $ids ) {
			foreach( $this->_ctrl->db->query( "SELECT * FROM `" . DB_PREFIX . "filter` WHERE `filter_group_id` IN(" . implode(',', $this->parseArrayToInt($ids)) . ")" )->rows as $row ) {
				$values[$row['filter_group_id']][] = $row['filter_id'];
			}
		}
		
		if( $typeOfCondition == 'and' ) {
			foreach( $values as $filter_group_id => $filter_ids ) {
				foreach( $filter_ids as $filter_id ) {
					$conditions = array();
					$conditionsIn = $this->_conditions['in'];
					$f = $_filters;
					$f[$filter_group_id][] = $filter_id;
					
					$this->_filtersToSQL( '', $f, $conditionsIn, $conditions );
					
					$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
					
					$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );

					$tmp = $this->_getCountsByFilters( $conditions, $conditionsIn );

					if( isset( $tmp[$filter_group_id][$filter_id] ) ) {
						$counts[$filter_group_id][$filter_id] = $tmp[$filter_group_id][$filter_id];
					}
				}
			}
		} else {
			$clearCounts = $conditions ? $this->_getCountsByFilters( $clearConditions, $conditionsIn ) : array();
		
			foreach( $filters as $key ) {
				$copy			= $this->_filters;
				$conditions		= array();
				$conditionsIn	= $this->_conditions['in'];

				list( $k ) = explode( '-', $key );

				unset( $copy[$key] );

				if( $copy ) {
					$this->_filtersToSQL( '', $copy, $conditionsIn, $conditions );
					
					$this->_attribsToSQL( '', NULL, $conditionsIn, $conditions );
					
					$this->_optionsToSQL( '', NULL, $conditionsIn, $conditions );

					$tmp = $this->_getCountsByFilters( $conditions, $conditionsIn );

					if( isset( $tmp[$k] ) ) {
						$counts = $counts + array( $k => $tmp[$k] );
					}
				} else {				
					if( isset( $clearCounts[$k] ) ) {
						$counts = $this->_replaceCounts( $counts, array( $k => $clearCounts[$k] ) );
					}
				}
			}
		}
		
		return $counts;
	}
	
	private static function _parseArrayToInt( $params ) {
		foreach( $params as $k => $v ) {
			if( $v === '' ) {
				unset( $params[$k] );
			} else {
				$params[$k] = (int) $v;
			}
		}
		
		return $params;
	}
	
	private function parseArrayToInt( $params ) {
		return self::_parseArrayToInt( $params );
	}
	
	private function _hasOnlyIntegers( $params ) {
		foreach( $params as $v ) {
			if( ! preg_match( '/^[0-9]+$/', $v ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	private static function _parseArrayToStr( & $ctrl, $params, $like = false ) {
		foreach( $params as $k => $v ) {
			$v = (string) $v;
			
			if( $v === '' ) {
				unset( $params[$k] );
			} else {
				if( $like && $like != ',' ) {
					$params[$k] = array();
					$params[$k][] = "'" . $ctrl->db->escape( $v ) . "'";
					$params[$k][] = "'%" . $like . $ctrl->db->escape( $v ) . $like . "%'";
					$params[$k][] = "'" . $ctrl->db->escape( $v ) . $like . "%'";
					$params[$k][] = "'%" . $like . $ctrl->db->escape( $v ) . "'";
				} else {
					$params[$k] = "'" . $ctrl->db->escape( $v ) . "'";
				}
			}
		}
		
		return $params;
	}
	
	private function parseArrayToStr( $params, $like = false ) {
		return self::_parseArrayToStr( $this->_ctrl, $params, $like );
	}
}
