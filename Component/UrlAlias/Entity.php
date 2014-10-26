<?php

namespace Floxim\Floxim\Component\UrlAlias;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    /**
     * Reset "is_original" flag from this alias
     */
    public function resetOriginal()
    {
        $this->set('is_original', 0)->save();
    }

    /**
     * Check "is_current" flag for this alias
     */
    public function isOriginal()
    {
        return $this['is_original'];
    }
}