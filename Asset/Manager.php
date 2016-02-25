<?php
namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

class Manager {
    protected $bundles = array(
        'js' => array(),
        'css' => array()
    );
    
    public function getBundle($type, $keyword)
    {
        if (!isset($this->bundles[$type][$keyword])) {
            $bundle_class = '\\Floxim\\Floxim\\Asset\\'. ($type === 'css' ? 'LessBundle' : 'JsBundle');
            $this->bundles[$type][$keyword] = new $bundle_class(
                $keyword,
                array(
                    'layout_id' => fx::env('layout_id'),
                    'site_id' => fx::env('site_id')
                )
            );
        }
        return $this->bundles[$type][$keyword];
    }
    
    public function addToBundle($files, $bundle_keyword) 
    {
        $files = (array) $files;
        $type = self::getTypeByFiles($files);
        $bundle = $this->getBundle($type, $bundle_keyword);
        $bundle->push($files);
    }
    
    protected static function getTypeByFiles($files)
    {
        foreach ($files as $f) {
            return preg_match("~\.(less|css)$~", $f) ? 'css' : 'js';
        }
    }
}