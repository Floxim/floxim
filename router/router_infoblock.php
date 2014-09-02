<?php
class fx_router_infoblock extends fx_router {
    public function route($url = null, $context = null) {
        if (!preg_match("~^/\~ib/(\d+|fake(?:\-\d+)?)@(\d+)$~", $url, $ib_info)) {
            return null;
        }
        $c_url = fx::input('post', 'c_url');
        if ($c_url) {
            $_SERVER['REQUEST_URI'] = $c_url;
            $c_url = parse_url($c_url);
            if (isset($c_url['query'])) {
                parse_str($c_url['query'], $_GET);
            }
        }
        $ib_id = $ib_info[1];
        $page_id = $ib_info[2];
        fx::env('page', $page_id);
        fx::env('ajax', true);
        
        $page_infoblocks = fx::router('front')->get_page_infoblocks(
            $page_id, 
            fx::env('layout')
        );
        fx::page()->set_infoblocks($page_infoblocks);
        
        
        // front end can try to reload the layout which is out of date
        // when updating from "layout settings" panel
        $infoblock = fx::data('infoblock', $ib_id);
        if ((!$infoblock && isset($_POST['infoblock_is_layout'])) || $infoblock->is_layout()) {
            $infoblock = fx::data('content_page', $page_id)->get_layout_infoblock();
        }
        
        fx::http()->status('200');
        $infoblock_overs = null;
        if (fx::is_admin() && isset($_POST['override_infoblock'])) {
            $infoblock_overs = fx::input('post', 'override_infoblock');
            if (is_string($infoblock_overs)){
                parse_str($infoblock_overs, $infoblock_overs);
                $infoblock_overs = fx::input()->prepare_superglobal($infoblock_overs);
            }
            $infoblock->override($infoblock_overs);
        }
        $infoblock->override_param('ajax_mode', true);
        return $infoblock->render();
    }
}