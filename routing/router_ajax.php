<?php
class fx_router_ajax extends fx_router {
    public function route($url = null, $context = null) {
        $action_info = null;
        if (!preg_match("~^/\~ajax/([a-z0-9_\.\:-]+)?~", $url, $action_info)) {
            return null;
        }
        if (isset($_POST['_ajax_base_url'])) {
            $_SERVER['REQUEST_URI'] = $_POST['_ajax_base_url'];
            $c_url = parse_url($_POST['c_url']);
            if (isset($c_url['query'])) {
                parse_str($c_url['query'], $_GET);
            }
            $page = fx::data('content_page')->get_by_url($_POST['_ajax_base_url'], $context['site_id']);
            fx::env('page', $page);
        }
        if (isset($_POST['_ajax_infoblock_id']) && is_numeric($_POST['_ajax_infoblock_id'])) {
            $infoblock = fx::data('infoblock', $_POST['_ajax_infoblock_id']);
            if ($infoblock) {
                $res = $infoblock->render();
                //$res = fx::page()->post_process($res);
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
        
        if (!preg_match("~^(component_|widget_)~", $controller_name)) {
            $controller_name = 'component_'.$controller_name;
        }
        
        $action = $controller_name.'.'.$action_name;
        
        $controller = fx::controller($action);
        
        $res = $controller->process();
        if ($template) {
            $tpl = fx::template($template);
            if ($tpl) {
                $res = $tpl->render($res);
                //$res = fx::page()->post_process($res);
            }
        }
        return $res ? $res : true;
    }
}