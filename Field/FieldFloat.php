<?php

namespace Floxim\Floxim\Field;

class FieldFloat extends \Floxim\Floxim\Component\Field\Entity
{
    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        $res['type'] = 'float';

        return $res;
    }
    
    public function getSavestring() {
        $val = parent::getSavestring();
        $val = (float) str_replace(",", '.', $val);
        return $val;
    }

    public function getSqlType()
    {
        return "DOUBLE";
    }
    
    public function getCastType() 
    {
        return 'float';
    }
}