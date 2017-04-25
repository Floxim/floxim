<?php

namespace Floxim\Floxim\Field;

class FieldColor extends \Floxim\Floxim\Component\Field\Entity
{
    public function getSqlType()
    {
        return "VARCHAR(7)";
    }
}