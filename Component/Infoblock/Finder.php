<?php

namespace Floxim\Floxim\Component\Infoblock;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Finder
{

    public function relations()
    {
        return array(
            'visuals' => array(
                self::HAS_MANY,
                'infoblock_visual',
                'infoblock_id'
            ),
            'page' => array(
                self::BELONGS_TO,
                'floxim.main.content',
                'page_id'
            ),
            'scope_entity' => array(
                self::BELONGS_TO,
                'scope',
                'scope_id'
            ),
            'site' => array(
                self::BELONGS_TO,
                'site',
                'site_id'
            )
        );
    }

    public $json_encode = array('params', 'scope', 'user_scope');

    public function isLayout()
    {
        return $this->where('controller', 'layout')->where('action', 'show');
    }

    public function getById($id)
    {
        if (is_numeric($id)) {
            return parent::getById($id);
        }
        if (preg_match("~fake~", $id)) {
            return $this->create(array('is_fake' => true));
        }
    }
    
    public function getForPath($path)
    {
        $current_page = $path->last();
        $this->where('site_id', $current_page['site_id']);
        $this->whereOr(
            array('scope_type', array('all_pages', 'custom')),
            array(
                array(
                    array('scope_type', 'one_page'),
                    array('page_id', $current_page['id']),
                ),
                null,
                'AND'
            ),
            array(
                array(
                    array('scope_type', 'infoblock_pages'),
                    array('scope_infoblock_id', $current_page['infoblock_id'])
                ),
                null,
                'AND'
            )
        );
        $this->with('scope_entity');
        $blocks = $this->all();
        $res = fx::collection();
        foreach ($blocks as $block) {
            if ($block['scope_type'] === 'custom' && !$block['scope_entity']->checkPath($path)) {
                continue;
            }
            $res[]= $block;
        }
        return $res;
    }

    public function getForPage($page_id = null)
    {
        
        $page = $page_id instanceof System\Entity ? $page_id : fx::data('floxim.main.page', $page_id);
        if(method_exists($page, 'getPath')) {
            $path = $page->getPath();
        } else {
            $path = fx::collection( array($page) );
        }
        return $this->getForPath($path);
    }
    
    /**
     * Get infoblocks where content can be placed and displayed
     * 
     * @param type $content
     */
    public function getForContent($content)
    {
        if (!$content['type']) {
            return fx::collection();
        }
        $this->whereContent($content['type']);
        if (!$content['parent_id']) {
            $site_id = $content['site_id'] ? $content['site_id'] : fx::env('site_id');
            return $this->where('site_id', $site_id)->all();
        }
        return $this->getForPage($content['parent']);
    }

    /**
     * Sort collection of infoblocks by scope "strength":
     * 1. "this page only" - the best
     * 2. "children of type..."
     * 3. "page and children of type"
     * 4. "children"
     * 5. "page and children"
     * If blocks have same expression type, the strongest is one bound to the deeper page
     * If mount page is the same, the newer is stronger
     *
     * @param fx_collection $infoblocks
     */
    public function sortInfoblocks($infoblocks)
    {
        $infoblocks->sort(function ($a, $b) {
            $a_scope = $a->getScopeWeight();
            $b_scope = $b->getScopeWeight();
            if ($a_scope > $b_scope) {
                return -1;
            }
            if ($a_scope < $b_scope) {
                return 1;
            }
            $a_page = fx::content('page', $a['page_id']);
            $b_page = fx::content('page', $b['page_id']);
            
            $a_level = $a_page ? count($a_page->getParentIds()) : 0;
            $b_level = $b_page ? count($b_page->getParentIds()) : 0;
            
            if ($a_level > $b_level) {
                return -1;
            }
            if ($a_level < $b_level) {
                return 1;
            }
            return $a['id'] < $b['id'] ? 1 : -1;
        });
        return $infoblocks;
    }
    
    public function whereContent($content_type = null, $with_child_types = false) 
    {
        if ($content_type) {
            $com = fx::component($content_type);
            
            $variants = $com->getChain()->getValues('keyword');
            
            if ($with_child_types) {
                $variants = array_merge($variants, $com->getAllVariants()->getValues('keyword'));
            }
            
            $this->where('controller', $variants);
        }
        
        $this->where('action', 'list_infoblock');
        return $this;
    }

    public function getContentInfoblocks($content_type = null)
    {
        $this->whereContent($content_type);
        return $this->all();
    }

    public static $isStaticCacheUsed = true;
    //public static $fullStaticCache = true;
    //public static $storeStaticCache = false;

    public static function prepareFullDataForCacheFinder($finder)
    {
        $finder->with('visuals');
    }
}