<?php

namespace Floxim\Floxim\Asset\Less;

use \Floxim\Floxim\System\Fx as fx;

use \Symfony\Component\Yaml;

/*
 * [block--name]_[style_name] - default (with no external vars)
 * [block--name]_[style_name]_inline_[infoblock_visual.id]_[md5(visual_id.'-'.bundle_id)]
 * [block--name]_[style_name]_variant_[style_variant.id]
 */


/**
 * Style bundle - for bem blocks with @fx:styled
 */
class StyleBundle extends Bundle {
    
    protected $type = 'style';
    protected $extension = 'css';
    
    public function __construct($keyword, $params = array()) {
        
        $this->meta = array_merge($this->meta, self::parseKeyword($keyword));
        
        if (isset($params['visual_path'])) {
            $this->meta['visual_path'] = $params['visual_path'];
            unset($params['visual_path']);
        }
        
        parent::__construct($keyword, $params);
        
        if ($this->is_new) {
            $this->init();
        }
    }
    
    
    public function getDirPath() {
        $res = $this->getCacheDir();
        switch ($this->meta['type']) {
            case 'default': default:
                $res .=  '/'.$this->getHash()
                        .'/'.$this->meta['block_name']
                        .'/'.$this->meta['style_name'];
                break;
            case 'inline':
                $res .= '/inline/'.$this->meta['visual_id'].'/'.$this->meta['visual_path_hash'];
                break;
            case 'variant':
                $res .= '/variant/'.$this->meta['style_variant_id'].'/'.$this->getHash();
                break;
            case 'tv':
                $res .= '/tv/'.$this->meta['template_variant_id'].'/'.$this->meta['visual_path_hash'];
                break;
        }
        return $res;
    }
    
    protected $meta_updated = false;
    
    public static function getDefaultValue($var)
    {
        switch ($var['type']) {
            case 'css-background':
                $v = $var['default'];
                $res = array_shift($v);
                foreach ($v as $l) {
                    $parts = explode(", ", $l);
                    if (count($parts) === 2) {
                        $l = $l.', ~"0% 0% / 100% 100%" no-repeat scroll';
                    }
                    $res .= ', '.$l;
                }
                return $res;
            default:
                $res = $var['default'];
                if (is_array($res)) {
                    $res = join(", ",$res);
                }
                return $res;
        }
    }
    
    public function getStyleMeta($force = false)
    {
        $declaration_file = $this->meta['declaration_file'];
        if (
            $force === true || 
            (
                !$this->meta_updated && 
                (!$this->isFresh($declaration_file) || !isset($this->meta['style'])) 
            )
        ) {
            if (!file_exists($declaration_file)) {
                fx::log('no file', $declaration_file, $this);
                return null;
            }
            $fh = fopen($declaration_file, 'r');
            $is_in_comment = false;
            $comment = '';
            $this->meta['style'] = null;
            while (! feof($fh) ) {
                $s = fgets($fh);
                if (!$is_in_comment) {
                    if (trim($s) === '/*') {
                        $is_in_comment = true;
                    }
                    continue;
                }
                if (trim($s) === '*/') {
                    break;
                }
                $comment .= $s;
            }
            fclose($fh);
            $res = array(
                'vars' => array(),
                'name' => null
            );
            if (!empty($comment)) {
                try {
                    $res = Yaml\Yaml::parse($comment);
                    if (is_array($res)) {
                        if (!isset($res['vars'])) {
                            $res['vars'] = array();
                        }
                        if (!isset($res['name'])) {
                            $res['name'] = null;
                        }
                    }
                } catch (\Exception $e) {
                    fx::log('cat', $e, $comment);
                }
            }
            
            $res['vars'] = $this->extractDefaults($res['vars']);
            $this->meta['style'] = $res;
            $this->meta_updated = true;
            
        }
        return $this->meta['style'];
    }
    
    public function init()
    {
        $block = $this->meta['block_name'];
        $block_info = self::parseBlockName($block);
        
        $declaration_file = $block_info['path'].'/'
                           .$block_info['block_base']
                           .'_style_'.$this->meta['style_name'].'.less';
        
        $this->meta['declaration_file'] = $declaration_file;
        $this->meta = array_merge($this->meta, $block_info);
        $this->push(array($this->meta['declaration_file']));
    }
    
