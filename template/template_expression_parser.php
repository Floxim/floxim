<?php
require_once (dirname(__FILE__).'/template_fsm.php');
class fx_template_expression_parser extends fx_template_fsm {
    
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
    
    public function __construct() {
        $this->add_rule(self::CODE, '`', null, 'start_esc');
        $this->add_rule(self::ESC, '`', null, 'end_esc');
        $this->add_rule(array(self::CODE, self::ARR_INDEX, self::VAR_NAME), '~^\$~', null, 'start_var');
        $this->add_rule(array(self::VAR_NAME, self::ARR_INDEX), array('[', '.'), null, 'start_arr');
        $this->add_rule(
            self::VAR_NAME, 
            "~^[^a-z0-9_]~i",
            null, 
            'end_var'
        );
        $this->add_rule(self::ARR_INDEX, "~^[^a-z0-9_\.]~", null, 'end_var_dot');
        $this->add_rule(self::ARR_INDEX, ']', null, 'end_arr');
        $this->init_state = self::CODE;
    }
    
    public $stack = array();
    public $curr_node = null;
    
    
    public function start_esc($ch) {
        $this->push_state(self::ESC);
    }
    
    public function end_esc($ch) {
        $this->pop_state();
    }
    
    public function push_stack($node) {
        $this->stack[]= $node;
        $this->curr_node = $node;
    }
    
    public function pop_stack() {
        $node = array_pop($this->stack);
        $this->curr_node = end($this->stack);
        return $node;
    }
    
    public function parse($string) {
        $this->root = self::node(self::T_ROOT);
        $this->stack = array();
        $this->push_stack($this->root);
        $this->string = $string;
        parent::parse($string);
        return $this->root;
    }
    
    public function start_arr($ch) {
        $is_dot = $ch == '.';
        // $item["olo".$id] - ignore dot
        if ($is_dot && $this->state == self::ARR_INDEX && $this->curr_node->starter != '.') {
            return false;
        }
        if (
                ($is_dot && $this->state == self::ARR_INDEX) ||
                $this->curr_node->starter == '.'
            ) {
            $this->end_arr();
        }
        
        // test for $loop.items.count()
        if ($is_dot) {
            list($method_name, $bracket) = $this->get_next(2);
            $is_method = preg_match("~^[a-z0-9_]+$~", $method_name) && $bracket == '(';
            if ($is_method) {
                $this->end_var('->');
                return;
            }
        }
        // test for $_._.olo
        // if var still has no name - just continue without switching state
        if ($this->curr_node->type == self::T_VAR && count($this->curr_node->name) == 0) {
            if ($ch == '.') {
                $this->curr_node->context_level_up(0);
                return;
            }
        }
        $arr = self::node(self::T_ARR);
        $arr->starter = $ch;
        $this->curr_node->add_child($arr);
        $this->push_stack($arr);
        $this->push_state(self::ARR_INDEX);
    }
    
    public function end_var_dot($ch) {
        if ($this->curr_node->starter != '.') {
            return false;
        }
        $this->end_var($ch);
    }
    
    public function start_var($ch) {
        $var = self::node(self::T_VAR);
        $var->name = array();
        if ($this->curr_node->type == self::T_VAR) {
            $this->curr_node->name []= $var;
        } else {
            $this->curr_node->add_child($var);
        }
        $this->push_stack($var);
        $this->push_state(self::VAR_NAME);
    }
    
    public function end_var($ch) {
        do {
            $this->pop_state();
            $this->pop_stack();
        } while ($this->state == self::VAR_NAME);
        if ($ch == ']') {
            $this->end_arr();
        } else {
            $this->read_code($ch);
        }
    }
    
    public function end_arr() {
        $this->pop_stack();
        $this->pop_state();
        // $_[$x] -> $this->v($this->v('x'), 0)
        if ($this->curr_node->type == self::T_VAR && count($this->curr_node->name) == 0) {
            $index_expr = $this->curr_node->children;
            $this->curr_node->children = array();
            $this->curr_node->last_child = null;
            $this->curr_node->name = $index_expr;
        }
    }
    
