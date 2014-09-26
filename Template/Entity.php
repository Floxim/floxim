<?php

namespace Floxim\Floxim\Template;

interface Entity extends \ArrayAccess {
    /**
     * Add meta attributes to entity buffered html
     */
    public function add_template_record_meta($html, $collection, $index, $is_subroot);
    
    /**
     * Get meta info about sertain entity field
     */
    public function get_field_meta($field_keyword);
}