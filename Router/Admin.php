<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\Admin\Controller;
use Floxim\Floxim\System\Fx as fx;

class Admin extends Base
{
    
    protected static function isAdminUrl($url)
    {
        if (!$url) {
            return;
        }
        $c_url = array_merge(
            [
                'scheme' => 'http',
                'query' => '',
                'path' => '/'
            ],
            parse_url($url)
        );
        return trim($c_url['path'],'/') === trim(self::getAdminUrl(), '/');
    }
    
    protected static function getAdminUrl()
    {
        return '/'.fx::config('path.admin_dir_name').'/';
    }

    public function route($url = null, $context = null)
    {
        if (!self::isAdminUrl($url)) {
            return;
        }
        
        $adm_path = self::getAdminUrl();
        
        if ($url !== $adm_path) {
            fx::http()->redirect( $adm_path, 301);
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
        
        if (!self::isAdminUrl($this->getUrlFromPost())) {
            $this->registerUrlFromPost();
        }
        
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