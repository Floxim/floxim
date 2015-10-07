<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Thumb extends Base
{
    public function route($url = null, $context = null)
    {
        $thumbs_path = fx::path()->http('@thumbs');
        if (substr($url, 0, strlen($thumbs_path)) !== $thumbs_path) {
            return null;
        }
        $dir = substr($url, strlen($thumbs_path));
        preg_match("~/([^/]+)(/.+$)~", $dir, $parts);
        $config = $parts[1];
        $source_path = $parts[2];
        $source_abs = fx::path($source_path);
        if (!file_exists($source_abs)) {
            return null;
        }
        $target_dir = dirname(fx::path($url));
        if (!file_exists($target_dir) || !is_dir($target_dir)) {
            return null;
        }
        $config = $config.'.async-false.output-true';
        
        $config = \Floxim\Floxim\System\Thumb::readConfigFromPathString($config);
        
        fx::image($source_abs, $config);
        fx::log('thumb done');
        fx::complete();
        die();
    }
}