<?php
/*
 * Class makes the tree tokens in ready php code
 */
class fx_template_compiler {
    protected $template_set_name = null;
    
    /**
     * Convert the tree of tokens in the php code
     * @param string $source source code of the template
     * @return string of php code
     */
    public function compile($tree) {
        $code = $this->_make_code($tree);
        $code = self::add_tabs($code);
        if (fx::config('templates.check_php_syntax')) {
            $is_correct = self::is_php_syntax_correct($code);
            if ($is_correct !== true) {
                $error_line = $is_correct[1][1];
                $lines = explode("\n", $code);
                $lines[ $error_line - 1] = '[[bad '.$lines[$error_line - 1].']]';
                $lined = join("\n", $lines);
                $error = $is_correct[0].': '.$is_correct[1][0].' (line '.$error_line.')';
                fx::debug($error, $lined);
                throw new Exception('Syntax error');
            }
        }
        return $code;
    }
    
    public static function add_tabs($code) {
        $res = '';
        $level = 0;
        $code = preg_split("~[\n\r]+~", $code);
        foreach ($code as $s) {
            $s = trim($s);
            if (preg_match("~^[\}\)]~", $s)) {
                $level--;
            }
            $res .= str_repeat("    ", $level).$s."\n";
            if (preg_match("~[\{\(]$~", $s)) {
                $level++;
            }
        }
        return $res;
    }
    
    
    
    protected $templates = array();
    
    protected $_code_context = 'text';
    
    protected function _token_code_to_code($token) {
        return $token->get_prop('value');
    }
    
    protected function _token_help_to_code($token) {
        $code = "<?php\n";
        $code .= "echo \$this->get_help();\n";
        $code .= "?>";
        return $code;
    }
    
    protected function _token_call_to_code(fx_template_token $token) {
        $each = $token->get_prop('each');
        if ($each) {
            $each_token = fx_template_token::create('{each}');
            $each_token->set_prop('select', $each);
            
            $item = '$'.$this->varialize($each).'_item';
            $each_token->set_prop('as', $item);
            $c_with = $token->get_prop('with');
            $token->set_prop('with', $item.($c_with ? ', '.$c_with : ''));
            $token->set_prop('each', '');
            $each_token->add_child($token);
            return $this->_token_each_to_code($each_token);
        }
        $code = "<?php\n";
        $tpl_name = $token->get_prop('id');
        // not a plain name
        if (!preg_match("~^[a-z0-9_\.]+$~", $tpl_name)) {
            $tpl_name = self::parse_expression($tpl_name);
        } else {
            if (!preg_match("~\.~", $tpl_name)) {
                $tpl_name = $this->_template_set_name.".".$tpl_name;
            }
            $tpl_name = '"'.$tpl_name.'"';
        }
        $tpl = '$tpl_'.$this->varialize($tpl_name);
        $code .= $tpl.' = fx::template('.$tpl_name.");\n";
        $inherit = $token->get_prop('apply') ? 'true' : 'false';
        $code .= $tpl.'->set_parent($this, '.$inherit.");\n";
        $call_children = $token->get_children();
        /*
         * Converted:
         * {call id="wrap"}<div>Something</div>{/call}
         * like this:
         * {call id="wrap"}{var id="content"}<div>Something</div>{/var}{/call}
         */
        $has_content_param = false;
        foreach ($call_children as $call_child) {
            if ($call_child->name == 'code' && $call_child->is_empty()) {
                continue;
            }
            if ($call_child->name != 'var') {
                $has_content_param = true;
                break;
            }
        }
        if ($has_content_param) {
            $token->clear_children();
            $var_token = new fx_template_token('var', 'single', array('id' => 'content'));
            foreach ($call_children as $call_child) { 
                $var_token->add_child($call_child);
            }
            $token->add_child($var_token);
        }
        $with_expr = $token->get_prop('with');
        if ($with_expr) {
            $ep = new fx_template_expression_parser();
            $with_expr = $ep->parse_with($with_expr);
        }
        $switch_context = is_array($with_expr) && isset($with_expr['$']);
        if ($switch_context) {
            $code .= '$this->push_context('.$this->parse_expression($with_expr['$']).");\n";
        }
        $code .= $tpl."->push_context(array(), array('transparent' => false));\n";
        if (is_array($with_expr)) {
            foreach ($with_expr as $alias => $var) {
                if ($alias == '$') {
                    continue;
                }
                $code .= $tpl."->set_var(".
                    "'".trim($alias, '$')."', ".
                    $this->parse_expression($var).");\n";
            }
        }
        foreach ($token->get_children() as $param_var_token) {
            // internal call only handle var
            if ($param_var_token->name != 'var') {
                continue;
            }
            $value_to_set = 'null';
            if ($param_var_token->has_children()) {
                // pass the inner html code
                $code .= "ob_start();\n";
                $code .= $this->_children_to_code($param_var_token);
                $code .= "\n";
                $value_to_set = 'ob_get_clean()';
            } elseif ( ($select_att = $param_var_token->get_prop('select') ) ) {
                // pass the result of executing the php code
                $value_to_set = self::parse_expression($select_att);
            }
            $code .= $tpl."->set_var(".
                "'".$param_var_token->get_prop('id')."', ".
                $value_to_set.");\n";
        }
        
        if ($switch_context) {
            $code .= "\$this->pop_context();\n";
            $code .= $tpl."->push_context(".$this->parse_expression($with_expr['$']).");\n";
        }
        $code .= 'echo '.$tpl."->render();\n";
        $code .= "\n?>";
        return $code;
    }
    