    public static function parseBlockName($block)
    {
        $block_parts = explode("--", $block);
        
        if ($block_parts[0] === 'theme') {
            array_shift($block_parts);
            $base = '@theme';
        } else {
            $base = '@module';
        }
        
        $block_base = array_pop($block_parts);
        $namespace = join("--", $block_parts);
        
        foreach ($block_parts as $i => &$part) {
            $part = str_replace("-", '_', $part);
            $part = \Floxim\Floxim\System\Util::underscoreToCamel($part);
        }
        
        $path = fx::path($base.'/'.join('/', $block_parts));
        
        $res = array();
        
        $res['namespace'] = $namespace;
        $res['block_base'] = $block_base;
        $res['path'] = $path;
        return $res;
    }
    
    public static function parseKeyword($keyword)
    {
        $kw_parts = explode("_", $keyword);
        
        $res = array();
        
        $res['block_name'] = $kw_parts[0];
        $res['style_name'] = $kw_parts[1];
        
        if (isset($kw_parts[2])) {
            $type = $kw_parts[2];
            switch ($type) {
                case 'inline':
                    $visual_id = $kw_parts[3];
                    if (preg_match('~-temp$~', $visual_id)) {
                        $visual_id = substr($visual_id, 0, -5);
                        $res['is_temp'] = true;
                    }
                    $res['visual_id'] = $visual_id;
                    $res['visual_path_hash'] = $kw_parts[4];
                    break;
                case 'variant':
                    $res['style_variant_id'] = $kw_parts[3];
                    break;
                case 'tv':
                    $res['template_variant_id'] = $kw_parts[3];
                    $res['visual_path_hash'] = $kw_parts[4];
                    break;
            }
        } else {
            $type = 'default';
        }
        $res['type'] = $type;
        return $res;
    }
    
    public static function deleteForVisual($visual_id)
    {
        $dir = fx::assets('css')->getCacheDir().'/inline/'.$visual_id;
        if (!file_exists($dir)) {
            return false;
        }
        fx::files()->rm($dir);
        return true;
    }
    
    public static function deleteForTemplateVariant($variant_id)
    {
        $dir = fx::assets('css')->getCacheDir().'/tv/'.$variant_id;
        if (!file_exists($dir)) {
            return false;
        }
        fx::files()->rm($dir);
        return true;
    }
    
    public function extractDefaults($vars)
    {
        $meta_parser = new MixinDefaultsParser($this->getMixinName(), $vars);
        $parser = $this->startParser(
            array(
                'plugins' => array(
                    $meta_parser
                )
            )
        );
        try {
            $parser->getCss();
        } catch (Exception $ex) {
            
        }
        return $vars;
    }
    
    public function getDeclarationKeyword()
    {
        return $this->meta['block_name'].'_'.$this->meta['style_name'];
    }
    
    public function getDeclarationOutput()
    {
        $declaration_file = $this->meta['declaration_file'];
        if (!file_exists($declaration_file)) {
            return;
        }
        $declaration = file_get_contents($declaration_file);
        $declaration = self::minifyLess($declaration);
        
        return array(
            'keyword' => $this->getDeclarationKeyword(),
            'meta' => $this->getStyleMeta(),
            'less' => $declaration
        );
    }
    
    protected function isOverriden()
    {
        $types = [
            'inline' => ['infoblock_visual', 'visual_id'],
            'tv' => ['template_variant', 'template_variant_id']
        ];
        $type = $this->meta['type'];
        
        if(!isset($types[$type])) {
            return false;
        }
        $overriden = \Floxim\Floxim\System\Page::getOverridenVisual();
        if ($overriden === true) {
            return true;
        }
        if (!$overriden) {
            return false;
        }
        $props = $types[$type];
        foreach ($overriden as $c_overriden) {
            if ($c_overriden[0] === $props[0] && $this->meta[$props[1]] == $c_overriden[1]) {
                return true;
            }
        }
        return false;
    }
    
