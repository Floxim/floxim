<?php

abstract class fx_essence implements ArrayAccess {

    // reference to the class object fx_data_
    //protected $finder;
    // field values
    protected $data = array();
    // the set of fields that have changed
    protected $modified = array();
    protected $modified_data = array();
    
    protected $validate_errors = array();
    
    protected $_form = null;
    
    protected function get_finder() {
        return fx::data($this->get_type());
    }

    protected static $_field_map = array();
    
    // virtual field types
    const VIRTUAL_RELATION = 0;
    const VIRTUAL_MULTILANG = 1;
    
    public function __construct($input = array()) {
        
        $this->_load_field_map();
        
        if (isset($input['data']) && $input['data']) {
            $data = $input['data'];
            if (isset($data['id'])) {
                $this->_loaded = false;
            }
            foreach ($data as $k => $v) {
                $this[$k] = $v;
            }
        }
        $this->_loaded = true;
    }
    
    protected function _load_field_map() {
        // cache relations & ml on first use
        // to increase offsetExists() speed (for isset($n[$val]) in templates)
        $c_type = $this->get_type();
        if (!isset(self::$_field_map[$c_type])) {
            $finder = $this->get_finder();
            self::$_field_map[$c_type] = array();
            foreach ($finder->relations() as $rel_name => $rel) {
                self::$_field_map[$c_type][$rel_name] = array(self::VIRTUAL_RELATION, $rel);
            }
            foreach ($finder->get_multi_lang_fields() as $f) {
                self::$_field_map[$c_type][$f] = array(self::VIRTUAL_MULTILANG);
            }
        }
    }
    
    public function __wakeup() {
        $this->_load_field_map();
    }

    public function save() {
        $this->_before_save();
        $pk = $this->_get_pk();
        // update
        if ($this->data[$pk]) {
            $this->_before_update();
            if ($this->validate() === false) {
                $this->throw_invalid();
                return false;
            }
            // updated only fields that have changed
            $data = array();
            foreach ($this->modified as $v) {
                $data[$v] = $this->data[$v];
            }
            $this->get_finder()->update($data, array($pk => $this->data[$pk]));
            $this->_save_multi_links();
            $this->_after_update();
        } // insert
        else {
            $this->_before_insert();
            if ($this->validate() === false) {
                $this->throw_invalid();
                return false;
            }
            $id = $this->get_finder()->insert($this->data);
            $this->data['id'] = $id;
            $this->_save_multi_links();
            $this->_after_insert();
        }
        $this->_after_save();

        return $this;
    }
    
    /**
     * Throw validation exception or append errors to form if it exists
     * @throws fx_essence_validation_exception
     */
    protected function throw_invalid() {
        $exception = new fx_essence_validation_exception(
            fx::lang("Unable to save essence \"".$this->get_type()."\"")
        );
        $exception->add_errors($this->validate_errors);
        if ($this->_form) {
            $exception->to_form($this->_form);
        } else {
            throw $exception;
        }
    }
    
    protected function _invalid($message, $field = null) {
        $error = array(
            'text' => $message
        );
        if ($field) {
            $error['field'] = $field;
        }
        $this->validate_errors[]= $error;
    }


    /*
     * Saves the fields links is determined in fx_data_content
     */
    protected function _save_multi_links() {
        
    }
    
    protected function _before_save () {
        
    }
    
    protected function _after_save() {
        
    }

    /**
     * Get a property data or an entire set of properties
     * @param strign $prop_name
     * @return mixed
     */
    public function get($prop_name = null) {
        if ($prop_name) {
            return $this->offsetGet($prop_name);
        }
        return $this->data;
    }

    public function set($item, $value = '') {
        if ( is_array($item) || $item instanceof Traversable) {
            foreach ( $item as $k => $v ) {
                $this->set($k, $v);
            }
            return $this;
        }
        $this->offsetSet($item, $value);
        return $this;
    }

    public function get_id() {
        return $this->data[$this->_get_pk()];
    }

    public function delete() {
        $pk = $this->_get_pk();
        $this->_before_delete();
        $this->get_finder()->delete($pk, $this->data[$pk]);
        $this->modified_data = $this->data;
        $this->_after_delete();
    }

    public function unchecked() {
        return $this->set('checked', 0)->save();
    }

    public function checked() {
        return $this->set('checked', 1)->save();
    }
    
    public function validate () {
        return true;
    }
    
