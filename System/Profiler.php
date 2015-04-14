<?php

namespace Floxim\Floxim\System;

class Profiler
{
    protected $level = 0;
    public $data = array();
    protected $stack = array();
    
    protected $openTags = array();

    const T_BLOCK = 1;
    const T_TAG = 2;

    protected $tags = array();
    
    public function hasData()
    {
        return count($this->tags > 0) || count($this->data) > 0;
    }

    public function block($name)
    {
        $this->level++;
        $this->tags = array();
        $this->stack [] = array($name, microtime(true), self::T_BLOCK);
        return $this;
    }

    public function tag($name)
    {
        $this->level++;
        $is_nested = in_array($name, $this->openTags);
        $this->stack [] = array($name, microtime(true), self::T_TAG, $is_nested);
        $this->openTags []= $name;
        return $this;
    }

    protected $stopped_type = null;

    public function stop()
    {
        $meta = array_pop($this->stack);
        $c_type = $meta[2];
        $this->stopped_type = $c_type;
        $time = microtime(true) - $meta[1];
        if ($c_type == self::T_BLOCK) {
            $this->data [] = array($meta[0], $this->level, $time, $this->tags);
            $this->tags = array();
        } else {
            if (!isset($this->tags[$meta[0]])) {
                $this->tags[$meta[0]] = 0;
            }
            $this->tags[$meta[0]] += $meta[3] ? 0 : $time;
            array_pop($this->openTags);
        }

        $this->level--;
        return $this;
    }

    public function then($name)
    {
        $this->stop();
        if ($this->stopped_type == self::T_BLOCK) {
            $this->block($name);
        } else {
            $this->tag($name);
        }
        return $this;
    }

    public function result($plain_data)
    {
        $root = new ProfilerBlock(array('root', 0, 0, array()));
        $roots = array($root);
        while ($b = array_pop($plain_data)) {
            $b = new ProfilerBlock($b);
            do {
                $c_target = array_pop($roots);
            } while ($b->level <= $c_target->level);
            $c_target->addChild($b);
            $roots[] = $c_target;
            $roots[] = $b;
        }
        return $root;
    }

    public function show()
    {
        $res = $this->result($this->data);
        ob_start();
        $res->show();
        return ob_get_clean();
    }
    
    public function getSortedTags()
    {
        $tags = $this->tags;
        asort($tags);
        $tags = array_reverse($tags);
        foreach ($tags as $t => &$v) {
            $v = round($v, 4);
        }
        return $tags;
    }
}