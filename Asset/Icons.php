<?php

namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

class Icons extends \Floxim\Floxim\Asset\Bundle {
    
    public function getBundleContent() {
        return '';
    }
    
    public static function getClass($icon_val) 
    {
        list($set, $icon) = explode(" ", $icon_val);
        
        static $added_styles = array();
        
        static $set_files = array(
            'fa' => '/vendor/floxim/floxim/lib/fontawesome/css/font-awesome.css'
        );
        
        if (!isset($added_styles[$set])) {
            fx::page()->addCssFile($set_files[$set]);
            $added_styles[$set] = true;
        }
        
        return $set.' '.$set.'-'.$icon;
    }
}