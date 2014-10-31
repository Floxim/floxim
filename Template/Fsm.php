<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class Fsm
{
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

    public function pushState($state)
    {
        $this->state_stack [] = $this->state;
        $this->setState($state);
    }

    public function setState($state)
    {
        if ($state === $this->state) {
            return;
        }
        $this->prev_state = $this->state;
        $this->state = $state;
    }

    public function popState()
    {
        if (count($this->state_stack) == 0) {
            return null;
        }
        $this->setState(array_pop($this->state_stack));
        return $this->state;
    }

    protected $any_rules = array();


    public function addRule($first_state, $char, $new_state, $callback = null)
    {
        if ($callback && !is_callable($callback)) {
            if (is_string($callback) && method_exists($this, $callback)) {
                $callback = array($this, $callback);
            } else {
                $callback = false;
            }
        }
        if (!$callback) {
            $callback = array($this, 'defaultCallback');
        }

        if (!is_array($first_state)) {
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
                $this->any_rules [] = $rule;
                foreach (array_keys($this->rules) as $existing_state) {
                    $this->rules[$existing_state][] = $rule;
                }
                continue;
            }

            if (!isset($this->rules[$c_first_state])) {
                $this->rules[$c_first_state] = $this->any_rules;
            }
            $this->rules [$c_first_state] [] = $rule;
        }
    }

    public function parse($string)
    {
        $this->state_stack = array();
        $this->prev_state = null;
        $this->position = 0;
        $this->state = $this->init_state;
        $this->parts = $this->splitString($string);
        if ($this->debug) {
            fx::debug($string, $this->parts);
        }
        while (($ch = current($this->parts)) !== false) {
            $this->position += mb_strlen($ch);
            $this->step($ch);
            next($this->parts);
        }
        return $this->res;
    }

    public function getNext($count = 1)
    {
        $moved = 0;
        $res = array();
        for ($i = 0; $i < $count; $i++) {
            $item = next($this->parts);
            if ($item === false) {
                end($this->parts);
                break;
            }
            $res[] = $item;
            $moved++;
        }
        for ($i = 0; $i < $moved; $i++) {
            prev($this->parts);
        }
        return $res;
    }

    public function getPrev($count = 1)
    {
        $moved = 0;
        $res = array();
        for ($i = 0; $i < $count; $i++) {
            $item = prev($this->parts);
            if ($item === false) {
                reset($this->parts);
                break;
            }
            $res[] = $item;
            $moved++;
        }
        for ($i = 0; $i < $moved; $i++) {
            next($this->parts);
        }
        return $res;
    }


    public function step($ch)
    {
        $callback_res = false;
        if (!isset($this->rules[$this->state])) {
            $this->defaultCallback($ch);
            return false;
        }

        foreach ($this->rules[$this->state] as $rule) {
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
                $this->setState($new_state);
            }
            return;
        }
        // won't work no rule
        $this->defaultCallback($ch);
        return $callback_res;
    }

    public function splitString($string)
    {
        return preg_split(
            $this->getSplitRegexp(),
            $string,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
    }

    public $split_regexp = '~(.)~';

    public function getSplitRegexp()
    {
        return $this->split_regexp;
    }

    public function defaultCallback($ch)
    {

    }
}