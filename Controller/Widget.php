<?php

namespace Floxim\Floxim\Controller;

use Floxim\Floxim\System\Fx as fx;

class Widget extends Frontoffice {
    protected $_action_prefix = 'do_';
    protected $_meta = array();
    
    protected function getConfigSources() {
        $sources = array();
        $c_name = $this->getControllerName();

        $com_file = fx::path('module', fx::getComponentPath($c_name) . '/cfg.php');
        if (file_exists($com_file)) {
            $sources[] = $com_file;
        }
        return $sources;
    }
    
    public function doShow() {
        return $this->input;
    }
}