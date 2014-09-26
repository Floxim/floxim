<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Exception;

/**
 * $items.first.name => fx::dig($this->v("items"), "first", "name")
 * $items.first.name() => fx::dig($this->v("items"), "first")->name()
 * $items.first().name => fx::dig($this->v("items")->first(), "name")
 * $items.first().name() => $this->v("items")->first()->name()
 * $item.%name => fx::dig('item','%name');
 */
class ExpressionParser extends Fsm {
    
    public $split_regexp = '~(\$\{|\`|\$|\s+|\.|\,|[\[\]]|[\'\"]|[\{\}]|[+=&\|\)\(-]|[^a-z0-9_])~i';
    
    const CODE = 1;
    const VAR_NAME = 2;
    const ARR_INDEX = 3;
    const STR = 4;
    const ESC = 5;
    
    const T_CODE = 1;
    const T_VAR = 2;
    const T_ARR = 3;
    const T_ROOT = 0;
    
    public function showState() {
        $vars = array(
            1 => 'CODE',
            2 => 'VAR_NAME',
            3 => 'ARR_INDEX',
            4 => 'STR',
            5 => 'ESC'
        );
        return $vars[$this->state];
    }
    
    public function __construct() {
        $this->addRule(self::CODE, '`', null, 'start_esc');
        $this->addRule(self::ESC, '`', null, 'end_esc');
        $this->addRule(array(self::CODE, self::ARR_INDEX, self::VAR_NAME), '~^\$~', null, 'start_var');
        $this->addRule(array(self::VAR_NAME, self::ARR_INDEX), array('[', '.'), null, 'start_arr');
        $this->addRule(
            self::VAR_NAME, 
            "~^[^\%a-z0-9_]~i",
            null, 
            'end_var'
        );
        $this->addRule(self::ARR_INDEX, "~^[^a-z0-9\%_\.]~", null, 'end_var_dot');
        $this->addRule(self::ARR_INDEX, ']', null, 'end_arr');
        $this->init_state = self::CODE;
    }
    
    public $stack = array();
    public $curr_node = null;
    
    
    public function startEsc($ch) {
        $this->pushState(self::ESC);
    }
    
    public function endEsc($ch) {
        $this->popState();
    }
    
    public function pushStack($node) {
        $this->stack[]= $node;
        $this->curr_node = $node;
    }
    
    public function popStack() {
        $node = array_pop($this->stack);
        $this->curr_node = end($this->stack);
        return $node;
    }
    
    public function parse($string) {
        $this->root = self::node(self::T_ROOT);
        $this->stack = array();
        $this->pushStack($this->root);
        $this->string = $string;
        parent::parse($string);
        return $this->root;
    }
    
    public function startArr($ch) {
        $is_dot = $ch == '.';
        // $item["olo".$id] - ignore dot
        if ($is_dot && $this->state == self::ARR_INDEX && $this->curr_node->starter != '.') {
            return false;
        }
        if (
                ($is_dot && $this->state == self::ARR_INDEX) ||
                $this->curr_node->starter == '.'
            ) {
            $this->endArr();
        }
        
        // test for $loop.items.count()
        if ($is_dot) {
            list($method_name, $bracket) = $this->getNext(2);
            $is_method = preg_match("~^[a-z0-9_]+$~", $method_name) && $bracket == '(';
            if ($is_method) {
                $this->endVar('->');
                return;
            }
        }
        // test for $_._.olo
        // if var still has no name - just continue without switching state
        if ($this->curr_node->type == self::T_VAR && count($this->curr_node->name) == 0) {
            if ($ch == '.') {
                $this->curr_node->contextLevelUp(0);
                return;
            }
        }
        $arr = self::node(self::T_ARR);
        $arr->starter = $ch;
        $this->curr_node->addChild($arr);
        $this->pushStack($arr);
        $this->pushState(self::ARR_INDEX);
    }
    
    public function endVarDot($ch) {
        if ($this->curr_node->starter != '.') {
            return false;
        }
        $this->endVar($ch);
    }
    
