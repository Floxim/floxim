<?php

namespace Floxim\Floxim\Component\Infoblock;

use Floxim\Floxim\System;
use fx;

class Finder extends System\Data {
    
    public function relations() {
        return array(
            'visuals' => array(
                self::HAS_MANY,
                'infoblock_visual',
                'infoblock_id'
            )
        );
    }

    public function __construct() {
        parent::__construct();
        // todo: psr0 need verify
        $this->classname = 'fx_infoblock';
        $this->serialized = array('params', 'scope');
    }
    
    public function is_layout() {
        return $this->where('controller', 'layout')->where('action', 'show');
    }
    
    public function get_by_id($id) {
        if (is_numeric($id)) {
            return parent::get_by_id($id);
        }
        if (preg_match("~fake~", $id)) {
            return $this->create(array('id' => $id));
        }
    }
    
    public function get_for_page($page_id) {
        $page = $page_id instanceof System\Essence ? $page_id : fx::data('page', $page_id);
        if (!$page) {
            return;
        }
        $ids = $page->get_parent_ids();
        $ids []= $page['id'];
        $ids []= 0; // root
        $infoblocks = $this->
            where('page_id', $ids)->
            where('site_id', $page['site_id'])->
            all();
        foreach ($infoblocks as $ib) {
            if (!$ib->is_available_on_page($page)) {
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
     * @param fx_collection $infoblocks
     */
    public function sort_infoblocks($infoblocks) {
        $infoblocks->sort(function($a, $b) {
            $a_scope = $a->get_scope_weight();
            $b_scope = $b->get_scope_weight();
            if ($a_scope > $b_scope) {
                return -1;
            }
            if ($a_scope < $b_scope) {
                return 1;
            }
            $a_level = count(fx::content('page', $a['page_id'])->get_parent_ids());
            $b_level = count(fx::content('page', $b['page_id'])->get_parent_ids());
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

    public function get_content_infoblocks($content_type = null) {
        if ($content_type) {
            $this->where('controller', 'component_'.$content_type);
        }
        $this->where('action', 'list_infoblock');
        return $this->all();
    }
}