<?php
class fx_controller_widget extends fx_controller {
    protected $_action_prefix = 'do_';
    protected $_meta = array();
    protected function _get_config_sources() {
        $sources = array();
        $c_name = $this->get_controller_name();
        $std_conf = fx::config()->DOCUMENT_ROOT.'/floxim/std/widget/'.$c_name."/".$c_name.'.cfg.php';
        $custom_conf = fx::config()->DOCUMENT_ROOT.'/widget/'.$c_name."/".$c_name.'.cfg.php';
        if (file_exists($std_conf)) {
            $sources []= $std_conf;
        }
        if (file_exists($custom_conf)) {
            $sources []= $custom_conf;
        }
        return $sources;
    }
    
    public function process() {
        $result = parent::process();
        if (!isset($result['_meta'])) {
            $result['_meta'] = array();
        }
        $result['_meta'] = array_merge_recursive($result['_meta'], $this->_meta);
        return $result;
    }

        public function do_show() {
        return $this->input;
    }
    
    protected $widget_keyword = null;
    public function set_keyword($keyword) {
        $this->widget_keyword = $keyword;
    }
    
    public function get_controller_name($with_type = false){
        if (!is_null($this->widget_keyword)) {
            return ($with_type ? "widget_" : '').$this->widget_keyword;
        }
        return parent::get_controller_name($with_type);
    }
    
    protected function _get_controller_variants() {
        return array($this->get_controller_name(true));
    }
}