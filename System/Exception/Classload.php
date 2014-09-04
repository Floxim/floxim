<?php

namespace Floxim\Floxim\System\Exception;

class Classload extends Base {

    public $class_file = false;
    public function get_class_file() {
        return $this->class_file;
    }

}