<?php

namespace Floxim\Floxim\System;

class Simplerow extends Entity
{
    public function getType()
    {
        return $this->table;
    }
}