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
    
    public function getDir()
    {
        static $dir = null;
        
        if (is_null($dir)) {
            $pl = $this->getPayload('module_data');
            $dir = fx::path('@module/'.$pl['vendor'].'/'.$pl['name']);
        }
        return $dir;
    }
    
    public function getControllerKeywords()
    {
        static $controllers = null;
        if (is_null($controllers)) {
            $subs = glob($this->getDir().'/*');
            
            $controllers = array();
            
            if (!is_array($subs)) {
                $subs = array();
            }
            $pl = $this->getPayload('module_data');
            $kw = $pl['keyword'];
            foreach ($subs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                $c_path = $dir.'/Controller.php';
                if (file_exists($c_path)) {
                    $dir_name = fx::path()->fileName($dir);
                    $c_keyword = fx::util()->camelToUnderscore($dir_name);
                    $controllers []= $kw .'.'.$c_keyword;
                }

            }
        }
        return $controllers;
    }
}