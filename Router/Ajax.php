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
        
        $this->registerUrlFromPost();
        
        // import layout template to recreate real env
        fx::router('front')->importLayoutTemplate();
        
        $page_infoblocks = fx::router('front')->getPageInfoblocks(); //$page_id, $layout_id);
        fx::page()->setInfoblocks($page_infoblocks);
        
        $controller_params = fx::input()->fetchGetPost('_ajax_controller_params');
        
        $c_infoblock_id = fx::input()->fetchGetPost('_ajax_infoblock_id');
        
        $container_props = fx::input()->fetchPost('_ajax_container_props');
        
        if ($c_infoblock_id) {
            $infoblock = fx::data('infoblock', $c_infoblock_id);
            if ($infoblock) {
                if ($controller_params) {
                    $infoblock->override(array('params' => $controller_params));
                }
                
                if ($container_props) {
                    $infoblock->bindLayoutContainerProps( $container_props );
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
            fx::log('ret nul');
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
        if (!fx::env('ajax')) {
            $res = fx::page()->postProcess($res);
        }
        return $res ? $res : true;
    }
    
    public function handleRedraw()
    {
        $redraw = fx::input('post', '_ajax_redraw');
        if (!$redraw) {
            return;
        }
        $res = [];
        foreach ($redraw as $ib_id => $params) {
            $ib = fx::data('infoblock', $ib_id);
            if (!$ib) {
                continue;
            }
            if (isset($params['controller_params'])) {
                $ib->override(array('params' => $params['controller_params']));
            }

            if (isset($params['container'])) {
                $ib->bindLayoutContainerProps( $params['container'] );
            }

            $res[$ib_id] = $ib->render();
        }
        return $res;
    }
}