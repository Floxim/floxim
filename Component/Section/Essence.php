<?php
namespace Floxim\Floxim\Component\Section;

class Essence extends \Floxim\Floxim\Component\Page\Essence {
    public function get_avail_parents_finder() {
        $f = parent::get_avail_parents_finder();
        $our_infoblock = fx::data('infoblock', $this['infoblock_id']);
        $f->where_or( 
            array('infoblock_id', $this['infoblock_id']), 
            array('id', $our_infoblock['page_id'])
        );
        return $f;
    }
    
    public function get_form_field_parent_id($field) {
        $ib = fx::data('infoblock', $this['infoblock_id']);
        if (!$ib['params']['submenu'] || $ib['params']['submenu'] == 'none') {
            return;
        }
        return parent::get_form_field_parent_id($field);
    }
}