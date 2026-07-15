<?php

/**
 * Mega Filter
 * 
 * @license Commercial
 * @author info@ocdemo.eu
 */

class Mfilter_Helper {
	
	private $_ctrl;
	
	private $_cache = array();
	
	private $_hasMFilterPlus = null;
	
	private static $instance;
	
	/**
	 * @return Mfilter Helper
	 */
	public static function create( & $ctrl ) {
		if( ! self::$instance ) {
			self::$instance = new self( $ctrl );
		}
		
		return self::$instance;
	}
	
	/**
	 * @param array $option
	 * @param string $template
	 * @param array $replace
	 * @return string
	 */
	public static function renderValue( $option, $template = ':name', array $replace = array() ) {
		if( $template == ':name' ) {
			$template = $option['name'];
		} else {
			foreach( $option as $name => $value ) {
				$template = str_replace( ':' . $name, $value, $template );
			}
		}
		
		if( $replace ) {
			foreach( $replace as $name => $value ) {
				$template = str_replace( ':' . $name, $value, $template );
			}
		}
		
		if( ! empty( $option['url'] ) ) {			
			return '<a' . self::arrayToHtmlAttribs(array(
				'href' => $option['url'],
				'class' => 'mfp-value-link',
				'data-value' => $option['value'],
			), false) . '>' . $template . '</a>';
		}
		
		return $template;
	}
	
	/**
	 * @param array $attribs
	 * @param bool $skip_empty
	 * @return string
	 */
	public static function arrayToHtmlAttribs( array $attribs, $skip_empty = true ) {
		/* @var $list array */
		$list = array();
		
		foreach( $attribs as $k => $v ) {
			if( ! $skip_empty || $v !== '' ) {
				$list[] = $k . '="' . str_replace( '"', '&qout;', $v ) . '"';
			}
		}
		
		return $list ? ' ' . implode( ' ', $list ) : '';
	}
	
	/**
	 * @return MfilterHelper
	 */
	public static function i() {
		return self::$instance;
	}
	
	private function __construct( & $ctrl ) {
		$this->_ctrl = $ctrl;
	}
	
	public function hasMFilterPlus() {
		if( $this->_hasMFilterPlus === null ) {
			$this->_hasMFilterPlus = file_exists( DIR_SYSTEM . 'library/mfilter_plus.php' );
		}
		
		return $this->_hasMFilterPlus;
	}
	
	protected static function addSeoAlias( $url, $alias, $mfp_param = null ) {
		/* @var $parsed array */
		$parsed = parse_url( $url );
		
		/* @var $out string */
		$out = '';
		
		if( ! empty( $parsed['host'] ) ) {
			$out .= empty( $parsed['scheme'] ) ? '//' : $parsed['scheme'] . '://';
			$out .= $parsed['host'];
		}
		
		$out .= empty( $parsed['path'] ) ? '/' : rtrim( $parsed['path'], '/' ) . '/';
		$out .= $alias;
		
		if( $mfp_param ) {
			if( ! isset( $parsed['query'] ) ) {
				$parsed['query'] = '';
			}
			
			$parsed['query'] = $mfp_param . ( $parsed['query'] ? '&' : '' ) . $parsed['query'];
		}
		
		if( isset( $parsed['query'] ) ) {
			$out .= '?' . $parsed['query'];
		}
		
		return $out;
	}
	
	public static function removeLinksByRel( & $ctrl, $rel ) {
		$ctrl->document->mfp_removeLink( $rel );
	}
	
