<?php
class fx_form implements ArrayAccess {
    
    
    protected $params = array();
    
    public function __construct($params = array()) {
        $params = array_merge(array(
            'method' => 'POST'
        ), $params);
        $fields = new fx_form_fields();
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

class fx_form_fields extends fx_collection {
    
    public function offsetSet($offset, $value) {
        if (! $value instanceof fx_form_field) {
            $value = fx_form_field::create($value);
        }
        $this->data[$offset] = $value;
    }
    
    public function set_value($field, $value) {
        if (isset($this[$field])) {
            $this[$field]->set_value($value);
        }
    }
    
    public function add_field($params) {
        $field = fx_form_field::create($params + array('owner' => $this));
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

class fx_form_field implements ArrayAccess, fx_template_essence {
    
    protected $params = array();
    
    public static function create($params) {
        if (!isset($params['type'])) {
            $params['type'] = 'text';
        }
        $classname = "fx_form_field_".$params['type'];
        try {
            $field = new  $classname($params);
        } catch (Exception $ex) {
            $field = new fx_form_field($params);
        }
        
        return $field;
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
            if ($template && $template instanceof fx_template) {
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

class fx_form_field_captcha extends fx_form_field {
    public function __construct($params = array()) {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($params['label'])) {
            $params['label'] = 'Aren\'t you a robot?';
        }
        if (!isset($params['valid_for'])) {
            $params['valid_for'] = 6*5; // 3 min
        }
        
        parent::__construct($params);
        
        $url = fx::path()->to_http(dirname(realpath(__FILE__)));
        $url .= '/captcha.php?fx_field_name='.urlencode($this->get_id());
        $url .= '&rand='.rand(0, 1000000);
        $this['captcha_url'] = $url;
        
        if (!$this->was_valid()) {
            $this['required'] = true;
            $this->add_validator('captcha');
        }
        $field = $this;
        $this->get_form()->on_finish(function($f) use ($field) {
            $field->was_valid(false);
        });
    }
    
    public function validate_captcha() {
        if ($this->was_valid()) {
            return;
        }
        if ($_SESSION['captcha_code_'.$this->get_id()] != $this->get_value()) {
            return 'Invalid code';
        }
        $this->was_valid(true);
    }
    
    public function was_valid($set = null) {
        $prop = 'captcha_was_valid_'.$this->get_id();
        if ($set === null) {
            $was = isset($_SESSION[$prop]) && $_SESSION[$prop] + $this['valid_for'] > time();
            if ($was) {
                $this['was_valid'] = true;
                $this['was_valid_time_left'] = $_SESSION[$prop] + $this['valid_for'] - time();
            }
            return $was;
        }
        if ($set === true) {
            $this['was_valid'] = true;
            $_SESSION[$prop] = time();
        } else {
            unset($_SESSION[$prop]);
        }
        return $this;
    }
}