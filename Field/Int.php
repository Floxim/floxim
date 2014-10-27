<?php

namespace Floxim\Floxim\Field;

class Int extends Baze
{

    public function validateValue($value)
    {
        if (!parent::validateValue($value)) {
            return false;
        }
        if ($value && ($value != strval(intval($value)))) {
            $this->error = sprintf(FX_FRONT_FIELD_INT_ENTER_INTEGER, $this['name']);
            return false;
        }
        return true;
    }

    public function getSqlType()
    {
        return "INT";
    }
}