<?php
namespace Floxim\Floxim\Component\StyleVariant;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\System\Entity
{
    public function getUsedFonts()
    {
        $params = $this->getLessVars();
        $res = array();
        foreach ($params as $p => $v) {
            if (preg_match('~^font~', $p)) {
                $res []= $v;
            }
        }
        return $res;
    }
    
    public function getLessVars()
    {
        $res = $this['less_vars'];
        if (!is_array($res)) {
            return array();
        }
        return $res;
    }
    
    protected $colors = null;
    
    public function getColor($code) 
    {
        $code = preg_replace("~^\@~",'', $code);
        $colors = $this->getColors();
        return isset($colors[$code]) ? $colors[$code] : null;
    }
    
    public function getColors()
    {
        if (!is_null($this->colors)) {
            return $this->colors;
        }
        $colors = array();
        $vars = $this->getLessVars();
        foreach ($vars as $var => $val) {
                $parts = null;
            preg_match("~^color-([a-z-]+)-(\d+)~", $var, $parts);
            if (!$parts) {
                continue;
            }
            $colors[$var] = $val;
        }
        $this->colors = $colors;
        return $colors;
    }
    
    public function getPalette()
    {
        $res = array();
        $colors = $this->getColors();

        foreach ($colors as $code => $val) {
            $parts = null;
            preg_match("~^color-([a-z-]+)-(\d+)~", $code, $parts);
            if (!$parts) {
                continue;
            }
            $name = $parts[1];
            if (!isset($res[$name])) {
                $res[$name] = array();
            }
            $res[$name]['@'.$code] = $val;
        }
        return $res;
    }
}