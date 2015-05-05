<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Loop implements \ArrayAccess
{

    public $loop;

    public function __construct($items, $key = null, $alias = null)
    {
        if (is_null($key)) {
            $key = 'key';
        }
        if (is_null($alias)) {
            $alias = 'item';
        }
        $this->_is_collection = $items instanceof System\Collection;

        $this->loop = $this;
        $this->looped = $items;
        $this->total = count($items);
        $this->position = 0;
        $this->current_key = $key;
        $this->current_alias = $alias;
        $this->current = null;
    }

    public function move()
    {
        $this->position++;
        if ($this->current === null) {
            $this->current = $this->_is_collection ? $this->looped->first() : current($this->looped);
        } else {
            $this->current = $this->_is_collection ? $this->looped->next() : next($this->looped);
        }
        if ($this->_is_collection){
            if ($this->current instanceof System\Collection && $this->current->group_key) {
                $this->key = $this->current->group_key;
            } else {
                $this->key = $this->looped->key();
            }
        } else {
            $this->key = key($this->looped);
        }
    }

    public function isLast()
    {
        return $this->position == $this->total;
    }

    public function isFirst()
    {
        return $this->position == 1;
    }

    public function isEven()
    {
        return $this->position % 2 == 0;
    }

    public function isOdd()
    {
        return $this->position % 2 != 0;
    }
    
    public function getAvailableOffsetKeys()
    {
        return array(
            $this->current_key => true,
            'position' => true,
            $this->current_alias => true
        );
    }

    public function offsetGet($offset)
    {
        if (isset($this->$offset)) {
            return $this->$offset;
        }
        if (method_exists($this, $offset)) {
            return $this->$offset();
        }
        if ($offset == $this->current_key) {
            return $this->key;
        }
        if ($offset == $this->current_alias) {
            return $this->current;
        }
    }

    public function offsetSet($offset, $value)
    {
        ;
    }

    public function offsetExists($offset)
    {
        return isset($this->$offset) || method_exists($this, $offset) ||
        $offset == $this->current_key || $offset == $this->current_alias;
    }

    public function offsetUnset($offset)
    {
        ;
    }
}