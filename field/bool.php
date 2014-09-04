<?php

namespace Floxim\Floxim\Field;

class Bool extends Baze {
    public function get_sql_type() {
        return "TINYINT";
    }
}