    public function parse_expression($str) {
        static $expression_parser = null;
        if ($expession_parser === null) {
            require_once (dirname(__FILE__).'/template_expression_parser.php');
            $expression_parser = new fx_template_expression_parser();
            $expression_parser->local_vars []= '_is_admin';
        }
        return $expression_parser->compile($expression_parser->parse($str));
    }
    
    protected function _apply_modifiers($display_var, $modifiers, $token) {
        if (!$modifiers || count($modifiers) == 0) {
            return '';
        }
        $token_type = $token->get_prop('type');
        $code = '';
        foreach ($modifiers as $mod) {
            $mod_callback = $mod['name'];
            
            if ($mod['is_template']) {
                $call_token = new fx_template_token('call', 'single', array('id' => $mod['name'], 'apply' => true));
                if (isset($mod['with'])) {
                    $call_token->set_prop('with', $mod['with']);
                }
            }
            
            if ($mod['is_each'] && $mod['is_template']) {
                $c_with = $call_token->get_prop('with');
                $call_token->set_prop('with', "`".$display_var.'`_item'.($c_with ? ', '.$c_with : ''));
                $each_token = new fx_template_token('each', 'single', array('select' => "`".$display_var."`"));
                $each_token->add_child($call_token);
                $code = "ob_start();\n?>";
                $code .= $this->_token_each_to_code($each_token);
                $code .= "<?php\n".$display_var." = ob_get_clean();\n";
                continue;
            }
            
            if ($mod["is_each"]){ 
                $display_var_item = $display_var."_item";
                $code .= 'foreach ('.$display_var.' as &'.$display_var_item.") {\n";
            } else {
                $display_var_item = $display_var;
            }
                
            if (empty($mod_callback)) {
                if ($token_type) {
                    $mod_callback = $token_type == 'image' ? 'fx::image' : 'fx::date';
                    $mod_callback .= '(';
                } else {
                    $token->_need_type = true;
                    $mod_callback = 'call_user_func(';
                    $mod_callback .= '($var_type == "image" ? "fx::image" : ';
                    $mod_callback .= '($var_type == "datetime" ? "fx::date" : "fx::cb")), ';
                }
            } elseif ($mod['is_template']) {
                $code .= "ob_start();\n?>";
                $c_with = $call_token->get_prop('with');
                $call_token->set_prop('with', "`".$display_var.'`'.($c_with ? ', '.$c_with : ''));
                $call_token->set_prop('apply', true);
                $code .= $this->_token_call_to_code($call_token);
                $code .= "<?php\n".$display_var_item. " = ob_get_clean();\n";
            } else {
                $mod_callback .= '(';
            }
            if (!$mod['is_template']) {
                $args = array();
                $self_used = false;
                foreach ($mod['args'] as $arg) {
                    if ($arg == 'self') {
                        $args []= $display_var_item;
                        $self_used = true;
                    } else {
                        $args []= self::parse_expression($arg);
                    }
                }
                if (!$self_used) {
                    array_unshift($args, $display_var_item);
                }
                $code .= $display_var_item.' = '. $mod_callback.join(', ', $args).");\n";
            }
            if ($mod['is_each']) {
                $code .= "}\n";
            }
        }
        return $code;
    }
    
    protected function _make_file_check($var) {
        
        $code = $var . ' = trim('.$var.");\n";
        $code .= "\nif (".$var." && !preg_match('~^(https?://|/)~', ".$var.")) {\n";
        $code .= $var . '= $template_dir.'.$var.";\n";
        $code .= "}\n";
        
        $code .= 'if (!preg_match("~^https?://~", '.$var.') && !file_exists(fx::path()->to_abs(preg_replace("~\?.+$~", "", '.$var.')))) {'."\n";
        $code .= $var . "= '';\n";
        $code .= "}\n";
        return $code;
    }
    
