<?php

namespace Floxim\Floxim\Field;

class Bool extends \Floxim\Floxim\Component\Field\Entity
{
    public function getSqlType()
    {
        return "TINYINT";
    }
}