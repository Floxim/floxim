<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Error extends Front
{
    public function route($url = null, $context = null)
    {
        $site_id = isset($context['site_id']) ? $context['site_id'] : fx::env('site_id');
        $url_path = preg_replace("~\?.*$~", '', $url);
        
        $type_map = [
            [
                ['jpg', 'jpeg', 'gif', 'png', 'ico'],
                'image/gif',
                base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
            ],
            [
                ['css'],
                'text/css',
                ''
            ],
            [
                ['js'],
                'text/javascript',
                ''
            ]
        ];
        $extension = fx::path()->fileExtension($url_path);
        foreach ($type_map as $type) {
            if (in_array($extension, $type[0])) {
                header("Content-type: ".$type[1]);
                return $type[2];
            }
        }
        
        $error_page = $this->getErrorPage($site_id);
        if ($error_page) {
            $res = fx::router('front')->route($error_page, $context);
        } else {
            $res = 'Page not found';
        }
        
        fx::http()->status('404');
        return $res;
    }
    
    public function getErrorPage($site_id = null) 
    {
        if (is_null($site_id)) {
            $site_id = fx::env('site_id');
        }
        if (!$site_id) {
            return null;
        }
        $error_page = fx::data(
            'floxim.main.page',
            fx::data('site', $site_id)->get('error_page_id')
        );
        return $error_page;
    }
    
    public function getPath($url = null, $site_id = null) 
    {
        $error_page = $this->getErrorPage($site_id);
        if ($error_page) {
            return parent::getPath($error_page->get('url'), $site_id);
        }
    }
}