    public function getAdminOutput()
    {
        $declaration_keyword = $this->getDeclarationKeyword();
        $res = array(
            'keyword' => $this->keyword,
            'style_class' => $this->getStyleClass(),
            'declaration_keyword' => $declaration_keyword,
            'version' => $this->version,
        );
        if ( $this->isOverriden() ) {
            $res['css'] = $this->getBundleContent();
            $res['file'] = 'temp';
            $res['filemtime'] = date('d, H:i:s');
        } else {
            $is_fresh = $this->isFresh();
            if (!$is_fresh) {
                $this->save();
            }
            $css_file = $this->getFilePath();
            if (!file_exists($css_file)) {
                fx::log('no file in GAO', $this, $css_file, debug_backtrace());
                throw new \Exception('style bundle error');
            }
            $res['css'] = file_get_contents($css_file);
            //$res['file'] = fx::path()->http($css_file);
            $res['filemtime'] = date('d, H:i:s', filemtime($css_file));
        }
        return $res;
    }
    
    public function getBundleContent() 
    {
        $res = '';
        
        $meta = $this->getStyleMeta();
        
        if (!$meta) {
            return $res;
        }
        $parser = $this->startParser(
            array(
                'plugins' => array(
                    new Bem\Processor()
                )
            )
        );

        $less_vars = $this->getLayoutVars();
        $less_call = $this->generateCallLess();
        
        try {
            ob_start();
            $parser->parse( $less_call );
            $parser->ModifyVars($less_vars);    
            $res = $parser->getCss();
            $this->generateExportFile();
            $errors = ob_get_clean();
            if ($errors !== '') {
                fx::log('hm ers', $errors, fx::debug()->backtrace(), $less_vars, $less_call);
            }
        } catch (\Less_Exception_Compiler $e) {
            $warnings = ob_get_clean();
            fx::log($e, $less_vars, $less_call, $warnings);
        } catch (\Less_Exception_Parser $e) {
            $warnings = ob_get_clean();
            fx::log('parser ex', $e, $e->getTrace(), $warnings, fx::debug()->backtrace(), $less_vars, $less_call);
        }
        $res = self::minifyLess($res);
        return $res;
    }
    
    public function getMixinName()
    {
        return '.'.$this->meta['block_base'].'_style_'.$this->meta['style_name'];
    }
    
    public function getVariantVars()
    {
        $vars = null;
        if (isset($this->meta['visual_path'])) {
            $id_parts = explode("-", $this->meta['visual_path']);
            
            $props = null;
            
            if (isset($this->meta['visual_id'])) {
                $visual_id = $this->meta['visual_id'];

                if ($visual_id === 'new') {
                    $visual = fx::env('new_infoblock_visual');
                } else {
                    $visual = fx::data('infoblock_visual', (int) $visual_id);
                }

                if ($visual) {   
                    $prop_set = array_shift($id_parts) === 'w' ? 'wrapper_visual' : 'template_visual';
                    $props = $visual[$prop_set];
                }
            } elseif (isset($this->meta['template_variant_id'])) {
                $template_variant = fx::data('template_variant', $this->meta['template_variant_id']);
                $props = $template_variant['params'];
            }
            if ($props) {
                $path = join(".", $id_parts).'_style';
                $vars = fx::dig($props, $path);
            }
        } else {
            $style_variant = $this->getStyleVariant();
            if ($style_variant && is_array($style_variant['less_vars'])) {
                $vars = $style_variant['less_vars'];
            }
        }
        
        if (!$vars) {
            return;
        }
        $style_meta = $this->getStyleMeta();
        $res = array();
        foreach ($style_meta['vars'] as $var_key => $var_props) {
            if (!isset($vars[$var_key])) {
                continue;
            }
            $val = $vars[$var_key];
            if (isset($var_props['units'])) {
                $u = $var_props['units'];
                if (substr($val, strlen($u)*-1) !== $u) {
                    $val = preg_replace("~[^\d\.\,]~", '', $val).$u;
                }
            }
            if (empty($val)) {
                $val = 'none';
            }
            $res[$var_key] = $val;
        }
        return $res;
    }
    
