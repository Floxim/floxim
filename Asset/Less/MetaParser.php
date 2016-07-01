<?php

namespace Floxim\Floxim\Asset\Less;

use \Floxim\Floxim\System\Fx as fx;

use \Symfony\Component\Yaml;

class MetaParser {
    
    public $isPreEvalVisitor = true;
    
    protected $parser = null;
    protected $current_values = null;

    protected $output = null;

    public function __construct()
    {
        $this->output = new Tweaker\Output();
    }

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
            if (!is_array($vv)) {
                $vv = array();
            }
            $vv['name'] = $vk;
        }
        $this->styles []= $params;
    }
    
    protected function processRules($token)
    {
        $res = array();
        $current_comment = null;
        foreach ($token->rules as $rule) {
            // handle comment
            if ( $rule instanceof \Less_Tree_Comment ) {
                $current_comment = $this->parseComment($rule);
                if (!$current_comment) {
                    continue;
                }
                if (isset($current_comment['for']) && $current_comment['for'] === 'style') {
                    $this->registerStyle($current_comment, $rule);
                }
                continue;
            }
            // handle style mixin definition - extract default values from arguments
            if ($rule instanceof \Less_Tree_Mixin_Definition && $current_comment && $current_comment['for'] === 'style') {
                $this->extractDefaults($rule);
                $current_comment = null;
                $res []= $rule;
                continue;
            }
            // handle variable
            if (isset($rule->variable) && $rule->variable && $current_comment && $current_comment['for'] === 'var') {
                $this->registerVar($current_comment, $rule);
                $current_comment = null;
            }
            $res []= $rule;
        }
        $token->rules = $res;
    }
    
    protected function extractDefaults($token)
    {
        $c_style =& $this->styles[count($this->styles) - 1];
        foreach ($token->params as $arg) {
            $var_name = substr($arg['name'], 1);
            if (!isset($c_style['vars'][$var_name])) {
                continue;
            }
            $c_var =& $c_style['vars'][$var_name];
            $value = $this->output->get($arg['value'], false);
            $parts = null;
            $match_units = preg_match("~^\d+(em|rem|px|pt|%|vh|vw)~", $value, $parts);
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
    
    protected function parseComment($token)
    {
        $text = $token->value;
        $text = preg_replace("~^/\*|\*/$~", '', $text);
        try {
            $res = Yaml\Yaml::parse($text);
            if (is_array($res)) {
                return $this->prepareComment($res);
            }
        } catch (\Exception $e) {
            fx::cdebug('cat', $e, $text);
        }
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

    public static function getQuickStyleMeta($f) {
        $fh = fopen($f, 'r');
        $c = 0;
        $name = null;
        $has_vars = null;
        $is_in_comment = false;
        while ($c < 30 && !feof($fh)) {
            $s = fgets($fh);
            $c++;
            if (!$is_in_comment && preg_match("~\s*/\*~", $s)) {
                $is_in_comment = true;
                continue;
            }
            if ($is_in_comment && preg_match("~\s*\*/~", $s)) {
                $is_in_comment = false;
                continue;
            }
            if (!$is_in_comment) {
                continue;
            }
            $parts = null;
            if (!preg_match("~(name|vars)\s*\:(.*)~", $s, $parts)){
                continue;
            }
            if ($parts[1] === 'name') {
                $name = trim($parts[2]);
                continue;
            }
            $has_vars = true;
        }
        fclose($fh);
        return array(
            'name' => $name,
            'is_tweakable' => $has_vars
        );
    }
}