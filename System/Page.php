<?php

namespace Floxim\Floxim\System;

class Page
{

    // title, keywords, description
    protected $metatags = array();
    
    protected $all_css;
    
    protected $files_js = [];
    
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
        return fx::assets();
    }
    
    public function getDefaultCssBundle()
    {
        static $bundle_added = false;
        $bundle = fx::assets('css');
        if (!$bundle_added) {
            $bundle->collect_pushed_files = true;
            $this->files_css[]= $bundle;
            $bundle_added = true;
        }
        return $bundle;
    }
    
    public function getTempCssBundle()
    {
        static $bundle_added = false;
        $bundle = fx::assets('css', 'temp');
        if (!$bundle_added) {
            $bundle->delete();
            $this->files_css[]= $bundle;
            $bundle_added = true;
        }
        return $bundle;
    }
    
    public function addToBundle($files, $bundle_keyword)
    {
        $this->getBundleManager()->addToBundle($files, $bundle_keyword);
    }

    protected $inline_styles = array();

    public function addInlineStyles($styles, $selector = null)
    {
        $this->inline_styles []= array($styles, $selector);
    }

    public function getInlineStyles()
    {
        $by_media = array();
        foreach ($this->inline_styles as $group) {
            $rules = $group[0];
            $selector = $group[1];
            foreach ($rules as $media_type => $props) {
                if (is_array($props)) {
                    $res_props = '';
                    foreach ($props as $p => $v) {
                        if ($v) {
                            $res_props .= $p . ':' . $v . ';';
                        }
                    }
                    $props = $res_props;
                }
                if (!$props) {
                    continue;
                }
                if (!isset($by_media[$media_type])) {
                    $by_media[$media_type] = '';
                }
                $by_media[$media_type] .= $selector ." {".$props."}";
            }
        }
        $res = '';
        if (isset($by_media['default'])) {
            $res .= $by_media['default'];
        }
        foreach ($by_media as $media => $css) {
            if ($media !== 'default') {
                $res .= "@media (".$media.") {".$css."}";
            }
        }
        return $res;
    }

    public function clearFiles()
    {
        $this->files_css = array();
        $this->files_js = array();
        $this->all_js = array();
    }
    
    public function getCssBundle($name)
    {
        static $added = [];
        $bundle = fx::assets('css', $name);
        if (!in_array($name, $added)) {
            $this->files_css []= $bundle;
            $added[]= $name;
        }
        return $bundle;
    }
    
    public function getJsBundle($name)
    {
        static $added = [];
        $bundle = fx::assets('js', $name);
        if (!in_array($name, $added)) {
            $this->files_js []= $bundle;
            $added[]= $name;
        }
        return $bundle;
    }
    
    public function addCss($files, $params = [])
    {
        $to = isset($params['to']) ? $params['to'] : 'default';
        $namespace = isset($params['namespace']) ? $params['namespace'] : '';
        
        $name = isset($params['name']) ? $params['name'] : md5(join(':',$files));
        
        $bundle = fx::assets('css', $name, ['namespace' => $namespace]);
        $bundle->push($files);
        
        $target = $this->getCssBundle($to);
        $target->push([$bundle]);
    }
    
    public function addJs($files, $params = [])
    {
        $to = isset($params['to']) ? $params['to'] : 'default';
        $target = $this->getJsBundle($to);
        $target->push($files);
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

    protected $after_body = array();
    public function setAfterBody($txt)
    {
        $this->after_body[] = $txt;
    }

    public function ajaxResponse($result)
    {
        $redrawn = fx::router('ajax')->handleRedraw();
        $css = $this->getCssFilesFinal();
        $css_res = array();
        foreach ($css as $set) {
            if (isset($set['file'])) {
                $css_res []= $set['file'];
            } else {
                $css_res []= $set;
            }
        }
        
        $js_res = [];
        
        foreach ($this->files_js as $f) {
            if ($f instanceof \Floxim\Floxim\Asset\JsBundle) {
                $f->save();
                
            }
        }
        
        $response = array(
            'format' => 'fx-response',
            'response' => $result,
            //'js' => $this->files_js,
            'css' => $css_res,
            'request' => $_POST
        );
        if ($redrawn) {
            $response['redraw'] = $redrawn;
        }
        return json_encode($response);
    }
    
    public function isOverriden()
    {
        static $is_overriden = null;
        if (is_null($is_overriden)) {
            $is_overriden = fx::isAdmin() && fx::input('post', 'override_infoblock');
        }
        return $is_overriden;
    }
    
    public static function getOverridenVisual()
    {
        static $res = null;
        if (is_null($res)) {
            $data = fx::input('post', 'override_infoblock');
            if (!$data) {
                $res = false;
            } elseif (!$data['id']) {
                $res = true;
            } else {
                $res = [];
                $vis = $data['visual'];
                
                $infoblock = fx::data('infoblock', $data['id']);
                $infoblock_visual = $infoblock->getVisual();
                
                $rex = '~\:~';
                foreach (['template','wrapper'] as $vis_prop) {
                    $template = $vis[$vis_prop];
                    if (preg_match($rex, $template)) {
                        $res []= [
                            'infoblock_visual',
                            $infoblock_visual['id'],
                            $vis_prop
                        ];
                    } else {
                        $res []= [
                            'template_variant',
                            $template,
                            $vis_prop
                        ];
                    }
                }
            }
        }
        return $res;
    }
    
    public function addStyleFilter($block)
    {
        static $added = [];
        if (isset($added[$block])) {
            return;
        }
        $parts = explode("--", $block);
        
        $last_name = array_pop($parts);
        $namespace = join(".", $parts);
        
        $res = \Floxim\Floxim\Template\Loader::nameToPath($namespace).DIRECTORY_SEPARATOR.$last_name.'_style_filter.js';
        
        $this->addJs([$res], ['to' => 'admin']);
        $added[$block] = true;
    }
    
    public function addStyleLess(
        $block, 
        $value, 
        $params = array()
    )
    {
        $bundle_is_temp = $this->isOverriden();
        
        $bundle_keyword = $block.'_'.$value;
        $bundle = fx::assets('style', $bundle_keyword, $params);
        $this->getDefaultCssBundle()->push( array($bundle) );
        
        if ( $bundle_is_temp ) {
            $export_file = $bundle->getTempExportFile();
        } else {
            $export_file = $bundle->getExportFile();
        }
        return $export_file;
    }
    
    public function getCssFilesFinal() 
    {
        $res = array();
        foreach ($this->files_css as $f) {
            if ($f instanceof \Floxim\Floxim\Asset\Less\Bundle) {
                $is_default = $f->isDefaultBundle();
                
                // don't save main bundle in IB override mode
                if (!$this->isOverriden() || !$is_default) {
                    $f->save();
                }
                
                if (fx::isAdmin() && $is_default) {
                    $f = $f->getAdminOutput();
                } else {
                    $f = array(
                        'file' => fx::path()->http($f->getFilePath())
                    );
                }
            }
            if (!is_array($f)) {
                $f = array('file' => $f);
            }
            if (isset($f['file'])) {
                $res[$f['file']] = $f;
            } else {
                $res []= $f;
            }
        }
        return $res;
    }

    public function getAssetsCode()
    {
        $r = '';
        $files_css = $this->getCssFilesFinal();
        foreach ($files_css as $file => $file_info) {
            if (isset($file_info['file'])) {
                $r .= '<link rel="stylesheet" type="text/css" href="' . $file . '" ';
                if (isset($file_info['media'])) {
                    $r .= ' media="('.$file_info['media'].')" ';
                }
                $r .= '/>' . PHP_EOL;
            } else {
                if (isset($file_info['declarations'])) {
                    $this->addJsText(
                        '$fx.less_block_declarations = '.  json_encode($file_info['declarations']).';'
                    );
                }
                if (isset($file_info['blocks'])) {
                    foreach ($file_info['blocks'] as $block) {
                        $r .= '<style type="text/css"';
                        $r .= ' id="'.$block['style_class'].'"';
                        $r .= ' data-file="'.$block['file'].'"';
                        $r .= ' data-filemtime="'.$block['filemtime'].'"';
                        $r .= ' data-declaration="'.$block['declaration_keyword'].'">'."\n";
                        $r .= $block['css'];
                        $r .= '</style>';
                    }
                }
                if (isset($file_info['styles'])) {
                    foreach ($file_info['styles'] as $style_href) {
                        $r .= '<link type="text/css" rel="stylesheet" href="'.$style_href.'" />'."\n";
                    }
                }
            }
        }
        
        if ($this->files_js) {
            //$files_js = array_unique($this->files_js);
            
            $files_js = $this->files_js;

            foreach ($files_js as $v) {
                if ($v instanceof \Floxim\Floxim\Asset\Bundle) {
                    $v->save();
                    $v = fx::path()->http($v->getFilePath());
                }
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
    
    public function addLayoutVars()
    {
        $vars = fx::env('palette')->getVals();
        
        $fonts = array();
        
        $all_fonts = \Floxim\Floxim\Asset\Fonts::getAvailableFonts();
        $font_types = \Floxim\Floxim\Component\Palette\Entity::getFontTypes();
        
        $theme_fonts = fx::env('theme')->getThemeFonts();
        
        foreach ($vars as $k => $v) {
            if (preg_match("~^font_~", $k)) {
                $v = trim($v, '"');
                if (isset($all_fonts[$v])) {
                    $font_styles = $all_fonts[$v];
                    $kw = preg_replace("~^font_~", '', $k);
                    $font_type = $font_types[$kw];
                    $fonts []= array(
                        'keyword' => $kw,
                        'type' => $font_type,
                        'family' => $v,
                        'styles' => $font_styles
                    );
                }
            }
        }
        
        $js = '$fx.container.layout_sizes = ' .json_encode( \Floxim\Floxim\Template\Container::getLayoutSizes()).";\n";
        $js .= '$fx.layout_vars = '.json_encode( $vars ).";\n";
        $js .= '$fx.layout_fonts = '.json_encode( $fonts ).";\n";
        $js .= '$fx.theme_fonts = '.json_encode( $theme_fonts ).";\n";
        $this->addJsText($js);
    }
    
    protected $raw_head_items = [];
    
    public function addHeadItem($source)
    {
        $this->raw_head_items[]= $source;
    }

    public function postProcess($buffer)
    {
        fx::trigger('before_postprocess', [
            'html' => $buffer
        ]);
        $r = '';
        if (isset($this->metatags['seo_title'])) {
            $r = "<title>" . strip_tags($this->metatags['seo_title']) . "</title>" . PHP_EOL;
        }
        
        $r .= join("\n", $this->raw_head_items);
        
        if (isset($this->metatags['seo_description'])) {
            $r .= '<meta name="description" content="'
                . strip_tags($this->metatags['seo_description']) . '" />' . PHP_EOL;
        }
        if (isset($this->metatags['seo_keywords'])) {
            $r .= '<meta name="keywords" content="'
                . strip_tags($this->metatags['seo_keywords']) . '" />' . PHP_EOL;
        }
        // $r .= '<base href="'.(is_null($this->base_url) ? FX_BASE_URL : $this->base_url).'/" />';
        
        $this->setAfterBody( 
            \Floxim\Floxim\Asset\Fonts::getLoaderJS(
                fx::env('palette')->getUsedFonts()
            ) 
        );
        

        $r .= $this->getAssetsCode();
        
        
        if (!preg_match("~<head(\s[^>]*?|)>~i", $buffer)) {
            if (preg_match("~<html[^>]*?>~i", $buffer)) {
                $buffer = preg_replace("~<html[^>]*?>~i", '$0<head> </head>', $buffer);
            } else {
                $buffer = '<html><head> </head>' . $buffer . '</html>';
            }
        }

        $buffer = preg_replace("~<title>.+</title>~i", '', $buffer);
        $buffer = preg_replace("~</head\s*?>~i", $r . '$0', $buffer);
        
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
                    fx::log('suitable?', $ib);
                    throw new \Exception('No more suitable (infoblock_id: '.$ib['id'].')');
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