<?php

namespace Floxim\Floxim\Router;

use \Floxim\Floxim\System\Fx as fx;

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
    
    public function registerUrlFromPost()
    {
        $c_url = $this->getUrlFromPost();
        fx::env()->forceUrl($c_url);
    }
    
    public function getUrlFromPost()
    {
        return fx::input()->fetchGetPost('_ajax_base_url');
    }
}