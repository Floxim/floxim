<?php

namespace Floxim\Floxim\Asset\Less\Tweaker;

use \Floxim\Floxim\System\Fx as fx;


class HardSelectorElement extends \Less_Tree_Element
{
    public function __construct($cond = null)
    {
        $cond_text = '';
        if ($cond) {
            $cond_text .= ' when ';

            if ($cond->negate) {
                $cond_text .= 'not ';
            }

            $cond_text .= '(';


            $cond_text .= Output::grab($cond->lvalue);
            $cond_text .= ' ' . $cond->op . ' ';
            $cond_text .= Output::grab($cond->rvalue);

            $cond_text .= ') ';
        }

        parent::__construct('', $cond_text);
    }

    public function compile($env)
    {
        return $this;
    }

}