<?php
/*
 * Class breaks the template into tokens and builds a tree
 */
class fx_template_parser {
    
    /**
     * Convert template to token tree
     * @param string $source source code of the template
     * @return array token tree
     */
    public function parse($source) {
        $tokenizer = new fx_template_tokenizer();
        $tokens = $tokenizer->parse($source);
        $tree = $this->_make_tree($tokens);
        return $tree;
    }
    
    /**
     * To determine the type of the token (opening/unit) on the basis of the following tokens
     * @param fx_template_token $token the token with an unknown type
     * @param array $tokens following tokens
     * @return null
     */
    protected  function solve_unclosed($token, $tokens) {
        if (!$token  || $token->type != 'unknown') {
            return;
        }
        $token_info = fx_template_token::get_token_info($token->name);
        $stack = array();
        while ($next_token = array_shift($tokens)) {
            if ($next_token->type == 'unknown') {
                $this->solve_unclosed($next_token, $tokens);
            }
            switch ($next_token->type) {
                case 'open':
                    if (count($stack) == 0) {
                        if (!in_array($next_token->name, $token_info['contains'])) {
                            $token->type = 'single';
                            return;
                        }
                    }
                    $stack[]= $token;
                    break;
                case 'close':
                    if (count($stack) == 0) {
                        if ($next_token->name == $token->name) {
                            $token->type = 'open';
                            return;
                        } else {
                            $token->type = 'single';
                            return;
                        }
                    }
                    array_pop($stack);
                    break;
            }
        }
        echo "solving ".$token->name.
                " | stack has ".count($stack). 
                'items at the end of the method<br />';
    }


    protected function _make_tree($tokens) {
        $stack = array();
        $root = $tokens[0];
        while ($token = array_shift($tokens)) {
            if ($token->type == 'unknown') {
                $this->solve_unclosed($token, $tokens);
            }
            switch ($token->type) {
                case 'open':
                    if (count($stack) > 0) {
                        end($stack)->add_child($token);
                    }
                    $stack []= $token;
                    break;
                case 'close':
                    if ($token->name == 'if') {
                        do {
                            $closed_token = array_pop($stack);
                        } while ($closed_token->name != 'if');
                    } else {
                        $closed_token = array_pop($stack);
                    }
                    
                    if ($token->name == 'if' || $token->name == 'elseif') {
                        // reading forward to check if there is nearby {elseif} / {else} tag
                        $count_skipped = 0;
                        foreach ($tokens as $next_token) {
                            // skip empty tokens
                            if ($next_token->is_empty()) {
                                $count_skipped++;
                                continue;
                            }
                            if (
                                $next_token->type == 'open' && 
                                ($next_token->name == 'elseif' || $next_token->name == 'else')
                            ) {
                                $next_token->stack_extra = true;
                                $stack []= $closed_token;
                                foreach (range(1, $count_skipped) as $skip) {
                                    array_shift($tokens);
                                }
                            }
                            break;
                        }
                    }
                    if ($token->name == 'template' && $closed_token->name == 'template') {
                        $this->_template_to_each($closed_token);
                    }
                    if ($closed_token->stack_extra) {
                        array_pop($stack);
                    }
                    break;
                case 'single': default:
                    $stack_last = end($stack);
                    if (!$stack_last) {
                        echo "Template error: stack empty, trying to add: ";
                        echo "<pre>" . htmlspecialchars(print_r($token, 1)) . "</pre>";
                        die();
                    }
                    $stack_last->add_child($token);
                    break;
            }
        }
        return $root;
    }
    
    protected function _template_to_each(fx_template_token $token) {
        $children = $token->get_children();
        $has_items = false;
        foreach ($children as $child) {
            if ($child->name == 'item') {
                $has_items = true;
                break;
            }
        }
        if (!$has_items) {
            return;
        }
        $with_each_token = new fx_template_token('with_each', 'double', array('select' => '$.items'));
        $with_each_token->set_children($children);
        $token->clear_children();
        $token->add_child($with_each_token);
    }
}

class fx_template_tokenizer extends fx_template_fsm {
    public $split_regexp = '~(\{[\$\%\/a-z0-9]+[^\{]*?\}|</?(?:script|style)[^>]*?>|<\?(?:php)?|\?>|<\!--|-->)~';
    
    const JS = 1;
    const CSS = 2;
    const PHP = 3;
    const HTML = 4;
    const COMMENT = 5;
    
    protected $stack = '';
    
    public function __construct() {
        $this->init_state = self::HTML;
        $this->res = array();
        
        
        $this->add_rule(self::HTML, '~^\{.+\}$~s', self::HTML, 'add_token');
        
        $this->add_rule(self::HTML, '~^<script~', self::JS);
        $this->add_rule(self::JS, '~^</script~', self::HTML);
        
        $this->add_rule(self::HTML, '~^<style~', self::CSS);
        $this->add_rule(self::CSS, '~^</style~', self::HTML);
        
        $this->add_rule(self::HTML, '~^<\?~', self::PHP);
        $this->add_rule(self::PHP, '~^\?>~', self::HTML);
        
        $this->add_rule(self::HTML, '~^<\!--~', self::COMMENT);
        $this->add_rule(self::COMMENT, '~^-->~', self::HTML);
    }
    
    protected function add_token($ch) {
        if (!empty($this->stack)) {
            $this->res []= fx_template_token::create($this->stack);
            $this->stack = '';
        }
        $this->res []= fx_template_token::create($ch);
    }
    
    public function default_callback($ch) {
        $this->stack .= $ch;
    }
}