    protected function _token_var_to_code(fx_template_token $token) {
        $code = "<?php\n";
        // parse var expression and store token 
        // to create correct expression for get_var_meta()
        $ep = new fx_template_expression_parser();
        $expr_token = $ep->parse('$'.$token->get_prop('id'));
        $expr = $ep->compile($expr_token);
        $var_token = $expr_token->last_child;
        
        $modifiers = $token->get_prop('modifiers');
        $token->set_prop('modifiers', null);
        
        $token_type = $token->get_prop('type');
        // analyze default value to get token type and wysiwyg linebreaks mode
        if (
            !$token_type || 
            ($token_type == 'html' && !$token->get_prop('linebreaks'))
        ) {
            $linebreaks = $token->get_prop('var_type') == 'visual';
            foreach ($token->get_children() as $child) {
                $child_source = $child->get_prop('value');
                if (!$token_type && preg_match("~<[a-z]+.*?>~i", $child_source)) {
                    $token_type = 'html';
                }
                if (preg_match("~<p.*?>~i", $child_source)) {
                    $linebreaks = false;
                }
            }
            if (!$token_type) {
                $token_type = 'string';
            } else {
                $token->set_prop('type', $token_type);
            }
            if ($linebreaks || $token->get_prop('var_type') === 'visual') {
                $token->set_prop('linebreaks', $linebreaks);
            }
        }
        
        // e.g. "name" or "image_".$this->v('id')
        $var_id = preg_replace('~^\$this->v\(~', '', preg_replace("~\)$~", '', $expr));
        
        $has_default = $token->get_prop('default') || count($token->get_children()) > 0;
        
        // if var has default value or there are some modifiers
        // store real value for editing
        $real_val_defined = false;
        $var_chunk = $this->varialize($var_id);
        $token_is_file = ($token_type == 'image' || $token_type == 'file');
        
        if ($modifiers || $has_default || $token->get_prop('inatt')) {
            $real_val_var = '$'.$var_chunk.'_real_val';
            
            $code .= $real_val_var . ' = '.$expr.";\n";
            
            if ($token_is_file) {
                $code .= $this->_make_file_check($real_val_var);
            }
            
            if ($modifiers || $has_default) {
                $display_val_var = '$'.$var_chunk.'_display_val';
                $code .= $display_val_var . ' = '.$real_val_var.";\n";
            } else {
                $display_val_var = $real_val_var;
            }
            $expr = $display_val_var;
            $real_val_defined = true;
        }
        if ($has_default) {
            $code .= "\nif (is_null(".$real_val_var.") || ".$real_val_var." == '') {\n";
            
            if (!($default = $token->get_prop('default')) ) {
                // ~= src="{%img}{$img /}{/%}" --> src="{%img}{$img type="image" /}{/%}
                $token_def_children = $token->get_non_empty_children();
                if (count($token_def_children) == 1 && $token_def_children[0]->name == 'var') {
                    $def_child = $token_def_children[0];
                    if (!$def_child->get_prop('type')) {
                        $def_child->set_prop('type', $token_type);
                    }
                }
                $code .= "\tob_start();\n";
                $code .= '$'.$var_chunk.'_was_admin = $_is_admin;'."\n";
                $code .= '$_is_admin = false;'."\n";
                $code .= "\t".$this->_children_to_code($token);
                $code .= '$_is_admin = $'.$var_chunk.'_was_admin;'."\n";
                $default = "ob_get_clean()";
            }
            if ($real_val_defined) {
                $code .= "\n".$display_val_var.' = '.$default.";\n";
                if ($token_is_file) {
                    $code .= $this->_make_file_check($display_val_var);
                }
                if ($token->get_prop('var_type') == 'visual') {
                    $code .= "\n".'$this->set_var('.$var_id.',  '.$display_val_var.");\n";
                }
            } elseif ($token->get_prop('var_type') == 'visual') {
                $code .= "\n".'$this->set_var('.$var_id.',  '.$default.");\n";
            }
            $code .= "}\n";
        }
        
        $var_meta_expr = $this->_get_var_meta_expression($token, $var_token, $ep);
        
        
        if ($modifiers) {
            
            $modifiers_code = $this->_apply_modifiers($display_val_var, $modifiers, $token);
            if ($token->_need_type) {
                $code .= '$var_meta = '.$var_meta_expr.";\n";
                $code .= '$var_type = $var_meta["type"]'.";\n";
                $var_meta_defined = true;
            }
            $code .= $modifiers_code;
        }
        $code .= 'echo !$_is_admin ? '.$expr.' : $this->print_var('."\n";
        $code .= $expr;
        $code .= ", \n";
        $token_is_visual = $token->get_prop('var_type') === 'visual';
        $meta_parts = array();
        if (!$token_is_visual) {
            //$code .= ($var_meta_defined ? '$var_meta' : $var_meta_expr) . ' + ';
            $meta_parts []= $var_meta_defined ? '$var_meta' : $var_meta_expr;
        }
        //if ($token->get_prop('var_type') == 'visual') {
        $token_props = $token->get_all_props();
        

        $tp_parts = array();

        foreach ($token_props as $tp => $tpval) {
            if (!$token_is_visual && in_array($tp, array('id', 'var_type'))) {
                continue;
            }
            $token_prop_entry = "'".$tp."' => ";
            if ($tp == 'id') {
                $token_prop_entry .= $var_id;
            } elseif (preg_match("~^\`.+\`$~s", $tpval)) {
                $token_prop_entry .= trim($tpval, '`');
            } else {
                $token_prop_entry .= "'".addslashes($tpval)."'";
            }
            $tp_parts[]= $token_prop_entry;
        }
        if (count($tp_parts) > 0) {
            $meta_parts []= "array(".join(", ", $tp_parts).")";
        }
        $meta_parts []= '$_is_wrapper_meta';
        //$code .= " + \$_is_wrapper_meta"; 
        //} else {
        
        if ($token->get_prop('editable') == 'false') {
            //$code .= ' + array("editable"=>false)';
            $meta_parts []= 'array("editable"=>false)';
        }
        if ($real_val_defined) {
            //$code .= ' + array("real_value" => '.$real_val_var.')';
            $meta_parts []= 'array("real_value" => '.$real_val_var.')';
        }
        $code .= 'array_merge('.join(", ", $meta_parts).')';
        $code .= "\n);\n";
        $code .= "?>";
        return $code;
    }
    
