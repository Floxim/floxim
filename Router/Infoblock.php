<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Infoblock extends Base
{
    public function route($url = null, $context = null)
    {
        if (!fx::isAdmin()) {
            return null;
        }
        if (!preg_match("~^/\~ib/(\d+|fake(?:\-\d+)?)@(\d+)~", $url, $ib_info)) {
            return null;
        }
        
        $c_url = fx::input()->fetchGetPost('_ajax_base_url');
        fx::env()->forceUrl($c_url);
        
        $ib_id = $ib_info[1];
        $page_id = $ib_info[2];
        if (!fx::env('page') && $page_id) {
            $page = fx::data('floxim.main.content', $page_id);
            fx::env('page', $page);
        }
        fx::env('ajax', true);

        $page_infoblocks = fx::router('front')->getPageInfoblocks($page_id);
        fx::page()->setInfoblocks($page_infoblocks);
        
        // import layout template to recreate real env
        fx::router('front')->importLayoutTemplate();
        
        // front end can try to reload the layout which is out of date
        // when updating from "layout settings" panel
        $infoblock = fx::data('infoblock', $ib_id);
        if ((!$infoblock && isset($_POST['infoblock_is_layout'])) || $infoblock->isLayout()) {
            //$infoblock = $layout_infoblock;
            $infoblock = fx::router('front')->getLayoutInfoblock(fx::env('page'));
        }

        fx::http()->status('200');
        
        
        $infoblock_overs = fx::input('post', 'override_infoblock');
        if (fx::isAdmin() && $infoblock_overs) {
            $infoblock->override($infoblock_overs);
        }
        $infoblock->overrideParam('ajax_mode', true);
        if (isset($_POST['content_parent_props'])) {
            $container_params = json_decode($_POST['content_parent_props'], true);
            $infoblock->bindLayoutContainerProps( $container_params );
        }
        $res = $infoblock->render();
        return $res;
    }
}