<?php

namespace Floxim\Floxim\Field;

class Color extends \Floxim\Floxim\Component\Field\Entity
{
    public function getSqlType()
    {
        return "VARCHAR(7)";
    }
}