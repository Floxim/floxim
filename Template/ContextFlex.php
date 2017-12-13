<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class ContextFlex extends Context {
    
    public $vars = array();
    
    protected $var_props = array();
    
    protected $scope_path = array();
    
    protected $visual_id = null;
    
    public function getFromTop($var)
    {
        return isset($this->stack[1][$var]) ? $this->stack[1][$var] : null;
    }
    
    protected $last_var_level = null;
    
    public function getLastVarLevel()
    {
        return $this->last_var_level;
    }
    
    public function push($data = array(), $meta = array(), $var_props = array()) {
        
        $l = ++$this->level;
        
        $this->stack [$l] = $data;
        $this->meta[$l] = array_merge(
            array(
                'transparent' => false,
                'autopop'     => false,
                'scope_depth' => count($this->scope_path)
                ), 
            $meta
        );
        $this->var_props[$l] = $var_props;
        $this->misses[$l] = array();
    }
    
    public function pop() {
        unset($this->stack[$this->level]);
        $meta = $this->meta[$this->level];
        unset($this->meta[$this->level]);
        unset($this->misses[$this->level]);
        unset($this->var_props[$this->level]);
        $this->level--;
        if ($meta['autopop']) {
            $this->pop();
        }
    }
    
    public function set($var, $val, $meta = null)
    {   
        if ($this->level === 0 || !is_array($this->stack[$this->level])) {
            $this->push(
                array($var => $val), 
                array('transparent' => true, 'autopop' => true),
                $meta ? array($var => $meta) : array()
            );
            return;
        }
        
        $this->stack[$this->level][$var] = $val;
        if ($meta) {
            $this->var_props[$this->level][$var] = $meta;
        }
        unset($this->misses[$this->level][$var]);
    }
    
    public function digSet($var, $path, $val)
    {
        $cv = $this->get($var);
        if (!$cv) {
            return;
        }
        $level = $this->last_var_level;
        $res = fx::digSet($cv, $path, $val);
        $this->stack[$level][$var] = $res;
    }
    
    protected $misses = array();
    
    public function get($name = null, $context_offset = null)
    {
        fx::count('get');
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
                if ( 
                    (is_array($this->stack[$i]) && array_key_exists($name, $this->stack[$i])) 
                    || isset($this->stack[$i][$name])
                ) {
                    return $this->stack[$i][$name];
                }
                if ($context_position > $context_offset) {
                    return null;
                }
            }
            return null;
        }
        
        for ($i = $this->level; $i > 0; $i--) {
            
            if (isset($this->misses[$i][$name])) {
                continue;
            }
            if ( 
                    ( is_array($this->stack[$i]) && array_key_exists($name, $this->stack[$i]) ) 
                    || isset($this->stack[$i][$name])
            ) {
                $this->last_var_level = $i;
                return $this->stack[$i][$name];
            } 
            $this->misses[$i][$name] = true;
        }
        $this->last_var_level = null;
        return null;
    }
    
    public function getFromScope($name)
    {
        $c_depth = count($this->scope_path) - 1;
        for ($i = $this->level; $i > 0; $i--) {
            
            if ($this->meta[$i]['scope_depth'] < $c_depth) {
                break;
            }
            if (isset($this->misses[$i][$name])) {
                continue;
            }
            if ( 
                    ( is_array($this->stack[$i]) && array_key_exists($name, $this->stack[$i]) ) 
                    || isset($this->stack[$i][$name])
            ) {
                $this->last_var_level = $i;
                return $this->stack[$i][$name];
            } 
            $this->misses[$i][$name] = true;
        }
        return null;
    }
    
    public function getVisual($name = null)
    {
        for ($i = $this->level; $i >= 0; $i--) {
            
            if (isset($this->misses[$i][$name])) {
                continue;
            }
            if (!isset($this->stack[$i]) || !is_array($this->stack[$i])) {
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
    
    public function getAll($name)
    {
        $res = array();
        for ($i = $this->level; $i >= 0; $i--) {
            if (isset($this->misses[$i][$name])) {
                continue;
            }
            if (isset($this->stack[$i][$name])) {
                $res[$i] = $this->stack[$i][$name];
            } else {
                $this->misses[$i][$name] = true;
            }
        }
        return $res;
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
            if (isset($this->var_props[$i][$var_name])) {
                return $this->var_props[$i][$var_name];
            }
        }
        return array();
    }
    
    protected $scope_depth = 0;
    
    public function startScope($name)
    {
        fx::count('start_scope');
        $this->scope_path []= $name;
    }
    
    public function stopScope() 
    {
        array_pop($this->scope_path);
    }
    
    public function getScopePath($offset = 0)
    {
        fx::count('scope_path');
        return join(".", array_slice($this->scope_path, $offset));
    }
    
    public function getScopeDepth()
    {
        fx::count('scope_depth');
        return count($this->scope_path);
    }
    
    public function getScopePrefix($separator = '-') 
    {
        fx::count('scope_prefix_'.$separator);
        return count($this->scope_path) === 0 ? '' : join($separator, $this->scope_path).$separator;
    }
    
    public function getVisualId()
    {
        if (is_null($this->visual_id)) {
            $ib = $this->getFromTop('infoblock');
            if (!$ib) {
                $this->visual_id = 'new';
            } else {
                $vis = $ib->getVisual();
                if ($vis->isSaved()) {
                    $vis_id = $vis['id'];
                } else {
                    $vis_id = 'new';
                    fx::env('new_infoblock_visual', $vis);
                }
                $this->visual_id = $vis_id;
            }
        }
        return $this->visual_id;
    }
    
    public function getClosestEntity($type = null)
    {
        if ( $type instanceof \Closure ) {
            $check_callback = $type;
        } else {
            $check_callback = function($entity) use ($type) {
                if ( ! ($entity instanceof \Floxim\Floxim\System\Entity) ) {
                    return false;
                }
                if (!$type) {
                    return true;
                }
                return $entity->isInstanceOf($type);
            };
        }
        for ($i = $this->level; $i >= 0; $i--) {
            if ($check_callback($this->stack[$i])) {
                return $this->stack[$i];
            }
        }
    }
}