<?php

namespace Floxim\Floxim\Asset;

use Floxim\Floxim\System\Fx as fx;

class LessBundle extends Bundle {
    protected $type = 'css';
    
    public function getBundleContent() {
        $map_path = fx::path($this->getFilePath()).'.map';
        $meta_parser = new LessMetaParser();
        $options = array( 
            'sourceMap' => true,
            'sourceMapWriteTo'  => $map_path, 
            'sourceMapURL'      => fx::path()->http($map_path),
            'plugins' => array(
                $meta_parser,
                new BemLess()
            )
        );
        $parser = new \Less_Parser($options);
        $files = array_unique($this->files);
        
        foreach ($files as $f) {
            $dir = fx::path()->http(dirname($f));
            if (file_exists($f)) {
                $parser->parseFile($f, $dir);
            }
        }
        
        try {
            $less_vars = fx::env()->getLayoutStyleVariant()->getLessVars();
            $parser->ModifyVars($less_vars);
            $css = $parser->getCss();
            $this->meta['vars'] = $meta_parser->getVars();
            return $css;
        } catch (\Less_Exception_Compiler $e) {
            fx::log($e, $parser);
        }
    }
    
    public function delete()
    {
        parent::delete();
        $map_path = $this->getFilePath().'.map';
        if (file_exists($map_path)) {
            unlink($map_path);
        }
    }
}