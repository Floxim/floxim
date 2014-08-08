<?php
class fx_option extends fx_essence {

    protected  function _before_save() {
        parent::_before_save();
        $this['value']=serialize($this['value']);
    }
}