	public static function createMetaLinks( & $ctrl, $page, $limit, $product_total, $class ) {
		/* @var $route_map array */
		$route_map = array(
			'ControllerProductManufacturer' => array(
				'product/manufacturer/info',
				'manufacturer_id',
			),
			'ControllerProductCategory' => array(
				'product/category',
				'path',
			),
			'ControllerProductSpecial' => array(
				'product/special',
			),
			'ControllerProductSearch' => array(
				'product/search',
			),
			'ControllerModuleMegaFilter' => array(
				'module/mega_filter/results',
			)
		);
		
		if( ! isset( $route_map[$class] ) ) {
			return;
		}
		
		/* @var $alias string */
		$alias = '';
		
		/* @var $config array */
		$config = (array) $ctrl->config->get( 'mega_filter_seo' );
		
		/* @var $params string|array */
		$params = array();
		
		/* @var $route string */
		$route = array_shift( $route_map[$class] );
		
		/* @var $mfp_param string */
		$mfp_param = null;
			
		foreach( $route_map[$class] as $param ) {
			if( isset( $ctrl->request->get[$param] ) ) {
				$params[] = $param . '=' . $ctrl->request->get[$param];
			}
		}
		
		$params = implode('&', $params);
		
		if( ! empty( $ctrl->request->request['mfp_seo_alias'] ) ) {
			$alias = $ctrl->request->request['mfp_seo_alias'];

			if( ! empty( $config['add_slash_at_the_end'] ) ) {
				$alias = rtrim( $alias, '/' ) .  '/';
			}

			self::removeLinksByRel( $ctrl, 'canonical' );

			$ctrl->document->addLink( self::addSeoAlias( $ctrl->url->link($route, $params, true), $alias ), 'canonical');
		} else if( ! empty( $ctrl->request->get[$ctrl->config->get('mfilter_url_param')?$ctrl->config->get('mfilter_url_param'):'mfp'] ) ) {
			$alias = $ctrl->request->get[$ctrl->config->get('mfilter_url_param')?$ctrl->config->get('mfilter_url_param'):'mfp'];

			if( ! empty( $config['add_slash_at_the_end'] ) ) {
				$alias = rtrim( $alias, '/' ) .  '/';
			}
			
			$seo = (array) $ctrl->config->get( 'mega_filter_seo' );
			
			if( ! empty( $seo['enabled'] ) ) {
				$alias = ( isset( $seo['separator'][$ctrl->config->get('config_language_id')] ) ? $seo['separator'][$ctrl->config->get('config_language_id')] : 'mfp' ) . '/' . $alias;
			} else {
				$mfp_param = ( $ctrl->config->get('mfilter_url_param')?$ctrl->config->get('mfilter_url_param'):'mfp' ) . '=' . $alias;
				$alias = '';
			}
		}

		if( $alias !== '' ) {
			if( isset( $page ) ) {
				self::removeLinksByRel( $ctrl, 'prev' );

				if ($page == 2) {
					$ctrl->document->addLink( self::addSeoAlias( $ctrl->url->link($route, $params, true), $alias, $mfp_param ), 'prev');
				} else if( $page > 2 ) {
					$ctrl->document->addLink( self::addSeoAlias( $ctrl->url->link($route, $params . '&page='. ($page - 1), true), $alias, $mfp_param ), 'prev');
				}

				if( ! empty( $limit ) && isset( $product_total ) && ceil($product_total / $limit) > $page ) {
					self::removeLinksByRel( $ctrl, 'next' );

					$ctrl->document->addLink( self::addSeoAlias( $ctrl->url->link($route, $params . '&page='. ($page + 1), true), $alias, $mfp_param ), 'next');
				}
			}
		}
	}
	
