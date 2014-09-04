<?php

namespace Floxim\Floxim\Field;

class Float extends Baze {
    public function get_js_field($content) {
        parent::get_js_field($content);
        $this->_js_field['type'] = 'floatfield';
        
        return $this->_js_field;
    }
    public function get_sql_type() {
        return "DOUBLE";
    }
}