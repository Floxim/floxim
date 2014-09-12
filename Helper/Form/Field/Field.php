<?php

namespace Floxim\Floxim\Helper\Form\Field;

use Floxim\Floxim\Template;
use Floxim\Floxim\System\Fx as fx;

class Field implements \ArrayAccess, Template\Essence {

    protected $params = array();

    public static function create($params) {
        if (!isset($params['type'])) {
            $params['type'] = 'text';
        }
        $classname = 'Floxim\\Floxim\\Helper\\Form\\Field\\'.ucfirst($params['type']);
        try {
            if (!class_exists($classname)) {
                throw new \Exception();
            }
            $field = new  $classname($params);
        } catch (\Exception $ex) {
            $field = new Field($params);
        }

        return $field;
    }

    public function get($offset = null) {
        if (is_null($offset)) {
            return $this->params;
        }
        return $this->offsetGet($offset);
    }


    public function __construct($params = array()) {
        if (isset($params['owner'])) {
            $this->owner = $params['owner'];
            unset($params['owner']);
        }
        foreach ($params as $k => $v) {
            $this[$k] = $v;
        }
        if (!isset($this['value'])) {
            $this->set_value(null);
        }
    }

    public function set_value($value) {
        $this->params['value'] = $value;
    }

    public function get_value() {
        return $this->params['value'];
    }

    public function is_empty() {
        return !$this->get_value();
    }

    protected $errors = null;

    public function add_error($message) {
        if (!isset($this->params['errors'])) {
            $this->params['errors'] = fx::collection();
            $this->params['has_errors'] = true;
        }
        $this->params['errors'][]= $message;
    }

    public function validate() {
        $is_valid = true;
        if ($this['validators']) {
            foreach ($this['validators'] as $validator) {
                switch ($validator['type']) {
                    case 'callback':
                        $res = call_user_func($validator['callback'], $this);
                        if (is_string($res) || $res === false) {
                            $is_valid = false;
                            $this->add_error($res);
                        }
                        break;
                    case 'regexp':
                        $res = preg_match($validator['regexp'], $this->get_value());
                        if (!$res) {
                            $is_valid = false;
                            $this->add_error( isset($validator['error']) ? $validator['error'] : 'Wrong value format');
                        }
                        break;
                }
                if ($validator['is_last'] && !$is_valid) {
                    return $is_valid;
                }
            }
        }
        return $is_valid;
    }

    public function validate_email() {
        $v = $this->get_value();
        if (!fx::util()->validate_email($v)) {
            return "Please enter valid e-mail adress!";
        }
    }

    public function validate_filled() {
        if ($this->is_empty()) {
            return 'This field is required';
        }
    }

    public function get_form() {
        return $this->owner->form;
    }

    public function get_id() {
        $form = $this->get_form();
        return $form->get_id().'_'.$this['name'];
    }

    /* ArrayAccess methods */

    public function offsetExists($offset) {
        if (preg_match("~^%~", $offset)) {
            return true;
        }
        return array_key_exists($offset, $this->params) || method_exists($this, 'get_'.$offset);
    }

    public function offsetGet($offset) {

        if (preg_match("~^%~", $offset)) {
            $essence = $this['_essence'];
            if ($essence) {
                return $essence[$offset];
            }
            $real_offset = preg_replace("~^%~", '', $offset);
            $template = fx::env('current_template');
            if ($template && $template instanceof Template\Template) {
                $template_value = $template->v($real_offset."_".$this['name']);
                if ($template_value){
                    return $template_value;
                }
            }
            $offset = $real_offset;
        }
        if (method_exists($this, 'get_'.$offset)) {
            return call_user_func(array($this, 'get_'.$offset));
        }
        if (array_key_exists($offset, $this->params)) {
            return $this->params[$offset];
        }

    }

    public function set($offset, $value) {
        $this->offsetSet($offset, $value);
        return $this;
    }

    public function offsetSet($offset, $value) {
        if ($offset === 'value') {
            $this->set_value($value);
            return;
        }
        if ($offset === 'validators') {
            if (!is_array($value)) {
                $value = array($value);
            }
            foreach ($value as $v) {
                $this->add_validator($v);
            }
            return;
        }
        $this->params[$offset] = $value;
        if ($offset === 'required' && $value) {
            $this->add_validator('filled -l');
        }
    }

    public function add_validator($v) {
        if (!isset($this->params['validators'])) {
            $this->params['validators'] = fx::collection();
        }
        if (is_string($v)) {
            $is_last = false;
            if (preg_match("~\s\-l(ast)?$~", $v)) {
                $is_last = true;
                $v = preg_replace("~\s\-l(ast)?$~", '', $v);
            }
            $v = trim($v);
            if (preg_match("/^~.+~$/", $v)) {
                $v = array(
                    'type' => 'regexp',
                    'regexp' => $v
                );
            } elseif (method_exists($this, 'validate_'.$v)) {
                // prevent double-adding of the same validator by shortcode
                if ($this->params['validators']->find_one('code', $v)) {
                    return;
                }
                $v = array(
                    'type' => 'callback',
                    'code' => $v,
                    'callback' => array($this, 'validate_'.$v)
                );
            }
            $v['is_last'] = $is_last;
        } elseif ($v instanceof Closure) {
            $v = array(
                'type' => 'callback',
                'callback' => $v
            );
        }
        $this->params['validators'][]= $v;
    }


    public function offsetUnset($offset) {
        unset($this->params[$offset]);
    }

    public function add_template_record_meta($html, $collection, $index, $is_subroot) {
        $essence = $this['_essence'];
        if ($essence) {
            return $essence->add_template_record_meta($html, $collection, $index, $is_subroot);
        }
        return $html;
    }

    public function get_field_meta($field_keyword) {
        $essence = $this['_essence'];
        if ($essence) {
            $meta = $essence->get_field_meta($field_keyword);
            return $meta;
        }
        if (preg_match("~^%~", $field_keyword)) {
            $field_keyword = preg_replace("~^%~", '', $field_keyword);
            $v_id = $this['name'];
            $field_meta = array(
                'var_type' => 'visual',
                'id' => $field_keyword.'_'.$v_id,
                'name' => $field_keyword.'_'.$v_id
            );
            return $field_meta;
        }
    }

}