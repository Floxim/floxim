<?php

class fx_admin_dialog {

    protected $title;

    public function set_title($title) {
        $this->title = $title;
    }

    public function to_array() {
        $result = array();
        if ($this->title) {
            $result['title'] = $this->title;
        }
        return $result;
    }

}