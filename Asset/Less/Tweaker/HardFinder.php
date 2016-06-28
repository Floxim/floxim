<?php

namespace Floxim\Floxim\Asset\Less\Tweaker;

use \Floxim\Floxim\System\Fx as fx;


class HardFinder extends \Less_VisitorReplacing {
    public $found = false;

    protected $current_ruleset;

    public function visitObj($obj) {
        if ($obj instanceof \Less_Tree_Ruleset) {
            $this->current_ruleset = $obj;
        }

        if ($obj instanceof HardVar || $obj instanceof HardExpression || $obj instanceof HardSelectorElement || $obj->_preserve) {
            $this->found = true;
        }
        if ($obj instanceof HardSelectorElement && $this->current_ruleset) {
            $this->current_ruleset->_is_hard_selected = true;
        }
        return parent::visitObj($obj);
    }
}