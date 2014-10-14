<?php

namespace Floxim\Floxim\System;

class Page {

    // title, keywords, description
    protected $metatags = array();
    
    
    /**
     * To install the meta-tag for the page
     * @param string title, keywords, description
     * @param string value
     */
    public function setMetatags($item, $value, $post = '') {
        $item = 'seo_'.$item;
        $this->metatags[$item] = $value;
        if ($post) {
            $this->metatags_post[$item] = $post;
        }
        return $this;
    }

    /**
     * Get current meta tag page
     * @param mixed title, keywords, description
     * @param mixed value or array
     */
    public function getMetatags($item = '') {
        $item = 'seo_'.$item;
        if ($item) {
            return isset($this->metatags[$item]) ? $this->metatags[$item] : null;
        }
        return $this->metatags;
    }

    public function addFile($file) {
        if (preg_match("~\.(?:less|css)$~", $file)) {
            return $this->addCssFile($file);
        }
        if (substr($file, strlen($file) - 3) == '.js') {
            return $this->addJsFile($file);
        }
    }
    
    public function addCssFile($file) {
        if (preg_match("~\.less$~", $file)) {
            
            $file_hash = trim(preg_replace("~[^a-z0-9_-]+~", '_', fx::path()->toHttp($file)), '_');
            
            $target_path = fx::path()->http('files', 'asset_cache/'.$file_hash.'.css');
            $full_target_path = fx::path()->toAbs($target_path);
            $full_source_path = fx::path()->toAbs($file);
            

            if (!file_exists($full_source_path)) {
                return;
            }
            
            if (!file_exists($full_target_path) || filemtime($full_source_path) > filemtime($full_target_path)) {
                fx::profiler()->block('compile less '.$file);
                $http_base = fx::path()->toHttp(preg_replace("~[^/]+$~", '', $file));

                $less = new \lessc();
                
                $file_content = file_get_contents($full_source_path);
                $file_content = $this->cssUrlReplace($file_content, $http_base);
                
                $file_content = $less->compile($file_content);
                fx::files()->writefile($full_target_path, $file_content);
                fx::profiler()->stop();
            }
            $this->_files_css[]= $target_path;
            $this->_all_css[] = $target_path;
            return;
        }
        if (!preg_match("~^https?://~", $file)) {
            $file = fx::path()->toHttp($file);
        }
        $this->_files_css[] = $file;
    }
    
    public function clearFiles() {
        $this->_files_css = array();
        $this->_files_js = array();
        $this->_all_js = array();
    }

    public function addCssBundle ($files, $params = array()) {
        
        if (!isset($params['name'])) {
            $params['name'] = md5(join($files));
        }
        $params['name'] .= '.cssgz';
        
        $http_path = fx::path()->http('files', 'asset_cache/'.$params['name']);
        $full_path = fx::path()->toAbs($http_path);
        
        $last_modified = 0;
        $less_flag = false;
        foreach ($files as $file) {
            if (preg_match("~\.less$~", $file)) {
                $less_flag = true;
            }
            if (!preg_match("~^http://~i", $file)) {
                $file_path = fx::path()->toAbs($file);
                $c_modified = filemtime($file_path);
                if ($c_modified > $last_modified) {
                    $last_modified = $c_modified;
                }
            }
        }
        
        if (!file_exists($full_path) || filemtime($full_path) < $last_modified) {
            $file_content = '';
            foreach ($files as $file) {
                if (preg_match("~^http://~i", $file)) {
                    $file_contents = file_get_contents($file);
                } else {
                    $http_base = fx::path()->toHttp($file);
                    $http_base = preg_replace("~[^/]+$~", '', $http_base);
                    $file_contents = file_get_contents(fx::path()->toAbs($file));
                    $file_contents = $this->cssUrlReplace($file_contents, $http_base);
                }
                $file_content .= $file_contents."\n";
            }

            if ($less_flag) {
                $less = new \lessc();
                $file_content = $less->compile($file_content);
            }
            
            $plain_path = preg_replace("~\.cssgz$~", ".css", $full_path);
            // directory should be created here:
            fx::files()->writefile($plain_path, $file_content);
            
            $fh = gzopen($full_path, 'wb5');
            gzwrite($fh, $file_content);
            gzclose($fh);
        }
        
        if (!$this->acceptGzip()) {
            $http_path = preg_replace("~\.cssgz$~", ".css", $http_path);
        }
        $this->_files_css[]= $http_path;
    }

