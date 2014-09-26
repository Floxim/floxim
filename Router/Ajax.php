<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Ajax extends Base {
    public function route($url = null, $context = null) {
        $action_info = null;
        if (!preg_match("~^/\~ajax/([a-z0-9_\.\:-]+)?~", $url, $action_info)) {
            return null;
        }
        
        $c_url = fx::input()->fetchGetPost('_ajax_base_url');
        if ($c_url) {
            $_SERVER['REQUEST_URI'] = $c_url;
            
            $page = fx::data('page')->getByUrl($c_url, $context['site_id']);
            fx::env('page', $page);
            
            $c_url = parse_url($c_url);
            if (isset($c_url['query'])) {
                parse_str($c_url['query'], $_GET);
            }
        }
        $c_infoblock_id = fx::input()->fetchGetPost('_ajax_infoblock_id');
        if ($c_infoblock_id) {
            $infoblock = fx::data('infoblock', $c_infoblock_id);
            if ($infoblock) {
                $res = $infoblock->render();
                return $res;
            }
        }
        
        $template = null;
        if ($action_info && !empty($action_info[1])) {
            $action = $action_info[1];
            $action = explode(":", $action);
            if (count($action) == 2) {
                $template = $action[1];
                $action = $action[0];
            } else {
                $action = $action[0];
            }
        } elseif (isset($_POST['action'])) {
            $action = $_POST['action'];
        } else {
            return null;
        }
        fx::env('ajax', true);
        $action = explode(".", $action);
        $controller_name = $action[0];
        if (preg_match("~^widget_~", $controller_name) && !isset($action[1])) {
            $action[1] = 'show';
        }
        $action_name = $action[1];
        // todo: psr0 need fix
        if (!preg_match("~^(component_|widget_)~", $controller_name)) {
            //$controller_name = 'component_'.$controller_name;
        }
        
        $action = $controller_name.':'.$action_name;
        
        $controller = fx::controller($action);
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