    public function load_from_form($form, $fields = true) {
        $vals = $this->_get_from_form($form, $fields);
        $this->set($vals);
        $this->bind_form($form);
        return $this;
    }
    
    public function bind_form(fx_form $form) {
        $this->_form = $form;
    }
    
    
    
    protected function _get_from_form($form, $fields) {
        if (is_array($fields)) {
            $vals = array();
            foreach ($fields as $f) {
                $vals[]= $form->$f;
            }
        } else {
            $vals = $form->get_values();
        }
        return $vals;
    }
    
    public function validate_with_form($form, $fields = true) {
        if ($fields !== false) {
            $vals = $this->_get_from_form($form, $fields);
            $this->set($vals);
        }
        $this->bind_form($form);
        if (!$this->validate()) {
            $this->throw_invalid();
            return false;
        }
        return true;
    }
    
    public function get_validate_errors () {
        return $this->validate_errors;
    }
    
    protected function _get_pk() {
        return 'id';
    }

    public function __toString() {
        $res = '';
        foreach ($this->data as $k => $v) {
            $res .= "$k = $v " . PHP_EOL;
        }
        return $res;
    }
    
    protected function _before_insert () {
        return false;
    }
    
    protected function _after_insert () {
        return false;
    }
    
    protected function _before_update () {
        return false;
    }
    
    protected function _after_update () {
        return false;
    }
    
    protected function _before_delete () {
        return false;
    }
    
    protected function _after_delete () {
        return false;
    }

    /* Array access */
    public function offsetGet($offset) {
        
        if (method_exists($this, '_get_'.$offset)) {
            return call_user_func(array($this, '_get_'.$offset));
        }
        
        if (array_key_exists($offset, $this->data)) {
            return $this->data[$offset];
        }
        if ($offset == 'id') {
            return null;
        }
        
        
        
        $c_type = $this->get_type();
        $c_field = self::$_field_map[$c_type][$offset];
        
        if (!$c_field) {
            return null;
        }
        
        if ($c_field[0] == self::VIRTUAL_MULTILANG) {
            $lang_offset = $offset.'_'.fx::config('ADMIN_LANG');
            if (!empty($this->data[$lang_offset])) {
                return $this->data[$lang_offset];
            }
            return $this->data[$offset.'_en'];
        }
        
        /**
         * For example, for $post['tags'], where tags - field-multiphase
         * If connected not loaded, ask finder download
         */
        
        $finder = $this->get_finder();
        $finder->add_related($offset, new fx_collection(array($this)));
        if (!isset($this->data[$offset])) {
            return null;
        }
        //$this->modified_data[$offset] = clone $this->data[$offset];
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value) {
        // put modified | modified_data only if there was a key
        // so when you first fill fields-ties they will not be marked as updated
        $offset_exists = array_key_exists($offset, $this->data);
        
        if (!$offset_exists) {
            $c_type = $this->get_type();
            $c_field = self::$_field_map[$c_type][$offset];
            if ($c_field && $c_field[0] == self::VIRTUAL_MULTILANG) {
               $offset = $offset.'_'.fx::config('ADMIN_LANG');
            }
        }
        
        if (!$this->_loaded) {
            $this->data[$offset] = $value;
            return;
        }
        
        // use non-strict '==' because int values from db becomes strings - should be fixed
        if ($offset_exists && $this->data[$offset] == $value) {
            return;
        }
        
        
        $this->modified_data[$offset] = $this->data[$offset];
        $this->modified[] = $offset;
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset) {
        if  (array_key_exists($offset, $this->data)) {
            return true;
        }
        if (method_exists($this, '_get_'.$offset)) {
            return true;
        }
        return isset(self::$_field_map[$this->get_type()][$offset]);
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }
    
    protected $_type = null;
    public function get_type() {
        if (is_null($this->_type)) {
            $this->_type = str_replace("fx_", "", get_class($this));
        }
        return $this->_type;
    }
    
    /*
     * Add meta-data to be edited from the front
     * @param string $html the html code of record
     * @return string string with added meta-data
     */
    public function add_template_record_meta($html) {
        return $html;
    }
    
    public function is_modified($field = null) {
        if ($field === null) {
            return count($this->modified) > 0;
        }
        if (!$this['id']) {
            return true;
        }
        return is_array($this->modified) && in_array($field, $this->modified);
    }
    
    public function get_old($field) {
        if (!$this->is_modified($field)) {
            return null;
        }
        return $this->modified_data[$field];
    }
}

class fx_essence_validation_exception extends Exception {
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