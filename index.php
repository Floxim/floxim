<?php

use Floxim\Floxim\System\Fx as fx;

// if request directs right to /floxim/index.php
// e.g. admin interface
// current dir /vendor/floxim/floxim/
require_once(dirname(__FILE__) . '/../../../boot.php');


register_shutdown_function(function () {
    if (!fx::env()->get('complete_ok')) {
        $ob_level = ob_get_level();
        $res = '';
        for ($i = 0; $i < $ob_level; $i++) {
            $res .= ob_get_clean();
        }
        if (fx::config('dev.on')) {
            echo fx::page()->postProcess($res);
        }
        fx::log('down', $res, $_SERVER, $_POST);
    }
});

$result = fx::router()->route();

if ($result) {
    $result = $result instanceof \Floxim\Floxim\System\Controller ? $result->process() : $result;
    if (fx::env('ajax')) {
        fx::page()->addAssetsAjax();
    }
    echo $result;
    fx::complete();
}