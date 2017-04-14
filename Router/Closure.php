<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Closure extends Base {
    public static function create($closure, $type = 'router') {
        return new Closure($closure, $type);
    }
    
    protected $route_type = null;
    
    protected $route_closure = null;
    public function __construct($closure, $type) {
        $this->route_closure = $closure;
        $this->route_type = $type;
    }
    
    public function route($url = null, $context = null) {
        if ($this->route_type === 'router') {
            return call_user_func_array($this->route_closure, func_get_args());
        }
        if ($this->route_type === 'path') {
            $path = call_user_func_array($this->route_closure, func_get_args());
            if ($path) {
                return fx::router('front')->route($path);
            }
        }
    }
    
    public function getPath()
    {
        if ($this->route_type === 'path') {
            return call_user_func_array($this->route_closure, func_get_args());
        }
    }
}