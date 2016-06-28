<?php
/**
 * Created by PhpStorm.
 * User: Админ без пароля
 * Date: 30.05.2016
 * Time: 15:04
 */

namespace Floxim\Floxim\Asset\Less\Bem;

use \Floxim\Floxim\System\Fx as fx;


class Stack {

    public $stack = array();
    public $has_special_rules = false;

    public function push($el)
    {
        $v = $el->value;
        if (is_object($v)) {
            $this->stack []= $el;
            return;
        }
        $is_special = preg_match("~^#_~", $v);
        if ($is_special) {
            $this->has_special_rules = true;
        }
        if ($is_special || preg_match("~^\.~", $v)) {
            $el = clone $el;
            $parts = preg_split("~(\.|_+[^_]+)~", $v, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if ($parts[0] === '.') {
                array_shift($parts);
                $block_name = array_shift($parts);
                $this->addBlock(
                    $block_name,
                    $el,
                    isset($parts[0]) && preg_match("~^__(.+)~", $parts[0])
                );
            } elseif ($parts[0] === '#') {
                array_shift($parts);
            }
            if (count($parts) > 0) {
                $part = array_shift($parts);
                if (preg_match("~^__(.+)~", $part, $el_name)) {
                    $this->addElement($el_name[1], $el);
                    $part = array_shift($parts);
                }
                if (preg_match("~^_(.+)~", $part, $mod_name)) {
                    $mod_name = $mod_name[1];
                    if (count($parts) > 0) {
                        $mod_val = preg_replace("~^_~", '', $parts[0]);
                    } else {
                        $mod_val = true;
                    }
                    $this->setMod($mod_name, $mod_val, $el);
                }
            }
        } else {
            $this->stack []= $el;
        }
    }

    protected $c_block = null;
    protected $last_bem_index = null;

    public function addBlock($name, $el, $is_transparent)
    {
        $this->c_block = $name;
        $this->stack []= array(
            'name' => $name,
            'is_transparent' => $is_transparent, // true if block added together with element
            'el' => $el,
            'type' => 'block',
            'mods' => array()
        );
        $this->last_bem_index = count($this->stack) - 1;
    }

    public function addElement($name, $el)
    {
        $this->stack []= array(
            'name' => $this->c_block.'__'.$name,
            'el' => $el,
            'type' => 'element',
            'mods' => array()
        );
        $this->last_bem_index = count($this->stack) - 1;
    }

    public function setMod($name, $value, $el)
    {
        $this->stack[ $this->last_bem_index ]['mods'][]= array($name, $value, $el);
    }

    protected static function getModSelector($mod, $base)
    {
        return $base.'_'.$mod[0].($mod[1] === true ? '' : '_'.$mod[1]);
    }

    public function getPath()
    {
        $res = array();
        foreach ($this->stack as $level) {
            // real less token
            if (!is_array($level)) {
                $res []= $level;
                continue;
            }
            $base = '.'.$level['name'];
            $first_mod = array_shift($level['mods']);
            $level_el = $level['el'];
            if ($first_mod) {
                $level_combinator = $level_el->combinator;
                if ($first_mod) {
                    $first_mod_el = $first_mod[2];
                    $first_mod_el->value = self::getModSelector($first_mod, $base);
                    $first_mod_el->combinator = $level_combinator;
                    $res []= $first_mod_el;
                }
                foreach ($level['mods'] as $mod) {
                    $mod_el = $mod[2];
                    $mod_el->value = self::getModSelector($mod, $base);
                    $mod_el->combinator = '';
                    $res []= $mod_el;
                }
            } else {
                if (!isset($level['is_transparent']) || !$level['is_transparent']) {
                    $level_el->value = $base;
                    $res []= $level_el;
                }
            }
        }
        return $res;
    }
}