    public function startVar($ch) {
        $var = self::node(self::T_VAR);
        $var->name = array();
        if ($this->curr_node->type == self::T_VAR) {
            $this->curr_node->name []= $var;
        } else {
            $this->curr_node->addChild($var);
        }
        $this->pushStack($var);
        $this->pushState(self::VAR_NAME);
    }
    
    public function endVar($ch) {
        do {
            $this->popState();
            $this->popStack();
        } while ($this->state == self::VAR_NAME);
        if ($ch == ']') {
            $this->endArr();
        } else {
            $this->readCode($ch);
        }
    }
    
    public function endArr() {
        $this->popStack();
        $this->popState();
        // $_[$x] -> $this->v($this->v('x'), 0)
        if ($this->curr_node->type == self::T_VAR && count($this->curr_node->name) == 0) {
            $index_expr = $this->curr_node->children;
            $this->curr_node->children = array();
            $this->curr_node->last_child = null;
            $this->curr_node->name = $index_expr;
        }
    }
    
    public function defaultCallback($ch) {
        switch ($this->state) {
            case self::VAR_NAME:
                if ($ch == '_') {
                    $this->curr_node->contextLevelUp();
                } else {
                    $this->curr_node->appendNameChunk($ch);
                }
                break;
            case self::CODE: case self::ESC:
                $this->readCode($ch);
                break;
            case self::ARR_INDEX:
                if ($this->curr_node->starter == '.' && preg_match("~^[\%a-z0-9_]+$~i", $ch)) {
                    $ch = '"'.$ch.'"';
                }
                $this->readCode($ch);
                break;
        }
    }
    
    public function readCode($ch) {
        
        if ($ch == '.') {
            $next = $this->getNext(2);
            $back = $this->getPrev();
            if ($next[1] === '(' && !preg_match("~\s~", $next[0]) && !preg_match("~\s~", $back[0])) {
                $ch = '->';
            }
        }
        $node = $this->curr_node;
        $is_separator = in_array($ch, array(',', '(', ')', 'as', '=')) || $this->state == self::ESC;
        if (
            !$is_separator && $node->last_child && !$node->last_child->is_separator 
            && $node->last_child->type == self::T_CODE
        ) {
            if (preg_match('~\"$~', $node->last_child->data) && preg_match('~^\"~', $ch)) {
                $node->last_child->data = preg_replace("~\"$~", '', $node->last_child->data);
                $ch = preg_replace("~^\"~", '', $ch);
            }
            $node->last_child->data .= $ch;
        } else {
            $code = self::node(self::T_CODE);
            $code->is_separator = $is_separator;
            if ($this->state == self::ESC) {
                $code->is_escaped = true;
            }
            $code->data = $ch;
            
            $node->addChild($code);
            if ($ch == ')') {
                $this->popStack();
            } elseif ($ch == '(') {
                $this->pushStack($code);
            }
        }
        if ($this->looking_for_var_name && count($this->stack) == 1) {
            $cdata = $node->last_child->data;
            
            if (preg_match("~[\=\|]$~", $cdata)) {
                $rex = "~(\||[a-z0-9_-]+\s?=)$~";
                $str = mb_substr($this->string, 0, $this->position);
                if (preg_match($rex, $str)) {
                    $str = preg_replace($rex, '', $str);
                    throw new Exception\TemplateVarFinder($str);
                }
            }
        }
    }
    
