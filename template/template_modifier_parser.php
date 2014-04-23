<?php
class fx_template_modifier_parser extends fx_template_fsm {
    
    public $split_regexp = "~(\|+|(?<!:):(?!:)|\,|\\\\?[\'\"])~";
    
    const INIT = 1;
    const MODIFIER = 2;
    const PARAM = 3;
    const QSTRING = 4;
    const DQSTRING = 5;
    
    protected $res = array();
    protected $stack = '';
    
    public function __construct() {
        $this->debug = false;
        $this->init_state = self::INIT;
        $this->add_rule(array(self::INIT, self::MODIFIER, self::PARAM), "~^\|+~", false, 'start_modifier');
        $this->add_rule(array(self::MODIFIER, self::PARAM), ":", false, 'start_param');
        $this->add_rule(array(self::MODIFIER, self::PARAM), '~[\"\']~', false, 'start_string');
        $this->add_rule(array(self::QSTRING, self::DQSTRING), '~\\\?[\"\']~', false, 'end_string');
    }
    
    protected $c_mod = null;
    
    public function parse($s) {
        parent::parse($s);
        $this->_bubble();
        return $this->res;
    }
    
    protected function _bubble() {
        while ($this->state != self::INIT) {
            if ($this->state == self::PARAM) {
                $this->end_param();
            } elseif ($this->state == self::MODIFIER) {
                $this->end_modifier();
            }
            $this->pop_state();
        }
    }
    
    public function start_modifier($ch) {
        $this->_bubble();
        $this->push_state(self::MODIFIER);
        $this->c_mod = array(
            'name' => '',
            'is_each' => $ch == '||',
            'args' => array()
        );
    }
    
    public function end_modifier() {
        if ($this->stack != '') {
            $this->c_mod['name'] = $this->stack;
            $this->stack = '';
        }
        $m = $this->c_mod;
        
        $m['name'] = trim($m['name']);
        
        // if mod name looks like arg, use default modifier
        if (count($m['args']) == 0 && preg_match("~^[\'\"]~", $m['name'])) {
            $m['args'] = array($m['name']);
            $m['name'] = '';
        } elseif (preg_match("~\.~", $m['name'])) {
            $m['name'] = preg_replace("~^\.~", '', $m['name']);
            $m['is_template']  = true;
            $parts = explode(' with ', $m['name'], 2);
            if (count($parts) == 2) {
                $m['name'] = trim($parts[0]);
                $m['with'] = trim($parts[1]);
            }
        }
        
        $this->res []= $m;
    }
    
    public function end_param() {
        $param = $this->stack;
        $this->c_mod['args'] []= trim($param);
        $this->stack = '';
    }
    
    public function start_param($ch) {
        if ($this->state == self::MODIFIER) {
            $this->c_mod['name'] = $this->stack;
        } else {
            $this->pop_state();
            $this->end_param();
        }
        $this->stack = '';
        $this->push_state(self::PARAM);
    }
    
    public function start_string($ch) {
        $this->push_state($ch == '"' ? self::DQSTRING : self::QSTRING);
        $this->stack .= $ch;
    }
    
    public function end_string($ch) {
        if (
            ($this->state == self::DQSTRING && ($ch == "'" || $ch == '\"')) ||
            ($this->state == self::QSTRING && ($ch == '"' || $ch == "\'"))
        ) {
                return false;
        }
        $this->pop_state();
        $this->stack .= $ch;
    }
    
    public function default_callback($ch) {
        $this->stack .= $ch;
    }
}