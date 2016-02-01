<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class String extends \Floxim\Floxim\Component\Field\Entity
{

    public function formatSettings()
    {
        $fields = array(
            array(
                'type'  => 'checkbox',
                'name'  => 'html',
                'label' => fx::alang('allow HTML tags', 'system')
            )
        );
        return $fields;
    }
    
    public function getCastType() 
    {
        return 'string';
    }

    public function getSqlType()
    {
        return "VARCHAR(255)";
    }
}