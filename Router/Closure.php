<?php

namespace Floxim\Floxim\Router;

class Closure extends Base {
    public function create($closure) {
        return new Closure($closure);
    }
    
    protected $route_closure = null;
    public function __construct($closure) {
        $this->route_closure = $closure;
    }
    
    public function route($url = null, $context = null) {
        return call_user_func_array($this->route_closure, func_get_args());
    }
}