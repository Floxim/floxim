<?php

namespace Floxim\Floxim\System\Exception;

class EntityValidation extends \Exception
{
    public function addErrors($errors)
    {
        $this->validate_errors = $errors;
    }

    public $validate_errors = array();

    public function toForm(fx_form $form)
    {
        foreach ($this->validate_errors as $e) {
            $form->addError($e['text'], isset($e['field']) ? $e['field'] : false);
        }
    }
}