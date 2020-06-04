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
            
            if (!isset($arg['value'])) {
                continue;
            }
            $c_val = $arg['value'];
            // extract default from vars yaml, e.g.
            // @background:@background
            if (
                isset($c_val->value) && 
                count($c_val->value) === 1 && 
                $c_val->value[0] instanceof \Less_Tree_Variable
            ) {
                $value = StyleBundle::getDefaultValue($c_var);
            } else {
                $value = $this->output->get($arg['value'], false);
                $value = preg_replace_callback(
                    "~(^|\s+)\.(\d)~", 
                    function($m) {
                        return $m[1].'0.'.$m[2];
                    }, 
                    $value
                );
            }
            $parts = null;
            $match_units = preg_match("~^[\-\d\.]+(em|rem|px|pt|%|vh|vw)~", $value, $parts);
            if ($match_units) {
                if (!isset($c_var['type'])) {
                    $c_var['type'] = 'number';
                }
                if ($c_var['type'] === 'number' && !isset($c_var['units'])) {
                    $c_var['units'] = $parts[1];
                }
            }
            if (isset($c_var['code']) && $value === '""') {
                $value = '';
            }
            $c_var['value'] = $value;
        }
    }
}