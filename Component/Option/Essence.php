<?php

namespace Floxim\Floxim\Component\Option;

use Floxim\Floxim\System;

class Essence extends System\Essence {

    protected  function _before_save() {
        parent::_before_save();
        $this['value']=serialize($this['value']);
    }
}