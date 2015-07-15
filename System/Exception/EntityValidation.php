<?php

namespace Floxim\Floxim\System\Exception;

class EntityValidation extends \Exception
{
    public function addErrors($errors)
    {
        $this->validate_errors = $errors;
    }

    public $validate_errors = array();
    public $entity = null;
    
    public function toForm(\Floxim\Form\Form $form)
    {
        foreach ($this->validate_errors as $e) {
            $form->addError($e['text'], isset($e['field']) ? $e['field'] : false);
        }
    }
    public function toResponse() {
        $res = array();
        foreach ($this->validate_errors as $e) {
            $res[]= array(
                'error' => $e['text'],
                'field' => isset($e['field']) ? $e['field'] : '',
                'entity_id' => $this->entity['id'],
                'entity_type' => $this->entity['type']
            );
        }
        return $res;
    }
}