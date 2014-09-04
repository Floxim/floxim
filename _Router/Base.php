<?php

namespace Floxim\Floxim\Router;

abstract class Base {
	public function get_context() {
		
	}
    
    abstract function route($url = null, $context = null);

}