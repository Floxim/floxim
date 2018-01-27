<?php

namespace Floxim\Floxim\Field;

class FieldFloat extends \Floxim\Floxim\Component\Field\Entity
{
    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        $res['type'] = 'float';
        $units = $this->getFormat('units');
        if ($units) {
            $res['units'] = $units;
            $res['show_units'] = true;
        }
        return $res;
    }

    public function formatSettings()
    {
        return [
            'units' => [
                'label' => 'Единицы измерения'
            ]
        ];
    }

    public function getSavestring($content) {
        $val = parent::getSavestring($content);
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