    protected function _get_var_meta_expression($token, $var_token, $ep) {
        // Expression to get var meta
        $var_meta_expr = '$this->get_var_meta(';
        // if var is smth like $item['parent']['url'], 
        // it should be get_var_meta('url', fx::dig( $this->v('item'), 'parent'))
        if ($var_token->last_child) {
            if ($var_token->last_child->type == fx_template_expression_parser::T_ARR) {
                $last_index = $var_token->pop_child();
                $tale = $ep->compile($last_index).', ';
                $tale .= $ep->compile($var_token).')';
                $var_meta_expr .= $tale;
            } else {
                $var_meta_expr .= ')';
            }
        } elseif ($var_token->context_offset !== null) {
            $prop_name = array_pop($var_token->name);
            $var_meta_expr .= '"'.$prop_name.'", '.$ep->compile($var_token);
            $var_meta_expr .= ')';
        } else {
            $var_meta_expr .= '"'.$token->get_prop('id').'")';
        }
        return $var_meta_expr;
    }
    
    protected function varialize($var) {
        return preg_replace("~^_+|_+$~", '', 
                preg_replace(
            '~[^a-z0-9_]+~', '_', 
            preg_replace('~(?:\$this\->v|fx\:\:dig)~', '', $var)
        ));
    }
    
    protected function _token_with_each_to_code(fx_template_token $token) {
        $expr = self::parse_expression($token->get_prop('select'));
        $arr_id = '$'.$this->varialize($expr).'_items';
        
        $each_token = new fx_template_token('each', 'double', array(
            'select' => '`'.$arr_id.'`',
            'as' => $token->get_prop('as'),
            'key' => $token->get_prop('key')
        ));
        
        
        if ( ($separator = $this->_find_separator($token)) ) {
            $each_token->add_child($separator);
        }
        
        
        $code .= "<?php\n";
        $code .= $arr_id.' = '.$expr.";\n";
        $code .= "if (".$arr_id." && (is_array(".$arr_id.") || ".$arr_id." instanceof Traversable) && count(".$arr_id.")) {\n?>";
        
        $items = array();
        
        foreach ($token->children as $child) {
            if ($child->name == 'item') {
                $items[]= $child;
            }
        }
        
        usort($items, function($a, $b) {
            $ta = $a->get_prop('test') ? 1 : 0;
            $tb = $b->get_prop('test') ? 1 : 0;
            return $tb - $ta;
        });
        
        $all_subroot = true;
        $target_token = $each_token;
        foreach ($items as $num => $item) {
            $test = $item->get_prop('test');
            $item_subroot = $item->get_prop('subroot');
            if (!$item_subroot || $item_subroot == 'false') {
                $all_subroot = false;
            }
            if (!$test) {
                $test = 'true';
            }
            $cond_token = new fx_template_token(
                $num == 0 ? 'if' : 'elseif', 
                'double', 
                array('test' => $test)
            );
            foreach ($item->get_children() as $item_child) {
                $cond_token->add_child($item_child);
            }
            $target_token->add_child($cond_token);
            $target_token = $cond_token;
        }
        if ($all_subroot) {
            $each_token->set_prop('subroot', 'true');
        }
        
        $in_items = false;
        $each_added = false;
        foreach ($token->children as $child) {
            if ($child->name == 'item' && !$in_items) {
                $in_items = true;
            }
            if (!$in_items) {
                $code .= $this->_get_token_code($child, $token);
                continue;
            }
            if (!$each_added) {
                $code .= $this->_get_token_code($each_token, $token);
                $each_added = true;
            }
            if ($child->name == 'item' || $child->is_empty()) {
                continue;
            }
            $in_items = false;
            $code .= $this->_get_token_code($child, $token);
        }
        
        $code .= "<?php\n}\n?>";
        return $code;
    }
    /*
     * Find & remove separator from token children and return it
     * separator is special token {separator}..{/separator} or var {%separator}..{/%}
     */
    protected function _find_separator(fx_template_token $token) {
        $separator = null;
        if ( ($separator_text = $token->get_prop('separator')) ) {
            $separator = new fx_template_token('separator', 'double', array());
            $separator_text = new fx_template_token('code', 'single', array('value' => $separator_text));
            $separator->add_child($separator_text);
            return $separator;
        }
        foreach ($token->get_children() as $each_child_num => $each_child) {
            if (
                $each_child->name == 'separator' || 
                ($each_child->name == 'var' && $each_child->get_prop('id') == 'separator')
            ) {
                if ($each_child->name == 'var') {
                    $separator = new fx_template_token('separator', 'double', array());
                    $separator->add_child($each_child);
                } else {
                    $separator = $each_child;
                }
                
                $token->set_child(null, $each_child_num);
                break;
            }
        }
        return $separator;
    }
    
