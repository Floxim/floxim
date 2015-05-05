<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class ContextFast extends Context {
    public $vars = array();
    //protected $level = -1;
    
    public function push($data = array(), $meta = array()) {
        
        $l = ++$this->level;
        
        if ($data instanceof Entity || $data instanceof Loop) {
            $vars = $data->getAvailableOffsetKeys();
            foreach ($vars as &$v) {
                $v = $l;
            }
        } elseif (is_array($data)) {
            $vars = array_fill_keys(array_keys($data), $l);
        } else {
            $vars = array();
        }
        
        $this->stack [$l] = $data;
        $this->meta[$l] = array_merge(
            array(
                'transparent' => false,
                'autopop'     => false
                ), 
            $meta
        );
        
        $this->vars[$l]= $l === 1 ? $vars : array_merge($this->vars[$l - 1], $vars);
    }
    
    public function pop() {
        //array_pop($this->stack);
        unset($this->stack[$this->level]);
        //$meta = array_pop($this->meta);
        $meta = $this->meta[$this->level];
        unset($this->meta[$this->level]);
        unset($this->vars[$this->level]);
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
        $this->vars[$this->level][$var]= $this->level;
    }
    
    public function get($name = null, $context_offset = null)
    {
        /*
        if (!isset($this->vars[$this->level][$name])) {
            return null;
        }
        
        //if (is_null($context_offset)) {
        return $this->stack[$this->vars[$this->level][$name]][$name];
        //}
        */
        // neither var name nor context offset - return current context
        if (is_null($name) && is_null($context_offset)) {
            for ($i = $this->level; $i >= 0; $i--) {
                if (!$this->meta[$i]['transparent']) {
                    return $this->stack[$i];
                }
            }
            return end($this->stack);
        }
        
        if (!isset($this->vars[$this->level][$name])) {
            return null;
        }
        
        if (is_null($context_offset)) {
            return $this->stack[$this->vars[$this->level][$name]][$name];
        }
        
        $context_position = 0;
        for ($i = $this->level; $i >= 0; $i--) {
            if (!$this->meta[$i]['transparent']) {
                $context_position++;
            }
            
            if ($context_position < $context_offset) {
                continue;
            }
            if (isset($this->vars[$i][$name])) {
                return $this->stack[$i][$name];
            }
        }
        
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
        $entity = $this->stack[ $this->vars[$this->level][$var_name] ];
        if ($entity instanceof Entity) {
            $meta = $entity->getFieldMeta($var_name);
            return is_array($meta) ? $meta : array();
        }/*
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if (!($this->stack[$i] instanceof Entity)) {
                continue;
            }
            if (($meta = $this->stack[$i]->getFieldMeta($var_name))) {
                return $meta;
            }
        }*/
        return array();
    }
    
}