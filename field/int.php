<?php

class fx_field_int extends fx_field_baze {

    public function validate_value($value) {
        if (!parent::validate_value($value)) {
            return false;
        }
        if ($value && ($value != strval(intval($value)))) {
            $this->error = sprintf(FX_FRONT_FIELD_INT_ENTER_INTEGER, $this['name']);
            return false;
        }
        return true;
    }

    public function get_sql_type (){
        return "INT";
    }
}