    protected function _get_item_code($token, $item_alias, $counter_id = null, $arr_id = 'array()') {
        $code = '';
        $is_essence = '$'.$item_alias."_is_essence";
        $code .=  $is_essence ." = \$".$item_alias." instanceof fx_essence;\n";
        $is_complex = 'is_array($'.$item_alias.') || is_object($'.$item_alias.')';
        $code .= '$this->push_context( '.$is_complex.' ? $'.$item_alias." : array());\n";
        
        $meta_test = "\tif (\$_is_admin && ".$is_essence." ) {\n";
        $code .= $meta_test;
        $code .= "\t\tob_start();\n";
        $code .= "\t}\n";
        $code .= $this->_children_to_code($token)."\n";
        $code .= $meta_test;
        $code .= "\t\techo \$".$item_alias."->add_template_record_meta(".
                    "ob_get_clean(), ".
                    $arr_id.", ".
                    ($counter_id ? '$'.$counter_id." - 1, " : '$this->v("position") - 1, ').
                    ($token->get_prop('subroot') ? 'true' : 'false').
                ");\n";
        $code .= "\t}\n";
        $code .= "\$this->pop_context();\n";
        return $code;
    }

    protected function _token_each_to_code(fx_template_token $token) {
        $code = "<?php\n";
        $select = $token->get_prop('select');
        if (empty($select)) {
            $select = '$.items';
        }
        $arr_id = self::parse_expression($select);
        
        
        $loop_alias = 'null';
        $item_alias = $token->get_prop('as');
        
        if (!preg_match('~^\$[a-z0-9_]+$~', $arr_id)) {
            $arr_hash_name = '$arr_'.$this->varialize($arr_id);
            $code .= $arr_hash_name .'= '.$arr_id.";\n";
            $arr_id = $arr_hash_name;
        }
        
        if (!$item_alias) {
            $item_alias = $arr_id.'_item';
        } else {
            $loop_alias = '"'.preg_replace('~^\$~', '', $item_alias).'"';
        }
        $item_alias = preg_replace('~^\$~', '', $item_alias);
        
        // key for loop
        $loop_key = 'null';
        
        $item_key = $token->get_prop('key');
        if (!$item_key) {
            $item_key = $item_alias.'_key';
        } else {
            $item_key = preg_replace('~^\$~', '', $item_key);
            $loop_key = '"'.$item_key.'"';
        }
        
        $separator = $this->_find_separator($token);
        $code .= "if (is_array(".$arr_id.") || ".$arr_id." instanceof Traversable) {\n";
        $loop_id = '$'.$item_alias.'_loop';
        $code .=  $loop_id.' = new fx_template_loop('.$arr_id.', '.$loop_key.', '.$loop_alias.");\n";
        //$code .= '$this->context_stack[]= '.$loop_id.";\n";
        $code .= "\$this->push_context(".$loop_id.", array('transparent' => true));\n";
        $code .= "\nforeach (".$arr_id." as \$".$item_key." => \$".$item_alias.") {\n";
        $code .= $loop_id."->_move();\n";
        // get code for step with scope & meta
        $code .= $this->_get_item_code($token, $item_alias, $counter_id, $arr_id);
        
        if ($separator) {
            $code .= 'if (!'.$loop_id.'->is_last()) {'."\n";
            $code .= $this->_children_to_code($separator);
            $code .= "\n}\n";
        }
        $code .= "}\n"; // close foreach
        //$code .= 'array_pop($this->context_stack);'."\n"; // pop loop object
        $code .= "\$this->pop_context();\n";
        $code .= "}\n";  // close if
        $code .= "\n?>";
        return $code;
    }
    
    protected function _token_with_to_code($token) {
        $code = "<?php\n";
        $expr = self::parse_expression($token->get_prop('select'));
        $item_name = $this->varialize($expr).'_with_item';
        $code .= '$'.$item_name.' = '.$expr.";\n";
        $code .= "if ($".$item_name.") {\n";
        $code .= $this->_get_item_code($token, $item_name);
        $code .= "}\n";
        $code .= "?>";
        return $code;
    }

    protected function _token_template_to_code($token) {
        $this->_register_template($token);
    }
    
    protected function _token_set_to_code($token) {
        $var = $token->get_prop('var');
        $value = self::parse_expression($token->get_prop('value'));
        $is_default = $token->get_prop('default');
        $code .= "<?php\n";
        
        if (preg_match("~\.~",$var)) {
            $parts = explode('.', $var, 2);
            $var_name = trim($parts[0], '$');
            $var_path = $parts[1];
            $code .= 'fx::dig_set($this->v("'.$var_name.'"), "'.$var_path.'", '.$value.");\n";
            $code .= "?>\n";
            return $code;
        }
        
        $var = $this->varialize($var);
        
        if ($is_default) {
            $code .= "if (is_null(\$this->v('".$var."','local'))) {\n";
        }
        
        $code .= '$this->set_var("'.$var.'", '.$value.');'."\n";
        if ($is_default) {
            $code .= "}\n";
        }
        $code .= "?>\n";
        return $code;
    }

