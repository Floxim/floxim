<?php

namespace Floxim\Floxim\Component\Session;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    public function setCookie()
    {
        fx::data('session')->setCookie(
            $this['session_key'],
            $this['remember'] ? time() + fx::config('auth.remember_ttl') : 0
        );
    }
    
    public function afterSave()
    {
        $this->setCookie();
    }
    
    public function setParam($k, $v)
    {
        $params = (array) $this['params'];
        $params[$k] = $v;
        $this['params'] = $params;
    }
    
    public function getParam($k, $default = null)
    {
        $params = $this['params'];
        return $params && isset($params[$k]) ? $params[$k] : $default;
    }
}