    /**
     * Parse 'with' part of call, like:
     * {call tpl with $news, strtoupper($name) as $title, $user as $author}
     * makes:
     * array('$' => '$news', '$title' => 'strtoupper($news)', '$author' => '$user');
     */
    public function parseWith($expr) {
        $tree = $this->parse($expr);
        $parts = array();
        $stack = array();
        foreach ($tree->children as $child) {
            if ($child->type == self::T_CODE) {
                if (preg_match("~^\s*,\s*$~", $child->data)) {
                    $parts []= $stack;
                    $stack = array();
                    continue;
                }
            }
            $stack []= $child;
        }
        if (count($stack) > 0) {
            $parts []= $stack;
        }
        $res = array();
        // helper to trim & clean parts
        $trim_esc = function($s) {
            return strReplace('``', '', trim($s));
        };
        foreach ($parts as $p) {
            $value = null;
            $stack = '';
            $part_is_eq = false;
            foreach ($p as $item) {
                if ($item->type == self::T_CODE) {
                    $is_as = preg_match('~\s*as\s*~', $item->data);
                    $is_eq = preg_match('~\s*=\s*~', $item->data);
                    if ($is_as || $is_eq) {
                        $value = $stack;
                        $stack = '';
                        $part_is_eq = $is_eq;
                        continue;
                    }
                }
                $stack .= $this->compile($item, true);
            }
            // no "as" separator met
            if (is_null($value)) {
                $value = $stack;
                $stack = '$';
            }
            $value = $trim_esc($value);
            $stack = $trim_esc($stack);
            if ($part_is_eq) {
                $res[$value] = $stack;
            } else {
                $res[$stack] = $value;
            }
        }
        return $res;
    }
    
    protected $looking_for_var_name = false;
    public function findVarName($str) {
        $this->looking_for_var_name = true;
        try {
            $this->parse($str);
            $res = $str;
        } catch (Exception\TemplateVarFinder $ex) {
            $res = $ex->getMessage();
        }
        $res = trim($res);
        return $res;
    }
    
    
    public static function node($type) {
        return new ExpressionNode($type);
    }
    
    public $local_vars = array('this', 'template_dir');
    
    public function build($expr) {
        $tree = $this->parse($expr);
        $res = $this->compile($tree);
        return $res;
    }
    
    public function compile($node, $rebuild = false) {
        $res = '';
        $proc = $this;
        $get_children = function($n, &$res = '') use ($proc, $rebuild) {
            if (isset($n->children)) {
                foreach ($n->children as $child) {
                    $res .= $proc->compile($child, $rebuild);
                }
            }
            return $res;
        };
        $add_children = function($n) use ($get_children, &$res) {
            $get_children($n, $res);
        };
        switch($node->type) {
            case self::T_ROOT:
                $add_children($node);
                break;
            case self::T_VAR:
                $is_local = $rebuild;
                $var_name = '';
                $context_level = $node->getContextLevel();
                if (!is_null($context_level)) {
                    $context_level = ", ".$context_level;
                }
                // simple var
                if (count($node->name) == 1 && is_string($node->name[0])) {
                    $var_name = $node->name[0];
                    $is_local = $is_local || in_array($var_name, $this->local_vars);
                    if ($is_local) {
                        $var = '$'.$var_name;
                    } else {
                        if (!is_numeric($var_name)) {
                            $var_name = '"'.$var_name.'"';
                        }
                        $var = '$this->v('.$var_name.$context_level.')';
                    }
                } 
                // complex var such as $image_$id
                else {
                    $var_name_parts = array();
                    foreach ($node->name as $np) {
                        if (is_string($np) && !is_numeric($np)) {
                            $np = '"'.$np.'"';
                        } else {
                            $np = $this->compile($np);
                        }
                        $var_name_parts []= $np;
                    }
                    $var_name = join(".", $var_name_parts);
                    // simplify $this->v("%"."name") to $this->v("%name")
                    $var_name = preg_replace('~\"\.\"~', '', $var_name);
                    if (empty($var_name)) {
                        $var_name = 'null';
                    }
                    $var = '$this->v('.$var_name.$context_level.')';
                }
                
                if ($node->last_child) {
                    $indexes = array();
                    foreach ($node->children as $arr_index) {
                        $indexes []= $this->compile($arr_index);
                    }
                    if ($is_local) {
                        $res .= $var.'['.join('][', $indexes).']';
                    } else {
                        $res .= "fx::dig(".$var.", ";
                        $res .= join(", ", $indexes);
                        $res .= ")";
                    }
                } else {
                    $res .= $var;
                }
                break;
            case self::T_CODE:
                $code = $node->data;
                $code .= $get_children($node);
                if ($rebuild && $node->is_escaped) {
                    $code = '`'.$code.'`';
                }
                $res .= $code;
                break;
            case self::T_ARR:
                $res .= $add_children($node);
                break;
        }
        $res = str_replace("``", '', $res); // empty escape
        return $res;
    }
}

