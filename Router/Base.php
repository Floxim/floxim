<?php

namespace Floxim\Floxim\Router;

abstract class Base {
	public function getContext() {
		
	}
    
    abstract function route($url = null, $context = null);

}