<?php

namespace Floxim\Floxim\Component\LangString;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    public function validate()
    {
        if (!parent::validate()) {
            return false;
        }
        $existing = fx::data('lang_string')->where('string', $this['string'])->where('dict', $this['dict'])->where('id',
            $this['id'], '!=')->all();
        foreach ($existing as $double) {
            if ($double['string'] == $this['string']) {
                //fx::log('nop', $double['string'], $this['string']);
                $this->validate_errors [] = 'String "' . $this['string'] . '" already exists in the "' . $this['dict'] . '" dictionary';
                return false;
            }
            //fx::log('double by case?', $this, $double);
        }
        return true;
    }

    protected function afterSave()
    {
        parent::afterSave();
        fx::alang()->dropDictFiles($this['dict']);
    }

    protected function afterDelete()
    {
        parent::afterDelete();
        fx::alang()->dropDictFiles($this['dict']);
    }
}