    protected function getStyleMod()
    {
        switch ($this->meta['type']) {
            case 'default': default:
                return $this->meta['style_name'];
            case 'variant':
                return $this->meta['style_name'].'--'.$this->meta['style_variant_id'];
            case 'inline':
            case 'tv':
                return $this->meta['visual_path_hash'];
        }
    }
    
    public function getStyleClass()
    {
        return $this->meta['block_name'].'_style_'.$this->getStyleMod();
    }
    
    public function generateCallLess()
    {
        $res = '.'.$this->getStyleClass();
        
        $res .= "{\n";
        $res .= $this->getMixinName()."(";
        
        $variant_vars = $this->getVariantVars();
        $style_meta = $this->getStyleMeta();
        
        if ($style_meta && isset($style_meta['vars']) && is_array($style_meta['vars'])) {
            foreach ($style_meta['vars'] as $var_key => $var_meta) {
                $var_value = isset($variant_vars[$var_key]) ? $variant_vars[$var_key] : $var_meta['value'];
                if (empty($var_value)) {
                    $var_value = 'none';
                }
                $res .= "\n    @".$var_key.":".$var_value.";";
            }
        }
        $res .= "\n);\n";
        $res .= "}\n";
        return $res;
    }
    
    
    protected function getTweakerLessPath()
    {
        return $this->getFilePath().'.tweak';
    }
    
    public function delete() {
        parent::delete();
        $tweaker_file = $this->getTweakerLessPath();
        if (file_exists($tweaker_file)) {
            unlink($tweaker_file);
        }
    }

    public function getTweakerLessFile()
    {
        $file_path = $this->getTweakerLessPath();
        if (!$this->isFresh()) {
            $this->save();
        }
        if (!file_exists($file_path)) {
            $tweak = $this->getTweakerLess();
            fx::files()->writefile($file_path, $tweak);
        }
        return fx::path()->http($file_path);
    }
    
    public function getTweakerLess()
    {
        $res = '';
        
        foreach ($this->getUniqueFiles() as $f) {
            $res .= file_get_contents($f)."\n";
        }
        
        foreach ($this->getLayoutVars()  as $var => $val) {
            $res .= '@'.$var.':'.$val.";\n";
        }
        $res = self::minifyLess($res);
        return $res;
    }
    
    public static function collectStyleVariants($block) 
    {
        $res = array();
        $parts = self::parseBlockName($block);
        $variant_files = glob($parts['path'].'/'.$parts['block_base'].'_style_*');
        if (!$variant_files) {
            return $res;
        }
        
        $variants = fx::data('style_variant')
            ->where('block', $block)
            ->whereOr(
                array('theme_id', fx::env('theme_id')),
                array('theme_id', null, 'is null')
            )
            ->whereOr(
                ['is_default', 0],
                ['is_default', null, 'is null']
            )
            ->all()
            ->group('style');
        
        foreach ($variant_files as $file) {
            $style_name = null;
            if (!preg_match("~([a-z0-9-]+)\.less$~", $file, $style_name)) {
                continue;
            }
            $style_name = $style_name[1];
            $bundle = fx::assets('style', $block.'_'.$style_name);
            $style_meta = $bundle->getStyleMeta();
            //fx::log($style_meta);
            $res[]= array(
                $style_name,
                //$style_meta['name'] ? $style_meta['name'] : $style_name,
                '- по умолчанию -',
                array(
                    'title' => $style_name,
                    'is_tweakable' => count($style_meta['vars']) > 0,
                    'style_variant_id' => null
                )
            );
            if (isset($variants[$style_name])) {
                foreach ($variants[$style_name] as $variant) {
                    $bundle = $variant->getBundle();
                    $bundle->save();
                    
                    $res[]= array(
                        $variant->getStyleKeyword(),
                        //' -- '.$variant['name'],
                        $variant['name'],
                        array(
                            'is_tweakable' => true,
                            'style_variant_id' => $variant['id'],
                            'screenshot' => $bundle->getScreenshot()
                        )
                    );
                }
            }
        }
        return $res;
    }
    
    
    public function getExportFilePath()
    {
        return $this->getDirPath().'/export.php';
    }
    
