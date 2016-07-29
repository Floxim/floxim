<?php

namespace Floxim\Floxim\Asset\Less\Bem;

use \Floxim\Floxim\System\Fx as fx;


class Stack {

    public $stack = array();
    public $has_special_rules = false;
    
    public function __construct($path) {
        $this->output = new \Floxim\Floxim\Asset\Less\Tweaker\Output();
        $this->pushPath($path);
    }
    
    public function pushPath($path)
    {
        $s = ' ';
        foreach ($path as $p) {
            $chunk = $this->output->get($p, false);
            $s .= $chunk;
        }
        if (!strstr($s, "#_")) {
            return;
        }
        $parts = null;
        if (!preg_match_all("~[\.\s\#]+[^\.\s\#]+~s", $s, $parts)) {
            return;
        }
        
        foreach ($parts[0] as $part) {
            $this->pushPart($part);
        }
        $this->has_special_rules = true;
    }
    
    public function pushPart($v) 
    {
        $combinator = preg_match("~^\s+~", $v) ? ' ' : '';
        $v = trim($v);
        
        $is_special = preg_match("~^#_~", $v);

        if (!$is_special && !preg_match("~^\.~", $v)) {
            $this->stack []= array(
                'combinator' => $combinator,
                'value' => $v
            );
            return;
        }
           
        $parts = preg_split("~(\.|_+[^_]+)~", $v, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts[0] === '.') {
            array_shift($parts);
            $block_name = array_shift($parts);
            $this->addBlock(
                $block_name,
                $combinator
            );
            $combinator = '';
        } elseif ($parts[0] === '#') {
            array_shift($parts);
        }
        if (count($parts) > 0) {
            $part = array_shift($parts);
            $el_name = null;
            $mod_name = null;
            if (preg_match("~^__(.+)~", $part, $el_name)) {
                $this->addElement($el_name[1], $combinator);
                $part = array_shift($parts);
            }
            if (preg_match("~^_(.+)~", $part, $mod_name)) {
                $mod_name = $mod_name[1];
                if (count($parts) > 0) {
                    $mod_val = preg_replace("~^_~", '', $parts[0]);
                } else {
                    $mod_val = true;
                }
                $this->setMod($mod_name, $mod_val);
            }
        }
    }

    protected $c_block = null;
    protected $last_bem_index = null;

    public function addBlock($name, $combinator)
    {
        $this->c_block = $name;
        $this->stack []= array(
            'combinator' => $combinator,
            'name' => $name,
            'type' => 'block',
            'mods' => array()
        );
        $this->last_bem_index = count($this->stack) - 1;
    }

    public function addElement($name, $combinator = '')
    {
        $this->stack []= array(
            'name' => $this->c_block.'__'.$name,
            'combinator' => $combinator,
            'type' => 'element',
            'mods' => array()
        );
        $this->last_bem_index = count($this->stack) - 1;
    }

    public function setMod($name, $value)
    {
        $this->stack[ $this->last_bem_index ]['mods'][]= array($name, $value);
    }

    protected static function getModSelector($mod, $base)
    {
        return $base.'_'.$mod[0].($mod[1] === true ? '' : '_'.$mod[1]);
    }
    
    public function getPath()
    {
        $res = '';
        foreach ($this->stack as $num => $level) {
            $res .= $level['combinator'];
            if (isset($level['value'])) {
                $res .= $level['value'];
                continue;
            }
            if ($level['type'] === 'block' && count($level['mods']) === 0 && isset($this->stack[$num + 1]) ) {
                $next = $this->stack[$num + 1];
                if (isset($next['type']) && $next['type'] === 'element') {
                    continue;
                }
            }
            $base = '.'.$level['name'];
            $res .= $base;
            foreach ($level['mods'] as $mod_num => $mod) {
                $res .= self::getModSelector($mod, $mod_num > 0 ? $base : '');
            }
        }
        $el = new \Less_Tree_Element('', trim($res));
        $sel = new \Less_Tree_Selector(array($el));
        return array($sel);
    }
}