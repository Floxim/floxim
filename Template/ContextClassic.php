<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class ContextClassic extends Context {
    public function get($name = null, $context_offset = null)
    {
        // neither var name nor context offset - return current context
        $stack_length = count($this->stack) - 1;
        if (!$name && !$context_offset) {
            for ($i = $stack_length; $i >= 0; $i--) {
                $c_meta = $this->meta[$i];
                if (!$c_meta['transparent']) {
                    return $this->stack[$i];
                }
            }
            return end($this->stack);
        }

        if (!is_null($context_offset)) {
            $context_position = -1;
            for ($i = $stack_length; $i >= 0; $i--) {
                $cc = $this->stack[$i];
                $c_meta = $this->meta[$i];
                if (!$c_meta['transparent']) {
                    $context_position++;
                }
                if ($context_position === $context_offset) {
                    if (!$name) {
                        return $cc;
                    }

                    if (is_array($cc)) {
                        if (array_key_exists($name, $cc)) {
                            return $cc[$name];
                        }
                    } elseif ($cc instanceof \ArrayAccess) {
                        if (isset($cc[$name])) {
                            return $cc[$name];
                        }
                    } elseif (is_object($cc) && isset($cc->$name)) {
                        return $cc->$name;
                    }
                    continue;
                }
                if ($context_position > $context_offset) {
                    return null;
                }
            }
            return null;
        }
        for ($i = $stack_length; $i >= 0; $i--) {
            $cc = $this->stack[$i];
            if (
                ($cc instanceof \ArrayAccess && isset($cc[$name])) ||
                (is_array($cc) && array_key_exists($name, $cc))
            ) {
                return $cc[$name];
            }
        }
        return null;
    }
    
    public function push($data = array(), $meta = array())
    {
        $this->stack [] = $data;
        $meta = array_merge(array(
            'transparent' => false,
            'autopop'     => false
        ), $meta);
        $this->meta[] = $meta;
    }
    
    public function pop()
    {
        array_pop($this->stack);
        $meta = array_pop($this->meta);
        if ($meta['autopop']) {
            array_pop($this->stack);
            array_pop($this->meta);
        }
    }
    
    public function set($var, $val)
    {
        $stack_count = count($this->stack);
        if ($stack_count == 0) {
            $this->push(array(), array('transparent' => true));
        }
        if (!is_array($this->stack[$stack_count - 1])) {
            $this->push(array(), array('transparent' => true, 'autopop' => true));
            $stack_count++;
        }
        $this->stack[$stack_count - 1][$var] = $val;
    }
    
    public function getVarMeta($var_name = null, $source = null)
    {
        if ($var_name === null) {
            return array();
        }
        if ($source && $source instanceof Entity) {
            $meta = $source->getFieldMeta($var_name);
            return is_array($meta) ? $meta : array();
        }
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if (!($this->stack[$i] instanceof Entity)) {
                continue;
            }
            if (($meta = $this->stack[$i]->getFieldMeta($var_name))) {
                return $meta;
            }
        }
        return array();
    }
}