    protected static function _get_area_local_templates($area_token) {
        $templates = array();
        $traverse = function(fx_template_token $node) use (&$templates, &$traverse) {
            foreach ($node->get_children() as $child) {
                if ($child->name === 'area') {
                    continue;
                }
                if ($child->name === 'template') {
                   $templates[]= $child->get_prop('id'); 
                }
                $traverse($child);
            }
        };
        $traverse($area_token);
        return $templates;
    }
    
    protected function _token_area_to_code($token) {
        //$token_props = var_export($token->get_all_props(),1);
        $token_props_parts = array();
        $local_templates = self::_get_area_local_templates($token);
        $parsed_props = array();
        foreach ($token->get_all_props() as $tp => $tpval) {
            $c_part = "'".$tp."' => ";
            if ($tp === 'suit') {
                $res_suit = fx_template_suitable::compile_area_suit_prop(
                    $tpval, 
                    $local_templates, 
                    $this->_template_set_name
                );
                $c_val = "'".$res_suit."'";
            } elseif (preg_match("~^`.+`$~s", $tpval)) {
                $c_val = trim($tpval, '`');
            } elseif (preg_match('~\$~', $tpval)) {
                $c_val = $this->parse_expression($tpval);
            } else {
                $c_val = "'".addslashes($tpval)."'";
            }
            $parsed_props[$tp] = $c_val;
            $token_props_parts []= $c_part.$c_val;
        }
        $token_props = 'array('.join(", ", $token_props_parts).')';
        $res = '';
        $res = '<?php $this->push_context(array("area_infoblocks" => fx::page()->get_area_infoblocks('.$parsed_props['id'].")));\n?>";
        $render_called = false;
        foreach ($token->get_children() as $child_num => $child) {
            if ($child->name == 'template') {
                $child->set_prop('area', $token->get_prop('id'));
                if (!$render_called) {
                    if ($child_num > 0) {
                        $res = 
                            "<?php\n".
                            'if ($_is_admin) {'."\n".
                            'echo $this->render_area('.$token_props.', \'marker\');'."\n".
                            '}'."\n?>\n".
                            $res.
                            '<?php echo $this->render_area('.$token_props.', \'data\');?>';
                    } else {
                        $res .= '<?php echo $this->render_area('.$token_props.');?>';
                    }
                    $render_called = true;
                }
                $this->_register_template($child);
            } else {
                $res .= $this->_get_token_code($child, $token);
            }
        }
        if (!$render_called) {
            $res = '<?php echo $this->render_area('.$token_props.');?>'.$res;
        }
        $res .= "<?php \$this->pop_context();\n?>";
        return $res;
    }
    
    protected function _token_if_to_code($token) {
        $code  = "<?php\n";
        $cond = $token->get_prop('test');
        $cond = trim($cond);
        $cond = self::parse_expression($cond);
        if (empty($cond)) {
            $cond = 'false';
        }
        $code .= 'if ('.$cond.") {\n";
        $code .= $this->_children_to_code($token)."\n";
        $code .= "} ";
        $code .= $this->_elses_to_code($token);
        $code .= "\n?>";
        return $code;
    }
    
    protected function _token_else_to_code($token) {
        $code .= " else {\n";
        $code .= $this->_children_to_code($token)."\n";
        $code .= "}\n";
        return $code;
    }


    protected function _token_elseif_to_code($token) {
        $cond = $token->get_prop('test');
        $cond = trim($cond);
        $cond = self::parse_expression($cond);
        
        $code = ' elseif ('.$cond.') {'."\n";
        $code .= $this->_children_to_code($token)."\n";
        $code .= "} ";
        $code .= $this->_elses_to_code($token);
        $code .= "\n";
        return $code;
    }
    
    protected function _token_js_to_code($token) {
        return $this->_token_headfile_to_code($token, 'js');
    }
    
    protected function _token_css_to_code($token) {
        return $this->_token_headfile_to_code($token, 'css');
    }
    
    protected function _token_headfile_to_code($token, $type) {
        $code .= "<?php\n";
        foreach ($token->get_children() as $set) {
            $set = preg_split("~[\n]~", $set->get_prop('value'));
            foreach ($set as $file) {
                $file = trim($file);
                if (empty($file)) {
                    continue;
                }
                $res_string = '';
                $alias = null;
                if (preg_match('~\sas\s~', $file)) {
                    $file_parts = explode(" as ", $file);
                    $file = trim($file_parts[0]);
                    $alias = trim($file_parts[1]);
                }
                // constant
                if (preg_match("~^[A-Z0-9_]+$~", $file)) {
                    $res_string = $file;
                } elseif (!preg_match("~^(/|https?://)~", $file)) {
                    $res_string = '$template_dir."'.$file.'"';
                } else {
                    $res_string = '"'.$file.'"';
                }
                if ($alias) {
                    $code .= "if (!fx::page()->has_file_alias('".$alias."', '".$type."')) {\n";
                }
                $code .= 'fx::page()->add_'.$type.'_file('.$res_string.");\n";
                if ($alias) {
                    $code .= "fx::page()->has_file_alias('".$alias."', '".$type."', true);\n";
                    $code .= "}\n";
                }
            }
        }
        $code .= "\n?>";
        return $code;
    }

