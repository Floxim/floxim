<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\Template;
use Floxim\Floxim\System\Fx as fx;

class Front extends Base
{

    public function route($url = null, $context = null)
    {
        if ($url instanceof \Floxim\Main\Page\Entity) {
            $url = fx::collection(array($url));
        }
        if ($url instanceof \Floxim\Floxim\System\Collection) {
            $path = $url;
            $url = $path->last()->get('url');
        } else {
            $path = $this->getPath($url, $context['site_id']);
            if (!$path) {
                return;
            }
        }
        
        fx::env('path', $path);

        $page = $path->last();
        
        if (!$page) {
            return null;
        }
        
        /**
         * @todo: check if the URL is canonical and add <link rel="canonical" /> to header if required
         */
        
        //fx::env('page', $page);
        fx::http()->status('200');
        
        
        $layout_ib = fx::page()->getLayoutInfoblock($path);
        
        fx::trigger('before_layout_render', array(
            'layout_infoblock' => $layout_ib
        ));
        $res = $layout_ib->render();
        return $res;
    }
    
    public function normalizeUrl($url)
    {
        if (!is_string($url)) {
            fx::log($url, debug_backtrace());
        }
        //$url = preg_replace("~^/~", '', $url);
        $url = urldecode($url);
        return $url;
    }
    
    public function getPath($url, $site_id = null) {
        if (is_null($site_id)) {
            $site_id = fx::env('site_id');
        }
        $url = $this->normalizeUrl($url);
        $page = fx::data('floxim.main.page')->getByUrl($url, $site_id);
        if (!$page) {
            return false;
        }
        return $page->getPath();
    }

    protected $_ib_cache = array();

    public function  getPageInfoblocks() // $page_id = null, $theme_id = null)
    {
        
        $theme_id = fx::env('theme_id');
        $path = fx::env('path');
        /*
        if (is_null($page_id)) {
            $page_id = fx::env('page_id');
        }
        $cache_key = $page_id . '.' . $theme_id;
        if (isset($this->_ib_cache[$cache_key])) {
            return $this->_ib_cache[$cache_key];
        }
        
        $c_page = $page_id === fx::env('page_id') ? fx::env('page') : fx::data('floxim.main.page', $page_id);
        */
        $infoblocks = fx::data('infoblock')
            //->getForPage($c_page)
            ->getForPath($path)
            ->find(function ($ib) {
                return !$ib->isLayout();
            });
        
        $areas = fx::collection();
        $visual = fx::data('infoblock_visual')->
                    with('template_variant')->
                    with('wrapper_variant')->
                    where('infoblock_id', $infoblocks->getValues('id'))->
                    where('theme_id', $theme_id)->
                    all();
        
        foreach ($infoblocks as $ib) {
            if (!$ib->isAvailableForUser()) {
                continue;
            }

            if (($c_visual = $visual->findOne('infoblock_id', $ib['id']))) {
                $ib->setVisual($c_visual);
            } elseif ($ib->getVisual()->get('is_stub')) {
                fx::log('suitable?!!', $ib);
                throw new \Exception('No more suitable');
                /*
                $suitable = new Template\Suitable();
                $suitable->suit($infoblocks, $layout_id);
                 * 
                 */
            }

            if (($visual_area = $ib->getPropInherited('visual.area'))) {
                $c_area = $visual_area;
            } else {
                $c_area = 'unknown';
            }
            if (!isset($areas[$c_area])) {
                $areas[$c_area] = fx::collection();
            }
            $areas[$c_area][] = $ib;
        }
        //$this->_ib_cache[$cache_key] = $areas;
        return $areas;
    }
    
    public function importLayoutTemplate()
    {
        $c_page = fx::env('page');
        if (!$c_page) {
            return false;
        }
        $layout_infoblock = $this->getLayoutInfoblock($c_page);
        if (!$layout_infoblock) {
            return false;
        }
        return fx::template()->import(preg_replace('~\:.*$~', '', $layout_infoblock->getVisual()->get('template')));
    }

    public function getLayoutInfoblock($page = null)
    {
        if (is_null($page)) {
            $page = fx::env('page');
        }
        if (!is_object($page)) {
            fx::log(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        }
        //$path = $page->getPath()->copy()->reverse();
        $path = fx::env('path')->copy()->reverse();
        foreach ($path as $c_page){
            if (method_exists($c_page, 'getLayoutInfoblock')) {
                $layout_ib = $c_page->getLayoutInfoblock();
                break;
            }
        }

        if ($layout_ib->getVisual()->get('is_stub') || !$layout_ib->getTemplate()) {
            fx::log('suitable for layout?!!', $layout_ib);
            throw new \Exception('No more suitable');
            /*
            $suitable = new Template\Suitable();
            //$infoblocks = $page->getPageInfoblocks();
            $infoblocks = fx::data('infoblock')->getForPage($page);

            // delete all parent layouts from collection
            $infoblocks->findRemove(function ($ib) use ($layout_ib) {
                return $ib->isLayout() && $ib['id'] !== $layout_ib['id'];
            });
            
            $suitable->suit($infoblocks, fx::env('layout_id'), fx::env()->getLayoutStyleVariantId());
            return $infoblocks->findOne(function ($ib) {
                return $ib->isLayout();
            });
             * 
             */
        }
        return $layout_ib;
    }
}