<?php

namespace Floxim\Floxim\Router;

abstract class Base
{
    public function getContext()
    {

    }

    public function route($url = null, $context = null) 
    {
        return false;
    }
    
    public function getPath($url) 
    {
        return false;
    }

}