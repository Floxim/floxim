<?php

namespace Floxim\Floxim\Component\Layout;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity {
    public function getPath() {
        return fx::config()->HTTP_LAYOUT_PATH.$this['keyword'].'/';
    }
    
    protected function beforeInsert() {
        parent::beforeInsert();
        $path = $this->getPath();
        fx::files()->mkdir($path);
    }
    
    protected function afterDelete() {
        parent::afterDelete();
        $path = fx::path()->toAbs($this->getPath());
        fx::files()->rm($path);
    }
}