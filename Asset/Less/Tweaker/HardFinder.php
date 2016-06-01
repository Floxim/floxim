<?php

namespace Floxim\Floxim\Asset\Less\Tweaker;


class HardFinder extends \Less_VisitorReplacing {
    public $found = false;

    public function visitObj($obj) {
        if ($obj instanceof HardVar || $obj instanceof HardExpression || $obj->_preserve) {
            $this->found = true;
        }
        return parent::visitObj($obj);
    }
}