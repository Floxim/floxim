<?php

namespace Floxim\Floxim\System;

class Page
{

    // title, keywords, description
    protected $metatags = array();
    
    protected $all_css;
    
    protected $files_js;
    
    protected $files_css = array();


    public function setMetatags($item, $value, $post = '')
    {
        $item = 'seo_' . $item;
        $this->metatags[$item] = $value;
        if ($post) {
            $this->metatags_post[$item] = $post;
        }
        return $this;
    }
    
    public function getLessCompiler()
    {
        $c = new \lessc();
        return $c;
    }

    /**
     * Get current meta tag page
     * @param mixed title, keywords, description
     * @param mixed value or array
     */
    public function getMetatags($item = '')
    {
        if ($item) {
            $item = 'seo_' . $item;
            return isset($this->metatags[$item]) ? $this->metatags[$item] : null;
        }
        return $this->metatags;
    }
    
    public function getBundleManager()
    {
        static $bundleManager = null;
        if (is_null($bundleManager)) {
            $bundleManager = new \Floxim\Floxim\Asset\Manager();
        }
        return $bundleManager;
    }
    
    public function addToBundle($files, $bundle_keyword)
    {
        $this->getBundleManager()->addToBundle($files, $bundle_keyword);
    }

    public function addFile($file)
    {
        if (preg_match("~\.(?:less|css)$~", $file)) {
            return $this->addCssFile($file);
        }
        if (substr($file, strlen($file) - 3) == '.js') {
            return $this->addJsFile($file);
        }
    }

    public function addCssFile($file, $params = array())
    {
        if (preg_match("~\.less$~", $file)) {

            $file_hash = strtolower(trim(preg_replace("~[^a-z0-9_-]+~i", '_', fx::path()->http($file)), '_'));

            $target_path = fx::path()->http('@files/asset_cache/' . $file_hash . '.css');
            $full_target_path = fx::path()->abs($target_path);
            $full_source_path = fx::path()->abs($file);


            if (!file_exists($full_source_path)) {
                return;
            }

            if (!file_exists($full_target_path) || filemtime($full_source_path) > filemtime($full_target_path)) {
                
                $http_base = fx::path()->http(preg_replace("~[^/]+$~", '', $file));

                $less = $this->getLessCompiler();
                $less->setImportDir(dirname($full_source_path));

                $file_content = file_get_contents($full_source_path);
                $file_content = $this->cssUrlReplace($file_content, $http_base);
                
                $file_content = $less->compile($file_content);
                fx::files()->writefile($full_target_path, $file_content);
            }
            if (count($params) > 0) {
                $file = array_merge($params, array('file' => $target_path));
            } else {
                $file = $target_path;
            }
            $this->files_css[] = $file;
            $this->all_css[] = $target_path;
            return;
        }
        if (!preg_match("~^https?://~", $file)) {
            $file = fx::path()->http($file);
        }
        if (count($params) > 0) {
            $file = array_merge($params, array('file' => $file));
        }
        $this->files_css[] = $file;
    }

    public function clearFiles()
    {
        $this->files_css = array();
        $this->files_js = array();
        $this->all_js = array();
    }
    
    public function addCssBundleFromString($string, $template_dir, $params = array())
    {
        $lines = explode("\n", $string);
        $files = array();
        if (is_string($template_dir)) {
            $template_dir = array($template_dir);
        }
        foreach ($lines as $l) {
            $l = trim($l);
            if (empty($l)) {
                continue;
            }
            if (preg_match("~^https?://~i", $l)) {
                $this->addCssFile($l);
                continue;
            }
            if (!preg_match("~^(/|https?://)~", $l)) {
                foreach ($template_dir as $c_dir) {
                    $files[]= fx::path()->abs($c_dir.$l);
                }
            }else {
                $files []= $l;
            }
        }
        $this->addCssBundle($files, $params);
    }

    public function addCssBundle($files, $params = array())
    {
        if (!isset($params['name'])) {
            $params['name'] = md5(join($files));
        }
        $bundle_name = $params['name'];
        if ($bundle_name === 'auto') {
            $bundle_name = fx::env('css_bundle');
        }
        
        if ($bundle_name === 'none') {
            return;
        }
        
        $manager = $this->getBundleManager();
        $bundle = $manager->getBundle('css', $bundle_name);
        $bundle->push($files);
        if (!in_array($bundle, $this->files_css)) {
            $this->files_css[]= $bundle;
        }
    }

    protected function lessImportUrlReplace($file_contents, $http_base) 
    {
        $http_base = preg_replace("~^/~", '', $http_base);
        $res = preg_replace_callback(
            "~@import (.+?);~", 
            function ($matches) use ($http_base) {
                $file = trim($matches[1], '"\'');
                return '@import "'.$http_base.$file.'";';
            },
            $file_contents
        );
        return $res;
    }
    
    protected function cssUrlReplace($file, $http_base)
    {
        $file = preg_replace_callback(
            '~(url\([\'\"]?)([^/][^\)]+)~i',
            function ($matches) use ($http_base) {
                // do not touch data and absolute urls
                if (preg_match("~data\:~", $matches[0]) || preg_match('~^[\'\"]?/~', $matches[2])) {
                    return $matches[0];
                }
                return $matches[1] . $http_base . $matches[2];
            },
            $file
        );
        return $file;
    }

