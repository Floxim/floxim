<?php

namespace Floxim\Floxim\Helper\Form;

class Form implements ArrayAccess {

    protected $params = array();
    
    public function __construct($params = array()) {
        $params = array_merge(array(
            'method' => 'POST'
        ), $params);
        $fields = new Fields();
        $fields->form = $this;
        $params['fields'] = $fields;
        $this->params = $params;
        $this->add_field(array('name' => 'default_submit', 'type' => 'submit', 'label' => 'Submit'));
    }
    
    public function add_fields($fields) {
        foreach ($fields as $name => $props) {
            $props['name'] = $name;
            $this->add_field($props);
        }
    }
    
    /**
     * @todo we need $_POST + $_FILES merge here
     */
    protected function _get_input() {
        return strtolower($this['method']) == 'post' ? $_POST : $_GET;
    }
    
    public function get_id() {
        if (!isset($this['id'])) {
            $this['id'] = md5(join(",",$this->params['fields']->keys()));
        }
        return $this['id'];
    }
    
    protected $_listeners = array();
    
    public function __call($name, $args) {
        if (preg_match("~^on_~", $name) && count($args) == 1) {
            $this->on(preg_replace("~^on_~", '', $name), $args[0]);
            return $this;
        }
    }
    
    public function on($event, $callback) {
        if (!isset($this->_listeners[$event])) {
            $this->_listeners[$event] = array();
        }
        $this->_listeners[$event] []= $callback;
    }
    
    public function trigger($event) {
        if (is_string($event) && isset($this->_listeners[$event])) {
            foreach ($this->_listeners[$event] as $listener) {
                if (is_callable($listener)) {
                    call_user_func($listener, $this); 
                }
            }
        }
    }
    
    protected $is_sent = null;
    public function is_sent() {
        if (is_null($this->is_sent)) {
            $input = $this->_get_input();
            $this->is_sent = isset($input[$this->get_id().'_sent']);
            if ($this->is_sent) {
                $this->load_values();
                $this->validate();
                $this->trigger('sent');
            }
        }
        return $this->is_sent;
    }
    
    public function validate() {
        return $this->params['fields']->validate();
    }
    
    public function load_values($source = null) {
        if (is_null($source)) {
            $source = $this->_get_input();
        }
        foreach ($this->params['fields'] as $name => $field) {
            $field->set_value( isset($source[$name]) ? $source[$name] : null);
        }
    }
    
    public function set_value ($field, $value) {
        $this->params['fields']->set_value($field, $value);
    }
    
    public function get_value ($field = null) {
        return $this->params['fields']->get_value($field);
    }
    
    /**
     * Magic getter returns field value
     * @param type $name
     */
    public function __get($name) {
        return $this->get_value($name);
    }
    
    public function get_values() {
        return $this->params['fields']->get_values();
    }
     
    public function add_field($params) {
        if ($params['type'] == 'submit') {
            $this->params['fields']->find_remove('name', 'default_submit');
        }
        return $this->params['fields']->add_field($params);
    }
    
    public function add_message($message, $after_finish = false) {
        if (!isset($this['messages'])) {
            $this['messages'] = fx::collection();
        }
        $this['messages'][]= array('message' => $message, 'after_finish' => (bool) $after_finish);
    }
    
    public function finish($message = null) {
        $this['is_finished'] = true;
        if ($message) {
            $this->add_message($message, true);
        }
        $this->trigger('finish');
    }
    
    public function has_errors() {
        return count($this->get_errors()) > 0;
    }
    
    public function get_errors() {
        $errors = isset($this['errors']) ? $this['errors'] : fx::collection();
        $errors->concat($this->params['fields']->get_errors());
        return $errors;
    }
    
    public function add_error($error, $field_name = false) {
        $field = $field_name ? $this->get_field($field_name) : false;
        if ($field) {
            $field->add_error($error);
            return;
        }
        if (!isset($this->params['errors'])) {
            $this->params['errors'] = fx::collection();
        }
        $this->params['errors'][]= array('error' => $error);
    }
    
    public function get_field($name) {
        return $this->params['fields']->get_field($name);
    }
    
    public function get($offset = null) {
        if (is_null($offset)) {
            return $this->params;
        }
        return $this->offsetGet($offset);
    }
    
    /* ArrayAccess methods */
    
    public function offsetExists($offset) {
        return array_key_exists($offset, $this->params) || in_array($offset, array('is_sent'));
    }
    
    public function offsetGet($offset) {
        if ($offset === 'is_sent') {
            return $this->is_sent();
        }
        return array_key_exists($offset, $this->params) ? $this->params[$offset] : null;
    }
    
    public function offsetSet($offset, $value) {
        $this->params[$offset] = $value;
    }
    
    public function offsetUnset($offset) {
        unset($this->params[$offset]);
    }
}