<?php

namespace Floxim\Floxim\Template;

use \Floxim\Floxim\System\Fx as fx;

class Container {
    
    protected $parent = null;
    
    protected $props = array();
    
    public static function create($props) {
        return new self($props);
    }
    
    public function __construct($props, $parent = null)
    {
        $this->props = $props;
        $this->parent = $parent;
    }
    
    public function getClasses()
    {
        
    }
    
    protected static $layout_sizes = null;
    public static function getLayoutSizes()
    {
        if (is_null(self::$layout_sizes)) {
            $palette = fx::env('theme')->get('palette');
            $props = $palette['params'];
            $sizes = array(
                'width' => (int) $props['vars']['layout_width'],
                'max-width' => (int) $props['vars']['max_width']
            );
            $sizes['breakpoint'] = 'min-width: '. $sizes['max-width'] / ($sizes['width']/100) . 'px';
            self::$layout_sizes = $sizes;
        }
        return self::$layout_sizes;
    }
}