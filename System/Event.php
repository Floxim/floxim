<?php

namespace Floxim\Floxim\System;

class Event implements \ArrayAccess
{
    public $name = 'event';
    
    protected $is_stopped = false;
    
    protected $params = array();
    
    protected $result = null;

    public function __construct($name, $params)
    {
        $this->name = $name;
        $this->params = $params;
    }
    
    public function offsetGet($offset) 
    {
        return $this->params[$offset];
    }
    
    public function offsetExists($offset) 
    {
        return isset($this->params[$offset]);
    }
    
    public function offsetSet($offset, $value) 
    {
        $this->params[$offset] = $value;
    }
    
    public function offsetUnset($offset) 
    {
        unset($this->params[$offset]);
    }
    
    public function get($offset) 
    {
        return $this->offsetGet($offset);
    }
    
    public function stop($result = null)
    {
        $this->is_stopped = true;
        $this->result = $result;
    }
    
    public function isStopped() 
    {
        return $this->is_stopped;
    }
    
    public function getResult()
    {
        return $this->result;
    }
    
    public function pushResult($value) {
        if (!is_array($this->result)) {
            $this->result = array();
        }
        $this->result []= $value;
    }
}