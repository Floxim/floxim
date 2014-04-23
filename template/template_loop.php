<?php
class fx_template_loop implements ArrayAccess {
    
    public $loop;
    
    public function __construct($items, $key = null, $alias = null) {
        if (is_null($key)){
            $key = 'key';
        }
        if (is_null($alias)) {
            $alias = 'item';
        }
        $this->loop = $this;
        $this->looped = $items;
        $this->total = count($items);
        $this->position = 0;
        $this->current_key = $key;
        $this->current_alias = $alias;
        $this->current = null;
        $this->_is_collection = $items instanceof fx_collection;
    }
    
    public function _move() {
        $this->position++;
        if ($this->current === null) {
            $this->current = $this->_is_collection ? $this->looped->first() : current($this->looped);
        } else {
            $this->current = $this->_is_collection ? $this->looped->next() : next($this->looped);
        }
        $this->key = $this->_is_collection ? $this->looped->key() : key($this->looped);
    }
    
    public function is_last() {
        return $this->position == $this->total;
    }
    
    public function is_first() {
        return $this->position == 1;
    }
    
    public function is_even() {
        return $this->position % 2 == 0;
    }
    
    public function is_odd() {
        return $this->position % 2 != 0;
    }
    
    public function offsetGet($offset) {
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
    public function offsetSet($offset, $value) {
        ;
    }
    public function offsetExists($offset) {
        return isset($this->$offset) || method_exists($this, $offset)  || 
                $offset == $this->current_key || $offset == $this->current_alias;
    }
    public function offsetUnset($offset) {
        ;
    }
}