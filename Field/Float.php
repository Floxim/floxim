<?php

namespace Floxim\Floxim\Field;

class Float extends Baze
{
    public function getJsField($content)
    {
        parent::getJsField($content);
        $this->_js_field['type'] = 'floatfield';

        return $this->_js_field;
    }

    public function getSqlType()
    {
        return "DOUBLE";
    }
}