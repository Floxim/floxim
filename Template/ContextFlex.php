<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class ContextFlex extends Context {
    public $vars = array();
    
    public function getFromTop($var)
    {
        return isset($this->stack[1][$var]) ? $this->stack[1][$var] : null;
    }
    
    protected $last_var_level = null;
    
    public function getLastVarLevel()
    {
        return $this->last_var_level;
    }
    
    public function push($data = array(), $meta = array()) {
        
        $l = ++$this->level;
        
        $this->stack [$l] = $data;
        $this->meta[$l] = array_merge(
            array(
                'transparent' => false,
                'autopop'     => false
                ), 
            $meta
        );
        //$this->vars[$l] = array();
        $this->misses[$l] = array();
    }
    
    public function pop() {
        unset($this->stack[$this->level]);
        $meta = $this->meta[$this->level];
        unset($this->meta[$this->level]);
        //unset($this->vars[$this->level]);
        unset($this->misses[$this->level]);
        $this->level--;
        if ($meta['autopop']) {
            $this->pop();
        }
    }
    
    public function set($var, $val)
    {   
        if ($this->level === 0 || !is_array($this->stack[$this->level])) {
            $this->push(array($var => $val), array('transparent' => true, 'autopop' => true));
            return;
        }
        
        $this->stack[$this->level][$var] = $val;
        unset($this->misses[$this->level][$var]);
        //$this->vars[$this->level][$var]= $this->level;
        //$this->vars[$this->level][$var]= $val;
    }
    
    protected $misses = array();
    
    public function get($name = null, $context_offset = null)
    {
        // neither var name nor context offset - return current context
        if (is_null($name) && is_null($context_offset)) {
            for ($i = $this->level; $i >= 0; $i--) {
                if (!$this->meta[$i]['transparent']) {
                    return $this->stack[$i];
                }
            }
            return end($this->stack);
        }
        
        if (!is_null($context_offset)) {
            $context_position = 0;
            for ($i = $this->level; $i >= 0; $i--) {
                if (!$this->meta[$i]['transparent']) {
                    $context_position++;
                }
                if ($context_position < $context_offset) {
                    continue;
                }
                if (isset($this->stack[$i][$name])) {
                    return $this->stack[$i][$name];
                }
                if ($context_position > $context_offset) {
                    return null;
                }
            }
            return null;
        }
        
        for ($i = $this->level; $i >= 0; $i--) {
            
            if (isset($this->misses[$i][$name])) {
                continue;
            }
            if (isset($this->stack[$i][$name])) {
                $this->last_var_level = $i;
                return $this->stack[$i][$name];
            } 
            $this->misses[$i][$name] = true;
        }
        $this->last_var_level = null;
        return null;
    }
    
    public function getVarMeta($var_name = null, $source = null)
    {
        if ($var_name === null) {
            return array();
        }
        if ($source instanceof Entity) {
            $meta = $source->getFieldMeta($var_name);
            return is_array($meta) ? $meta : array();
        }
        if (!is_null( $source )) {
            return array();
        }
        for ($i = $this->level; $i >= 0; $i--) {
            if (!isset($this->stack[$i])) {
                continue;
            }
            $e = $this->stack[$i];
            if ($e instanceof Entity && isset($e[$var_name])) {
                $meta = $e->getFieldMeta($var_name);
                return is_array($meta) ? $meta : array();
            }
        }
        return array();
    }
    
}