<?php

namespace Floxim\Floxim\Asset\Less\Tweaker;


class HardVar extends \Less_Tree_Quoted
{
    public function __construct($name) {
        $name = '@'.$name;
        parent::__construct('"'.$name.'"', $name, true);
    }

    public function compile() {
        return $this;
    }
}