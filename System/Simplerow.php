<?php

namespace Floxim\Floxim\System;

class Simplerow extends Entity {
    public function get_type() {
        return $this->table;
    }
}