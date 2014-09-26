<?php

namespace Floxim\Floxim\Field;

class Bool extends Baze {
    public function getSqlType() {
        return "TINYINT";
    }
}