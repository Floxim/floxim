<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Error extends Front {
    public function route($url = null, $context = null) {
        $site = fx::data(
                'site', 
                isset($context['site_id']) ? $context['site_id'] : fx::env('site')
        );
        $error_page = fx::data('page', $site['error_page_id']);
        $ctr = fx::router('front')->route($error_page['url'], $context);
        fx::http()->status('404');
        return $ctr;
    }
}