<?php

namespace Floxim\Floxim\Component\SelectValue;

class Finder extends \Floxim\Floxim\System\Finder {
    public function relations()
    {
        return array(
            'field' => array(
                self::BELONGS_TO,
                'field',
                'field_id'
            )
        );
    }
    
    public function getMultiLangFields()
    {
        return array(
            'name',
            'description'
        );
    }
}