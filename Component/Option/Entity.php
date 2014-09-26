<?php

namespace Floxim\Floxim\Component\Option;

use Floxim\Floxim\System;

class Entity extends System\Entity {

    protected  function _before_save() {
        parent::_before_save();
        $this['value']=serialize($this['value']);
    }
}