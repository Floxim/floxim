<?php

namespace Floxim\Floxim\System\Exception;

class Classload extends Base
{

    public $class_file = false;

    public function getClassFile()
    {
        return $this->class_file;
    }

}