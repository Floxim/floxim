<?php
namespace Floxim\Floxim\Component\Theme;

use Floxim\Floxim\System\Fx as fx;

use Symfony\Component\Yaml\Yaml;

class Entity extends \Floxim\Floxim\System\Entity {
    
    public function getThemeConfig()
    {
        $path = $this->getThemePath().'/template.yaml';
        if (!file_exists($path)) {
            return array();
        }
        try {
            $res = Yaml::parse(file_get_contents($path));
            return $res;
        } catch (Exception $ex) {
            fx::log('theme config parser error', $ex);
            return array();
        }
    }
    
    public function getThemePath()
    {
        $parts = explode(".", $this['layout']);
        $res = '@theme/'. join(
            "/", 
            array_map(
                function($v) {
                    return fx::util()->underscoreToCamel($v);
                },
                $parts
            )
        );
        return fx::path($res);
    }
    
    public function getThemeFonts()
    {
        $config = $this->getThemeConfig();
        if ($config && isset($config['fonts'])) {
            $fonts = $config['fonts'];
            foreach ($fonts as &$font) {
                if (isset($font['css'])) {
                    $font['css'] = $this->getThemePath().'/'.$font['css'];
                }
            }
            return $fonts;
        }
        return array();
    }
}