    // both simple scripts & scripts from bundles
    protected $all_js = array();

    public function addJsFile($file)
    {
        if (empty($file)) {
            return;
        }
        if (!preg_match("~^https?://~", $file)) {
            $file = fx::path()->http($file);
        }
        if (!in_array($file, $this->all_js)) {
            $this->files_js[] = $file;
            $this->all_js[] = $file;
        }
    }

    protected $_file_aliases = array();

    public function hasFileAlias($alias, $type, $set = null)
    {
        $key = $alias . '_' . $type;
        if ($set === null) {
            return isset($this->_file_aliases[$key]) && $this->_file_aliases[$key];
        }
        $this->_file_aliases[$key] = (bool)$set;
    }

    public function addJsBundle($files, $params = array())
    {
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
        $params['name'] .= '.js.gz';

        $http_path = fx::path()->http('@files/asset_cache/' . $params['name']);
        $full_path = fx::path()->abs($http_path);

        $http_files = array();
        foreach ($files as $f) {
            if (!empty($f)) {
                $http_files[] = fx::path()->http($f);
            }
        }
        $this->all_js = array_merge($this->all_js, $http_files);

        if (!file_exists($full_path)) {
            $bundle_content = '';
            foreach ($files as $i => $f) {
                if (!preg_match("~^http://~i", $f)) {
                    $f = fx::path()->abs($f);
                }
                $file_content = file_get_contents($f);
                if (!preg_match("~\.min~", $f)) {
                    $minified = \JSMin::minify($file_content);
                    $file_content = $minified;
                }
                $bundle_content .= $file_content . ";\n";
            }

            $plain_path = preg_replace("~\.js\.gz$~", ".js", $full_path);
            fx::files()->writefile($plain_path, $bundle_content);

            $fh = gzopen($full_path, 'wb5');
            gzwrite($fh, $bundle_content);
            gzclose($fh);

        }
        if (!$this->acceptGzip()) {
            $http_path = preg_replace("~\.js\.gz$~", ".js", $http_path);
        }
        $this->files_js[] = $http_path;
    }

    protected function acceptGzip()
    {
        if (!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            return false;
        }
        if (!fx::config('cache.gzip_bundles')) {
            return false;
        }
        return in_array('gzip', explode(",", $_SERVER['HTTP_ACCEPT_ENCODING']));
    }

    public function addDataJs($keyword, $values)
    {
        $this->_data_js[$keyword] = $values;
    }

    public function getDataJs()
    {
        return $this->_data_js;
    }

    public function addJsText($text)
    {
        $this->_js_text[] = $text;
    }

    public function getJsText()
    {
        return $this->_js_text;
    }

    public function setNumbers($block_number = 1, $field_number = 1)
    {
        $this->block_number = intval($block_number);
        $this->field_number = intval($field_number);
    }

    protected $after_body = array();
    public function setAfterBody($txt)
    {
        $this->after_body[] = $txt;
    }

    /**
     * Add assets (js & css) to ajax responce via http headers
     */
    public function addAssetsAjax()
    {
        fx::http()->header('fx_assets_js', $this->files_js);
        $files_css = array_keys($this->getCssFilesFinal());
        fx::http()->header('fx_assets_css', $files_css);
    }
    
    public function getCssFilesFinal() 
    {
        $res = array();
        foreach ($this->files_css as $f) {
            if ($f instanceof \Floxim\Floxim\Asset\Bundle) {
                $f->save();
                $f = array(
                    'file' => fx::path()->http($f->getFilePath())
                );
            }
            if (!is_array($f)) {
                $f = array('file' => $f);
            }
            $res[$f['file']] = $f;
        }
        return $res;
    }

    public function getAssetsCode()
    {
        $r = '';
        $files_css = $this->getCssFilesFinal();
        foreach ($files_css as $file => $file_info) {
            $r .= '<link rel="stylesheet" type="text/css" href="' . $file . '" ';
            if (isset($file_info['media'])) {
                $r .= ' media="('.$file_info['media'].')" ';
            }
            $r .= '/>' . PHP_EOL;
        }
        
        if ($this->files_js) {
            $files_js = array_unique($this->files_js);

            foreach ($files_js as $v) {
                $r .= '<script type="text/javascript" src="' . $v . '" ></script>' . PHP_EOL;
            }
        }
        if ($this->all_js || $this->all_css) {
            $r .= "<script type='text/javascript'>\n";
            if ($this->all_js) {
                $r .= "window.fx_assets_js = [\n";
                $r .= "'" . join("', \n'", $this->all_js) . "'\n";
                $r .= "];\n";
            }
            if ($this->all_css) {
                $r .= "window.fx_assets_css = [\n";
                $r .= "'" . join("', \n'", $this->all_css) . "'\n";
                $r .= "];\n";
            }
            $r .= '</script>';
        }
        return $r;
    }
    