	private static function nonLatinChars() {
		return array(
			'À', 'à', 'Á', 'á', 'Â', 'â', 'Ã', 'ã', 'Ä', 'ä', 'Å', 'å', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ǟ', 'ǟ', 'Ǻ', 'ǻ', 'Α', 'α',
			'Ḃ', 'ḃ', 'Б', 'б',
			'Ć', 'ć', 'Ç', 'ç', 'Č', 'č', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Ч', 'ч', 'Χ', 'χ',
			'Ḑ', 'ḑ', 'Ď', 'ď', 'Ḋ', 'ḋ', 'Đ', 'đ', 'Ð', 'ð', 'Д', 'д', 'Δ', 'δ',
			'Ǳ',  'ǲ', 'ǳ', 'Ǆ', 'ǅ', 'ǆ', 
			'È', 'è', 'É', 'é', 'Ě', 'ě', 'Ê', 'ê', 'Ë', 'ë', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ę', 'ę', 'Ė', 'ė', 'Ʒ', 'ʒ', 'Ǯ', 'ǯ', 'Е', 'е', 'Э', 'э', 'Ε', 'ε', 'ё', 'є', 'Є',
			'Ḟ', 'ḟ', 'ƒ', 'Ф', 'ф', 'Φ', 'φ',
			'ﬁ', 'ﬂ', 
			'Ǵ', 'ǵ', 'Ģ', 'ģ', 'Ǧ', 'ǧ', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ǥ', 'ǥ', 'Г', 'г', 'Γ', 'γ',
			'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ж', 'ж', 'Х', 'х',
			'Ì', 'ì', 'Í', 'í', 'Î', 'î', 'Ĩ', 'ĩ', 'Ï', 'ï', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'И', 'и', 'Η', 'η', 'Ι', 'ι', 'і', 'І', 'ї', 'Ї',
			'Ĳ', 'ĳ', 
			'Ĵ', 'ĵ',
			'Ḱ', 'ḱ', 'Ķ', 'ķ', 'Ǩ', 'ǩ', 'К', 'к', 'Κ', 'κ',
			'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Л', 'л', 'Λ', 'λ',
			'Ǉ', 'ǈ', 'ǉ', 
			'Ṁ', 'ṁ', 'М', 'м', 'Μ', 'μ',
			'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'Ñ', 'ñ', 'ŉ', 'Ŋ', 'ŋ', 'Н', 'н', 'Ν', 'ν',
			'Ǌ', 'ǋ', 'ǌ', 
			'Ò', 'ò', 'Ó', 'ó', 'Ô', 'ô', 'Õ', 'õ', 'Ö', 'ö', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ø', 'ø', 'Ő', 'ő', 'Ǿ', 'ǿ', 'О', 'о', 'Ο', 'ο', 'Ω', 'ω',
			'Œ', 'œ', 
			'Ṗ', 'ṗ', 'П', 'п', 'Π', 'π',
			'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Р', 'р', 'Ρ', 'ρ', 'Ψ', 'ψ',
			'Ś', 'ś', 'Ş', 'ş', 'Š', 'š', 'Ŝ', 'ŝ', 'Ṡ', 'ṡ', 'ſ', 'ß', 'С', 'с', 'Ш', 'ш', 'Щ', 'щ', 'Σ', 'σ', 'ς',
			'Ţ', 'ţ', 'Ť', 'ť', 'Ṫ', 'ṫ', 'Ŧ', 'ŧ', 'Þ', 'þ', 'Т', 'т', 'Ц', 'ц', 'Θ', 'θ', 'Τ', 'τ',
			'Ù', 'ù', 'Ú', 'ú', 'Û', 'û', 'Ũ', 'ũ', 'Ü', 'ü', 'Ů', 'ů', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ų', 'ų', 'Ű', 'ű', 'У', 'у',
			'В', 'в', 'Β', 'β',
			'Ẁ', 'ẁ', 'Ẃ', 'ẃ', 'Ŵ', 'ŵ', 'Ẅ', 'ẅ',
			'Ξ', 'ξ',
			'Ỳ', 'ỳ', 'Ý', 'ý', 'Ŷ', 'ŷ', 'Ÿ', 'ÿ', 'Й', 'й', 'Ы', 'ы', 'Ю', 'ю', 'Я', 'я', 'Υ', 'υ',
			'Ź', 'ź', 'Ž', 'ž', 'Ż', 'ż', 'З', 'з', 'Ζ', 'ζ',
			'Æ', 'æ', 'Ǽ', 'ǽ', 'а', 'А',
			'ь', 'ъ', 'Ъ', 'Ь',
		);
	}
	
