<?php

namespace Floxim\Floxim\Component\Option;

use Floxim\Floxim\System;

class Finder extends System\Data {

    public function __construct() {
        parent::__construct();
        $this->serialized = array('value');
    }

}