<?php


class fx_field_float extends fx_field_baze {
    public function get_js_field($content) {
        parent::get_js_field($content);
        $this->_js_field['type'] = 'floatfield';
        
        return $this->_js_field;
    }
    public function get_sql_type() {
        return "DOUBLE";
    }
}



?>
