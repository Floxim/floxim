<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\Template;
use Floxim\Floxim\System\Fx as fx;

class Front extends Base
{

    public function route($url = null, $context = null)
    {
        $path = fx::router()->getPath($url, $context['site_id']);
        if (!$path) {
            return;
        }
        
        $page = $path->last();
        
        if (!$page) {
            return null;
        } else {
            if (
                $url && !$page->hasVirtualPath() && $url != $page['url']
            ) {
                fx::http()->redirect($page['url'], 301);
                exit;
            }
        }
        fx::log('front');
        fx::env('page', $page);
        fx::http()->status('200');
        $layout_ib = $this->getLayoutInfoblock($page);
        $res = $layout_ib->render();
        return $res;
    }
    
    public function getPath($url, $site_id) {
        $page = fx::data('page')->getByUrl(urldecode($url), $site_id);
        if (!$page) {
            return false;
        }
        return $page->getPath();
    }

    protected $_ib_cache = array();

    public function  getPageInfoblocks($page_id, $layout_id = null)
    {
        if (is_null($layout_id)) {
            $layout_id = fx::env('layout');
        }
        $cache_key = $page_id . '.' . $layout_id;
        if (isset($this->_ib_cache[$cache_key])) {
            return $this->_ib_cache[$cache_key];
        }

        $c_page = $page_id === fx::env('page_id') ? fx::env('page') : fx::data('page', $page_id);

        $infoblocks = fx::data('infoblock')
            ->getForPage($c_page)
            ->find(function ($ib) {
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
        $this->_ib_cache[$cache_key] = $areas;
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

    public function getLayoutInfoblock($page)
    {
        $path = $page->getPath()->copy()->reverse();
        foreach ($path as $c_page){
            if (method_exists($c_page, 'getLayoutInfoblock')) {
                $layout_ib = $c_page->getLayoutInfoblock();
                break;
            }
        }
        if ($layout_ib->getVisual()->get('is_stub')) {
            $suitable = new Template\Suitable();
            //$infoblocks = $page->getPageInfoblocks();
            $infoblocks = fx::data('infoblock')->getForPage($page);

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