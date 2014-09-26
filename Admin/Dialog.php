<?php

namespace Floxim\Floxim\Admin;

class Dialog {

    protected $title;

    public function setTitle($title) {
        $this->title = $title;
    }

    public function toArray() {
        $result = array();
        if ($this->title) {
            $result['title'] = $this->title;
        }
        return $result;
    }

}