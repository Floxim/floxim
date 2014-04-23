<?php
class fx_cache {
    protected $_local_cache = array();
    
    public function get($key) {
        return isset($this->_local_cache[$key]) ? $this->_local_cache[$key] : null;
    }
    
    public function set($key, $value) {
        $this->_local_cache[$key] = $value;
    }
    
    public function drop($key) {
        unset($this->_local_cache[$key]);
    }
}
?>