<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\Admin\Controller;
use Floxim\Floxim\System\Fx as fx;

class Admin extends Base {
    
    public function route($url = null, $context = null) {
        $regexp = "/((floxim\/)+|(vendor\/Floxim\/Floxim\/)+|(vendor\/Floxim\/Floxim\/index.php)+)$/";
        if (!preg_match($regexp, $url)) {
            return null;
        }
        $input = fx::input()->make_input();
        

        $entity = fx::input()->fetch_post('entity');
        $action = fx::input()->fetch_post('action');
        
        if (!$entity || !$action) {
            return new Controller\Admin();
        }
        
        fx::env('ajax', true);
        
        $posting = fx::input()->fetch_post('posting');
        if (!preg_match("~^module_~", $entity) || fx::input()->fetch_post('fx_admin')) {
            $entity = 'admin_'.$entity;
        }
        if ($posting && $posting !== 'false') {
            $action .= "_save";
        }

        $path = explode('_',$entity,2);
        if ($path[0] == 'admin') {
            $classname = 'Floxim\\Floxim\\Admin\\Controller\\'.fx::util()->underscoreToCamal($path[1]);
        } else {
            // todo: psr0 what?
        }
       
        try {
            $controller = new $classname($input, $action);
        } catch (\Exception $e) {
            die("Error! Entity: " . htmlspecialchars($entity));
        }
        return $controller;
    }
}