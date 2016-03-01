<?php

namespace Floxim\Floxim\Asset;

use Floxim\Floxim\System\Fx as fx;

class LessBundle extends Bundle {
    protected $type = 'css';
    
    protected function getBundleContent() {
        $map_path = fx::path($this->getFilePath()).'.map';
        $options = array( 
            'sourceMap' => true,
            'sourceMapWriteTo'  => $map_path, 
            'sourceMapURL'      => fx::path()->http($map_path)
        );
        $parser = new \Less_Parser($options);
        $files = array_unique($this->files);
        foreach ($files as $f) {
            $dir = fx::path()->http(dirname($f));
            $parser->parseFile($f, $dir);
        }
        try {
            return $parser->getCss();
        } catch (\Less_Exception_Compiler $e) {
            fx::log($e, $parser);
        }
    }
    
    public function delete()
    {
        parent::delete();
        $map_path = $this->getFilePath().'.map';
        unlink($map_path);
    }
}