<?php

namespace Floxim\Floxim\Admin;

class Breadcrumb
{
    protected $path = array();

    public function addItem($name, $href = '')
    {
        $this->path[] = array('name' => $name, 'href' => $href);
    }

    public function toArray()
    {
        return $this->path;
    }
}