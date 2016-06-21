<?php

namespace Floxim\Floxim\Template;

use \Floxim\Floxim\System\Fx as fx;

class Container {
    protected $context = null;
    protected $name = null;
    protected $type = null;

    protected $values = array();
    protected $forced_values = array();
    
    protected $visual_entity = null;
    
    protected $visual_prop_name;
    
    protected $parents = array();
    
    protected static $field_keys = array (
        'bg-color',
        'margin',
        'padding',
        //'min_height',
        'align',
        'valign',
        //'sizing',
        'width',
        'width-custom',
        'height',
        'bg-image',
        'bg-color-2',
        'bg-position',
        'lightness',
        'corners',
        'shadow-spread',
        'shadow-opacity'
    );
    
    public static function create($props)
    {
        $res = array();
        foreach ($props as $k => $v) {
            $res[ preg_replace("~[^a-z0_9_-]~", '', $k) ] = preg_replace("~[^a-z0_9_-]~", '', $v);
        }
        $container = new \Floxim\Floxim\Template\Container();
        $container->setValues($res);
        return $container;
    }
    
    public function __construct(
        $context = null, 
        $name = null, 
        $visual_prop_name = 'template_visual',
        $parents = array()
    )
    {
        $this->handleName($name);
        $this->context = $context;
        $this->visual_prop_name = $visual_prop_name;
        $this->parents = $parents;

        if ($context) {
            foreach (self::$field_keys as $field_key) {
                $value = $this->context->get($this->getStoredFieldName($field_key));
                $this->values[$field_key] = $value;
            }
        }
    }

    protected function handleName($name)
    {
        preg_match("~^([^_+]+_?)~", $name, $base);
        $this->name = $name;
        switch ($base[1]) {
            case 'layout':
                $this->type = 'layout';
                break;
            case 'layout_':
                $this->type = 'section';
                break;
            case 'column_':
                $this->type = 'column';
                break;
            case 'wrapper_':
                $this->type = 'wrapper';
                break;
        }
    }
    
    public function setValues($vals)
    {
        $this->values = $vals;
    }
    
    public function bindVisual($vis)
    {
        $this->visual_entity = $vis;
        $params = $vis[$this->visual_prop_name];
        if (!is_array($params)) {
            $params = array();
        }
        foreach (self::$field_keys as $field_key) {
            $stored_key = $this->getStoredFieldName($field_key);
            $this->values[$field_key] = isset($params[$stored_key]) ? $params[$stored_key] : null;
        }
    }
    
    public function getValues()
    {
        $defaults = array(

        );
        $res = array();
        foreach ($this->values as $k => $v) {
            $res[$k] = !empty($v) ? $v : (isset($defaults[$k]) ? $defaults[$k] : $v);
        }
        if ($this->name === 'layout') {
            $res['width'] = 'full';
            if (!isset($res['lightness'])) {
                $res['lightness'] = 'light';
            }
        }
        return $res;
    }
    
    public function getValue($prop)
    {
        $vals = $this->getValues();
        return isset($vals[$prop]) ? $vals[$prop] : null;
    }
    
    public function getClasses()
    {
        $cl = 'fx-container';
        $parts = array();
        $vals = $this->getValues();
        
        $props = array(
            'align',
            'valign',
            //'sizing',
            'lightness'
        );
        foreach ($props as $p) {
            if (isset($vals[$p]) && $vals[$p]) {
                $parts[$p] = $vals[$p];
            }
        }
        if (isset($vals['height']) && $vals['height'] !== 'auto') {
            $parts['height'] = $vals['height'];
        }
        if (!isset($vals['width'])) {
            $vals['width'] = $this->getType() === 'column' ? 'column' : 'container';
        }

        $parts['width'] = $vals['width'];

        if (isset($vals['bg-color'])) {
            $bg_parts = explode(" ", $vals['bg-color']);
            if (count($bg_parts) === 2 && !isset($vals['bg-color-2'])) {
                $bg_class = join('-', $bg_parts);
                $parts['bg-color'] = $bg_class;
            }
        }
        $res = $cl;
        $parts['name'] = $this->name;
        foreach ($parts as $k => $v) {
            $res .= ' '.$cl.'_'.$k.'_'.$v;
        }
        $res .= ' '.$this->getContentClasses();
        return $res;
    }
    
