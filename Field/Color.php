<?php

namespace Floxim\Floxim\Field;

class Color extends Baze {
    public function getSqlType() {
        return "VARCHAR(7)";
    }
}