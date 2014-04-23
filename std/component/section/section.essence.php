<?php
class fx_content_section extends fx_content_page {
    public function get_avail_parents_finder() {
        $f = parent::get_avail_parents_finder();
        $f->where_or( 
            array('infoblock_id', $this['infoblock_id']), 
            array('level', 0)
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