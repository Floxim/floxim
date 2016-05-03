<?php

namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

class LessMetaParser {
    
    public $isPreEvalVisitor = true;
    
    public function run($root)
    {
        $this->processRules($root);
    }
    
    protected $vars = array();
    
    protected function registerVar($params, $token)
    {
        $var_name = preg_replace("~^@~", '', $token->name);
        $params['name'] = $var_name;
        $this->vars [$var_name]= $params;
    }
    
    public function getVars() 
    {
        return $this->vars;
    }
    
    protected $styles = array();
    
    protected function registerStyle($params, $token)
    {
        $file_name = fx::path()->fileName($token->currentFileInfo['filename']);
        $style_name = preg_replace("~\.less$~", '', $file_name);
        $params['keyword'] = $style_name;
        $this->styles []= $params;
    }
    
    protected function processRules($token)
    {
        $res = array();
        $current_comment = null;
        foreach ($token->rules as $rule) {
            if ( $rule instanceof \Less_Tree_Comment ) {
                $current_comment = $this->parseComment($rule);
                if (isset($current_comment['for']) && $current_comment['for'] === 'style') {
                    $this->registerStyle($current_comment, $rule);
                    $current_comment = null;
                }
                continue;
            }
            if ($rule->variable && $current_comment) {
                $this->registerVar($current_comment, $rule);
                $current_comment = null;
            }
            $res []= $rule;
        }
        $token->rules = $res;
    }
    
    
    
    protected function parseComment($token)
    {
        $text = $token->value;
        $text = str_replace("\n", ' ', $text);
        $text = preg_replace("~^\s*/\*+\s*|\s*\*+\/\s*$~s", '', $text);
        if (!preg_match("~^\{.+\}$~", $text)) {
            return;
        }
        $parsed = json_decode($text,1);
        if (!$parsed) {
            return;
        }
        return $this->prepareComment($parsed);
    }
    
    protected function prepareComment($parsed)
    {
        if (!isset($parsed['for'])) {
            if (isset($parsed['name']) || isset($parsed['params'])) {
                $parsed['for'] = 'style';
            } else {
                $parsed['for'] = 'var';
            }
        }
        return $parsed;
    }
}