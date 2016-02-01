<?php

namespace Floxim\Floxim\System;

//use Fx as fx;

class Registry extends Collection {
    
    public function register($entity, $id = null)
    {
        $this[is_null($id) ? $entity['id'] : $id] = $entity;
    }
}