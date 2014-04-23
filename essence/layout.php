<?php
class fx_layout extends fx_essence {
    public function get_path() {
        return fx::config()->HTTP_LAYOUT_PATH.$this['keyword'].'/';
    }
    
    protected function _before_insert() {
        parent::_before_insert();
        $path = $this->get_path();
        fx::files()->mkdir($path);
    }
    
    protected function _after_delete() {
        parent::_after_delete();
        $path = fx::path()->to_abs($this->get_path());
        fx::files()->rm($path);
    }
}