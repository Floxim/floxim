<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\Admin\Controller;
use Floxim\Floxim\System\Fx as fx;

class Admin extends Base
{

    public function route($url = null, $context = null)
    {
        $adm_path = '/'.fx::config('path.admin_dir_name').'/';
        if (trim($url, '/') === trim($adm_path, '/') && $url !== $adm_path) {
            fx::http()->redirect( fx::config('paht.admin'), 301);
        }
        if ($url !== $adm_path) {
            return null;
        }
        fx::env('css_bundle', 'admin');
        $input = fx::input()->makeInput();


        $entity = fx::input()->fetchPost('entity');
        $action = fx::input()->fetchPost('action');

        if (!$entity || !$action) {
            fx::page()->setBaseUrl(FX_BASE_URL . '/'. trim($adm_path, '/') );
            return new Controller\Admin();
        }
        
        $this->registerUrlFromPost();
        
        fx::env('ajax', true);

        $posting = fx::input()->fetchPost('posting');
        if (!preg_match("~^module_~", $entity) || fx::input()->fetchPost('fx_admin')) {
            $entity = 'admin_' . $entity;
        }
        if ($posting && $posting !== 'false') {
            $action .= "_save";
        }

        $path = explode('_', $entity, 2);
        if ($path[0] == 'admin') {
            $classname = 'Floxim\\Floxim\\Admin\\Controller\\' . fx::util()->underscoreToCamel($path[1]);
        } else {
            // todo: psr0 what?
        }

        try {
            $controller = new $classname($input, $action);
        } catch (\Exception $e) {
            die("Error! Entity: " . htmlspecialchars($entity));
        }
        //header("Content-type: application/json; charset=utf-8");
        return $controller;
    }
}