    public function getParentValue($prop)
    {
        $parents = $this->parents;
        if (count($parents) === 0) {
            return null;
        }
        $parents = array_reverse($parents);
        foreach ($parents as $parent) {
            $parent_value = $parent->getValue($prop);
            if ($parent_value) {
                return $parent_value;
            }
        }
    }
    
    public function getContentClasses($with_self = false)
    {
        $block_class = 'fx-content';
        $parents = $this->parents;
        if ($with_self) {
            $parents []= $this;
        }
        if (count($parents) === 0) {
            return '';
        }
        $parents = array_reverse($parents);
        $parts = array();
        $props_to_inherit = array(
            'width',
            'lightness',
            'align'
        );
        foreach ($parents as $parent) {
            foreach ($props_to_inherit as $prop) {
                if (isset($parts[$prop])) {
                    continue;
                }
                $parent_value = $parent->getValue($prop);
                if ($parent_value) {
                    $parts[$prop] = $parent_value;
                }
            }
        }
        $res = $block_class;
        foreach ($parts as $p => $v) {
            $res .= ' '.$block_class.'_parent-'.$p.'_'.$v;
        }
        return $res;
    }

    protected static function getLessColorVar($color_code)
    {
        if (!$color_code) {
            return;
        }
        $parts = explode(" ", $color_code);
        if (count($parts) < 2) {
            return;
        }
        $var = '@color-'.$parts[0].'-'.$parts[1];
        if (isset($parts[2])) {
            $var = 'fade('.$var.', '. ($parts[2] * 100).'%)';
        }
        return $var;
    }

    public function getLess()
    {
        $res = '';
        $bg_c1_var = $this->getLessColorVar($this->getValue('bg-color'));
        $bg_c2_var = $this->getLessColorVar($this->getValue('bg-color-2'));
        $bg_img = $this->getValue('bg-image');
        if ($bg_c1_var || $bg_c2_var || $bg_img) {
            if ($bg_c1_var && !$bg_c2_var && !$bg_img) {
                $res .= 'background-color:' . $bg_c1_var . '; ';
            } else {
                $res .= 'background-image: linear-gradient(to bottom, ' . $bg_c1_var . ', ' . ($bg_c2_var ? $bg_c2_var : $bg_c1_var) . ')';
                if ($bg_img) {
                    $res .= ', url(' . $bg_img . ') ';
                }
                $res .= ';';
            }
        }
        return $res;
    }

    protected static $layout_sizes = null;
    public static function getLayoutSizes()
    {
        if (is_null(self::$layout_sizes)) {
            $style_variant = fx::env()->getLayoutStyleVariant();
            $sizes = array(
                'width' => (int) $style_variant->getLessVar('layout_width'),
                'max-width' => (int) $style_variant->getLessVar('max_width')
            );
            $sizes['breakpoint'] = 'min-width: '. $sizes['max-width'] / ($sizes['width']/100) . 'px';
            self::$layout_sizes = $sizes;
        }
        return self::$layout_sizes;
    }

    protected static function getMeasureParts($prop)
    {
        $parts = explode(" ", $prop);
        foreach (range(0,3) as $n) {
            if (!isset($parts[$n]) || $parts[$n] === '0') {
                $parts[$n] = 0;
            }
        }
        return $parts;
    }
    
