<?php

namespace Floxim\Floxim\Template;

class ModifierParser extends Fsm {
    
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
        $this->addRule(array(self::INIT, self::MODIFIER, self::PARAM), "~^\|+~", false, 'start_modifier');
        $this->addRule(array(self::MODIFIER, self::PARAM), ":", false, 'start_param');
        $this->addRule(array(self::MODIFIER, self::PARAM), '~[\"\']~', false, 'start_string');
        $this->addRule(array(self::QSTRING, self::DQSTRING), '~\\\?[\"\']~', false, 'end_string');
    }
    
    protected $c_mod = null;
    
    public function parse($s) {
        parent::parse($s);
        $this->bubble();
        return $this->res;
    }
    
    protected function bubble() {
        while ($this->state != self::INIT) {
            if ($this->state == self::PARAM) {
                $this->endParam();
            } elseif ($this->state == self::MODIFIER) {
                $this->endModifier();
            }
            $this->popState();
        }
    }
    
    public function startModifier($ch) {
        $this->bubble();
        $this->pushState(self::MODIFIER);
        $this->c_mod = array(
            'name' => '',
            'is_each' => $ch == '||',
            'args' => array()
        );
    }
    
    public function endModifier() {
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
        } elseif (preg_match("~\.[a-z0-9]+~", $m['name'])) {
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
    
    public function endParam() {
        $param = $this->stack;
        $this->c_mod['args'] []= trim($param);
        $this->stack = '';
    }
    
    public function startParam($ch) {
        if ($this->state == self::MODIFIER) {
            $this->c_mod['name'] = $this->stack;
        } else {
            $this->popState();
            $this->endParam();
        }
        $this->stack = '';
        $this->pushState(self::PARAM);
    }
    
    public function startString($ch) {
        $this->pushState($ch == '"' ? self::DQSTRING : self::QSTRING);
        $this->stack .= $ch;
    }
    
    public function endString($ch) {
        if (
            ($this->state == self::DQSTRING && ($ch == "'" || $ch == '\"')) ||
            ($this->state == self::QSTRING && ($ch == '"' || $ch == "\'"))
        ) {
                return false;
        }
        $this->popState();
        $this->stack .= $ch;
    }
    
    public function defaultCallback($ch) {
        $this->stack .= $ch;
    }
}