    protected function cssUrlReplace ($file, $http_base) {
        $file = preg_replace_callback(
            '~(url\([\'\"]?)([^/][^\)]+)~i', 
            function($matches) use ($http_base) {
                if (preg_match("~data\:~", $matches[0])) {
                    return $matches[0];
                }
                return $matches[1].$http_base.$matches[2];
            }, 
            $file
        );
        return $file;
    }

    // both simple scripts & scripts from bundles
    protected $_all_js = array();
    
    public function addJsFile($file) {
        if (!preg_match("~^https?://~", $file)) {
            $file = fx::path()->toHttp($file);
        }
        if (!in_array($file, $this->_all_js)) {
            $this->_files_js[] = $file;
            $this->_all_js[]= $file;
        }
    }
    
    protected $_file_aliases = array();
    public function hasFileAlias($alias, $type, $set = null) {
        $key = $alias.'_'.$type;
        if ($set === null) {
            return isset($this->_file_aliases[$key]) && $this->_file_aliases[$key];
        }
        $this->_file_aliases[$key] = (bool) $set;
    }
    
    public function addJsBundle($files, $params = array()) {
        // for dev mode
        if (fx::config('dev.on')) {
            foreach ($files as $f) {
                $this->addJsFile($f);
            }
            return;
        }
        if (!isset($params['name'])) {
            $params['name'] = md5(join($files));
        }
        $params['name'] .= '.jsgz';
        
        $http_path = fx::path()->http('files', 'asset_cache/'.$params['name']);
        $full_path = fx::path()->toAbs($http_path);
        
        $this->_all_js = array_merge($this->_all_js, $files);
        
        if (!file_exists($full_path)) {
            require_once(fx::config()->INCLUDE_FOLDER.'JSMinPlus.php');
            $bundle_content = '';
            foreach ($files as $i => $f) {
                if (!preg_match("~^http://~i", $f)) {
                    $f = fx::path()->toAbs($f);
                }
                $file_content = file_get_contents($f);
                if (!preg_match("~\.min~", $f)) {
                    $minified = JSMinPlus::minify($file_content);
                    $file_content = $minified;
                }
                $bundle_content .= $file_content.";\n";
            }
            
            $plain_path = preg_replace("~\.jsgz$~", ".js", $full_path);
            fx::files()->writefile($plain_path, $bundle_content);
            
            $fh = gzopen($full_path, 'wb5');
            gzwrite($fh, $bundle_content);
            gzclose($fh);
            
        }
        if (!$this->acceptGzip()) {
            $http_path = preg_replace("~\.jsgz$~", ".js", $http_path);
        }
        $this->_files_js[]= $http_path;
    }
    
    protected function acceptGzip() {
        if (!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            return false;
        }
        if (!fx::config('cache.gzip_bundles')) {
            return false;
        }
        return in_array('gzip', explode(",", $_SERVER['HTTP_ACCEPT_ENCODING']));
    }

    public function addDataJs($keyword, $values) {
        $this->_data_js[$keyword] = $values;
    }

    public function getDataJs() {
        return $this->_data_js;
    }

    public function addJsText($text) {
        $this->_js_text[] = $text;
    }

    public function getJsText() {
        return $this->_js_text;
    }

    public function setNumbers($block_number = 1, $field_number = 1) {
        $this->block_number = intval($block_number);
        $this->field_number = intval($field_number);
    }

    public function setAfterBody($txt) {
        $this->_after_body[] = $txt;
    }
    
    /**
     * Add assets (js & css) to ajax responce via http headers
     */
    public function addAssetsAjax() {
        fx::http()->header('fx_assets_js', $this->_files_js);
        fx::http()->header('fx_assets_css', $this->_files_css);
    }
    