    public function getStyles()
    {
        $styles = array();
        $vals = $this->values;

        $default = $this->getBackgroundStyle($vals);

        if (in_array($vals['padding'], array('0 0 0 0', 'none', 'all', 'ns', 'we'))) {
            $vals['padding'] = null;
        }
        if ($vals['padding']) {
            $default ['padding'] = $vals['padding'];
        }
        
        $width = isset($vals['width']) ? $vals['width'] : 'container';

        $parent_width = $this->getParentValue('width');

        if ($width === 'layout' && $parent_width !== 'full') {
            $width = 'container';
        }

        if ($width === 'full' && $parent_width === 'full') {
            $width = 'container';
        }


        if ($width === 'custom' && isset($vals['width-custom']) && is_numeric($vals['width-custom'])) {
            $default['width'] = $vals['width-custom'].'%';
        }

        if ($vals['margin'] && $vals['margin'] !== '0 0 0 0') {
            $default ['margin'] = $vals['margin'];
        }

        $layout_sizes = self::getLayoutSizes();

        $margin_parts = $this->getMeasureParts($vals['margin']);
        $padding_parts = $this->getMeasureParts($vals['padding']);

        if ($width === 'full' || $width === 'full-outer') {

            $f_margin = 50 - (5000 / $layout_sizes['width']);
            $f_bp_margin = 'calc( ( 100vw - '.$layout_sizes['max-width'].'px) / -2  ';
            $res_bp = array();

            foreach (array(1 => 'right', 3 => 'left') as $side_index => $side) {
                $c_margin = $margin_parts[$side_index];
                $c_padding = $padding_parts[$side_index];

                if ($parent_width === 'layout' || $parent_width === 'full-outer') {
                    $default['margin-' . $side] = !$c_margin ? $f_margin . '%' : 'calc(' . $f_margin . '% + ' . $c_margin . ')';
                    $res_bp ['margin-' . $side] = $f_bp_margin . (!$c_margin ? '' : ' + ' . $c_margin) . ')';
                }
                if ($width === 'full-outer') {
                    $default['padding-' . $side] = !$c_padding ? ($f_margin * -1) . '%' : 'calc(' . ($f_margin * -1) . '% + ' . $c_padding . ')';
                    $res_bp ['padding-' . $side] = $f_bp_margin . ' * -1 ' . (!$c_padding ? '' : ' + ' . $c_padding) . ')';
                }
            }
            $styles[$layout_sizes['breakpoint']] = $res_bp;
        } elseif ($width === 'layout') {
            $f_margin = 'calc( (100vw - ' . $layout_sizes['max-width'] ."px) / 2";
            $res_bp = array();
            foreach (array(1 => 'right', 3 => 'left') as $side_index => $side) {
                $c_margin = $margin_parts[$side_index];
                $res_bp ['margin-'.$side] = $f_margin .( !$c_margin ? '' : ' + '.$c_margin).')';
            }
            $styles[$layout_sizes['breakpoint']] = $res_bp;
        }
        
        if (isset($vals['corners']) && $vals['corners'] !== '0 0 0 0') {
            $default ['border-radius'] = $vals['corners'];
        }
        if (isset($vals['shadow-spread']) && !empty($vals['shadow-spread'])) {
            $opacity = isset($vals['shadow-opacity']) && !empty($vals['shadow-opacity']) ? $vals['shadow-opacity'] : 0.3;
            $default ['box-shadow'] = '0 0 '.$vals['shadow-spread'].'px rgba(0,0,0,'.$opacity.')';
        }
        $styles ['default'] = $default;
        return $styles;
    }

    public function getCssSelector()
    {
        return '.fx-container_name_'.$this->name;
    }
    
    public static function getBackgroundStyle($vals)
    {
        $style_variant = fx::env()->getLayoutStyleVariant();
        $c1 = $vals['bg-color'];
        $c2 = $vals['bg-color-2'];
        $img = $vals['bg-image'];

        if (!$c1 && !$c2 && !$img) {
            return '';
        }

        $c1 = $c1 ? $style_variant->getColor($c1) : false;
        $c2 = $c2 ? $style_variant->getColor($c2) : false;

        $css = array(
            'background-color' => '',
            'background-image' => '',
            'background-position' => '',
            'background-repeat' => '',
            'background-size' => ''
        );
        
        // first color only
        if ($c1 && !$c2 && !$img) {
            $css['background-color'] = $c1;
        } 
        // image only
        elseif (!$c1 && !$c2 && $img) {
            $css['background-image'] = "url('" . $img . "')";
        } 
        // use gradient: two colors or color(s) and image
        else {
            $bg  = 'linear-gradient(to bottom, ';
            $bg .= ($c1 ? $c1 : 'transparent') . ', ';
            $bg .= $c2 ? $c2 : $c1;
            $bg .= ')';
            if ($img) {
                $bg .= ", url('" . $img . "')";
            }
            $css['background-image'] = $bg;
        }
        $css = array_merge($css, self::getBackgroundPositionProps($vals));
        return $css;
    }

    protected function getBackgroundPositionProps($vals)
    {
        $css = array();
        if (!isset($vals['bg-image']) || !isset($vals['bg-position']) || !$vals['bg-position'] || !$vals['bg-image']) {
            return $css;
        }
        $pos_val = $vals['bg-position'];
        $pos = '';
        $size = '';
        $repeat = 'no-repeat';
        switch ($pos_val) {
            case 'cover':
                $size = 'cover';
                break;
            case 'repeat':
                $repeat = 'repeat';
                break;
            default:
                $size = 'contain';
                $pos_parts = explode('-', $pos_val);
                $h_map = array(
                    'left' => 0,
                    'center' => '50%',
                    'right' => '100%'
                );
                $v_map = array(
                    'top' => '0',
                    'middle' => '50%',
                    'bottom' => '100%'
                );
                $pos = $h_map[$pos_parts[0]] .' '.$v_map[$pos_parts[1]];
                break;
        }
        $css['background-position'] = $pos;
        $css['background-size'] = $size;
        $css['background-repeat'] = $repeat;
        return $css;
    }
    
