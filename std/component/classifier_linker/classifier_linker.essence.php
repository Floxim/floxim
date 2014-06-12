<?php
class fx_content_classifier_linker extends fx_content {
    
    protected function _after_insert() {
        parent::_after_insert();
        $classifier = $this['classifier'];
        if (!$classifier) {
            return;
        }
        $classifier['counter'] = $classifier['counter']+1;
        $classifier->save();
    }
    
    protected function _after_delete() {
        parent::_after_delete();
        if (! ($classifier = $this['classifier']) ) {
            return;
        }
        $classifier['counter'] = $classifier['counter']-1;
        if ($classifier['counter'] < 0) {
            $classifier['counter'] = 0;
        }
        $classifier->save();
    }
}