<?php

namespace Floxim\Floxim\Field;

class FieldBool extends \Floxim\Floxim\Component\Field\Entity
{
    public function getSqlType()
    {
        return "TINYINT";
    }
}