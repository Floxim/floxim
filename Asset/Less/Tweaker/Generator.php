<?php

namespace Floxim\Floxim\Asset\Less\Tweaker;

use Floxim\Floxim\System\Fx as fx;

class Generator extends \Less_VisitorReplacing {
    
    public $isPreEvalVisitor = true;
    
    protected $vars = array();

    public function __construct($vars) {
        $this->vars = $vars;
        parent::__construct();
    }
    
    public function visitOperation($obj) {
        return new \Less_Tree_Expression( array(
            $obj->operands[0],
            new \Less_Tree_Quoted('"'.$obj->op.'"', $obj->op, true),
            $obj->operands[1]
        ));
    }
    
    public function visitNameValue($obj) {
        return array();
    }

    
    function run( $root ){

        foreach ($this->vars as $var) {
            $hard = new HardVar($var);
            $rule = new \Less_Tree_Rule('@' . $var, $hard);
            $root->rules [] = $rule;
        }

        $res =  $this->visitObj($root);
        return $res;
    }
}