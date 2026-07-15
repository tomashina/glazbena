<?php

require_once DIR_SYSTEM . 'minify/lib/Minify/CSS/Compressor.php';
require_once DIR_SYSTEM . 'minify/lib/Minify/CommentPreserver.php';
require_once DIR_SYSTEM . 'minify/lib/Minify/CSS/UriRewriter.php';
require_once DIR_SYSTEM . 'minify/lib/Minify/CSS.php';
require_once DIR_SYSTEM . 'minify/lib/Minify/JSMin.php';
require_once DIR_SYSTEM . 'minify/lib/Minify/HTML.php';

function j2_js_minify($js) {
    return JSMin::minify($js);
}

class MinifyMinifier {

    /** @var MinifyCache */
    private $cache;
    private $minify_css = false;
    private $minify_js = false;
    private $salt = null;
    private $path = null;
    private $dir = null;
    private $styles = array();
    private $scripts = array(
        'header' => array(),
        'footer' => array()
    );
    private $config =  array();

    public function __construct($store_id,$registry) {
        //global  $registry;
        $this->config = $registry->get('config');
        // $this->cache = $cache;
        if(!file_exists(DIR_CACHE.'css-js-cache/')){
            mkdir(DIR_CACHE.'css-js-cache/');
        }
         $this->path = DIR_CACHE.'css-js-cache/'.$store_id.'/';
       if(!file_exists($this->path)){
           mkdir($this->path);
       }
        $this->dir = HTTPS_SERVER."system/storage/cache/css-js-cache".$store_id.'/';
    }

    public function getSalt() {
        if ($this->salt !== null) {
            return $this->salt;
        }
      
        $file = $this->path . 'salt';
        if (!file_exists($file)) {
            $this->salt = md5(mt_rand());
            file_put_contents($file, $this->salt);
        } else {
            $this->salt = file_get_contents($file);
        }
        return $this->salt;
    }
    
    public function delete_css_and_js_cache($store_id){
        $path =  DIR_CACHE.'css-js-cache/'.$store_id.'/';
        $this->deleteDir($path);
    }
    
    public static function deleteDir($dirPath) {
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            self::deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

    public function getMinifyCss() {
        return $this->minify_css;
    }

    public function setMinifyCss($value) {
        $this->minify_css = $value;
    }

    public function getMinifyJs() {
        return $this->minify_js;
    }

    public function setMinifyJs($value) {
        $this->minify_js = $value;
    }

    public function addStyle($href) {
        $this->styles[md5($href)] = $href;
    }

    public function addScript($src, $position = 'header') {
        $this->scripts[$position][md5($src)] = $src;
    }

    public function css() {
     $this->minify_css= $this->config->get('config_minify_css');
        if ($this->minify_css) { 
        
            /* generate file if not exits */
             $combined_css_file = '_' . $this->getSalt() . '_' . $this->getHash($this->styles, 'css');  
            if (!file_exists($this->path . $combined_css_file)) { 
                /* parse all styles, generate corresponding minified css file */
                foreach ($this->styles as $style) {
                    $file = $this->path . $this->getHash($style, 'css');
                    if (!file_exists($file)) {
                        $css_file = realpath(DIR_SYSTEM . '../' . $this->removeQueryString($style));
                        if (!file_exists($css_file)) {
                            trigger_error("{$style} css file not found!");
                            continue;
                        }
                        $content = file_get_contents($css_file);
                        $content_min = Minify_CSS::minify($content, array(
                                    'preserveComments' => false,
                                    'currentDir' => dirname($css_file)
                        ));
                        file_put_contents($file, $content_min, LOCK_EX);
                    }
                }

                /* combine all styles into one file */
                $fh = @fopen($this->path . $combined_css_file, 'w');
                flock($fh, LOCK_EX);
                foreach ($this->styles as $style) {
                    $file = $this->path . $this->getHash($style, 'css');
                    if (!file_exists($file)) {
                        continue;
                    }
                    $content = file_get_contents($file);
                    fwrite($fh, $content);
                }

                /* append minify.css at the end */
               
                flock($fh, LOCK_UN);
                fclose($fh);
            }
            /* return link tag */
           
          return $this->printStyle($this->dir . $combined_css_file);
        }
        $assets = '';
        foreach ($this->styles as $style) {
            $assets .= $this->printStyle($style);
        }
       
        return $assets;
    }

    public function js($position = 'header') {
        $this->minify_js = $this->config->get('config_minify_js');
        if ($this->minify_js) {
            /* generate file if not exits */
            $combined_js_file = '_' . $this->getSalt() . '_' . $this->getHash($this->scripts[$position], 'js');
            if (!file_exists($this->path . $combined_js_file)) {
                /* parse all scripts, generate corresponding minified js file */
                foreach ($this->scripts[$position] as $script) {
                    $file = $this->path . $this->getHash($script, 'js');
                    if (!file_exists($file)) {
                        $js_file = realpath(DIR_SYSTEM . '../' . $this->removeQueryString($script));
                        if (!file_exists($js_file)) {
                            trigger_error("{$script} js file not found!");
                            continue;
                        }
                        $content = file_get_contents($js_file);
                        $content_min = JSMin::minify($content);
                        file_put_contents($file, $content_min, LOCK_EX);
                    }
                }

                /* combine all scripts into one file */
                $fh = @fopen($this->path . $combined_js_file, 'w');
                flock($fh, LOCK_EX);
                foreach ($this->scripts[$position] as $script) {
                    $file = $this->path . $this->getHash($script, 'js');
                    if (!file_exists($file)) {
                        continue;
                    }
                    $content = file_get_contents($file);
                    fwrite($fh, ';' . $content);
                }

                /* append minify.js at the end */
               
                flock($fh, LOCK_UN);
                fclose($fh);
            }
            /* return link tag */
            if ($position === 'footer') {
                return $this->printScript($this->dir . $combined_js_file, ' defer', $this->config);
            }
            return $this->printScript($this->dir . $combined_js_file,'',$this->config);
        }
        $assets = '';
        foreach ($this->scripts[$position] as $script) {
            $assets .= $this->printScript($script,'',$this->config);
        }
        return $assets;
    }

    private function getHash($files, $ext) {
        $hash = '';
        if (is_array($files)) {
            foreach ($files as $file) {
                $hash .= $file;
            }
        } else {
            $hash = $files;
        }
       
        return md5($hash) . '.' . $ext;
    }

    private function printStyle($href) {
            return '<link rel="stylesheet" href="' . $this->addminifiedVersion($href, $this->minify_css) . '"/>' . PHP_EOL;
    }

    private function printScript($src, $def = '', $config) {
        
        //global $config;
        if ($config->get('cdn_status')) {
            if ($config->get('cdn_js')) {
                $cdn_protocol = (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) ? 'https://' : 'http://';
                $cdn_domain = $cdn_protocol . $config->get('cdn_domain_data_type');
                $src = $cdn_domain.$src;
            }
        }
        $def = rtrim($def) . ' ';
        
        return '<script  type="text/javascript"' . $def . 'src="' . $this->addminifiedVersion($src, $this->minify_css) . '"></script>' . PHP_EOL;
    }

    private function removeQueryString($file) {
        $file = explode('?', $file);
        return $file[0];
    }

    private function addminifiedVersion($url, $is_minified) {
        if ($is_minified) {
            return $url;
        }
       
        return $url ;
    }

}