<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\Template;
use Floxim\Floxim\System\Fx as fx;

class Front extends Base {

    public function route($url = null, $context = null) {

        $page = fx::data('page')->getByUrl(urldecode($url), $context['site_id']);

        if (!$page) {
            return null;
        }
        else if (
                $url != $page['url']
            ) {
            // oldest urlAlias
            // @TODO: check site_id here
            fx::http()->redirect($page['url'], 301);
            exit;
        }
        
        fx::env('page', $page);
        fx::http()->status('200');
        $layout_ib = $this->getLayoutInfoblock($page);
        $res = $layout_ib->render();
        return $res;
    }
    
    protected $_ib_cache = array();
    
    public function  getPageInfoblocks($page_id, $layout_id = null) {
        if (is_null($layout_id)) {
            $layout_id = fx::env('layout');
        }
        $cache_key = $page_id.'.'.$layout_id;
        if (isset($this->_ib_cache[$cache_key])) {
            return $this->_ib_cache[$cache_key];
        }
        
        $c_page = $page_id === fx::env('page_id') ? fx::env('page') : fx::data('page', $page_id);

        $infoblocks = $c_page
                        ->getPageInfoblocks()
                        ->find(function($ib) {
                            return !$ib->isLayout();
                        });
        $areas = fx::collection();
        $visual = fx::data('infoblock_visual')->
                where('infoblock_id', $infoblocks->getValues('id'))->
                where('layout_id', $layout_id)->
                all();
        
        foreach ($infoblocks as $ib) {
            if (!$ib->isAvailableForUser()) {
                continue;
            }
            
            if (($c_visual = $visual->findOne('infoblock_id', $ib['id']))) {
                $ib->setVisual($c_visual);
            } elseif ($ib->getVisual()->get('is_stub')) {
                $suitable = new Template\Suitable();
                $suitable->suit($infoblocks, $layout_id);
            }

            if ( ($visual_area = $ib->getPropInherited('visual.area')) ) {
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
    
    public function getLayoutInfoblock($page) {
        $layout_ib = $page->getLayoutInfoblock();
        if ($layout_ib->getVisual()->get('is_stub')) {
            $suitable = new Template\Suitable();
            $infoblocks = $page->getPageInfoblocks();
            
            // delete all parent layouts from collection
            $infoblocks->findRemove(function ($ib) use ($layout_ib) {
                return $ib->isLayout() && $ib['id'] !== $layout_ib['id'];
            });
            
            $suitable->suit($infoblocks, fx::env('layout_id'));
            return $infoblocks->findOne(function ($ib) {
               return $ib->isLayout(); 
            });
        }
        return $layout_ib;
    }
}