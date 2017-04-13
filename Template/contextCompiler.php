<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class contextCompiler extends Compiler {
    
    protected $context = null;
    
    protected $root_template = null;
    
    public function __construct($context, $root_template) {
        $this->context = $context;
        $this->root_template = $root_template;
    }
    
    protected function findTemplateToken($tree, $id)
    {
        foreach ($tree->getChildren() as $token) {
            $is_preset = false;
            if ($token->name === 'preset') {
                $token = $this->presetToTemplate($token);
                $is_preset = true;
            }
            
            if ( ($token->name === 'template' || $is_preset) && $token->getProp('id') === $id) {
                return $token;
            }


            if ( ($sub_res = $this->findTemplateToken($token, $id)) ) {
                return $sub_res;
            }
        }
    }
    
    public function compile($tree) {
        
        $root = $this->findTemplateToken($tree, $this->root_template);
        $res = $this->childrenToCode($root);
        $res = self::addTabs($res);
        return $res;
    }
    
    public function tokenBemElementToCode($token)
    {
        return '';
    }
    
    public function tokenBemBlockToCode($token) 
    {
        return '';
    }
    
}