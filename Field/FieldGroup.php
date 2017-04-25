<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class FieldGroup extends \Floxim\Floxim\Component\Field\Entity
{
    public function getJsField($content) {
        $res = parent::getJsField($content);
        $res['keyword'] = $this['keyword'];
        return $res;
    }
    public function getSqlType()
    {
        return false;
    }
}