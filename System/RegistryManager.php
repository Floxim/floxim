<?php

namespace Floxim\Floxim\System;

//use Fx as fx;

class RegistryManager extends Collection {
    
    public function register($entity, $types) 
    {
        foreach ($types as $type) {
            $this[$type]->register($entity);
        }
    }
    
    public function offsetGet($offset) {
        return $this->get($offset);
    }
    
    public function get($type)
    {
        if (!isset($this->data[$type])) {
            $this->data[$type] = new Registry();
        }
        return $this->data[$type];
    }
    
    public function getEntity($type, $id)
    {
        return $this->get($type)->get($id);
    }
}