    public function getFields()
    {
        $res = array();
        $type = $this->getType();

        $res['bg-color'] = array(
            'type' => 'palette',
            'colors' => fx::env()->getLayoutStyleVariant()->getPalette(),
            'label' => 'Цвет фона',
            'opacity' => true
        );
        $res['bg-color-2'] = array(
            'type' => 'palette',
            'colors' => fx::env()->getLayoutStyleVariant()->getPalette(),
            'label' => 'Цвет 2',
            'opacity' => true
        );
        $res['bg-image'] = array(
            'type' => 'image',
            'label' => 'Фоновое изображение'
        );
        $res['bg-position'] = $this->getBackgroundPositionField();
        $res['lightness'] = array(
            'type' => 'select',
            'label' => 'Тон фона',
            'values' => array(
                '' => 'Прозрачный',
                'light' => 'Светлый',
                'dark' => 'Темный'
            )
        );
        if ($type !== 'layout') {
            $res['padding'] = array(
                'label' => 'Внутренний отступ',
                'type' => 'measures',
                'prop' => 'padding',
                'value' => '0 0 0 0'
            );
            $res['margin'] = array(
                'label' => 'Внешний отступ',
                'type' => 'measures',
                'prop' => 'margin',
                'value' => '0 0 0 0'
            );
        }

        /*
        if ($type !== 'column' && $type !== 'layout') {
            $res['min-height'] = array(
                'label' => 'Мин. высота',
                'type' => 'number',
                'min' => 0,
                'max' => 1000,
                'step' => 10
            );
        }
        */

        if ($type !== 'layout' && $type !== 'columns' && $type !== 'section') {
            $res['align'] = array(
                'label' => 'Выравнивание',
                'type' => 'livesearch',
                'allow_empty' => false,
                'values' => $this->getLivesearchSchemes(
                    'align',
                    array(
                        'left' => 'Слева',
                        'center' => 'По центру',
                        'right' => 'Справа'
                    )
                ),
                'value' => 'left'
            );
            $res['valign'] = array(
                'label' => '&nbsp;',
                'type' => 'livesearch',
                'allow_empty' => false,
                'values' => $this->getLivesearchSchemes(
                    'valign',
                    array(
                        'top' => 'Сверху',
                        'middle' => 'Посередине',
                        'bottom' => 'Снизу'
                    )
                )
            );
        }
        if ($type !== 'column' && $type !== 'layout') {
            /*
            $sizing_variants = $this->getSizingVariants();
            if (count($sizing_variants) > 0) {
                $res['sizing'] = array(
                    'label' => 'Ширина',
                    'values' => $sizing_variants,
                    'type' => "livesearch",
                    'allow_empty' => false,
                    'value' => "default" 
                );
            }
            */
            $res['width'] = array(
                'type' => 'livesearch',
                'label' => 'Ширина',
                'value' => 'container',
                'values' => array(
                    'container' => '100%',
                    'auto' => 'Авто',
                    'layout' => 'Ширина лейаута',
                    'full' => 'На весь экран',
                    'full-outer' => 'На весь экран + отступ',
                    'custom' => 'Задать...'
                )
            );
            $res['width-custom'] = array(
                'type' => 'number',
                'units' => '%',
                'min' => 10,
                'max' => 100,
                'step' => 5,
                'parent' => array(
                    $this->getStoredFieldName('width') => 'custom'
                )
            );

            $res['height'] = array(
                'type' => 'livesearch',
                'label' => 'Высота',
                'value' => 'auto',
                'values' => array(
                    'auto' => 'Авто',
                    'grow' => 'Растягивать'
                )
            );
        }
        
        if (in_array($type, array('section', 'wrapper'))) {
            $res['corners'] = array(
                'label' => 'Углы',
                'type' => 'measures',
                'prop' => 'corners',
                'value' => '0 0 0 0'
            );
            $res['shadow-spread'] = array(
                'label' => 'Тень',
                'type' => 'number',
                'value' => 0,
                'min' => 0,
                'max' => 50,
                'step' => 5
            );
            $res['shadow-opacity'] = array(
                'label' => 'прозрачность',
                'type' => 'number',
                'value' => 0,
                'min' => 0.1,
                'max' => 1,
                'step' => 0.1
            );
        }
        return $res;
    }
    
