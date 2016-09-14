<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

/*
 * Class for a separate token fx-templating engine
 */

class Token
{
    public $name = null;
    public $type = null;
    public $props = array();
    
    public $stack_extra = null;
    public $need_type = null;


    /**
     * to create a token from source
     * @param string $source
     * @return fx_template_token
     */
    public static function create($source)
    {
        if (!preg_match('~^\{~', $source)) {
            $type = 'single';
            $name = 'code';
            $props['value'] = $source;
            return new Token($name, $type, $props);
        }
        $props = array();
        $source = trim($source, '{}'); //preg_replace("~^\{|\}$~", '', $source);
        $is_close = preg_match('~^\/~', $source);
        $source = ltrim($source, '/');
        $first_char = substr($source, 0, 1);
        $is_var = in_array($first_char, array('$', '%'));
        $is_param = false;
        if ($is_var && !$is_close) {
            $ep = new ExpressionParser();
            $name = $ep->findVarName(trim($source, '/ '));
        } elseif ($first_char === '@') {
            $name = 'param';
            $source = preg_replace_callback(
                "~^@([^\s]+)~", 
                function($m) use (&$props) {
                    $props['name'] = $m[1];
                    return '';
                }, 
                $source
            );
            $source = trim($source);
            $is_param = true;
        } elseif ($first_char === '=') {
            $source = preg_replace("~^=~", 'print ', $source);
            $name = 'print';
        } else {
            preg_match("~^[^\s\/\\|}]+~", $source, $name);
            $name = $name[0];
        }
        
        if (!$is_param) {
            $source = substr($source, strlen($name));
        }
        
        if ($name == 'apply') {
            $name = 'call';
            $props['apply'] = true;
        } elseif ($name == 'default') {
            $name = 'set';
            $props['default'] = 'true';
        }

        if ($name == 'wtf') {
            $name = 'help';
        }


        $type_info = self::getTokenInfo($name);
        $token_close_type = isset($type_info['type']) ? $type_info['type'] : null;
        //if (preg_match("~^[\\\$%]~", $name, $var_marker)) {
        if ($is_var) {
            $props['id'] = preg_replace("~^[\\\$%]~", '', $name);
            $props['var_type'] = $first_char == '%' ? 'visual' : 'data';
            $name = 'var';
        }
        if ($name == 'with-each') {
            $name = 'with_each';
        }
        if (preg_match("~\/$~", $source)) {
            $type = 'single';
            $source = preg_replace("~/$~", '', $source);
        } elseif ($is_close) {
            $type = 'close';
        } elseif ($token_close_type == 'single') {
            $type = 'single';
        } elseif ($token_close_type == 'double') {
            $type = 'open';
        } else {
            $type = 'unknown';
        }
        
        if ($name === 'print' && !preg_match("~expression\s*?=~",$source)) {
            $source = preg_replace('~([^\\\])"~', '$1\\"', $source);
            $source = ' expression="'.$source.'" ';
        }
        
        if ($name === 'preset' && $type !== 'close' && !preg_match("~id\s*?=~", $source)) {
            $source = preg_replace_callback(
                "~([a-z0-9\.\_\-\:]+)\#?([a-z0-9_-]+)?~", 
                function($m) {
                    return 'id="'.$m[0].'"';
                    //return 'template="'.trim($m[1]).'" preset_id="'.trim($m[2]).'"';
                },
                $source,
                1
            );
        }
        
        if (($name == 'if' || $name == 'elseif') && $type != 'close' && !preg_match('~test=~', $source)) {
            $props['test'] = $source;
        } elseif ($name == 'call' && $type != 'close' && !preg_match('~id=~', $source)) {
            $source = trim($source);
            $parts = preg_split("~\s+(with|each)\s+~", $source, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $props['id'] = array_shift($parts);
            foreach ($parts as $prop_num => $prop) {
                if ($prop_num % 2 == 0 && isset($parts[$prop_num + 1])) {
                    $props[$prop] = $parts[$prop_num + 1];
                }
            }
        } elseif (($name == 'each' || $name == 'with_each') && $type != 'close' && !preg_match('~select=~', $source)) {
            $props['select'] = trim($source);
        } elseif ($name == 'set' && $source) {
            $parts = explode("=", $source, 2);
            $var_part = trim($parts[0]);
            
            // {set $var = 'value' /}
            if (preg_match('~^\$[a-z0-9_]+$~i', $var_part)) {
                $props['var'] = $var_part;
                if (isset($parts[1])) {
                    $props['value'] = trim($parts[1]);
                }
            }
            // {set $var att="value" att2="value2"}...{/set}
            else {
                $real_var_part = null;
                if (preg_match('~^\$[a-z0-9_]+~i', $var_part, $real_var_part)) {
                    $props['var'] = $real_var_part[0];
                    $source = preg_replace('~^\$[a-z0-9_]+~i', '', $source);
                    $props = array_merge($props, TokenAttParser::getAtts($source));
                }
            }
        } elseif ($name == 'with' && !preg_match("~select=~", $source)) {
            $props['select'] = trim($source);
        } else {
            $props = array_merge($props, TokenAttParser::getAtts($source));
            if ($name == 'var' && preg_match("~^\s*\|~", $source)) {
                $props['modifiers'] = self::getVarModifiers($source);
            } elseif (isset($props['modifiers']) && is_string($props['modifiers'])) {
                $props['modifiers'] = json_decode($props['modifiers'], true);
            }
        }

        if ($name == 'each' || $name == 'with_each') {
            $arr_id_parts = null;
            $item_key = null;
            $item_alias = null;
            $arr_id = isset($props['select']) ? $props['select'] : null;
            if (preg_match("~(.+?)\sas\s(.+)$~", $arr_id, $arr_id_parts)) {
                $arr_id = trim($arr_id_parts[1]);
                $as_parts = explode("=>", $arr_id_parts[2]);
                if (count($as_parts) == 2) {
                    $item_key = trim($as_parts[0]);
                    $item_alias = trim($as_parts[1]);
                } else {
                    $item_alias = trim($as_parts[0]);
                }
            }
            $props['select'] = $arr_id;
            $props['as'] = $item_alias;
            $props['key'] = $item_key;
        }
        return new self($name, $type, $props);
    }


    public function dump()
    {
        if ($this->name == 'code') {
            return $this->getProp('value');
        }
        $res = '{';
        $res .= $this->type == 'close' ? '/' : '';
        $res .= $this->name . ' ';
        foreach ($this->props as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v);
            }
            $v = str_replace("'", "\\'", $v);
            $res .= $k . "='" . $v . "' ";
        }
        $res .= $this->type == 'single' ? '/' : '';
        $res .= '}';
        return $res;
    }
    
    public function toPlain()
    {
        if ($this->name === 'code') {
            return $this->getProp('value');
        }
        $res = array($this->name);
        $props = $this->props;
        unset($props['offset']);
        unset($props['source']);
        $res []= $props;
        $children = $this->getChildren();
        if ($children && count($children) > 0) {
            $plain_children = array();
            foreach ($this->children as $child) {
                $plain_children[]= $child->toPlain();
            }
            $res[]= $plain_children;
        }
        return $res;
    }

    public function isEmpty()
    {
        if ($this->name != 'code') {
            return false;
        }
        return !trim($this->getProp('value'));
    }


    public static function getVarModifiers($source_str)
    {
        $p = new ModifierParser();
        $res = $p->parse($source_str);
        return $res;
    }

    protected static $_token_types = array(
        'template'  => array(
            'type'     => 'double',
            'contains' => array(
                'code',
                'template',
                'area',
                'var',
                'call',
                'each',
                'if',
                'with_each',
                'separator',
                'with',
                'lang', 
                'bem_block', 
                'bem_element',
                'param',
                'preset',
                'use'
            )
        ),
        'preset'  => array(
            'type'     => 'double',
            'contains' => array(
                'code',
                'set',
                'use'
            )
        ),
        'styled' => array(
            'type' => 'double',
            'contains' => array(
                'code',
                'var'
            )
        ),
        'param' => array(
            'type' => 'single'
        ),
        'code'      => array(
            'type' => 'single'
        ),
        'area'      => array(
            'type'     => 'both',
            'contains' => array(
                'code', 
                'template', 
                'var', 
                'lang', 
                'area', 
                'each', 
                'with_each', 
                'if', 
                'elseif', 
                'else', 
                'bem_block', 
                'bem_element',
                'preset'
            )
        ),
        'var'       => array(
            'type'     => 'both',
            'contains' => array('code', 'var', 'call', 'area', 'template', 'each', 'if', 'apply', 'call', 'lang', 'bem_block', 'bem_element')
        ),
        'call'      => array(
            'type'     => 'both',
            'contains' => array(
                'var', 
                'lang', 
                'each', 
                'if', 
                'apply', 
                'call', 
                'with_each', 
                'with', 
                'bem_block', 
                'bem_element',
                'use'
            )
        ),
        'use' => array(
            'type' => 'double',
            'contains' => array(
                'code',
                'template',
                'area',
                'var',
                'call',
                'each',
                'if',
                'with_each',
                'separator',
                'with',
                'lang', 
                'bem_block', 
                'bem_element',
                'param'
            )
        ),
        'templates' => array(
            'type'     => 'double',
            'contains' => array('template', 'templates', 'preset')
        ),
        'lang'      => array(
            'type'     => 'double'
        ),
        'each'      => array(
            'type'     => 'double',
            'contains' => array('code', 'template', 'area', 'var', 'lang', 'call', 'each', 'if', 'elseif', 'else', 'separator', 'bem_block', 'bem_element')
        ),
        'bem_block' => array(
            'type'     => 'double',
            'contains' => array('code', 'var', 'lang', 'call', 'if', 'elseif', 'else', 'styled')
        ),
        'bem_element' => array(
            'type'     => 'double',
            'contains' => array('code', 'var', 'lang', 'call', 'if', 'elseif', 'else')
        ),
        'with_each' => array(
            'type'     => 'double',
            'contains' => array(
                'item',
                'code',
                'template',
                'area',
                'var',
                'lang',
                'call',
                'each',
                'if',
                'elseif',
                'else',
                'separator',
                'bem_block', 
                'bem_element'
            )
        ),
        'with'      => array(
            'type'     => 'double',
            'contains' => array(
                'item',
                'code',
                'template',
                'area',
                'var',
                'lang',
                'call',
                'each',
                'if',
                'elseif',
                'else',
                'separator'
            )
        ),
        'item'      => array(
            'type'     => 'double',
            'contains' => array('code', 'template', 'area', 'var', 'lang', 'call', 'each', 'if', 'elseif', 'else')
        ),
        'if'        => array(
            'type'     => 'double',
            'contains' => array('code', 'template', 'area', 'var', 'lang', 'call', 'each', 'elseif', 'else')
        ),
        'else'      => array(
            'type'     => 'both',
            'contains' => array('code', 'template', 'area', 'var', 'lang', 'call', 'each', 'elseif', 'else', 'bem_block', 'bem_element')
        ),
        'elseif'    => array(
            'type'     => 'both',
            'contains' => array('code', 'template', 'area', 'var', 'lang', 'call', 'each', 'elseif', 'else', 'bem_block', 'bem_element')
        ),
        'separator' => array(
            'type' => 'double'
        ),
        'css' => array(
            'type' => 'double',
            'contains' => array('var', 'lang', 'if', 'elseif', 'else', 'apply', 'call')
        ),
        'set' => array(
            'type' => 'both',
            'contains' => array('code', 'var', 'lang', 'call', 'apply', 'if', 'elseif', 'else', 'each')
        )
    );

    public static function getTokenInfo($type)
    {
        $info = isset(self::$_token_types[$type]) ? self::$_token_types[$type] : array();
        if (!isset($info['contains'])) {
            $info['contains'] = array();
        }
        return $info;
    }

    /**
     *
     * @param type $name name of the token, e.g. "template"
     * @param type $type the type - open/close/single
     * @param type $props attributes token
     */
    public function __construct($name, $type, $props)
    {
        $this->name = $name;
        $this->type = $type;
        $this->props = $props;
    }

    public function addChild(Token $token)
    {
        if (!isset($this->children)) {
            $this->children = array();
        }
        $this->children [] = $token;
    }

    public function addChildren(array $children)
    {
        foreach ($children as $child) {
            $this->addChild($child);
        }
    }


    public function clearChildren()
    {
        $this->children = array();
    }

    public function getChildren()
    {
        return isset($this->children) ? $this->children : array();
    }
    
    public function getFirstChild()
    {
        return isset($this->children) && isset($this->children[0]) ? $this->children[0] : null;
    }
    

    public function getNonEmptyChildren()
    {
        $res = array();
        $children = $this->getChildren();
        foreach ($children as $i => $ch) {
            if (!$ch->isEmpty()) {
                $res [] = $ch;
            }
        }
        return $res;
    }

    public function hasChildren()
    {
        return isset($this->children) && count($this->children) > 0;
    }

    public function setChild($child, $index)
    {
        if ($child === null) {
            unset($this->children[$index]);
        } else {
            $this->children[$index] = $child;
        }
    }

    public function setChildren($children)
    {
        $this->children = $children;
    }


    public function setProp($name, $value)
    {
        if ($value === null) {
            unset($this->props[$name]);
        } else {
            $this->props[$name] = $value;
        }
    }

    public function getProp($name)
    {
        return isset($this->props[$name]) ? $this->props[$name] : null;
    }

    public function getAllProps()
    {
        return $this->props;
    }

    public function show()
    {
        $r = '[' . ($this->type == 'close' ?
                '/' :
                ($this->type == 'unknown' ? '?' : '')) . $this->name . ' ';
        foreach ($this->props as $pk => $pv) {
            $r .= $pk . '="' . $pv . '" ';
        }
        $r .= ']';
        return $r;
    }
    
    public function getRawValue()
    {
        $res = '';
        foreach ($this->getChildren() as $child) {
            if ($child->name !== 'code') {
                return null;
            }
            $res .= $child->getProp('value');
        }
        return $res;
    }
}

