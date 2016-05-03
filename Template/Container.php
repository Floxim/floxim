<?php

namespace Floxim\Floxim\Template;

use \Floxim\Floxim\System\Fx as fx;

class Container {
    protected $context = null;
    protected $name = null;
    
    protected $values = array();
    protected $forced_values = array();
    
    protected $visual_entity = null;
    
    protected $visual_prop_name;
    
    protected $parents = array();
    
    public function __construct(
        $context = null, 
        $name = null, 
        $visual_prop_name = 'template_visual',
        $parents = array()
    )
    {
        $this->name = $name;
        $this->context = $context;
        $this->visual_prop_name = $visual_prop_name;
        $this->parents = $parents;
        
        if ($context) {
            $field_keys = array_keys($this->getFields());
            foreach ($field_keys as $field_key) {
                $value = $this->context->get($this->getStoredFieldName($field_key));
                $this->values[$field_key] = $value;
            }
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
        $field_keys = array_keys($this->getFields());
        foreach ($field_keys as $field_key) {
            $stored_key = $this->getStoredFieldName($field_key);
            $this->values[$field_key] = isset($params[$stored_key]) ? $params[$stored_key] : null;
        }
    }
    
    public function getValues()
    {
        $defaults = array(
            'sizing' => 'default'
        );
        $res = array();
        foreach ($this->values as $k => $v) {
            $res[$k] = !empty($v) ? $v : (isset($defaults[$k]) ? $defaults[$k] : $v);
        }
        if ($this->name === 'layout') {
            $res['sizing'] = 'fullwidth';
        }
        if (preg_match("~^column_~", $this->name)) {
            $res['sizing'] = 'column';
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
        
        $props = array('align', 'valign', 'sizing', 'padding', 'lightness');
        foreach ($props as $p) {
            if (isset($vals[$p]) && $vals[$p]) {
                $parts[$p] = $vals[$p];
            }
        }
        $res = $cl;
        foreach ($parts as $k => $v) {
            $res .= ' '.$cl.'_'.$k.'_'.$v;
        }
        $res .= ' '.$this->getContentClasses();
        return $res;
    }
    
    public function getContentClasses($with_self = false)
    {
        $block_class = 'fx-content';
        $parents = $this->parents;
        if ($with_self) {
            $parents []= $this;
            //fx::log("push parent", $parents);
        }
        if (count($parents) === 0) {
            return '';
        }
        $parents = array_reverse($parents);
        $parts = array();
        $props_to_inherit = array(
            'sizing',
            'padding',
            'lightness'
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
    
    public function getStyles()
    {
        $res = '';
        $vals = $this->values;
        $res .= $this->getBackgroundStyle($vals);
        if ($vals['min_height']) {
            $res .= 'min-height:'.$vals['min_height'].';';
        }
        return $res;
    }
    
    public static function getBackgroundStyle($vals)
    {
        $css = array(
            'background-color' => '',
            'background-image' => '',
            'background-position' => '',
            'background-repeat' => '',
            'background-size' => ''
        );
        
        $c1 = $vals['bg_color'];
        $c2 = $vals['bg_color_2'];
        $img = $vals['bg_image'];
        
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
        if ($img && isset($vals['bg_position']) && $vals['bg_position']) {
            $pos_val = $vals['bg_position'];
            $pos = '';
            $size = '';
            $repeat = '';
            switch ($pos_val) {
                case 'cover':
                    $repeat = 'no-repeat';
                    $size = 'cover';
                    break;
                case 'repeat':
                    $repeat = 'repeat';
                    break;
                default:
                    $repeat = 'no-repeat';
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
        }
        $res = '';
        foreach ($css as $p => $v) {
            if ($v) {
                $res .= $p.':'.$v.';';
            }
        }
        return $res;
    }
    
    public function getFields()
    {
        $res = array();
        $type = $this->getType();
        $res['bg_color'] = array(
            'type' => 'color',
            'label' => 'Цвет фона'
        );
        $res['bg_color_2'] = array(
            'type' => 'color',
            'label' => 'Цвет фона 2'
        );
        $res['bg_image'] = array(
            'type' => 'image',
            'label' => 'Фоновое изображение'
        );
        $res['bg_position'] = $this->getBackgroundPositionField();
        $res['lightness'] = array(
            'type' => 'select',
            'label' => 'Тон фона',
            'values' => array(
                '' => 'Прозрачный',
                'light' => 'Светлый',
                'dark' => 'Темный'
            )
        );
        if ($type !== 'layout' && $type !== 'columns') {
            $res['padding'] = array(
                'label' => 'Внутренний отступ',
                'type' => 'livesearch',
                'allow_empty' => false,
                'values' => $this->getLivesearchSchemes(
                    'padding',
                    array(
                        'none' => 'Нет',
                        'all' => 'Да',
                        'ns' => 'Верх и низ',
                        'we' => 'Лево и право'
                    )
                ),
                'value' => 'all'
            );
        }
        if ($type !== 'column' && $type !== 'layout') {
            $res['min_height'] = array(
                'label' => 'Мин. высота',
                'type' => 'number'
            );
        }
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
            'values' => $pos_vals
        );
    }
    
    public function getSizingVariants()
    {
        $variants = array(
            'default',
            'fullwidth'
        );
        $type = $this->getType();
        if ($type === 'wrapper') {
            $variants []= 'fullwidth-outer';
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
        $name = $this->name;
        if ($name === 'layout') {
            return 'layout';
        }
        if ($name === 'columns') {
            return 'columns';
        }
        if (preg_match("~^layout_~", $name)) {
            return 'section';
        }
        if (preg_match("~^column_~", $name)) {
            return 'column';
        }
        if (preg_match("~^wrapper_~", $name)) {
            return 'wrapper';
        }
    }
    
    public function save($input)
    {
        $vis = $this->visual_entity;
        $params = $vis[$this->visual_prop_name];
        if (!is_array($params)) {
            $params = array();
        }
        $fields = $this->getFields();
        foreach (array_keys($fields) as $field_key) {
            $stored_key = $this->getStoredFieldName($field_key);
            if (isset($input[$stored_key])) {
                $params[$stored_key] = $input[$stored_key];
            }
        }
        $vis->set($this->visual_prop_name, $params)->save();
    }
    
}