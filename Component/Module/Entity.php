<?php

namespace Floxim\Floxim\Component\Module;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity {
    public function init()
    {
        
    }
    
    public function getType()
    {
        return 'module';
    }
    
    public function scaffold()
    {
        fx::console('module scaffold --name='.$this['keyword']);
    }
}