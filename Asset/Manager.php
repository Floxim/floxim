<?php
namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

class Manager {
    protected $bundles = array(
        'js' => array(),
        'css' => array()
    );
    
    public function getBundle($type, $keyword, $params = array())
    {
        if (!isset($this->bundles[$type][$keyword])) {
            $types = array(
                'js' => 'JsBundle',
                'css' => 'Less\\Bundle',
                'style' => 'Less\\StyleBundle'
            );
            $bundle_class = '\\Floxim\\Floxim\\Asset\\'.$types[$type];

            $hash_params = $keyword === 'admin' ? array() : array(
                'site' => fx::env('site_id'),
                'theme' => fx::env('theme_id')
                //'style' => fx::env()->getLayoutStyleVariantId()
            );
            if (!is_array($params)) {
                fx::log('hm ', $params, debug_backtrace());
                $params = [];
            }
            $params = array_merge($hash_params, $params);

            $this->bundles[$type][$keyword] = new $bundle_class(
                $keyword,
                $params
            );
        }
        return $this->bundles[$type][$keyword];
    }
    
    public function addToBundle($files, $bundle_keyword, $params = array()) 
    {
        $files = (array) $files;
        $type = self::getTypeByFiles($files);
        $bundle = $this->getBundle($type, $bundle_keyword, $params);
        $bundle->push($files);
    }
    
    protected static function getTypeByFiles($files)
    {
        foreach ($files as $f) {
            return preg_match("~\.(less|css)$~", $f) ? 'css' : 'js';
        }
    }
}