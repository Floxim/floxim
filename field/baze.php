<?php

/**
 * Base class for the various field types
 */
class fx_field_baze extends fx_field {

    protected $value, $error, $is_error = false;
    protected $_edit_jsdata;
    protected $_js_field = array();
    protected $_wrap_tag = 'span';

    public function get_edit_jsdata($content) {
        $data = $this->get_js_field($content);
        unset($data['label'], $data['id'], $data['parent'], $data['name']);
        return $data;
        return $this->_edit_jsdata;
    }

    public function get_js_field($content) {

        $name = $this->name;
        $this->_js_field = array('id' => $name, 'name' => $name, 'label' => $this->description, 'type' => $this->get_type(false));
        $this->_js_field['value'] = $this['default'];
        if ($content[$this->name]) {
            $this->_js_field['value'] = $content[$this->name];
        }
        return $this->_js_field;
    }


    public function get_html($opt = '') {
        $asterisk = $this['not_null'] ? '<span class="fx_field_asterisk">*</span>' : '';
        return '<div class="'.$this->get_wrap_css_class().'"><label>'.$this['description'].$asterisk.'</label>:'.$this->get_input($opt).'</div>';
    }

    protected function get_css_class() {
        return "fx_form_field fx_form_field_".fx_field::get_type_by_id($this->type_id).($this->is_error ? " fx_form_field_error" : "");
    }

    protected function get_wrap_css_class() {
        return "fx_form_wrap fx_form_wrap_".fx_field::get_type_by_id($this->type_id);
    }

    public function set_value($value) {
        $this->value = $value;
    }

    public function validate_value($value) {
        if (!is_array($value) && !is_object($value)) {
            $value = trim($value);
        }
        if ($this['not_null'] && !$value) {
            $this->error = sprintf(FX_FRONT_FIELD_FILED, $this->description);
            return false;
        }
        return true;
    }

    public function get_savestring() {
        return $this->value;
    }

    public function post_save($content) {
        return false;
    }

    public function get_error() {
        return $this->error;
    }

    public function set_error() {
        $this->is_error = true;
    }
    
    public function get_export_value ( $value, $dir = '' ) {
        return $value;
    }
    
    public function get_import_value ( $content, $value, $dir = '' ) {
        return $value;
    }
}