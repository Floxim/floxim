<?php

namespace Floxim\Floxim\Helper\Form;

use Floxim\Floxim\System;

class Fields extends System\Collection {

    public function offsetSet($offset, $value) {
        if (! $value instanceof Field\Field) {
            $value = Field\Field::create($value);
        }
        $this->data[$offset] = $value;
    }

    public function set_value($field, $value) {
        if (isset($this[$field])) {
            $this[$field]->set_value($value);
        }
    }

    public function add_field($params) {
        $field = Field\Field::create($params + array('owner' => $this));
        $this[$field['name']] = $field;
        return $field;
    }

    public function get_field($name) {
        return $this[$name];
    }

    public function get_value($field_name) {
        $f = $this->get_field($field_name);
        return $f ? $f->get_value() : null;
    }

    public function get_values() {
        $values = fx::collection();
        foreach ($this->data as $name => $field) {
            $values[$name] = $field->get_value();
        }
        return $values;
    }

    public function get_errors() {
        $errors = fx::collection();
        foreach ($this->data as $f) {
            $f_errors = $f['errors'];
            if (!$f_errors) {
                continue;
            }
            foreach ($f_errors as $e) {
                $errors []= array('error' => $e, 'field' => $f);
            }
        }
        return $errors;
    }

    public function validate() {
        $is_valid = true;
        foreach ($this->data as $f) {
            if ($f->validate() === false) {
                $is_valid = false;
            }
        }
        return $is_valid;
    }

}