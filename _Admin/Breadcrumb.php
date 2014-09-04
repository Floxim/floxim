<?php

namespace Floxim\Floxim\Admin;

class Breadcrumb {
    protected $path = array();
    
    public function add_item ( $name, $href = '' ) {
        $this->path[] = array('name' => $name, 'href' => $href );
    }
    
    public function to_array() {
        return $this->path;
    }
}