    public function getBackgroundPositionField()
    {
        $pos_h = array('left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа');
        $pos_v = array('top' => 'Сверху', 'middle' => 'По центру', 'bottom' => 'Снизу');
        
        $pos_vals = array(
            array('cover', 'Растянуть'),
            array('repeat','Зациклить')
        );
        foreach ($pos_h as $h_key => $h_name) {
            $c_val = array(
                $h_key,
                $h_name,
                array(
                    'disabled' => true,
                    'collapsed' => true,
                    'children' => array()
                )
            );
            foreach ($pos_v as $v_key => $v_name) {
                $c_val[2]['children'][]= array($h_key.'-'.$v_key, $v_name);
            }
            $pos_vals []= $c_val;
        }
        return array(
            'type' => 'livesearch',
            'label' => 'Позиция изображения',
            'values' => $pos_vals,
            'parent' => array(
                $this->getStoredFieldName('bg-image')
            )
        );
    }
    
    public function getSizingVariants()
    {
        return array(
            array(
                'auto',
                'По размеру контента'
            ),
            array(
                'full',
                'На весь экран'
            ),
            array(
                '',
                '',
                array(
                    'custom' => array(
                        'type' => 'number',
                        'min' => 10,
                        'max' => 100,
                        'units' => '%'
                    )
                )
            )
        );
        $variants = array(
            'default'
        );
        $parent_sizing = $this->getParentValue('sizing');
        if ($parent_sizing !== 'column') {
            $variants []= 'fullwidth';
        }
        $type = $this->getType();
        if ($type === 'wrapper' && $parent_sizing !== 'column') {
            $variants []= 'fullwidth-outer';
        }
        $parent_padding = $this->getParentValue('padding');
        if ( in_array($parent_padding, array('all', 'we')) ) {
            $variants []= 'antipad';
        }
        $res = $this->getLivesearchSchemes('wrapper_sizing', $variants);
        return $res;
    }
    
    protected function getLivesearchSchemes($prop_name, $variants)
    {
        $res = array();
        foreach($variants as $k => $v) {
            $title_att = '';
            if (is_numeric($k)) {
                $var_key = $v;
            } else {
                $var_key = $k;
                $title_att = ' title="'.$v.'" ';
            }
            
            $html = '<span class="fx_livesearch_scheme fx_livesearch_'.$prop_name.' '.$var_key.'" '.$title_att.'>';
            $html .= '<span class="c"><span class="w"><span class="d"></span></span></span>';
            $html .= '</span>';
            $html = str_replace('"', '\'', $html);
            $res []= array(
                $var_key,
                $html
            );
        }
        return $res;
    }
    
    protected function getStoredFieldName($field_key)
    {
        return 'container_'.$this->name.'_'.$field_key;
    }
    
    public function getMeta()
    {
        $res = array(
            'name' => $this->name,
            'type' => $this->getType(),
            'values' => $this->values,
            'set' => $this->visual_prop_name
        );
        return $res;
    }
    
    public function getMetaJson()
    {
        $meta = $this->getMeta();
        $json = json_encode($meta);
        $json = str_replace("'", '&apos;', $json);
        $json = str_replace("&quot;", '"', $json);
        return $json;
    }
    
    public function getForm()
    {
        $fields = $this->getFields();
        $res = array();
        foreach ($fields as $field_key => $field) {
            if (isset($this->values[$field_key])) {
                $value = $this->values[$field_key];
                if ($field['type'] === 'image') {
                    $value = \Floxim\Floxim\Field\Image::prepareValue($value);
                }
                $field['value'] = $value;
            }
            $field['name'] = $this->getStoredFieldName($field_key);
            $res []= $field;
        }
        return $res;
    }
    
    public function getType()
    {
        return $this->type;
    }
    
    public function appendInput($input)
    {
        $vis = $this->visual_entity;
        $params = $vis[$this->visual_prop_name];
        if (!is_array($params)) {
            $params = array();
        }
        foreach (self::$field_keys as $field_key) {
            $stored_key = $this->getStoredFieldName($field_key);
            if (isset($input[$stored_key])) {
                $params[$stored_key] = $input[$stored_key];
            }
        }
        $vis->set($this->visual_prop_name, $params);
    }
    
    public function save($input)
    {
        $this->appendInput($input);
        $this->visual_entity->save();
    }
    
}