    public function default_callback($ch) {
        switch ($this->state) {
            case self::VAR_NAME:
                if ($ch == '_') {
                    $this->curr_node->context_level_up();
                } else {
                    $this->curr_node->name []= $ch;
                }
                break;
            case self::CODE: case self::ESC:
                $this->read_code($ch);
                break;
            case self::ARR_INDEX:
                if ($this->curr_node->starter == '.' && preg_match("~^[a-z0-9_]+$~i", $ch)) {
                    $ch = '"'.$ch.'"';
                }
                $this->read_code($ch);
                break;
        }
    }
    
    public function read_code($ch) {
        
        if ($ch == '.') {
            $next = $this->get_next(2);
            $back = $this->get_prev();
            if ($next[1] === '(' && !preg_match("~\s~", $next[0]) && !preg_match("~\s~", $back[0])) {
                $ch = '->';
            }
        }
        $node = $this->curr_node;
        $is_separator = in_array($ch, array(',', '(', ')', 'as')) || $this->state == self::ESC;
        if (
            !$is_separator && $node->last_child && !$node->last_child->is_separator 
            && $node->last_child->type == self::T_CODE
        ) {
            $node->last_child->data .= $ch;
        } else {
            $code = self::node(self::T_CODE);
            $code->is_separator = $is_separator;
            if ($this->state == self::ESC) {
                $code->is_escaped = true;
            }
            $code->data = $ch;
            
            $node->add_child($code);
            if ($ch == ')') {
                $this->pop_stack();
            } elseif ($ch == '(') {
                $this->push_stack($code);
            }
        }
        if ($this->looking_for_var_name && count($this->stack) == 1) {
            $cdata = $node->last_child->data;
            $rex = "~(\||[a-z0-9_-]+\s?=)$~";
            if (preg_match($rex, $cdata)) {
                $str = substr($this->string, 0, $this->position);
                $str = preg_replace($rex, '', $str);
                throw new fx_template_var_finder_exception($str);
            }
        }
    }
    
    /**
     * Parse 'with' part of call, like:
     * {call tpl with $news, strtoupper($name) as $title, $user as $author}
     * makes:
     * array('$' => '$news', '$title' => 'strtoupper($news)', '$author' => '$user');
     */
    public function parse_with($expr) {
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
        foreach ($parts as $p) {
            $value = null;
            $stack = '';
            foreach ($p as $item) {
                if ($item->type == self::T_CODE && preg_match('~\s*as\s*~', $item->data)) {
                    $value = $stack;
                    $stack = '';
                    continue;
                }
                $stack .= $this->compile($item, true);
            }
            // no "as" separator met
            if (is_null($value)) {
                $value = $stack;
                $stack = '$';
            }
            $res[$this->_trim_esc($stack)] = $this->_trim_esc($value);
        }
        return $res;
    }
    
    protected function _trim_esc($s) {
        return str_replace('``', '', trim($s));
    }

    
    protected $looking_for_var_name = false;
    public function find_var_name($str) {
        $this->looking_for_var_name = true;
        try {
            $this->parse($str);
            $res = $str;
        } catch (fx_template_var_finder_exception $ex) {
            $res = $ex->getMessage();
        }
        $res = trim($res);
        //fx::debug($str, $res);
        return $res;
    }
    
    
    public static function node($type) {
        return new fx_template_expression_node($type);
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
                $context_level = $node->get_context_level();
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

class fx_template_expression_node {
    public $type;
    
    protected $context_offset = null;
    
    public function __construct($type = fx_template_expression_parser::T_CODE) {
        $this->type = $type;
    }
    
    public function context_level_up($count = 1) {
        if (is_null($this->context_offset)) {
            $this->context_offset = 0;
        }
        $this->context_offset += $count;
    }
    
    public function get_context_level() {
        return $this->context_offset;
    }
    
    public $last_child = null;
    
    public function add_child($n) {
        if (!$this->last_child) {
            $this->children = array();
        }
        $this->children []= $n;
        $this->last_child = $n;
    }
    
    public function pop_child() {
        if (!$this->last_child) {
            return null;
        }
        $child = array_pop($this->children);
        if (count($this->children) == 0) {
            $this->last_child = null;
        } else {
            $this->last_child = end($this->children);
        }
        return $child;
    }
}

class fx_template_var_finder_exception extends Exception {
    
}