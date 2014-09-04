<?php

namespace Floxim\Floxim\System\Exception;

class EssenceValidation extends \Exception {
    public function add_errors($errors) {
        $this->validate_errors = $errors;
    }
    public $validate_errors = array();
    public function to_form(fx_form $form) {
        foreach ($this->validate_errors as $e) {
            $form->add_error($e['text'], isset($e['field']) ? $e['field'] : false);
        }
    }
}