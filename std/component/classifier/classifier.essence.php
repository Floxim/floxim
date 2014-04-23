<?php
class fx_content_classifier extends fx_content_page {    
    protected function _after_delete() {
        parent::_after_delete();
        $linkers = fx::data('content_classifier_linker')->where('classifier_id', $this['id'])->all();
        $linkers->apply(function($tp) {
            $tp['classifier_id'] = null;
            $tp->delete(); 
        });
    }
}
?>