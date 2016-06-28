<?php

namespace Floxim\Floxim\Asset\Less\Tweaker;

use \Floxim\Floxim\System\Fx as fx;


class PostProcessor extends \Less_VisitorReplacing {

    public function run($root) {
        $res = $this->visitObj($root);
        //fx::cdebug($res);
        return $res;
    }

    public function visitParen($obj) {
        $obj->value->_preserve = true;
        return $obj;
    }

    public function visitComment() {
        return array();
    }

    protected function isHardSelector($sel)
    {
        foreach ($sel->elements as $el) {
            if ($el instanceof HardSelectorElement) {
                return true;
            }
        }
        return false;
    }

    protected function sortHardSelector($sel)
    {
        $elements = array();
        $hard = null;
        foreach ($sel->elements as $el) {
            if ($el instanceof HardSelectorElement) {
                $hard = $el;
            } else {
                $elements []= $el;
            }
        }
        $elements []= $hard;
        $sel->elements = $elements;
        return $sel;
    }

    public function visitRuleset ($obj) {
        if (isset($obj->_is_hard_selected) && $obj->_is_hard_selected) {
            fx::cdebug($obj->paths);
            foreach ($obj->paths as &$c_path) {
                $sorted_path = array();
                $hard_selector = null;
                $prev_selector = null;
                foreach ($c_path as $c_sel) {
                    if ($this->isHardSelector($c_sel)) {
                        $hard_selector = $c_sel;
                        $this->sortHardSelector($hard_selector);
                        if ( preg_match("~^:~", $hard_selector->elements[0]->value) && $prev_selector) {
                            fx::cdebug($hard_selector->elements[0]->value);
                            //$first_el = array_shift($hard_selector->elements);
                            //$prev_selector->elements []= $first_el;
                        }

                    } else {
                        $sorted_path []= $c_sel;
                        $prev_selector = $c_sel;
                    }
                }
                $sorted_path []= $hard_selector;
                $c_path = $sorted_path;
            }
            return $obj;
        }
        $rules = array();
        foreach ($obj->rules as $rule) {
            $finder = new HardFinder();
            $finder->visitObj($rule);
            if ($finder->found) {
                $double_hard_selected = $this->splitDoubleHardSelected($rule);
                if ($double_hard_selected) {
                    foreach ($double_hard_selected as $sub_rules) {
                        $rules []= $sub_rules;
                    }
                } else {
                    $rules [] = $rule;
                }
            }
        }
        if (count($rules) === 0) {
            $obj->rules = array();
        }
        $obj->rules = $rules;

        return $obj;
    }

    protected function splitDoubleHardSelected($rule)
    {
        if (! $rule instanceof \Less_Tree_Ruleset || !$rule->paths) {
            return;
        }
        $hard_selected_paths = array();
        foreach ($rule->paths as $c_path) {
            foreach ($c_path as $c_selector) {
                if ($this->isHardSelector($c_selector)) {
                    $hard_selected_paths []= $c_path;
                    continue;
                }
            }
        }
        if (count($hard_selected_paths) < 2) {
            return;
        }
        $sets = array();
        foreach ($hard_selected_paths as $c_path) {
            $sub_ruleset = new \Less_Tree_Ruleset($rule->selectors, $rule->rules, $rule->strictImports);
            $sub_ruleset->_is_hard_selected = true;
            $sub_ruleset->paths = array($c_path);
            $sets []= $sub_ruleset;
        }
        fx::cdebug($rule, $sets);
        return $sets;
    }
}