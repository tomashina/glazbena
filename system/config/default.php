<?php
// Site
$_['site_url']             = '';
$_['site_ssl']             = false;

// Url
$_['url_autostart']        = true;

// Language
$_['language_directory']   = 'en-gb';
$_['language_autoload']    = array('en-gb');

// Date
$_['date_timezone']        = 'UTC';

// Database
$_['db_engine']            = 'mysqli'; // mpdo, mysqli or pgsql
$_['db_hostname']          = 'localhost';
$_['db_username']          = 'root';
$_['db_password']          = '';
$_['db_database']          = '';
$_['db_port']              = 3306;
$_['db_autostart']         = false;

// Mail
$_['mail_engine']          = 'mail'; // mail or smtp
$_['mail_from']            = ''; // Your E-Mail
$_['mail_sender']          = ''; // Your name or company name
$_['mail_reply_to']        = ''; // Reply to E-Mail
$_['mail_smtp_hostname']   = '';
$_['mail_smtp_username']   = '';
$_['mail_smtp_password']   = '';
$_['mail_smtp_port']       = 25;
$_['mail_smtp_timeout']    = 5;
$_['mail_verp']            = false;
$_['mail_parameter']       = '';

// Cache
$cache_password = getenv('REDIS_PASSWORD');

if ($cache_password === false || in_array(strtolower(trim($cache_password)), array('', 'null', '(null)'), true)) {
	$cache_password = '';
}

$_['cache_engine']   = strtolower(getenv('CACHE_ENGINE') ?: (getenv('CACHE_DRIVER') ?: 'file')); // redis, mem or file
$_['cache_hostname'] = getenv('REDIS_HOST') ?: '127.0.0.1';
$_['cache_port']     = (int)(getenv('REDIS_PORT') ?: 6379);
$_['cache_password'] = $cache_password;
$_['cache_prefix']   = getenv('REDIS_PREFIX') ?: 'oc_';
$_['cache_expire']   = (int)(getenv('CACHE_EXPIRE') ?: 3600);

// (optional constants for your Redis driver)
if (!defined('CACHE_HOSTNAME')) define('CACHE_HOSTNAME', $_['cache_hostname']);
if (!defined('CACHE_PORT'))     define('CACHE_PORT',     $_['cache_port']);
if (!defined('CACHE_PASSWORD')) define('CACHE_PASSWORD', $_['cache_password']);
if (!defined('CACHE_PREFIX'))   define('CACHE_PREFIX',   $_['cache_prefix']);
if (!defined('CACHE_TIMEOUT'))  define('CACHE_TIMEOUT',  (float)(getenv('REDIS_TIMEOUT') ?: 1.0));

// Session
$_['session_engine']       = 'db';
$_['session_autostart']    = true;
$_['session_name']         = 'OCSESSID';

// Template
$_['template_engine']      = 'twig';
$_['template_directory']   = '';
$_['template_cache']       = false;

// Error
$_['error_display']        = true;
$_['error_log']            = true;
$_['error_filename']       = 'error.log';

// Reponse
$_['response_header']      = array('Content-Type: text/html; charset=utf-8');
$_['response_compression'] = 0;

// Autoload Configs
$_['config_autoload']      = array();

// Autoload Libraries
$_['library_autoload']     = array();

// Autoload Libraries
$_['model_autoload']       = array();

// Actions
$_['action_default']       = 'common/home';
$_['action_router']        = 'startup/router';
$_['action_error']         = 'error/not_found';
$_['action_pre_action']    = array();
$_['action_event']         = array();