    public function getAssetsCode() {
        $r = '';
        if ($this->_files_css) {
            $files_css = array_unique($this->_files_css);
            foreach ($files_css as $v) {
                $r .= '<link rel="stylesheet" type="text/css" href="'.$v.'" />'.PHP_EOL;
            }
        }
        if ($this->_files_js) {
            $files_js = array_unique($this->_files_js);
            
            foreach ($files_js as $v) {
                $r .= '<script type="text/javascript" src="'.$v.'" ></script>'.PHP_EOL;
            }
        }
        if ($this->_all_js || $this->_all_css) {
            $r .= "<script type='text/javascript'>\n";
            if ($this->_all_js) {
                $r .= "window.fx_assets_js = [\n";
                $r .= "'".join("', \n'", $this->_all_js)."'\n";
                $r .= "];\n";
            }
            if ($this->_all_css) {
                $r .= "window.fx_assets_css = [\n";
                $r .= "'".join("', \n'", $this->_all_css)."'\n";
                $r .= "];\n";
            }
            $r .= '</script>';
        }
        return $r;
    }

    public function postProcess($buffer) {
        if ($this->metatags['seo_title']) {
            $r = "<title>".strip_tags($this->metatags['seo_title'])."</title>".PHP_EOL;
        }
        if ($this->metatags['seo_description']) {
            $r .= '<meta name="description" content="' 
                    . strip_tags($this->metatags['seo_description']) . '" />'.PHP_EOL;
        }
        if ($this->metatags['seo_keywords']) {
            $r .= '<meta name="keywords" content="' 
                    . strip_tags($this->metatags['seo_keywords']) . '" />'.PHP_EOL;
        }

        $r .= $this->getAssetsCode();
        
        if (!preg_match("~<head(\s[^>]*?|)>~i", $buffer)) {
            if (preg_match("~<html[^>]*?>~i", $buffer)) {
                $buffer = preg_replace("~<html[^>]*?>~i", '$0<head> </head>', $buffer);
            } else {
                $buffer = '<html><head> </head>'.$buffer.'</html>';
            }
        }
        
        //$buffer = preg_replace("~<head(\s[^>]*?|)>~", '$0'.$r, $buffer);
        $buffer = preg_replace("~<title>.+</title>~i", '', $buffer);
        $buffer = preg_replace("~</head\s*?>~i", $r.'$0', $buffer);

        if ($this->_after_body) {
            $after_body = $this->_after_body;
            $buffer = preg_replace_callback(
                '~<body[^>]*?>~i', 
                function($body) use ($after_body) {
                    return $body[0].join("\r\n", $after_body);
                },
                $buffer
            );
        }
        $buffer = str_replace("<body", "<body data-fx_page_id='".fx::env('page_id')."'", $buffer);

        
        if (fx::isAdmin()) {
            $js = '<script type="text/javascript">'.PHP_EOL;
            if ( ($js_text = $this->getJsText() )) {
                $js .= join(PHP_EOL, $js_text).PHP_EOL;
            }
            $js .= '</script>'.PHP_EOL;
            $buffer = str_replace('</body>', $js.'</body>', $buffer);
        }
        return $buffer;
    }
    
    
    protected $areas = null;
    public function setInfoblocks($areas) {
        $this->areas = $areas;
    }
    protected $areas_cache = array();
    
    public function getAreaInfoblocks($area_id) {
        // do nothing if the areas are not loaded yet
        if (is_null($this->areas)) {
            return array();
        }
        // or give them from cache
        if (isset($this->areas_cache[$area_id])) {
            return $this->areas_cache[$area_id];
        }
        
        $area_blocks = isset($this->areas[$area_id]) ? $this->areas[$area_id] : array();
        
        if (!$area_blocks || !(is_array($area_blocks) || $area_blocks instanceof \ArrayAccess) ) {
            $area_blocks = array();
        }
        $area_blocks = fx::collection($area_blocks)->sort(function($a, $b) {
            $a_pos = $a->getPropInherited('visual.priority');
            $b_pos = $b->getPropInherited('visual.priority');
            return $a_pos - $b_pos;
        });
        
        $area_blocks->findRemove(function($ib) {
            return $ib->isDisabled();
        });
        $this->areas_cache[$area_id] = $area_blocks;
        return $area_blocks;
    }
}