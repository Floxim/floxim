<?php
namespace Floxim\Floxim\Component\Palette;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\System\Entity {
    
    public static function paramsFromStyleVariant($params)
    {
        $res = array(
            'fonts' => array(),
            'colors' => array(),
            'vars' => array()
        );
        foreach ($params as $p => $v) {
            if (preg_match("~^font_(.+)~", $p, $font)) {
                    $res['fonts'][$font[1]] = $v;
                continue;
            }
            if (preg_match("~^color-([^-]+)-(.+)~", $p, $color)) {
                $color_key = $color[1];
                $color_prop = $color[2];
                if (!isset($res['colors'][$color_key])) {
                    $res['colors'][$color_key] = array();
                }
                if (preg_match('~^[0-9]+$~', $color_prop)) {
                    if (!isset($res['colors'][$color_key]['vals'])) {
                        $res['colors'][$color_key]['vals'] = array();
                    }
                    $res['colors'][$color_key]['vals'][$color_prop] = $v;
                } else {
                    $res['colors'][$color_key][$color_prop] = $v;
                }
                continue;
            }
            if (in_array($p, array('layout_width', 'max_width'))) {
                $res['vars'][$p] = $v;
                continue;
            }
        }
        return $res;
    }
    
    public function _getParams()
    {
        $params = $this->getReal('params');
        if (!is_array($params)) {
            return $this->getDefaults();
        }
        return $params;
    }
    
    public function getVals() 
    {
        $res = array();
        foreach ($this['params'] as $group => $vals) {
            if ($group === 'vars') {
                $res = array_merge($res, $vals);
                continue;
            }
            if ($group === 'fonts') {
                foreach ($vals as $font_type => $font_family) {
                    $res['font_'.$font_type] = '"'.$font_family.'"';
                }
                continue;
            }
            if ($group === 'colors') {
                foreach ( $vals as $color_type => $color_props) {
                    foreach ($color_props['vals'] as $color_index => $color_rgb) {
                        $res['color-'.$color_type.'-'.$color_index] = $color_rgb;
                    }
                }
            }
        }
        return $res;
    }
    
    public function getUsedFonts()
    {
        return array_values($this['params']['fonts']);
    }
    
    public function getLessVars()
    {
        $res = '';
        foreach ($this->getVals() as $k => $v) {
            $res .= '@'.$k.': '.$v.";\n";
        }
        return $res;
    }
    
    public function getForm()
    {
        $tabs = array(
            'colors' => 'Цвета',
            'fonts' => 'Шрифты',
            'sizes' => 'Размеры и отступы'
        );
        $fields = array();
        
        $defaults = self::getDefaults();
        
        $vals = $this['params'];
        
        $font_types = self::getFontTypes();
        $color_types = self::getColorTypes();
        
        foreach ($defaults['colors'] as $color_key => $color_data) {
            $color_field = array(
                'type' => 'colorset',
                'tab' => 'colors',
                'name' => 'color-'.$color_key,
                'label' => isset($color_types[$color_key]) ? $color_types[$color_key] : $color_key
            );
            if ($color_key === 'main') {
                $color_field['neutral'] = true;
            }
            if (isset($vals['colors']) && isset($vals['colors'][$color_key])) {
                $color_data = $vals['colors'][$color_key];
            }
            $color_field['value'] = $color_data;
            $fields []= $color_field;
        }
        
        foreach ($defaults['fonts'] as $font_key => $font_family) {
            if (isset($vals['fonts']) && isset($vals['fonts'][$font_key])) {
                $font_family = $vals['fonts'][$font_key];
            }
            $font_field = array(
                'type' => 'livesearch',
                'fontpicker' => $font_key,
                'value' => $font_family,
                'name' => 'font_'.$font_key,
                'tab' => 'fonts',
                'label' => isset($font_types[$font_key]) ? $font_types[$font_key] : $font_key
            );
            $fields []= $font_field;
        }
        
        $fields []= array(
            'name' => 'vars-layout_width',
            'type' => 'number',
            'min' => 50,
            'max' => 100,
            'label' => 'Ширина лейаута',
            'units' => '%',
            'value' => (int) (isset($vals['vars']['layout_width']) 
                            ? $vals['vars']['layout_width'] 
                            : $defaults['vars']['layout_width']),
            'tab' => 'sizes'
        );
        
        $fields []= array(
            'name' => 'vars-max_width',
            'type' => 'number',
            'min' => 300,
            'max' => 2000,
            'label' => 'Максимальная ширина',
            'units' => 'px',
            'value' => (int) (isset($vals['vars']['max_width']) 
                            ? $vals['vars']['max_width'] 
                            : $defaults['vars']['max_width']),
            'tab' => 'sizes'
        );
        
        return array(
            'tabs' => $tabs,
            'fields' => $fields
        );
    }
    
    public function paramsFromInput($input)
    {
        $res = array_merge((array) $this['params'], self::getDefaults());
        foreach ($input as $k => $v) {
            if (preg_match("~^font_(.+)~", $k, $font_type)) {
                $res['fonts'][$font_type[1]] = $v;
                continue;
            }
            if (preg_match("~^color-(.+)~", $k, $color_type)) {
                $v = json_decode($v, true);
                $color = array('vals' => array());
                foreach ($v as $cpk => $cpv) {
                    if (preg_match("~-([^\-]+)$~", $cpk, $cp)) {
                        $cp = $cp[1];
                        if (is_numeric($cp)) {
                            $color['vals'][$cp] = $cpv;
                        } else {
                            $color[$cp] = $cpv;
                        }
                    }
                }
                $res['colors'][$color_type[1]] = $color;
                continue;
            }
            if (preg_match("~vars-(.+)$~", $k, $var_type)) {
                $var_type = $var_type[1];
                switch ($var_type) {
                    case 'max_width':
                        $v .= 'px';
                        break;
                    case 'layout_width':
                        $v .= '%';
                        break;
                }
                $res['vars'][$var_type] = $v;
            }
        }
        return $res;
    }
    
    public static function getDefaults()
    {
        return json_decode('{"fonts":{"text":"Roboto","nav":"Merriweather","headers":"Lora"},"colors":{"main":{"vals":["#000000","#51626e","#768b9a","#bbc5cd","#eaeef0","#ffffff"],"hue":206,"saturation":0.15},"alt":{"vals":["#293d8a","#364fb3","#5f75cf","#afbae7","#cbd2ef","#e4e7f7"],"hue":228,"saturation":0.537},"third":{"vals":["#47460c","#5c5a0f","#807e16","#c4c221","#dcd92d","#edeb93"],"hue":59,"saturation":0.711}},"vars":{"layout_width":"85%","max_width":"995px"}}', true);
    }
    
    public static function getFontTypes()
    {
        return array(
            'text' => 'Основной',
            'nav' => 'Для навигации',
            'headers' => 'Для заголовков'
        );
    }
    
    public static function getColorTypes()
    {
        return array(
            'main' => 'Основной',
            'alt' => 'Акценты',
            'third' => 'Дополнительный'
        );
    }
}