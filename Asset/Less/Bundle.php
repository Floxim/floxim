<?php

namespace Floxim\Floxim\Asset\Less;

use Floxim\Floxim\System\Fx as fx;

class Bundle extends \Floxim\Floxim\Asset\Bundle {
    
    protected $type = 'css';
    protected $extension = 'css';

    public function __construct($keyword, $params = array())
    {
        
        if (isset($params['namespace'])) {
            $this->meta['namespace'] = $params['namespace'];
            unset($params['namespace']);
        }
        
        parent::__construct($keyword, $params);
        /*
         * @todo: replace hardcoded path to vars.less with counted value
         */
        $this->push($this->getCommonLessFiles());
    }

    public function getCommonLessFiles()
    {
        return $this->keyword === 'admin' ? array() : array(
            fx::path('/theme/Floxim/Basic/vars.less')
        );
    }
    
    public function startParser($options)
    {
        $options = array_merge(
            array(
                'cache_dir' => fx::path('@files/less_cache')
            ),
            $options
        );
        if (isset($options['sourceMap']) && $options['sourceMap']) {
            $map_path = $this->getSourceMapPath();
            $options = array_merge(
                array(
                    'sourceMapWriteTo'  => $map_path, 
                    'sourceMapURL'      => fx::path()->http($map_path),
                ),
                $options
            );
        }
        
        $parser = new \Less_Parser($options);
        if (isset($options['files'])) {
            $files = $options['files'];
        } else {
            $files = array();
            foreach ($this->files as $file) {
                if (!is_string($file)) {
                    continue;
                }
                $files[] = fx::path($file);
            }
            $files = array_unique($files);
        }
        
        foreach ($files as $f) {
            if (is_array($f) && isset($f['source'])) {
                $parser->parse($f['source']);
            } else {
                $dir = fx::path()->http(dirname($f));
                if (file_exists($f)) {
                    $parser->parseFile($f, $dir);
                }
            }
        }
        return $parser;
    }
    
    public function getBundleContent() {
        $is_admin = $this->keyword === 'admin';
        $is_default = $this->keyword === 'default';
        
        $options = array( );
        
        if (!$is_admin) {
            $meta_parser = new MetaParser();
            $options['plugins'] = array(
                $meta_parser,
                new Bem\Processor()
            );
        }
        
        $parser = $this->startParser($options);
        
        $res = '';

        try {
            if (!$is_admin) {
                $less_vars = $this->getLayoutVars();
                $parser->ModifyVars($less_vars);
            }
            $css = $parser->getCss();
            // collect common vars (colors, fonts etc.) for "theme settings" dialog
            if ($is_default) {
                $this->meta['vars'] = $meta_parser->getVars();
            } 
            // collect meta for "style settings" dialog
            elseif (!$is_admin) {
                $this->meta['styles'] = $meta_parser->getStyles();
                //$meta_parser->generateExportFiles();
            }
            $res = $css;
        } catch (\Less_Exception_Compiler $e) {
            fx::log($e, fx::debug()->backtrace(), $parser);
        }
        foreach ($this->files as $f) {
            $sub_bundle = $this->getSubBundle($f);
            if ($sub_bundle instanceof Bundle) {
                if (!$sub_bundle->isFresh()) {
                    $sub_bundle->save();
                }
                $res .= file_get_contents($sub_bundle->getFilePath());
            }
        }
        return $res;
    }
    
    protected function getLayoutVars()
    {
        $vars = fx::env()->getLayoutStyleVariant()->getLessVars();
        foreach ($vars as $k => $v) {
            if (preg_match("~^font_~", $k)) {
                $vars[$k] = '"'.trim($v, '"').'"';
            }
        }
        if (isset($this->meta['namespace'])) {
            $vars['namespace'] = $this->meta['namespace'];
        }
        return $vars;
    }

    protected static function minifyLess($less)
    {
        $res = preg_replace("~/\*.+?\*/~is", '', $less);
        $res = preg_replace("~^//[^\r\n]+~m", '', $res);
        //$res = preg_replace("~\s+~", ' ', $res);
        return $res;
    }

    
    public function getTweakerLess()
    {
        $vars = array_keys($this->getLayoutVars());

        $options = array(
            'strictMath' => true,
            'plugins' => array(
                new Tweaker\Generator($vars),
                new Tweaker\PostProcessor(),
                new Bem\Processor()
            )
        );

        $parser = $this->startParser($options);
        try {
            $res = $parser->getCss();
            return $res;
        } catch (\Exception $ex) {
            fx::log($ex);
        }
    }
    
    protected function getSourceMapPath()
    {
        return $this->getFilePath().'.map';
    }
    
    protected function getTweakLessPath()
    {
        return $this->getFilePath().'.tweak';
    }

    
    public function getTweakerLessFile()
    {
        $file_path = $this->getTweakLessPath();
        if (!file_exists($file_path)) {
            $tweak = $this->getTweakerLess();
            file_put_contents($file_path, $tweak);
        }
        return fx::path()->http($file_path);
    }
    
    public function delete()
    {
        parent::delete();
        $files = array(
            $this->getSourceMapPath(),
            $this->getTweakLessPath()
        );
        if (isset($this->meta['styles']) && is_array($this->meta['styles'])) {
            foreach ($this->meta['styles'] as $style) {
                $files []= $this->getStyleTweakerLessPath($style['keyword']);
            }
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}