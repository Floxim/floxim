<?php
class fx_template_fsm {
    protected $rules = array();
    protected $res = array();
    public $debug = false;
    
    const STATE_ANY = 0;
    public $state = null;
    public $prev_state = null;
    public $state_stack = array();
    
    const RULE_CHAR = 1;
    const RULE_REGEXP = 2;
    const RULE_ARRAY = 3;
    
    public function push_state($state) {
        $this->state_stack []= $this->state;
        $this->set_state($state);
    }
    
    public function set_state($state) {
        if ($state === $this->state) {
            return;
        }
        $this->prev_state = $this->state;
        $this->state = $state;
    }
    
    public function pop_state() {
        if (count($this->state_stack) == 0) {
            return null;
        }
        $this->set_state(array_pop($this->state_stack));
        return $this->state;
    }
    
    protected $any_rules = array();


    public function add_rule($first_state, $char, $new_state, $callback = null) {
        if ($callback && !is_callable($callback)) {
            if (is_string($callback) && method_exists($this, $callback)) {
                $callback = array($this, $callback);
            } else {
                $callback = false;
            }
        }
        if (!$callback) {
            $callback = array($this, 'default_callback');
        }
        
        if (!is_array($first_state)){
            $first_state = array($first_state);
        }
        
        if (is_array($char)) {
            $rule_type = self::RULE_ARRAY;
        } elseif (preg_match('~^\~.+\~[ismgu]*$~', $char)) {
            $rule_type = self::RULE_REGEXP;
        } else {
            $rule_type = self::RULE_CHAR;
        }
        
        foreach ($first_state as $c_first_state) {
            $rule = array(
                $char, 
                $new_state, 
                $callback, 
                $rule_type
            );
            if ($c_first_state === self::STATE_ANY) {
                $this->any_rules []= $rule;
                foreach (array_keys($this->rules) as $existing_state) {
                    $this->rules[$existing_state][]= $rule;
                }
                continue;
            }
            
            if (!isset($this->rules[$c_first_state])) {
                $this->rules[$c_first_state] = $this->any_rules;
            }
            $this->rules [$c_first_state] []= $rule;
        }
    }
    
    public function parse($string) {
        $this->state_stack = array();
        $this->prev_state = null;
        $this->position = 0;
        $this->state = $this->init_state;
        $this->parts = $this->split_string($string);
        if ($this->debug) {
            fx::debug($string, $this->parts);
            foreach ($this->parts as $p) {
                fx::debug('~'.$p.'~');
            }
        }
        while ( ($ch = current($this->parts)) !== false) {
            $this->position += mb_strlen($ch);
            $this->step($ch);
            next($this->parts);
        }
        return $this->res;
    }
    
    public function get_next($count = 1) {
        $moved = 0;
        $res = array();
        for ($i = 0; $i < $count; $i++) {
            $item = next($this->parts);
            if ($item === false) {
                end($this->parts);
                break;
            }
            $res[]= $item;
            $moved++;
        }
        for ($i = 0; $i < $moved; $i++) {
            prev($this->parts);
        }
        return $res;
    }
    
    public function get_prev($count = 1) {
        $moved = 0;
        $res = array();
        for ($i = 0; $i < $count; $i++) {
            $item = prev($this->parts);
            if ($item === false) {
                reset($this->parts);
                break;
            }
            $res[]= $item;
            $moved++;
        }
        for ($i = 0; $i < $moved; $i++) {
            next($this->parts);
        }
        return $res;
    }


    public function step($ch) {
        $callback_res = false;
        if (!isset($this->rules[$this->state])) {
            $this->default_callback($ch);
            return false;
        }
        foreach($this->rules[$this->state] as $rule) {
            list($rule_val, $new_state, $callback, $rule_type) = $rule;
            if (
                ($rule_type == self::RULE_CHAR && $ch != $rule_val) ||
                ($rule_type == self::RULE_REGEXP && !preg_match($rule_val, $ch)) ||
                ($rule_type == self::RULE_ARRAY && !in_array($ch, $rule_val))
            ) {
                continue;
            }
            $callback_res = $callback ? call_user_func($callback, $ch) : true;
            if ($callback_res === false) {
                continue;
            }
            if ($new_state) {
                $this->set_state($new_state);   
            }
            return;
        }
        // won't work no rule
        $this->default_callback($ch);
        return $callback_res;
    }
    
    public function split_string($string) {
        return preg_split(
            $this->get_split_regexp(), 
            $string, 
            -1, 
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
    }
    
    public $split_regexp = '~(.)~';
    public function get_split_regexp() {
        return $this->split_regexp;
    }
    
    public function default_callback($ch) {
        
    }
}