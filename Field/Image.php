<?php

namespace Floxim\Floxim\Field;

class Image extends File {

    public function getJsField($content) {
        parent::getJsField($content);
        $this->_js_field['type'] = 'image';

        return $this->_js_field;
    }

    public function getEditJsdata($content) {
        parent::getEditJsdata($content);

        $this->_edit_jsdata['type'] = 'image';
        return $this->_edit_jsdata;
    }
    
    public function fakeValue() {
        static $num = 1;
        $num = $num === 1 ? 2 : 1;
        return '/vendor/floxim/floxim/admin/style/images/stub_'.$num.'.jpg';
    }
}