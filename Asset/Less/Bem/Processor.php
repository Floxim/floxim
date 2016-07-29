<?php

namespace Floxim\Floxim\Asset\Less\Bem;

use \Floxim\Floxim\System\Fx as fx;



class Processor {

    public function run($root)
    {
        foreach ($root->rules as $r) {
            if (!isset($r->paths) || !is_array($r->paths)) {
                if (isset($r->rules)) {
                    $this->run($r);
                }
                continue;
            }
            foreach ($r->paths as &$p) {
                $res = $this->processPath($p);
                if ($res) {
                    $p = $res;
                }
            }
        }
    }
    
    public function processPath($p) {
        $stack = new Stack($p);
        /*
    	foreach ($p as $sel) {
            $group = array();
            foreach ($sel->elements as $el) {
                if ($el->combinator !== '') {
                    $stack->pushGroup($group);
                    $group = array();
                    
                }
                $group []= $el;
                //$stack->push($el);
            }
            if (count($group) > 0) {
                $stack->pushGroup($group);
            }
        }
        */
        if ($stack->has_special_rules) {
            return $stack->getPath();
        }
    }
}