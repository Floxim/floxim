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
            )
        );
    }

    public function __construct()
    {
        parent::__construct();
        // todo: psr0 need verify
        $this->classname = 'fx_infoblock';
        $this->json_encode = array('params', 'scope');
    }

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
            return $this->create(array('id' => $id));
        }
    }

    public function getForPage($page_id)
    {
        $page = $page_id instanceof System\Entity ? $page_id : fx::data('page', $page_id);
        if (!$page) {
            return;
        }
        $ids = $page->getParentIds();
        $ids [] = $page['id'];
        $ids [] = 0; // root
        $infoblocks = $this->where('page_id', $ids)->where('site_id', $page['site_id'])->all();
        foreach ($infoblocks as $ib) {
            if (!$ib->isAvailableOnPage($page)) {
                $infoblocks->remove($ib);
            }
        }

        return $infoblocks;
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
            $a_level = count(fx::content('page', $a['page_id'])->getParentIds());
            $b_level = count(fx::content('page', $b['page_id'])->getParentIds());
            if ($a_level > $b_level) {
                return -1;
            }
            if ($a_level < $b_level) {
                //echo $b['id'].' winzzz';
                return 1;
            }
            return $a['id'] < $b['id'] ? 1 : -1;
        });
        return $infoblocks;
    }

    public function getContentInfoblocks($content_type = null)
    {
        if ($content_type) {
            // @todo: always store full component keyword
            $this->where(
                'controller', 
                array_unique(array(
                    preg_replace("~^floxim\.main\.~", '', $content_type), 
                    fx::getComponentFullName($content_type)
                ))
            );
        }
        $this->where('action', 'list_infoblock');
        return $this->all();
    }

    protected static $isStaticCacheUsed = true;
    protected static $fullStaticCache = true;
    protected static $storeStaticCache = false;

    public static function prepareFullDataForCacheFinder($finder)
    {
        $finder->with('visuals');
    }
}