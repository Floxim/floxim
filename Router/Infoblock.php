<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Infoblock extends Base
{
    public function route($url = null, $context = null)
    {
        if (!preg_match("~^/\~ib/(\d+|fake(?:\-\d+)?)@(\d+)$~", $url, $ib_info)) {
            return null;
        }
        $c_url = fx::input('post', '_ajax_base_url');
        if ($c_url) {
            $_SERVER['REQUEST_URI'] = $c_url;
            $path = fx::router()->getPath($c_url);
            if ($path) {
                fx::env('page', $path->last());
            } else {
                fx::env('page', fx::router('error')->getErrorPage());
            }
            $c_url = parse_url($c_url);
            if (isset($c_url['query'])) {
                parse_str($c_url['query'], $_GET);
            }
            
        }
        $ib_id = $ib_info[1];
        $page_id = $ib_info[2];
        //fx::env('page', $page_id);
        fx::env('ajax', true);

        $page_infoblocks = fx::router('front')->getPageInfoblocks(
            $page_id,
            fx::env('layout')
        );
        fx::page()->setInfoblocks($page_infoblocks);


        // front end can try to reload the layout which is out of date
        // when updating from "layout settings" panel
        $infoblock = fx::data('infoblock', $ib_id);
        if ((!$infoblock && isset($_POST['infoblock_is_layout'])) || $infoblock->isLayout()) {
            $infoblock = fx::router('front')->getLayoutInfoblock(fx::env('page'));
        }

        fx::http()->status('200');
        $infoblock_overs = null;
        if (fx::isAdmin() && isset($_POST['override_infoblock'])) {
            $infoblock_overs = fx::input('post', 'override_infoblock');
            if (is_string($infoblock_overs)) {
                parse_str($infoblock_overs, $infoblock_overs);
                $infoblock_overs = fx::input()->prepareSuperglobal($infoblock_overs);
            }
            $infoblock->override($infoblock_overs);
        }
        $infoblock->overrideParam('ajax_mode', true);
        $res = $infoblock->render();
        return $res;
    }
}