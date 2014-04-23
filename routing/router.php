<?php
abstract class fx_router {
	public function get_context() {
		
	}
    
    abstract function route($url = null, $context = null);


}