    protected function _get_token_code($token, $parent) {
        $method_name = '_token_'.$token->name.'_to_code';
        if (method_exists($this, $method_name)) {
            return call_user_func(array($this, $method_name), $token, $parent);
        }
        return '';
    }

    protected function _children_to_code(fx_template_token $token) {
        $parts = array();
        foreach ($token->get_children() as $child) {
            if ($child->name !== 'elseif' && $child->name !== 'else') {
                $parts []= $this->_get_token_code($child, $token);
            }
        }
        if (count($parts) == 0) {
            return '';
        }
        $code = '?>'.join("", $parts)."<?php ";
        return $code;
    }
    
    protected function _elses_to_code($token) {
        $code = '';
        foreach ($token->get_children() as $child) {
            if ($child->name == 'elseif' || $child->name == 'else') {
                $code .= $this->_get_token_code($child, $token);
            }
        }
        return $code;
    }
    
    protected function _make_template_code($tpl_props) {
        $tpl_id = $tpl_props['id'];
        
        $children_code = $tpl_props['_code'];
        
        $code = "public function tpl_".$tpl_id.'() {'."\n";
        
        $template_path = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', $this->_current_source_file);
        $template_path = str_replace('\\', '/', $template_path);
        $template_dir = preg_replace("~/[^/]+$~", '', $template_path).'/';
        
        $code .= "fx::env()->add_current_template(\$this);\n";
        
        $code .= "\$template_dir = '".$template_dir."';\n";
        $code .= "\$_is_admin = \$this->is_admin();\n";
        $code .= 'if ($_is_admin) {'."\n";
        $code .= "\$_is_wrapper_meta = \$this->is_wrapper() ? array('template_is_wrapper' => 1) : array();\n";
        $code .= "}\n";
        
        if (isset($tpl_props['_variants'])) {
            foreach ($tpl_props['_variants'] as &$v) {
                $t = $v['_token'];
                if ( !($prior = $t->get_prop('priority')) ){ 
                    $prior = $t->get_prop('test') ? 0.5 : 0;
                }
                $v['_priority'] = $prior;
            }
            
            usort($tpl_props['_variants'], function($a, $b) {
                return $a['_priority'] - $b['_priority'];
            });
            
            foreach ($tpl_props['_variants'] as $var_num => $var) {
                $token = $var['_token'];
                $test = $token->get_prop('test');
                if (!$test) {
                    $test = 'true';
                }
                $code .= $var_num == 0 ? 'if' : 'elseif';
                $code .= '('.self::parse_expression($test).") {\n";
                $is_subroot = $token->get_prop('subroot') ? 'true' : 'false';
                $code .= "\t\$this->is_subroot = ".($is_subroot).";\n";
                $code .= $var['_code']."\n"; //$this->_children_to_code($token)."\n";
                $code .= "}\n";
            }
        } else {
            $token = $tpl_props['_token'];
            $is_subroot = $token->get_prop('subroot') ? 'true' : 'false';
            $code .= "\t\$this->is_subroot = ".($is_subroot).";\n";
            $code .= $children_code;
        }
        $code .= "fx::env()->pop_current_template();\n";
        $code .= "\n}\n";
        return $code;
    }
    
    protected function _get_template_props(fx_template_token $token) {
        $tpl_props = array(
            'id' => $token->get_prop('id'),
            'file' => $this->_current_source_file
        );
        if ( ($offset = $token->get_prop('offset')) ) {
            $tpl_props['offset'] = $offset;
        }
        if ( ($size = $token->get_prop('size'))) {
            $tpl_props['size'] = $size;
        }
        if ( ($suit=  $token->get_prop('suit'))) {
            $tpl_props['suit'] = $suit;
        }
        if (  ($area_id = $token->get_prop('area'))) {
            $tpl_props['area'] = $area_id;
        }
        
        if ( !($name = $token->get_prop('name'))) {
            $name = $token->get_prop('id');
        }
        
        $tpl_props['full_id'] = $this->_template_set_name.'.'.$tpl_props['id'];
        
        $of = $token->get_prop('of');
        $of_map = array(
            'menu' => 'component_section.list',
            'wrapper' => 'widget_wrapper.show', // fake widget!
            'blockset' => 'widget_blockset.show',
            'grid' => 'widget_grid.show',
            'block' => 'widget_block.show' // no implementation yet
        );

        if (isset($of_map[$of])) {
            $of = $of_map[$of];
        }
        
        
        if (!$of) {
            if ($this->_controller_type != 'layout') {
                $of = $this->_controller_type."_".$this->_controller_name.".".$token->get_prop('id');
            } else {
                $of = false;
            }
        } elseif ($of === 'false') {
            $of = false;
        } elseif (!preg_match("~\.~", $of ) ) {
            $of = $this->_controller_type."_".$this->_controller_name.".".$of;
        }
        
        if ($of && !preg_match("~^(layout|component_|widget_)~", $of)) {
            $of = 'component_'.$of;
        }
        
        $tpl_props += array(
            'name' => $name,
            'of' => $of,
            '_token' => $token
        );
        return $tpl_props;
    }
    
