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
        $input = fx::input()->makeInput();
        

        $entity = fx::input()->fetchPost('entity');
        $action = fx::input()->fetchPost('action');
        
        if (!$entity || !$action) {
            return new Controller\Admin();
        }
        
        fx::env('ajax', true);
        
        $posting = fx::input()->fetchPost('posting');
        if (!preg_match("~^module_~", $entity) || fx::input()->fetchPost('fx_admin')) {
            $entity = 'admin_'.$entity;
        }
        if ($posting && $posting !== 'false') {
            $action .= "_save";
        }

        $path = explode('_',$entity,2);
        if ($path[0] == 'admin') {
            $classname = 'Floxim\\Floxim\\Admin\\Controller\\'.fx::util()->underscoreToCamel($path[1]);
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