<?php

namespace Floxim\Floxim\Component\Datatype;

use Floxim\Floxim\System;

class Finder extends System\Finder
{
    public function getMultiLangFields()
    {
        return array(
            'name'
        );
    }
}