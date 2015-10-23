<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Ajax extends Base
{
    public function route($url = null, $context = null)
    {
        $action_info = null;
        if (!preg_match("~^/\~ajax/([a-z0-9_\.\:\@-]+)?~", $url, $action_info)) {
            return null;
        }
        
        fx::env('ajax', true);

        $c_url = fx::input()->fetchGetPost('_ajax_base_url');
        
        if ($c_url) {
            $_SERVER['REQUEST_URI'] = $c_url;
            
            $base_path = fx::router()->getPath( fx::path()->removeBase($c_url) );
            if ($base_path) {
                $page = $base_path->last();
                fx::env('page', $page);
            } else {
                fx::env('page', fx::router('error')->getErrorPage());
            }

            $c_url = parse_url($c_url);
            if (isset($c_url['query'])) {
                parse_str($c_url['query'], $_GET);
            }
        }
        
        // import layout template to recreate real env
        fx::router('front')->importLayoutTemplate();
        
        $controller_params = fx::input()->fetchGetPost('_ajax_controller_params');
        
        $c_infoblock_id = fx::input()->fetchGetPost('_ajax_infoblock_id');
        if ($c_infoblock_id) {
            $infoblock = fx::data('infoblock', $c_infoblock_id);
            if ($infoblock) {
                if ($controller_params) {
                    $infoblock->override(array('params' => $controller_params));
                }
                $res = $infoblock->render();
                return $res;
            }
        }
        
        
        $template = null;
        if ($action_info && !empty($action_info[1])) {
            $action = $action_info[1];
            $action = explode("@", $action);
            if (count($action) == 2) {
                $template = $action[1];
                $action = $action[0];
            } else {
                $action = $action[0];
            }
        } elseif (isset($_POST['_ajax_controller'])) {
            $action = $_POST['_ajax_controller'];
        } else {
            return null;
        }
        
        $action = explode(":", $action);
        $controller_name = $action[0];
        if (preg_match("~^widget_~", $controller_name) && !isset($action[1])) {
            $action[1] = 'show';
        }
        $action_name = $action[1];
        
        $controller = fx::controller($controller_name . ':' . $action_name, $controller_params);
        
        if (!$template) {
            $template = fx::input()->fetchGetPost('_ajax_template');
        }
        
        if (!$template) {
            $tpls = $controller->getAvailableTemplates();
            if (count($tpls) > 0) {
                $template = $tpls[0]['full_id'];
            }
        }
        
        
        $res = $controller->process();
        
        if ($template) {
            $tpl = fx::template($template);
            if ($tpl) {
                $res = $tpl->render($res);
            }
        }
        return $res ? $res : true;
    }
}