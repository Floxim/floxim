<?php

namespace Floxim\Floxim\Asset\Less;

use \Floxim\Floxim\System\Fx as fx;

class MetaParser {
    
    public $isPreEvalVisitor = true;
    
    protected $parser = null;
    protected $current_values = null;
    
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

    public function getStyles()
    {
        return $this->styles;
    }
    
    protected $styles = array();
    
    protected function registerStyle($params, $token)
    {
        $file = $token->currentFileInfo['filename'];
        $file_name = fx::path()->fileName($file);
        $style_name = preg_replace("~\.less$~", '', $file_name);
        $params['keyword'] = $style_name;
        $params['file'] = $file;
        if (!isset($params['vars'])) {
            $params['vars'] = array();
        }
        foreach ($params['vars'] as $vk => &$vv) {
            $vv['name'] = $vk;
        }
        $this->styles []= $params;
    }
    
    protected function processRules($token)
    {
        $res = array();
        $current_comment = null;
        fx::cdebug($token->rules);
        foreach ($token->rules as $rule) {
            // handle comment
            if ( $rule instanceof \Less_Tree_Comment ) {
                $current_comment = $this->parseComment($rule);
                if (isset($current_comment['for']) && $current_comment['for'] === 'style') {
                    $this->registerStyle($current_comment, $rule);
                }
                continue;
            }
            // handle style mixin definition - extract default values from arguments
            if ($rule instanceof \Less_Tree_Mixin_Definition && $current_comment && $current_comment['for'] === 'style') {
                $this->extractDefaults($rule);
                $current_comment = null;
            }
            // handle variable
            if ($rule->variable && $current_comment && $current_comment['for'] === 'var') {
                $this->registerVar($current_comment, $rule);
                $current_comment = null;
            }
            $res []= $rule;
        }
        $token->rules = $res;
    }
    
    protected function extractDefaults($token)
    {

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