<?php

namespace Floxim\Floxim\Component\Option;

use Floxim\Floxim\System;

class Entity extends System\Entity
{

    protected function beforeSave()
    {
        parent::beforeSave();
        $this['value'] = serialize($this['value']);
    }
}