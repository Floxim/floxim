<?php
// if request directs right to /floxim/index.php 
// e.g. admin interface
require_once (dirname(__FILE__).'/../boot.php');

register_shutdown_function(function() {
    if (!fx::env()->get('complete_ok')) {
    	$ob_level = ob_get_level();
        $res = '';
        for ($i = 0; $i < $ob_level; $i++) {
            $res .= ob_get_clean();
        }
        if (fx::config('dev.on')) {
            echo fx::page()->post_process($res);
        }
        fx::log('down', $res, debug_backtrace(), $_SERVER, $_POST); 
    }
});

$result = fx::router()->route();

if ( $result ) {
    $result = $result instanceof fx_controller ? $result->process() : $result;
    if (fx::env('ajax')) {
        fx::page()->add_assets_ajax();
    }
    echo $result;
    fx::env()->set('complete_ok', true);
}