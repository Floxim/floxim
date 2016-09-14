<?php

namespace Floxim\Floxim\Asset\Less;

use \Floxim\Floxim\System\Fx as fx;

class MixinDefaultsParser {
    
    public $isPreEvalVisitor = true;
    
    protected $output = null;
    
    protected $mixin_name = null;
    
    protected $vars = null;

    public function __construct($mixin_name, &$vars)
    {
        $this->mixin_name = $mixin_name;
        $this->vars =& $vars;
        $this->output = new Tweaker\Output();
    }

    public function run($root)
    {
        foreach ($root->rules as $rule) {
            // handle style mixin definition - extract default values from arguments
            if ($rule instanceof \Less_Tree_Mixin_Definition && $rule->name === $this->mixin_name) {
                $this->extractDefaults($rule);
                break;
            }
        }
    }
    
    protected function extractDefaults($token)
    {
        foreach ($token->params as $arg) {
            $var_name = substr($arg['name'], 1);
            if (!isset($this->vars[$var_name])) {
                continue;
            }
            $c_var =& $this->vars[$var_name];
            $value = $this->output->get($arg['value'], false);
            $parts = null;
            $match_units = preg_match("~^[\d\.]+(em|rem|px|pt|%|vh|vw)~", $value, $parts);
            if ($match_units) {
                if (!isset($c_var['type'])) {
                    $c_var['type'] = 'number';
                }
                if ($c_var['type'] === 'number' && !isset($c_var['units'])) {
                    $c_var['units'] = $parts[1];
                }
            }
            $c_var['value'] = $value;
        }
    }
}