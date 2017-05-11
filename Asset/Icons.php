<?php

namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

class Icons extends \Floxim\Floxim\Asset\Bundle {
    
    public function getBundleContent() {
        return '';
    }
    
    public static function getClass($icon_val) 
    {
        $icon_val = trim($icon_val);
        if (empty($icon_val)) {
            return '';
        }
        list($set, $icon) = explode(" ", $icon_val);
        
        static $added_styles = array();
        
        static $set_files = array(
            'fa' => '@floxim/lib/icons/fontawesome/css/font-awesome.css',
            'gmdi' => '@floxim/lib/icons/gmdi/gmdi.css',
            'lnr' => '@floxim/lib/icons/linearicons/style.css',
            'ti' => '@floxim/lib/icons/themify/themify.css'
        );
        
        if (!isset($added_styles[$set])) {
            if (isset($set_files[$set])) {
                fx::page()->addCss([ fx::path()->abs($set_files[$set]) ]);
            }
            $added_styles[$set] = true;
        }
        
        return $set.' '.$set.'-'.$icon;
    }
}