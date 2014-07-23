<?php
interface fx_template_essence extends ArrayAccess {
    /**
     * Add meta attributes to essence buffered html
     */
    public function add_template_record_meta($html, $collection, $index, $is_subroot);
    
    /**
     * Get meta info about sertain essence field
     */
    public function get_field_meta($field_keyword);
}