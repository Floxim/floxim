<?php

namespace Floxim\Floxim\Asset\Less\Tweaker\Style;

use \Floxim\Floxim\System\Fx as fx;

use \Floxim\Floxim\Asset\Less;


class Generator extends Less\Tweaker\Generator
{
    protected $style = null;

    protected $output = null;

    public function visitNameValue($obj) {
        return $obj;
    }

    public function __construct($vars, $style)
    {
        $this->style = $style;
        $this->output = new Less\Tweaker\Output();
        parent::__construct($vars);
    }

    public function visitVariable($obj)
    {
        $name = substr($obj->name, 1);
        return new Less\Tweaker\HardVar($name. (isset($this->vars[$name]) ? '-tweaked' : '') );
    }

    public function visitDetachedRuleset($obj)
    {
        $expr = "{\n";
        foreach ($obj->ruleset->rules as &$rule) {
            $rule = $this->visitObj($rule);
            $expr .= $this->output->get($rule);
        }
        $expr .= "\n}\n";
        $res = new Less\Tweaker\HardExpression($expr);
        return $res;
    }

    public function visitMixinCall($obj)
    {
        if ($obj->is_style_mixin_call_token) {
            return array($obj);
        }
        $name = $obj->selector->elements[0]->value;

        $expr = $name .'(';

        foreach ($obj->arguments as &$arg) {
            $val = $this->visitObj($arg['value']);
            $expr .= $this->output->get($val);
        }

        $expr .= ');';

        return new Less\Tweaker\HardExpression($expr);
    }

    public function visitComment()
    {
        return array();
    }

    protected $inner_vars = array();

    public function visitRule($obj)
    {
        if (!$obj->variable) {
            return $obj;
        }
        $expr = $obj->name.": ";
        foreach ($obj->value->value as $part) {
            $part = $this->visitObj($part);
            $expr .= $this->output->get($part, false).' ';
        }
        $expr .= '; ';
        $this->inner_vars []= new Less\Tweaker\HardExpression($expr);
        return $obj;
    }

    public function run($root) {

        $rules = array();

        foreach ($root->rules as $rule) {
            if ($rule->type !== 'Ruleset') {
                $rules []= $rule;
            }
        }

        $rules []= MixinCallGenerator::generateMixinCall($this->vars, $this->style);

        $root->rules = $rules;
        $res =  $this->visitObj($root);
        foreach ($this->inner_vars as $var) {
            $root->rules []= $var;
        }
        return $res;
    }
}