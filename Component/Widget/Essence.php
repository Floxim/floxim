<?php

namespace Floxim\Floxim\Component\Widget;

use Floxim\Floxim\System;

class Essence extends System\Essence {

    public function validate() {
        $res = true;

        if (!$this['name']) {
            $this->validate_errors[] = array('field' => 'name', 'text' => fx::alang('Specify the name of the widget','system'));
            $res = false;
        }

        if (!$this['keyword']) {
            $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('Enter the keyword of widget','system'));
            $res = false;
        }

        if ($this['keyword'] && !preg_match("/^[a-z][a-z0-9_-]*$/i", $this['keyword'])) {
            $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('Keyword can contain only letters and numbers','system'));
            $res = false;
        }

        if ($this['keyword']) {
            $widgets = fx::data('widget')->all();
            foreach ($widgets as $widget) {
                if ($widget['id'] != $this['id'] && $widget['keyword'] == $this['keyword']) {
                    $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('This keyword is used by widget','system') . ' "'.$widget['name'].'"');
                    $res = false;
                }
            }
        }

        return $res;
    }
    
    protected function _after_insert() {
        parent::_after_insert();
        $this->scaffold();
    }
    
    public function scaffold() {
        $keyword = $this['keyword'];
        $controller_file = fx::path(
            ($this['vendor'] === 'std') ? 'std' : 'root', 
            'widget/'.$keyword.'/'.$keyword.'.php'
        );
        ob_start();
        // todo: psr0 need fix
        echo "<?php\n";?>
class fx_controller_widget_<?=$keyword?> extends fx_controller_widget {
    /* 
    //uncomment this to create widget action logic
    public function do_show() {
    
    }
    */
}<?php
        $code = ob_get_clean();
        fx::files()->writefile($controller_file, $code);
    }
}