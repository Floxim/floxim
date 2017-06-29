<?php

namespace Floxim\Floxim\Component\Module;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Finder 
{
    
    public function getEntityClassName($data = []) 
    {
        $parts = explode('.', $data['keyword']);
        list($vendor, $module) = $parts;
        $class = fx::util()->underscoreToCamel($vendor)
                ."\\"
                .fx::util()->underscoreToCamel($module)
                ."\\Module";
        if (class_exists($class)) {
            return $class;
        }
        return parent::getEntityClassName();
    }
}