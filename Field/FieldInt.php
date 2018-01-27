<?php

namespace Floxim\Floxim\Field;

class FieldInt extends \Floxim\Floxim\Component\Field\Entity
{

    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        $units = $this->getFormat('units');
        $res['type'] = 'number';
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

    public function validateValue($value)
    {
        if (!parent::validateValue($value)) {
            return false;
        }
        if ($value && ($value != strval(intval($value)))) {
            $this->error = sprintf(FX_FRONT_FIELD_INT_ENTER_INTEGER, $this['name']);
            return false;
        }
        return true;
    }

    public function getSqlType()
    {
        return "INT";
    }
    
    public function getCastType() 
    {
        return 'int';
    }
}