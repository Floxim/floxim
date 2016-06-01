<?php

namespace Floxim\Floxim\Asset\Less\Bem;

use \Floxim\Floxim\System\Fx as fx;



class Processor {

    public function run($root)
    {
        foreach ($root->rules as $r) {
            if (!is_array($r->paths)) {
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
        $stack = new Stack();
    	foreach ($p as $sel) {
            foreach ($sel->elements as $el) {
                $stack->push($el);
            }
        }
        
        if ($stack->has_special_rules) {
            return $stack->getPath();
        }
    }
}