    public function getStyleVariant()
    {
        
        if (isset($this->meta['visual_path'])) {
            return null;
        }
        $id = isset($this->meta['style_variant_id']) ? $this->meta['style_variant_id'] : null;
        if (!$id) {
            $default = fx::data('style_variant')->getDefault($this->meta['block_name']);
            return $default;
        }
        return fx::data('style_variant', $id);
    }
    
    
    public function getExportFileCode() 
    {
        $meta = $this->getStyleMeta();
        
        $export = isset($meta['export']) ? $meta['export'] : array();
        $container = isset($meta['container']) ? $meta['container'] : array();
        
        if (count($export) === 0 && count($container) === 0) {
            return;
        }
        
        $variant_vars = $this->getVariantVars();
        if ($variant_vars) {
            $values = $variant_vars;
        } else {
            $values = array();
            foreach ($meta['vars'] as $var_name => $var) {
                if (isset($var['value'])) {
                    $values[$var_name] = $var['value'];
                }
            }
        }
        
        $code = \Floxim\Floxim\Template\Compiler::generateStyleExportCode(
            array(
                'export' => $export, 
                'container' => $container
            ),
            $values
        );
        return $code;
    }
    
    public function generateExportFile()
    {
        $code = $this->getExportFileCode();
        if (!$code) {
            return;
        }
        $path = $this->getExportFilePath();
        fx::files()->writefile($path, $code);
        $this->meta['export_file'] = $path;
    }
    
    public function getExportFile()
    {
        $this->save();
        return isset($this->meta['export_file']) ? $this->meta['export_file'] : null;
    }
    
    public function getTempExportFile()
    {
        if (isset($this->temp_export_file)) {
            return $this->temp_export_file;
        }
        $code = $this->getExportFileCode();
        if ($code) {
            $path = $this->getDirPath().'/export.temp.php';
            fx::files()->writefile($path, $code);
            $this->temp_export_file = $path;
        } else {
            $this->temp_export_file = false;
        }
        return $this->temp_export_file;
    }
    
    protected function prepareStyleField($props)
    {
        
        if (isset($props['units'])) {
            foreach (array('value', 'default') as $prop) {
                if (isset($props[$prop])) {
                    $props[$prop] = preg_replace("~[^\d\.]+~", '', $props[$prop]);
                }
            }
        }
        return $props;
    }
    
    public function getGroupField($vals)
    {
        $style = $this->getStyleMeta();
        $fields = $this->getFormFields($vals);
        return array(
            'type' => 'group',
            'style' => 'transparent',
            'fields' =>  $fields,
            'tabs' => isset($style['tabs']) ? $style['tabs'] : [],
            'tweaker' => array(
                'tweaker_file' => $this->getTweakerLessFile(),
                'rootpath' => $this->getRootPath(),
                'vars' => array_keys($style['vars']),
                'mixin_name' => substr($this->getMixinName(), 1),
                'container' => isset($style['container']) ? $style['container'] : array()
            )
        );
    }
    
    public function getFormFields($vals = array())
    {
        
        $style = $this->getStyleMeta();
        
        if (!isset($style['vars'])) {
            $style['vars'] = array();
        }
        
        $fields = array();
        
        foreach ($style['vars'] as $var => $props) {
            $props['name'] = $var;
            if (isset($props['value'])) {
                $props['default'] = $props['value'];
            }
            if (isset($vals[$var])) {
                $props['value'] = $vals[$var];
            }
            $props = $this->prepareStyleField($props);
            $fields []= $props;
        }
        return $fields;
    }
    
    public function getRootPath()
    {
        return fx::path()->http($this->meta['path']);
    }
    
    public function getScreenPath()
    {
        return $this->getDirPath().'/screenshot.png';
    }
    
    public function getScreenshot()
    {
        $f = $this->getScreenPath();
        if (file_exists($f)) {
            return fx::path()->http($f)."?v=".$this->version;
        }
        return null;
    }
}