    protected function _register_template(fx_template_token $token) {
        if ($token->name != 'template') {
            return;
        }
        $tpl_id = $token->get_prop('id');
        
        $tpl_props = $this->_get_template_props($token);
        $tpl_props['_code'] = $this->_children_to_code($token);
        
        if (isset($this->templates[$tpl_id])) {
            // this is the second template with the same name
            if (!isset($this->templates[$tpl_id]['_variants'])) {
                $first_tpl = $this->templates[$tpl_id];
                $this->templates[$tpl_id] = $first_tpl + array(
                    '_variants' => array($first_tpl)
                );
            }
            $this->templates[$tpl_id]['_variants'][]= $tpl_props;
        } else {
            $this->templates[$tpl_id] = $tpl_props;
        }
    }
    
    /*
     * Passes through the upper level, starting the collection of templates deep
     */
    protected function _collect_templates($root) {
        foreach ($root->get_children() as $template_file_token) {
            $this->_current_source_file = $template_file_token->get_prop('source');
            foreach ($template_file_token->get_children() as $template_token) {
                $this->_register_template($template_token);
            }
        }
    }
    
    protected function  _make_code(fx_template_token $tree) {
        // Name of the class/template group
        $this->_template_set_name = $tree->get_prop('name');
        if ( ($ct = $tree->get_prop('controller_type'))) {
            $this->_controller_type = $ct;
        }
        if ( ($cn = $tree->get_prop('controller_name'))) {
            $this->_controller_name = $cn;
        }
        $this->_collect_templates($tree);
        ob_start();
        echo "<?php\n";
        echo 'class fx_template_'.$this->_template_set_name." extends fx_template {\n";
        
        $tpl_var = array();
        foreach ( $this->templates as $tpl_props) {
            echo $this->_make_template_code($tpl_props);
            unset($tpl_props['_token']);
            unset($tpl_props['_variants']);
            unset($tpl_props['_code']);
            $tpl_var []= $tpl_props;
        }
        echo 'protected $_templates = '.var_export($tpl_var,1).";\n";
        echo "}";
        $code = ob_get_clean();
        return $code;
    }
    
    /*
     * From comments: http://php.net/manual/en/function.php-check-syntax.php
     */
    public static function is_php_syntax_correct($code) {
        $braces = 0;
        $inString = 0;
        $code = preg_replace("~^\s*\<\?(php)?~", '', $code);
        $code = preg_replace("~\?>\s*$~", '', $code);
        // First of all, we need to know if braces are correctly balanced.
        // This is not trivial due to variable interpolation which
        // occurs in heredoc, backticked and double quoted strings
        $all_tokens = token_get_all('<?php '.$code);
        foreach ($all_tokens as $token) {
            if (is_array($token)) {
                switch ($token[0])  {
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                    case T_START_HEREDOC: ++$inString; break;
                    case T_END_HEREDOC:   --$inString; break;
                }
            } else if ($inString & 1) {
                switch ($token) {
                    case '`':
                    case '"': --$inString; break;
                }
            } else {
                switch ($token) {
                    case '`':
                    case '"': ++$inString; break;
                    case '{': ++$braces; break;
                    case '}':
                        if ($inString) {
                            --$inString;
                        } else {
                            --$braces;
                            if ($braces < 0) {
                                break 2;
                            }
                        }
                        break;
                }
            }
        }

        // Display parse error messages and use output buffering to catch them
        $prev_ini_log_errors = @ini_set('log_errors', false);
        $prev_ini_display_errors = @ini_set('display_errors', true);
        

        // If $braces is not zero, then we are sure that $code is broken.
        // We run it anyway in order to catch the error message and line number.

        // Else, if $braces are correctly balanced, then we can safely put
        // $code in a dead code sandbox to prevent its execution.
        // Note that without this sandbox, a function or class declaration inside
        // $code could throw a "Cannot redeclare" fatal error.

        $braces || $code = "if(0){{$code}\n}";
        
        ob_start();
        $eval_res = eval($code);
        
        if (false === $eval_res) {
            if ($braces) {
                $braces = PHP_INT_MAX;
            } else {
                // Get the maximum number of lines in $code to fix a border case
                false !== strpos($code, "\r") && $code = strtr(str_replace("\r\n", "\n", $code), "\r", "\n");
                $braces = substr_count($code, "\n");
            }

            $buffer_output = ob_get_clean();
            $buffer_output = strip_tags($buffer_output);
            
            // Get the error message and line number
            if (preg_match("'syntax error, (.+) in .+ on line (\d+)$'s", $buffer_output, $error_data)) {
                $error_data[2] = (int) $error_data[2];
                $error_data = $error_data[2] <= $braces
                    ? array_slice($error_data,1)
                    : array('unexpected $end' . substr($error_data[1], 14), $braces);
            }
            $result = array('syntax error', $error_data);
        } else {
            ob_end_clean();
            $result = true;
        }

        @ini_set('display_errors', $prev_ini_display_errors);
        @ini_set('log_errors', $prev_ini_log_errors);
        return $result;
    }
}