<?php

class fx_field_vars_file {

    protected $path = '', $size = 0, $type = '', $name = '';
    protected $to_string = null;

    public function __construct($info) {
        if ($info['path']) {
            $this->path = fx::config()->HTTP_FILES_PATH.$info['path'];
        }
        if ($info['real_name']) {
            $this->name = $info['real_name'];
        }
        if ($info['type']) {
            $this->type = $info['size'];
        }
        if ($info['size']) {
            $this->size = $info['size'];
        }
    }

    public function get_name() {
        return $this->name;
    }

    public function get_path() {
        return $this->path;
    }

    public function get_size() {
        return $this->size;
    }

    public function get_type() {
        return $this->type;
    }

    public function __toString() {
        return $this->to_string ? $this->to_string : $this->path;
    }

    public function set_to_str_value($value) {
        $this->to_string = $value;
    }

}

?>