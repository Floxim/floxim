<?php
class fx_content_classifier_linker extends fx_content {
    
    protected function _get_classifier(){
        if (!$this['classifier_id']) {
            return;
        }
        return fx::data('content_classifier', $this['classifier_id']);
    }
    
    protected function _after_insert() {
        parent::_after_insert();
        $classifier = $this->_get_classifier();
        if (!$classifier) {
            return;
        }
        $classifier['counter'] = $classifier['counter']+1;
        $classifier->save();
    }
    
    protected function _after_delete() {
        parent::_after_delete();
        if (! ($classifier = $this->_get_classifier()) ) {
            return;
        }
        $classifier['counter'] = $classifier['counter']-1;
        if ($classifier['counter'] < 0) {
            $classifier['counter'] = 0;
        }
        $classifier->save();
    }
}
?>