	private static function latinChars() {
		return array(
			'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a',
			'B', 'b', 'B', 'b',
			'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'CH', 'ch', 'CH', 'ch',
			'D', 'd', 'D', 'd', 'D', 'd', 'D', 'd', 'D', 'd', 'D', 'd', 'D', 'd',
			'DZ', 'Dz', 'dz', 'DZ', 'Dz', 'dz',
			'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'e', 'e', 'E',
			'F', 'f', 'f', 'F', 'f', 'F', 'f',
			'fi', 'fl',
			'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g',
			'H', 'h', 'H', 'h', 'ZH', 'zh', 'H', 'h',
			'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'i', 'I', 'i', 'I',
			'IJ', 'ij',
			'J', 'j',
			'K', 'k', 'K', 'k', 'K', 'k', 'K', 'k', 'K', 'k',
			'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l',
			'LJ', 'Lj', 'lj',
			'M', 'm', 'M', 'm', 'M', 'm',
			'N', 'n', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'N', 'n', 'N', 'n', 'N', 'n',
			'NJ', 'Nj', 'nj',
			'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o',
			'OE', 'oe',
			'P', 'p', 'P', 'p', 'P', 'p', 'PS', 'ps',
			'R', 'r', 'R', 'r', 'R', 'r', 'R', 'r', 'R', 'r',
			'S', 's', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 's', 'ss', 'S', 's', 'SH', 'sh', 'SHCH', 'shch', 'S', 's', 's',
			'T', 't', 'T', 't', 'T', 't', 'T', 't', 'T', 't', 'T', 't', 'TS', 'ts', 'TH', 'th', 'T', 't',
			'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u',
			'V', 'v', 'V', 'v',
			'W', 'w', 'W', 'w', 'W', 'w', 'W', 'w',
			'X', 'x',
			'Y', 'y', 'Y', 'y', 'Y', 'y', 'Y', 'y', 'Y', 'y', 'Y', 'y', 'YU', 'yu', 'YA', 'ya', 'Y', 'y',
			'Z', 'z', 'Z', 'z', 'Z', 'z', 'Z', 'z', 'Z', 'z',
			'AE', 'ae', 'AE', 'ae', 'a', 'A',
			'', '', '', '',
		);
	}
	
	public function convertNonLatinToLatin( $str ) {		
		return str_replace( self::nonLatinChars(), self::latinChars(), $str );
	}
	
	public function removeSpecialCharacters( $str ) {
		return str_replace(array(
			' ', '`', '~', '!', '@', '#', '$', '%', '^', '*', '(', ')', '+', '=', '[', '{', ']', '}', '\\', '|', ';', ':', "'", '"', ',', '<', '.', '>', '/', '?'
		), '-', str_replace(array(
			'&'
		), array(
			'and'
		), htmlspecialchars_decode( $str )) );
	}
	
	public function isSeoEnabled() {
		/* @var $settings array */
		$settings = $this->_ctrl->config->get('mega_filter_seo');
		
		return ! empty( $settings['enabled'] );
	}
	
	public function convertValueToSeo( $value, $only_if_enabled = true ) {
		if( ! $this->hasMFilterPlus() ) {
			return $value;
		}
		
		/* @var $settings array */
		$settings = $this->_ctrl->config->get('mega_filter_seo');
		
		if( $only_if_enabled && empty( $settings['enabled'] ) ) {
			return $value;
		}
		
		if( ! empty( $settings['convert_non_to_latin'] ) ) {
			$value = $this->convertNonLatinToLatin( $value );
		}
		
		$value = $this->removeSpecialCharacters( $value );
		
		if( ! empty( $settings['convert_to_lowercase'] ) ) {
			$value = mb_strtolower( $value, 'utf-8' );
		}
		
		return $value;
	}
}
