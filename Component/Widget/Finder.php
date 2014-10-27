<?php

namespace Floxim\Floxim\Component\Widget;

use Floxim\Floxim\System;

class Finder extends System\Data
{


    public function getById($id)
    {
        return $this->where(is_numeric($id) ? 'id' : 'keyword', $id)->one();
    }

    public function getMultiLangFields()
    {
        return array(
            'name',
            'description'
        );
    }
}