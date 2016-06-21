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
        $defaults = array(
            'max_width' => '1200px',
            'layout_width' => '80%'
        );
        if (!is_array($res)) {
            $res = array();
        }
        $res = array_merge($defaults, $res);
        return $res;
    }

    public function getLessVar($var_name)
    {
        $vars = $this->getLessVars();
        return isset($vars[$var_name]) ? $vars[$var_name] : null;
    }
    
    protected $colors = null;
    
    public function getColor($code) 
    {
        $parts = explode(" ", $code);
        $color_key = 'color-'.$parts[0].'-'. (isset($parts[1]) ? $parts[1] : '0');

        //$code = 'color-'.preg_replace("~\s~",'-', $code);
        $colors = $this->getColors();
        if (!isset($colors[$color_key])) {
            return null;
        }

        $hex = $colors[$color_key];

        if (!isset($parts[2]) || $parts[2] === 1) {
            return $hex;
        }

        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        $rgb = 'rgba('.$r.', '.$g.', '.$b.', '.$parts[2].')';
        return $rgb;
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
            $level = $parts[2];
            if (!isset($res[$name])) {
                $res[$name] = array();
            }
            $res[$name][$name .' '.$level] = $val;
        }
        return $res;
    }

    public function getStyleKeyword()
    {
        $parts = explode('_', $this['style']);
        return $parts[1].'--'.$this['id'];
    }

    public function getStyleLess()
    {
        $parts = explode('_', $this['style']);
        $block = $parts[0];
        $style = $parts[1];
        $res = '.'.$block.'_style_'.$this->getStyleKeyword()." {\n";
        $t = '    ';
        $res .= $t.".".$block.'_style_'.$style."(\n";
        foreach ($this['less_vars'] as $var_name => $var_value) {
            $res .= $t.$t."@".$var_name.':'.$var_value.";\n";
        }
        $res .= $t.");\n";
        $res .= "}";
        return $res;
    }

    public function afterSave()
    {
        parent::afterSave();
        $this->getStyleLessFile(true);
    }

    public function getStyleLessFilePath()
    {
        return fx::path( '@files/asset_cache/'.$this['style'].'-'.$this['id'].'.less');
    }

    public function getStyleLessFile($force_update = false)
    {
        $path = $this->getStyleLessFilePath();
        if (!file_exists($path) || $force_update) {
            $less = $this->getStyleLess();
            fx::files()->writefile($path, $less);
        }
        return $path;
    }
}