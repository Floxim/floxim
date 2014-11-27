<?php

namespace Floxim\Floxim\Template;

class ExpressionNode
{
    public $type;

    public $context_offset = null;
    public $is_separator = false;
    public $starter = null;
    public $is_escaped = null;

    public function __construct($type = ExpressionParser::T_CODE)
    {
        $this->type = $type;
    }

    public function dump()
    {
        $types = array(
            1 => 'T_CODE',
            2 => 'T_VAR',
            3 => 'T_ARR',
            0 => 'T_ROOT'
        );
        $res = array(
            'type' => $types[$this->type]
        );
        foreach (array('data', 'name') as $prop) {
            if (isset($this->$prop)) {
                $res[$prop] = $this->$prop;
            }
        }
        if ($this->last_child) {
            $res['children'] = array();
            foreach ($this->children as $t) {
                $res['children'] [] = $t->dump();
            }
        }
        return $res;
    }

    public function contextLevelUp($count = 1)
    {
        if (is_null($this->context_offset)) {
            $this->context_offset = 0;
        }
        $this->context_offset += $count;
    }

    public function getContextLevel()
    {
        return $this->context_offset;
    }

    public $last_child = null;

    public function addChild($n)
    {
        if (!$this->last_child) {
            $this->children = array();
        }
        $this->children [] = $n;
        $this->last_child = $n;
    }

    public function popChild()
    {
        if (!$this->last_child) {
            return null;
        }
        $child = array_pop($this->children);
        if (count($this->children) == 0) {
            $this->last_child = null;
        } else {
            $this->last_child = end($this->children);
        }
        return $child;
    }

    public function appendNameChunk($ch)
    {
        $last_chunk = end($this->name);
        if (is_string($last_chunk) && is_string($ch)) {
            $this->name[count($this->name) - 1] .= $ch;
        } else {
            $this->name[] = $ch;
        }
        //$this->curr_node->name []= $ch;
    }
}