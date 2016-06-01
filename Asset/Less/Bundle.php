<?php

namespace Floxim\Floxim\Asset\Less;

use Floxim\Floxim\System\Fx as fx;

class Bundle extends \Floxim\Floxim\Asset\Bundle {
    protected $type = 'css';

    public function __construct($keyword, $params = array())
    {
        parent::__construct($keyword, $params);
        /*
         * @todo: replace hardcoded path to vars.less with counted value
         */
        if ($keyword === 'default') {
            $this->push($this->getCommonLessFiles());
        }
    }

    protected function getCommonLessFiles()
    {
        return array(
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
            $files = array_unique($this->files);
        }
        
        foreach ($files as $f) {
            $dir = fx::path()->http(dirname($f));
            if (file_exists($f)) {
                $parser->parseFile($f, $dir);
            }
        }
        return $parser;
    }
    
    public function getBundleContent() {
        $is_admin = $this->keyword === 'admin';
        
        $options = array( );
        
        if (!$is_admin) {
            $meta_parser = new MetaParser();
            $options['plugins'] = array(
                $meta_parser,
                new Bem\Processor()
            );
        }
        
        $parser = $this->startParser($options);
        
        try {
            if (!$is_admin) {
                $less_vars = $this->getLayoutVars();
                $parser->ModifyVars($less_vars);
            }
            $css = $parser->getCss();
            if (!$is_admin) {
                $this->meta['vars'] = $meta_parser->getVars();
                $this->meta['styles'] = $meta_parser->getStyles();
            }
            return $css;
        } catch (\Less_Exception_Compiler $e) {
            fx::log($e, $parser);
        }
    }
    
    protected function getLayoutVars()
    {
        return fx::env()->getLayoutStyleVariant()->getLessVars();
    }

    public function getStyle($block, $style) {
        if (!isset($this->meta['styles']) || !is_array($this->meta['styles'])) {
            return;
        }
        foreach ($this->meta['styles'] as $c_style) {
            if ($c_style['keyword'] === $block.'_style_'.$style) {
                return $c_style;
            }
        }
    }

    public function getStyleTweakerLess($style)
    {
        $block = $style[0];
        $style_name = $style[1];
        $style_meta = $this->getStyle($block, $style_name);

        $vars = $style_meta['vars'];

        $options = array(
            'strictMath' => true,
            'files' => array(
                $style_meta['file']
            ),
            'plugins' => array(
                new Tweaker\Style\Generator($vars, $style),
                new Tweaker\PostProcessor(),
                new Bem\Processor()
            )
        );

        $parser = $this->startParser($options);
        try {

            $res = $parser->getCss();
            foreach ($this->getLayoutVars()  as $var => $val) {
                $res .= '@'.$var.':'.$val.";\n";
            }
            $commons = '';
            foreach ($this->getCommonLessFiles() as $f) {
                $commons .= file_get_contents($f)."\n";
            }
            $commons = preg_replace("~/\*.*?\*/~s", '', $commons);
            $res = $commons . $res;
            return $res;
        } catch (\Exception $ex) {
            fx::cdebug($ex);
        }

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

    protected function getStyleTweakerLessPath($style)
    {
        if (is_array($style)) {
            $style = $style[0].'_style_'.$style[1];
        }
        return $this->getFilePath().'-'.$style.'.tweak';
    }

    public function getStyleTweakerLessFile($style)
    {
        $file_path = $this->getStyleTweakerLessPath($style);
        if (!file_exists($file_path)) {
            $tweak = $this->getStyleTweakerLess($style);
            file_put_contents($file_path, $tweak);
        }
        return fx::path()->http($file_path);
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