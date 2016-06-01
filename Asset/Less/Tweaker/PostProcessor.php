<?php

namespace Floxim\Floxim\Asset\Less\Tweaker;

use \Floxim\Floxim\System\Fx as fx;


class PostProcessor extends \Less_VisitorReplacing {

    public function run($root) {
        $res = $this->visitObj($root);
        return $res;
    }

    public function visitParen($obj) {
        $obj->value->_preserve = true;
        return $obj;
    }

    public function visitComment() {
        return array();
    }

    public function visitRuleset ($obj) {
        $rules = array();
        foreach ($obj->rules as $rule) {
            $finder = new HardFinder();
            $finder->visitObj($rule);
            if ($finder->found) {
                $rules []= $rule;
            }
        }
        if (count($rules) === 0) {
            $obj->rules = array();
        }
        $obj->rules = $rules;

        return $obj;
    }
}