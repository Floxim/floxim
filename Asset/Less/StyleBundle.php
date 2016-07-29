<?php

namespace Floxim\Floxim\Asset\Less;

use \Floxim\Floxim\System\Fx as fx;

use \Symfony\Component\Yaml;


/**
 * Style bundle - for bem blocks with @fx:styled
 */
class StyleBundle extends Bundle {
    
    protected $type = 'style';
    protected $extension = 'css';
    
    public function __construct($keyword, $params = array()) {
        $kw_parts = explode("_", $keyword);
        
        $this->meta['variant_id'] = isset($kw_parts[2]) ? $kw_parts[2] : null;
        
        parent::__construct($keyword, $params);
        
        if ($this->is_new) {
            $parts = self::parseKeyword($keyword);
            $this->meta = array_merge($this->meta, $parts);
            
            $files = array();
            $files []= $this->meta['declaration_file'];
            $this->push($files);
        }
    }
    
    public function getHash() 
    {
        if ($this->meta['variant_id']) {
            return '';
        }
        return parent::getHash();
    }
    
    public function getStyleMeta()
    {
        $declaration_file = $this->meta['declaration_file'];
        if (!$this->isFresh($declaration_file) || !isset($this->meta['style'])) {
            fx::cdebug('real read meta');
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
                    fx::cdebug('cat', $e, $comment);
                }
            }
            $this->meta['style'] = $res;
        }
        return $this->meta['style'];
    }
    
    public static function parseKeyword($keyword)
    {
        $parts = explode("_", $keyword);
        $path = $parts[0];
        $path = explode("--", $path);
        $block = array_pop($path);
        $style = $parts[1];
        $namespace = join("--", $path);
        
        if ($path[0] === 'theme') {
            array_shift($path);
            $base = '@theme';
        } else {
            $base = '@module';
        }
        
        foreach ($path as &$part) {
            $part = str_replace("-", '_', $part);
            $part = \Floxim\Floxim\System\Util::underscoreToCamel($part);
        }
        
        $path = fx::path($base.'/'.join('/', $path));
        
        $res = array(
            'path' => $path,
            'block_name' => $block,
            'style_name' => $style,
            'variant_id' => isset($parts[2]) ? $parts[2] : null,
            'namespace' => $namespace
        );
        $res ['declaration_file'] = $res['path'].'/'.$block.'_style_'.$style.'.less';
        return $res;
    }
    
    
    public function getBundleContent() 
    {
        
        $res = '';
        
        $meta = $this->getStyleMeta();
        
        if (!$meta) {
            return $res;
        }
        
        $vars = $meta['vars'];
        
        $meta_parser = new MixinDefaultsParser($this->getMixinName(), $vars);
        $parser = $this->startParser(
            array(
                'plugins' => array(
                    $meta_parser,
                    new Bem\Processor()
                ),
                'compress' => false
            )
        );

        try {
            $less_vars = $this->getLayoutVars();
            $parser->parse( $this->generateCallLess() );
            
            $parser->ModifyVars($less_vars);
            $res = $parser->getCss();
            
            $this->meta['style']['vars'] = $vars;
            fx::cdebug('set vars', $vars);
            $this->generateExportFile();
            
        } catch (\Less_Exception_Compiler $e) {
            fx::log($e, fx::debug()->backtrace(), $parser);
        }
        return $res;
    }
    
    public function getMixinName()
    {
        return '.'.$this->meta['block_name'].'_style_'.$this->meta['style_name'];
    }
    
    public function generateCallLess()
    {
        $block = $this->meta['block_name'];
        $style = $this->meta['style_name'];
        $variant_id = $this->meta['variant_id'];
        $res = '.'.$this->meta['namespace'].'--'.$block.'_style_'.$style;
        if ($variant_id) {
            $res .= '--'.$variant_id;
        }
        $res .= "{\n";
        $res .= $this->getMixinName()."(";
        if ($variant_id) {
            $style_variant = fx::data('style_variant', $variant_id);
            if ($style_variant && is_array($style_variant['less_vars'])) {
                $res .= "\n";
                foreach ($style_variant['less_vars'] as $var_name => $var_value) {
                    $res .= "    @".$var_name.':'.$var_value.";\n";
                }
            }
        }
        $res .= ");\n";
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
        if (!file_exists($file_path)) {
            $tweak = $this->getTweakerLess();
            file_put_contents($file_path, $tweak);
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
        $parts = self::parseKeyword($block.'_default');
        $variant_files = glob($parts['path'].'/'.$parts['block_name'].'_style_*');
        if (!$variant_files) {
            return $res;
        }
        
        $variants = fx::data('style_variant')
            ->where('block', $block)
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
            $res[]= array(
                $style_name,
                $style_meta['name'] ? $style_meta['name'] : $style_name,
                array(
                    'title' => $style_name,
                    'is_tweakable' => count($style_meta['vars']) > 0,
                    'style_variant_id' => null
                )
            );
            if (isset($variants[$style_name])) {
                foreach ($variants[$style_name] as $variant) {
                    $res[]= array(
                        $variant->getStyleKeyword(),
                        ' -- '.$variant['name'],
                        array(
                            'is_tweakable' => true,
                            'style_variant_id' => $variant['id']
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
        if (!isset($this->meta['variant_id'])) {
            return null;
        }
        $id = $this->meta['variant_id'];
        if (!$id) {
            return null;
        }
        return fx::data('style_variant', $id);
    }
    
    public function generateExportFile()
    {
        $meta = $this->getStyleMeta();
        
        if (!isset($meta['export'])) {
            return;
        }
        
        $export = $meta['export'];
        
        if (!is_array($export) || count($export) === 0) {
            return;
        }
        
        $variant = $this->getStyleVariant();
        if ($variant) {
            $values = $variant['less_vars'];
        } else {
            $values = array();
            foreach ($meta['vars'] as $var_name => $var) {
                if (isset($var['value'])) {
                    $values[$var_name] = $var['value'];
                }
            }
        }
        
        $path = $this->getExportFilePath();
        $code = \Floxim\Floxim\Template\Compiler::generateStyleExportCode($export, $values);
        fx::files()->writefile($path, $code);
        fx::cdebug($code, $export, $values);
        $this->meta['export_file'] = $path;
    }
    
    public function getExportFile()
    {
        return isset($this->meta['export_file']) ? $this->meta['export_file'] : null;
    }
}