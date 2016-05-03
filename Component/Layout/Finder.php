<?php

namespace Floxim\Floxim\Component\Layout;

use Floxim\Floxim\System;

class Finder extends System\Finder
{
    protected $json_encode = array(
        'less_params'
    );
    
    public static $isStaticCacheUsed = true;
}