    protected $base_url = null;
    public function setBaseUrl($url)
    {
        $this->base_url = $url;
    }

    public function postProcess($buffer)
    {
        $r = '';
        if (isset($this->metatags['seo_title'])) {
            $r = "<title>" . strip_tags($this->metatags['seo_title']) . "</title>" . PHP_EOL;
        }
        if (isset($this->metatags['seo_description'])) {
            $r .= '<meta name="description" content="'
                . strip_tags($this->metatags['seo_description']) . '" />' . PHP_EOL;
        }
        if (isset($this->metatags['seo_keywords'])) {
            $r .= '<meta name="keywords" content="'
                . strip_tags($this->metatags['seo_keywords']) . '" />' . PHP_EOL;
        }
        $r .= '<base href="'.(is_null($this->base_url) ? FX_BASE_URL : $this->base_url).'/" />';

        $r .= $this->getAssetsCode();
        
        if (!preg_match("~<head(\s[^>]*?|)>~i", $buffer)) {
            if (preg_match("~<html[^>]*?>~i", $buffer)) {
                $buffer = preg_replace("~<html[^>]*?>~i", '$0<head> </head>', $buffer);
            } else {
                $buffer = '<html><head> </head>' . $buffer . '</html>';
            }
        }

        //$buffer = preg_replace("~<head(\s[^>]*?|)>~", '$0'.$r, $buffer);
        $buffer = preg_replace("~<title>.+</title>~i", '', $buffer);
        $buffer = preg_replace("~</head\s*?>~i", $r . '$0', $buffer);
        
        $this->setAfterBody( 
            \Floxim\Floxim\Asset\Fonts::getLoaderJS(
                fx::env()->getLayoutStyleVariant()->getUsedFonts()
            ) 
        );
        
        if (count($this->after_body)) {
            $after_body = $this->after_body;
            if (!stristr($buffer, '<body')) {
                $buffer = str_replace("</html", '<body> </body></html', $buffer);
            }
            $buffer = preg_replace_callback(
                '~<body[^>]*?>~i',
                function ($body) use ($after_body) {
                    return $body[0] . join("\r\n", $after_body);
                },
                $buffer
            );
        }
        $buffer = str_replace("<body", "<body data-fx_page_id='" . fx::env('page_id') . "'", $buffer);


        if (fx::isAdmin()) {
            $js = '<script type="text/javascript">' . PHP_EOL;
            if (($js_text = $this->getJsText())) {
                $js .= join(PHP_EOL, $js_text) . PHP_EOL;
            }
            $js .= '</script>' . PHP_EOL;
            $buffer = str_replace('</body>', $js . '</body>', $buffer);
        }
        return $buffer;
    }


    protected $areas = null;

    public function setInfoblocks($areas)
    {
        $this->areas = $areas;
    }

    protected $areas_cache = array();
    
    public function getAreaInfoblocks($area_id)
    {
        $path = fx::env('path');
        
        $ibs = $this->getInfoblocks($path);
        
        $filtered = $ibs->find(function($ib) use ($area_id) {
            return $ib->getVisual()->get('area') === $area_id;
        });
        
        return $filtered;
    }
    
    public function getInfoblocks($path = null)
    {
        if (is_null($path)) {
            $path = fx::env('path');
        }
        static $cache = array();
        $hash = join('.', $path->getValues('id'));
        if (!isset($cache[$hash])) {
            $ibs = fx::data('infoblock')->with('visuals')->getForPath($path);
            $cache[$hash] = $ibs;
            foreach ($ibs  as $ib) {
                if ($ib->getVisual()->get('is_stub')) {
                    $suitable = new \Floxim\Floxim\Template\Suitable();
                    $suitable->suit($ibs, fx::env('layout_id'), fx::env()->getLayoutStyleVariantId());
                    break;
                }
            }
            $ibs->sort(function($ib) {
                return $ib->getVisual()->get('priority');
            });
        }
        return $cache[$hash];
    }
    
    public function getLayoutInfoblock($path = null)
    {
        return $this->getInfoblocks($path)->findOne(function($ib) {
            return $ib->isLayout();
        });
    }

    public function _getAreaInfoblocks($area_id)
    {
        // do nothing if the areas are not loaded yet
        if (is_null($this->areas)) {
            return array();
        }
        // or give them from cache
        if (isset($this->areas_cache[$area_id])) {
            return $this->areas_cache[$area_id];
        }

        $area_blocks = isset($this->areas[$area_id]) ? $this->areas[$area_id] : array();

        if (!$area_blocks || !(is_array($area_blocks) || $area_blocks instanceof \ArrayAccess)) {
            $area_blocks = array();
        }
        $area_blocks = fx::collection($area_blocks)->sort(function ($a, $b) {
            $a_pos = $a->getPropInherited('visual.priority');
            $b_pos = $b->getPropInherited('visual.priority');
            return $a_pos - $b_pos;
        });

        $area_blocks->findRemove(function ($ib) {
            return $ib->isDisabled();
        });
        $this->areas_cache[$area_id] = $area_blocks;
        return $area_blocks;
    }
}