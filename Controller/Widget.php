<?php

namespace Floxim\Floxim\Controller;

use Floxim\Floxim\System\Fx as fx;

class Widget extends Frontoffice {
    protected $_action_prefix = 'do_';
    protected $_meta = array();
    
    protected function getConfigSources() {
        $sources = array();
        $c_name = $this->getControllerName();
        // todo: psr0 need fix
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
    
    public function doShow() {
        return $this->input;
    }
    
    protected $widget_keyword = null;
    public function setKeyword($keyword) {
        $this->widget_keyword = $keyword;
    }
    
    public function getControllerName($with_type = false){
        if (!is_null($this->widget_keyword)) {
            return ($with_type ? "widget_" : '').$this->widget_keyword;
        }
        return parent::getControllerName($with_type);
    }
    
    protected function getControllerVariants() {
        return array($this->getControllerName(true));
    }
}