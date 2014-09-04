<?php

namespace Floxim\Floxim\Component\Widget;

use Floxim\Floxim\System;

class Finder extends System\Data {

    
    public function get_by_id($id) {
        return $this->where(is_numeric($id) ? 'id' : 'keyword', $id)->one();
    }
    
    public function get_multi_lang_fields() {
        return array(
            'name',
            'description'
        );
    }
}