<?php

namespace Floxim\Floxim\Field;

class Color extends Baze {
    public function get_sql_type() {
        return "VARCHAR(7)";
    }
}