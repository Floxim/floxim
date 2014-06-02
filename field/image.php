<?php
class fx_field_image extends fx_field_file {

    public function get_js_field($content) {
        parent::get_js_field($content);
        $this->_js_field['type'] = 'image';

        return $this->_js_field;
    }

    public function get_edit_jsdata($content) {
        parent::get_edit_jsdata($content);

        $this->_edit_jsdata['type'] = 'image';
        return $this->_edit_jsdata;
    }
    
    public function fake_value() {
        static $num = 1;
        $num = $num === 1 ? 2 : 1;
        return '/floxim/admin/style/images/stub_'.$num.'.jpg';
    }
}