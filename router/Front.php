<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\Template;

class Front extends Base {

    public function route($url = null, $context = null) {
        
        $page = fx::data('content_page')->get_by_url(urldecode($url), $context['site_id']);
        
        if (!$page) {
            return null;
        }
        fx::env('page', $page);
        fx::http()->status('200');
        $layout_ib = $this->get_layout_infoblock($page);
        $res = $layout_ib->render();
        return $res;
    }
    
    protected $_ib_cache = array();
    
    public function  get_page_infoblocks($page_id, $layout_id = null) {
        if (is_null($layout_id)) {
            $layout_id = fx::env('layout');
        }
        $cache_key = $page_id.'.'.$layout_id;
        if (isset($this->_ib_cache[$cache_key])) {
            return $this->_ib_cache[$cache_key];
        }
        
        $c_page = $page_id === fx::env('page_id') ? fx::env('page') : fx::data('content_page', $page_id);

        $infoblocks = $c_page
                        ->get_page_infoblocks()
                        ->find(function($ib) {
                            return !$ib->is_layout();
                        });
        $areas = fx::collection();
        $visual = fx::data('infoblock_visual')->
                where('infoblock_id', $infoblocks->get_values('id'))->
                where('layout_id', $layout_id)->
                all();
        
        foreach ($infoblocks as $ib) {
            if (!$ib->is_available_for_user()) {
                continue;
            }
            
            if (($c_visual = $visual->find_one('infoblock_id', $ib['id']))) {
                $ib->set_visual($c_visual);
            } elseif ($ib->get_visual()->get('is_stub')) {
                $suitable = new Template\Suitable();
                $suitable->suit($infoblocks, $layout_id);
            }

            if ( ($visual_area = $ib->get_prop_inherited('visual.area')) ) {
                $c_area = $visual_area;
            } else {
                $c_area = 'unknown';
            }
            if (!isset($areas[$c_area])) {
                $areas[$c_area] = fx::collection();
            }
            $areas[$c_area][]= $ib;
        }
        $this->_ib_cache[$cache_key] = $areas;
        return $areas;
    }
    
    public function get_layout_infoblock($page) {
        $layout_ib = $page->get_layout_infoblock();
        if ($layout_ib->get_visual()->get('is_stub')) {
            $suitable = new Template\Suitable();
            $infoblocks = $page->get_page_infoblocks();
            
            // delete all parent layouts from collection
            $infoblocks->find_remove(function ($ib) use ($layout_ib) {
                return $ib->is_layout() && $ib['id'] !== $layout_ib['id'];
            });
            
            $suitable->suit($infoblocks, fx::env('layout_id'));
            return $infoblocks->find_one(function ($ib) {
               return $ib->is_layout(); 
            });
        }
        return $layout_ib;
    }
}