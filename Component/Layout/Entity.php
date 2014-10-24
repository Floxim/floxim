<?php

namespace Floxim\Floxim\Component\Layout;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity {
    public function getPath() {
        $path = explode(".", $this['keyword']);
        array_walk($path, function(&$item) {
            $item = fx::util()->underscoreToCamel($item, true);
        });
        return fx::path()->toAbs('/theme/'.join('/', $path).'/');
    }
    
    protected function beforeInsert() {
        parent::beforeInsert();
    }
    
    public function scaffold() {
        $path = $this->getPath();
        fx::files()->mkdir($path);
    }
    
    protected function afterDelete() {
        parent::afterDelete();
        $path = fx::path()->toAbs($this->getPath());
        fx::files()->rm($path);
    }
}