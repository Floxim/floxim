<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

/*
 * Transform token tree into php code
 */

class Compiler
{
    protected $template_set_name = null;
    protected $is_aliased = false;
    protected $current_source_file = null;
    protected $current_source_is_imported = false;
    protected $current_source_import_level = null;
    
    protected $state_stack = array();
    
    protected function pushState($type, $value)
    {
        if (!isset($this->state_stack[$type])) {
            $this->state_stack[$type] = array();
        }
        $this->state_stack[$type][]= $value;
    }
    
    protected function popState($type)
    {
        return array_pop($this->state_stack[$type]);
    }
    
    protected function getState($type)
    {
        if (!isset($this->state_stack[$type])) {
            return null;
        }
        if (count($this->state_stack[$type]) === 0) {
            return null;
        }
        return end($this->state_stack[$type]);
    }
    
    protected $config_entries = array();
    public function addTemplateConfigEntries($entries) 
    {
        $this->config_entries = $entries;
    }

    /**
     * Convert the tree of tokens in the php code
     * @param string $tree source code of the template
     * @return string of php code
     */
    public function compile($tree, $class_name)
    {
        $code = $this->makeCode($tree, $class_name);
        $code = self::addTabs($code);
        if (fx::config('templates.check_php_syntax')) {
            $is_correct = self::isPhpSyntaxCorrect($code);
            if ($is_correct !== true) {
                $error_line = $is_correct[1][1];
                $lines = explode("\n", $code);
                $lines[$error_line - 1] = '[[bad ' . $lines[$error_line - 1] . ']]';
                $lined = join("\n", $lines);
                $error = $is_correct[0] . ': ' . $is_correct[1][0] . ' (line ' . $error_line . ')';
                fx::debug($error, $lined);
                fx::log($error, $is_correct, $lined);
                throw new \Exception('Syntax error');
            }
        }
        return $code;
    }

    public static function addTabs($code)
    {
        $res = '';
        $level = 0;
        $code = preg_split("~[\n\r]+~", $code);
        foreach ($code as $s) {
            $s = trim($s);
            if (preg_match("~^[\}\)]~", $s)) {
                $level = $level > 0 ? $level - 1 : 0;
            }
            $res .= str_repeat("    ", $level) . $s . "\n";
            if (preg_match("~[\{\(]$~", $s)) {
                $level++;
            }
        }
        return $res;
    }


    protected $templates = array();

    protected $_code_context = 'text';

    protected function tokenCodeToCode($token)
    {
        return $token->getProp('value');
    }
    
    protected function tokenBemBlockToCode($token)
    {
        $code =  "<?php\n";
        $code .= "ob_start();\n";
        $this->pushState('edit', false);
        $code .= $this->childrenToCode($token);
        $this->popState('edit');
        $code .= '$block_string = ob_get_clean();'."\n";
        $code .= '$block_parts = \\Floxim\\Floxim\\Template\\Template::bemParseStr($block_string);'."\n";
        $code .= "\$this->bemStartBlock(\$block_parts['name']);\n";
        $code .= "echo \$block_parts['name'].' ';\n";
        $code .= "foreach (\$block_parts['modifiers'] as \$mod) {\n";
        $code .= "echo \$block_parts['name'].'_'.\$mod.' ';\n";
        $code .= "}\n";
        $code .= "echo join(' ', \$block_parts['plain']);\n";
        $code .= "?>";
        return $code;
    }
    
    protected function tokenBemElementToCode($token)
    {
        $code =  "<?php\n";
        $code .= "ob_start();\n";
        $this->pushState('edit', false);
        $code .= $this->childrenToCode($token);
        $this->popState('edit');
        $code .= '$el_string = ob_get_clean();'."\n";
        $code .= '$el_parts = \\Floxim\\Floxim\\Template\\Template::bemParseStr($el_string);'."\n";
        $code .= "\$full_name = \$this->bemGetBlock().'__'.\$el_parts['name'];\n";
        $code .= "echo \$full_name.' ';\n";
        $code .= "foreach (\$el_parts['modifiers'] as \$mod) {\n";
        $code .= "echo \$full_name.'_'.\$mod.' ';\n";
        $code .= "}\n";
        $code .= "echo join(' ', \$el_parts['plain']);\n";
        $code .= "?>";
        return $code;
    }
    
    protected function tokenLangToCode($token)
    {
        $code =  "<?php\n";
        $code .= "ob_start();\n";
        $code .= $this->childrenToCode($token);
        $code .= "\$lang_key = trim(ob_get_clean());\n";
        $dict = $token->getProp('dict');
        if (!$dict) {
            $dict = 'system';
        }
        $code .= "echo fx::lang(\$lang_key, '".$dict."');\n";
        $code .= "?>";
        return $code;
    }

    protected function tokenHelpToCode($token)
    {
        $code = "<?php\n";
        $code .= "echo \$this->getHelp();\n";
        $code .= "?>";
        return $code;
    }
    
    protected function presetToTemplate(Token $token)
    {
        $tpl_token = Token::create('{template}');
        $target_tpl = $token->getProp('template');
        $full_target_tpl = $target_tpl;
        
        $id = preg_replace("~[^a-z0-9_]+~", '_', $target_tpl);
        if ( ($own_id = $token->getProp('id'))) {
            $id .= '_'.$own_id;
        }
        
        $source_info = array();
        if (preg_match("~[a-z0-9\._-]+?\:[a-z0-9_-]+$~", $target_tpl)) {
            try {
                $target_tpl_obj = fx::template($target_tpl);
                if ($target_tpl_obj) {
                    $source_info = $target_tpl_obj->getInfo();
                }
            } catch (\Exception $e) {
                fx::log('Can not init target template', $token, $e);
            }
        } else {
            $full_target_tpl = $this->template_set_name.':'.$target_tpl;
            if (isset($this->templates[$target_tpl])) {
                $source_info =& $this->templates[$target_tpl];
                if (!$own_id) {
                    $source_hash = md5($source_info['_code'].$target_tpl);
                    if (!isset($source_info['tags'])){
                        $source_info['tags'] = array();
                    }
                    $source_info['tags'][]= $source_hash;
                    $full_target_tpl .= '#'.$source_hash;
                }
            }
        }
        
        // copy these props from source template and/or preset token to the generated template
        $passed_props = array('suit', 'of', 'size');
        
        foreach ($passed_props as $passed_prop) {
            if (isset($source_info[$passed_prop])) {
                $tpl_token->setProp($passed_prop, $source_info[$passed_prop]);
            }
        }
        
        if (isset($source_info['name'])) {
            $tpl_token->setProp('name', $source_info['name']. ( $own_id ? '#'.$own_id : ''));
        }
        
        $tpl_token->setProp('is_preset_of', preg_replace("~\#.+$~", '', $full_target_tpl));
        
        if ($token->getProp('replace')) {
            $tpl_token->setProp('replace_original', true);
        }
        
        
        $tpl_token->setProp('id', $id);
        
        $passed_props []= 'name';
        
        foreach ($passed_props as $passed_prop) {
            if ( ($preset_prop = $token->getProp($passed_prop)) ) {
                $tpl_token->setProp($passed_prop, $preset_prop);
            }
        }
        
        //$apply = '{apply '.$token->getProp('template');
        $apply = '{apply '.$full_target_tpl;
        $vars = array();
        foreach ($token->getChildren() as $child) {
            if (in_array($child->name, array('js', 'css', 'param', 'if', 'else', 'elseif', 'set'))) {
                $tpl_token->addChild($child);
                continue;
            }
            if ($child->name !== 'code') {
                fx::log('unknown preset child', $child);
                continue;
            }
            $data = $child->getProp('value');
            $data = explode("\n", trim($data));
            foreach ($data as $line) {
                $line = trim($line);
                if (!preg_match("~([\$a-z0-9_-]+)\s*\:\s*(.+)$~i", $line, $parts)) {
                    continue;
                }
                $var = $parts[1];
                if (!preg_match("~^\$~", $var)) {
                    $var = '$'.$var;
                }
                $val = $parts[2];
                if (!preg_match('~^([\"\\\']).+\1$~', $val)) {
                    $val = '"'.$val.'"';
                }
                $vars []= $var.' = '.$val;
            }
        }
        if (count($vars) > 0) {
            $apply .= ' with '.join(", ", $vars);
        }
        $apply .= '/}';
        $apply_token = Token::create($apply);
        $apply_token->setProp('extract_subroot', '$this->is_subroot');
        $tpl_token->addChild($apply_token);
        return $tpl_token;
    }

    protected function tokenCallToCode(Token $token)
    {
        $each = $token->getProp('each');
        if ($each) {
            $each_token = Token::create('{each}');
            $each_token->setProp('select', $each);

            $item = '$' . $this->varialize($each) . '_item';
            $each_token->setProp('as', $item);
            $token->setProp('each', '');
            $each_token->addChild($token);
            return $this->tokenEachToCode($each_token);
        }
        $code = "<?php\n";
        
        $is_apply = $token->getProp('apply');
        
        $tpl_name = $token->getProp('id');
        $tpl = '$tpl_' . $this->varialize($tpl_name);
        
        $call_children = $token->getChildren();
        /*
         * Converted:
         * {call id="wrap"}<div>Something</div>{/call}
         * like this:
         * {call id="wrap"}{var id="content"}<div>Something</div>{/var}{/call}
         */
        $has_content_param = false;
        foreach ($call_children as $call_child) {
            if ($call_child->name == 'code' && $call_child->isEmpty()) {
                continue;
            }
            if ($call_child->name != 'var') {
                $has_content_param = true;
                break;
            }
        }
        if ($has_content_param) {
            $token->clearChildren();
            $var_token = new Token('var', 'single', array('id' => 'content'));
            foreach ($call_children as $call_child) {
                $var_token->addChild($call_child);
            }
            $token->addChild($var_token);
        }
        $with_expr = $token->getProp('with');
        if ($with_expr) {
            $ep = new ExpressionParser();
            $with_expr = $ep->parseWith($with_expr);
        }
        $switch_context = is_array($with_expr) && isset($with_expr['$']); // && !$is_apply;
        if ($switch_context) {
            $new_context_expression = $this->parseExpression($with_expr['$']);
        }
        
        $passed_vars = array();
        if (is_array($with_expr)) {
            foreach ($with_expr as $alias => $var) {
                if ($alias == '$') {
                    continue;
                }
                $passed_vars [trim($alias, '$')] = array('string', $this->parseExpression($var));
            }
        }
        foreach ($token->getChildren() as $param_var_token) {
            // internal call only handle var
            if ($param_var_token->name != 'var') {
                continue;
            }
            $value_to_set = '';
            if ($param_var_token->hasChildren()) {
                // pass the inner html code
                $value_to_set .= "ob_start();\n";
                $value_to_set .= $this->childrenToCode($param_var_token);
                $value_to_set .= "\n";
                $passed_value_type = 'buffer';
            } elseif (($select_att = $param_var_token->getProp('select'))) {
                // pass the result of executing the php code
                $value_to_set = self::parseExpression($select_att);
                $passed_value_type = 'string';
            }
            $passed_vars[$param_var_token->getProp('id')] = array($passed_value_type, $value_to_set);
        }
        $switch_context_local = $switch_context && count($passed_vars) > 0;
        if ($is_apply) {
            $context_var = '$context';
        } else {
            if (count($passed_vars) > 0 || $switch_context) {
                $context_var = $tpl.'_context';
                $code .= $context_var. " = new \\Floxim\\Floxim\\Template\\".fx::config('templates.context_class')."();\n";
            } else {
                $context_var = 'new \\Floxim\\Floxim\\Template\\'.fx::config('templates.context_class').'()';
            }
        }
        
        // switch context to calculate passed vars inside it
        if ($switch_context_local) {
            $code .= '$context->push(' . $new_context_expression . ");\n";
        }
        
        if (count($passed_vars) > 0) {
            $tpl_passed = $tpl."_passed";
            $code .=  $tpl_passed ." = array();\n";
            foreach ($passed_vars as $passed_var_key => $passed_var) {
                switch ($passed_var[0]) {
                    case 'string': default:
                        $code .= $tpl_passed."['".$passed_var_key."'] = ".$passed_var[1].";\n";
                        break;
                    case 'buffer':
                        $code .= $passed_var[1];
                        $code .= $tpl_passed."['".$passed_var_key."'] = ob_get_clean();\n";
                }
            }
            // passed vars calculated, clear context
            if ($switch_context_local) {
                $code .= "\$context->pop();\n";
            }
        }
        if ($switch_context) {
            $code .= $context_var . "->push(" . $new_context_expression . ");\n";
        }
        
        if (isset($tpl_passed)) {
            $code .= $context_var."->push(".$tpl_passed.", array('transparent' => true));\n";
        }
        
        // ------------
        
        $tpl_name_is_expression = !preg_match("~^[a-z0-9_\,\.\:\@\#]+$~", $tpl_name);
        

        
        $loader = "\\Floxim\\Floxim\\Template\\Loader";
        // not a plain name
        if ($tpl_name_is_expression) {
            $tpl_name = self::parseExpression($tpl_name);
            $pn = $tpl.'_parsed';
            $code .= $pn.' = '.$loader.'::parseTemplateName('.
                    $tpl_name.', '.
                    var_export($this->template_set_name,1).', '.
                    var_export($this->is_aliased,1).");\n";
            
            $code .= $tpl." = ";
            $code .= $loader."::loadTemplateVariant(".
                        $pn.'["group"], '.
                        $pn.'["action"], '.
                        $context_var.', '.
                        $pn.'["forced_group"], '.
                        $pn.'["tags"]); '."\n";
        } else {
            $parsed_name = \Floxim\Floxim\Template\Loader::parseTemplateName(
                $tpl_name, 
                $this->template_set_name,
                $this->is_aliased
            );
            foreach ($parsed_name as &$v) {
                $v = var_export($v,1);
            }
            $code .= $tpl." = ";
            $code .= $loader."::loadTemplateVariant(".
                        $parsed_name['group'].", ".
                        $parsed_name['action'].", ".
                        $context_var.", ".
                        $parsed_name['forced_group'].', '.
                        $parsed_name['tags'].");\n";
        }
        
        /*
        if (!preg_match("~[\:\@]~", $tpl_name)) {
           $tpl_name = $this->template_set_name . ":" . $tpl_name;
        }
        
        $tpl_at_parts = explode("@", $tpl_name);
        if (count($tpl_at_parts) === 1) {
            $forced_group = 'null';
            list($set_name, $action_name) = explode(":", $tpl_name);
            // save @ for named ("aliased") template groups (like "@admin")
            if ($set_name === $this->template_set_name && $this->is_aliased) {
                $set_name = '@'.$set_name;
            }
        } else {
            $forced_group = !empty($tpl_at_parts[0]) ? $tpl_at_parts[0] : $this->template_set_name;
            $action_parts = explode(":", $tpl_at_parts[1]);
            if (count($action_parts) === 1) {
                    array_unshift($action_parts, $forced_group);
            }
            list($set_name, $action_name) = $action_parts;
            $forced_group = "'".$forced_group."'";
        }
        $tag_parts = explode("#", $action_name);
        if (count($tag_parts) > 1) {
            $action_name = $tag_parts[0];
            $tags = "array('".join("', '", explode(",", $tag_parts[1]))."')";
        } else {
            $tags = 'null';
        }
        */
        
        
        
        
        $code .= "if ( ".$tpl." ) {\n";
        $code .= "echo ".$tpl."->setParent(\$this)->render();\n";
        if ( ($subroot_var = $token->getProp('extract_subroot'))) {
            $code .= $subroot_var. " = ".$tpl."->is_subroot;\n";
        }
        $code .= "}\n";
        // ------------
        
        // clear vars passed into child template from current context
        if ($is_apply && count($passed_vars) > 0) {
            $code .= "\$context->pop();\n";
        }
        // clear context object
        if ($is_apply && $switch_context) {
            $code .= "\$context->pop();\n";
        }
        $code .= "\n?>";
        return $code;
    }

    public function parseExpression($str)
    {

        static $parser = null;
        if (is_null($parser)) {
            $parser = self::getExpressionParser();
        }
        return $parser->compile($parser->parse($str));
    }
    
    public static function getExpressionParser() {
        $parser = new ExpressionParser();
        $parser->local_vars [] = '_is_admin';
        $parser->local_vars []= 'context';
        return $parser;
    }

    protected function applyModifiers($display_var, $modifiers, $token)
    {
        if (!$modifiers || count($modifiers) == 0) {
            return '';
        }
        $token_type = $token->getProp('type');
        $code = '';
        foreach ($modifiers as $mod) {
            $mod_callback = $mod['name'];

            if (isset($mod['is_template']) && $mod['is_template']) {
                $call_token = new Token('call', 'single', array('id' => $mod['name'], 'apply' => true));
                if (isset($mod['with'])) {
                    $call_token->setProp('with', $mod['with']);
                }
            }

            if ($mod['is_each'] && $mod['is_template']) {
                $c_with = $call_token->getProp('with');
                //$call_token->setProp('with', "`" . $display_var . '`_item' . ($c_with ? ', ' . $c_with : ''));
                $each_token = new Token('each', 'single', array('select' => "`" . $display_var . "`"));
                $each_token->addChild($call_token);
                $code = "ob_start();\n?>";
                $code .= $this->tokenEachToCode($each_token);
                $code .= "<?php\n" . $display_var . " = ob_get_clean();\n";
                continue;
            }

            if ($mod["is_each"]) {
                $display_var_item = $display_var . "_item";
                $code .= 'foreach (' . $display_var . ' as &' . $display_var_item . ") {\n";
            } else {
                $display_var_item = $display_var;
            }

            if (empty($mod_callback)) {
                if ($token_type) {
                    $mod_callback = $token_type == 'image' ? 'fx::image' : 'fx::date';
                    $mod_callback .= '(';
                } else {
                    $token->need_type = true;
                    $mod_callback = 'call_user_func(';
                    $mod_callback .= '($var_type == "image" ? "fx::image" : ';
                    $mod_callback .= '($var_type == "datetime" ? "fx::date" : "fx::cb")), ';
                }
            } elseif (isset($mod['is_template']) && $mod['is_template']) {
                $code .= "ob_start();\n?>";
                $call_token->setProp('apply', true);
                $c_with = $call_token->getProp('with');
                $call_token->setProp('with', "`" . $display_var . '`' . ($c_with ? ', ' . $c_with : ''));
                $code .= $this->tokenCallToCode($call_token);
                $code .= "<?php\n" . $display_var_item . " = ob_get_clean();\n";
            } else {
                $mod_callback .= '(';
            }
            if (!isset($mod['is_template']) || !$mod['is_template']) {
                $args = array();
                $self_used = false;
                foreach ($mod['args'] as $arg) {
                    if ($arg == 'self') {
                        $args [] = $display_var_item;
                        $self_used = true;
                    } else {
                        $args [] = self::parseExpression($arg);
                    }
                }
                if (!$self_used) {
                    array_unshift($args, $display_var_item);
                }
                $code .= $display_var_item . ' = ' . $mod_callback . join(', ', $args) . ");\n";
            }
            if ($mod['is_each']) {
                $code .= "}\n";
            }
        }
        return $code;
    }

    protected function makeFileCheck($var, $use_stub = false)
    {

        $code = $var . ' = trim(' . $var . ");\n";
        //$code .= "if (!preg_match(\"~^###fxf\d+~\", ".$var.")) {\n";
        $code .= "\nif (" . $var . " && !preg_match('~^(https?://|/)~', " . $var . ")) {\n";
        $code .= $var . '= $template_dir.' . $var . ";\n";
        $code .= "}\n";

        $code .= 'if (!' . $var . ' || ( !preg_match("~^https?://~", ' . $var . ') && !file_exists(fx::path()->abs(preg_replace("~\?.+$~", "", ' . $var . '))) )) {' . "\n";
        if ($use_stub) {
            $stub_image = fx::path()->http('@floxim/Admin/style/images/no.png');
            $code .= $var . "= \$_is_admin ? '" . $stub_image . "' : '';\n";
        } else {
            $code .= $var . "= '';\n";
        }
        $code .= "}\n";
        //$code .= "}\n";
        return $code;
    }

    protected function tokenVarToCode(Token $token)
    {
        $code = "<?php\n";
        // parse var expression and store token 
        // to create correct expression for get_var_meta()
        //$ep = new ExpressionParser();
        $ep = self::getExpressionParser();
        $expr_token = $ep->parse('$' . $token->getProp('id'));
        $expr = $ep->compile($expr_token);
        $var_token = $expr_token->last_child;
        
        $var_meta_defined = false;

        $modifiers = $token->getProp('modifiers');
        $token->setProp('modifiers', null);
        $token_is_visual = $token->getProp('var_type') == 'visual';

        $token_type = $token->getProp('type');
        // analyze default value to get token type and wysiwyg linebreaks mode
        if (
            !$token_type ||
            ($token_type == 'html' && !$token->getProp('linebreaks'))
        ) {
            $linebreaks = $token_is_visual;
            foreach ($token->getChildren() as $child) {
                $child_source = $child->getProp('value');
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
                $token->setProp('type', $token_type);
            }
            if ($linebreaks || $token_is_visual) {
                $token->setProp('linebreaks', $linebreaks);
            }
        }

        // e.g. "name" or "image_".$context->get('id')
        $var_id = preg_replace('~^\$context->get\(~', '', preg_replace("~\)$~", '', $expr));

        $has_default = $token->getProp('default') || count($token->getChildren()) > 0;

        // if var has default value or there are some modifiers
        // store real value for editing
        $real_val_defined = false;
        $var_chunk = $this->varialize($var_id);
        $token_is_file = ($token_type == 'image' || $token_type == 'file');

        if ($modifiers || $has_default || $token->getProp('inatt')) {
            $real_val_var = '$' . $var_chunk . '_real_val';

            $code .= $real_val_var . ' = ' . $expr . ";\n";

            if ($token_is_file) {
                $code .= $this->makeFileCheck($real_val_var, !$has_default);
            }

            if ($modifiers || $has_default) {
                $display_val_var = '$' . $var_chunk . '_display_val';
                $code .= $display_val_var . ' = ' . $real_val_var . ";\n";
            } else {
                $display_val_var = $real_val_var;
            }
            $expr = $display_val_var;
            $real_val_defined = true;
        }

        $var_meta_expr = $this->getVarMetaExpression($token, $var_token, $ep);

        if ($has_default) {
            $code .= "\nif (is_null(" . $real_val_var . ") || " . $real_val_var . " == '') {\n";

            if (!($default = $token->getProp('default'))) {
                // ~= src="{%img}{$img /}{/%}" --> src="{%img}{$img type="image" /}{/%}
                $token_def_children = $token->getNonEmptyChildren();
                if (count($token_def_children) == 1 && $token_def_children[0]->name == 'var') {
                    $def_child = $token_def_children[0];
                    if (!$def_child->getProp('type')) {
                        $def_child->setProp('type', $token_type);
                    }
                }
                $has_complex_tokens = false;
                $default_parts = array();
                foreach ($token_def_children as $def_child) {
                    if ($def_child->name != 'code') {
                        $has_complex_tokens = true;
                        break;
                    }
                    $def_child_code = $def_child->getProp('value');
                    if (preg_match("~<\?(php|=)~", $def_child_code)) {
                        $has_complex_tokens = true;
                        break;
                    }
                    $default_parts [] = '"' . addslashes($def_child_code) . '"';
                }
                if ($has_complex_tokens) {
                    $code .= "\tob_start();\n";
                    if (!$token_is_visual) {
                        $code .= '$var_has_meta = count(' . $var_meta_expr . ");\n";
                        $code .= "if (\$var_has_meta) {\n";
                    }
                    $code .= '$' . $var_chunk . '_was_admin = $_is_admin;' . "\n";
                    $code .= '$_is_admin = false;' . "\n";
                    if (!$token_is_visual) {
                        $code .= "}\n";
                    }
                    $code .= "\t" . $this->childrenToCode($token);
                    if (!$token_is_visual) {
                        $code .= "if (\$var_has_meta) {\n";
                    }
                    $code .= '$_is_admin = $' . $var_chunk . '_was_admin;' . "\n";
                    if (!$token_is_visual) {
                        $code .= "}\n";
                    }
                    $default = "ob_get_clean()";
                } else {
                    $default = join(".", $default_parts);
                }
            }
            if ($real_val_defined) {
                $code .= "\n" . $display_val_var . ' = ' . $default . ";\n";
                if ($token_is_file) {
                    $code .= $this->makeFileCheck($display_val_var, true);
                }
                if ($token_is_visual) {
                    $code .= "\n" . '$context->set(' . $var_id . ',  ' . $display_val_var . ");\n";
                }
            } elseif ($token_is_visual) {
                $code .= "\n" . '$context->set(' . $var_id . ',  ' . $default . ");\n";
            }
            $code .= "}\n";
        }


        if ($modifiers) {
            $modifiers_code = $this->applyModifiers($display_val_var, $modifiers, $token);
            if ($token->need_type) {
                $code .= '$var_meta = ' . $var_meta_expr . ";\n";
                $code .= '$var_type = $var_meta["type"]' . ";\n";
                $var_meta_defined = true;
            }
            $code .= $modifiers_code;
            
            // default formatters for date and image
            if (count($modifiers) === 1) {
                if (!$modifiers[0]['name'] && isset($modifiers[0]['args'][0])) {
                    $token->setProp('format_modifier', trim($modifiers[0]['args'][0], '"\''));
                } elseif (in_array($modifiers[0]['name'], array('fx::date', 'fx::image'))) {
                    $token->setProp('format_modifier', $modifiers[0]['args'][0]);
                }
            }
        }
        
        if ($token->getProp('editable') == 'false' || $this->getState('edit') === false) {
            $code .= 'echo  ' . $expr . ";\n";
        } else {
            $code .= 'echo !$_is_admin ? ' . $expr . ' : $this->printVar(' . "\n";
            $code .= $expr;
            $code .= ", \n";
            $meta_parts = array();
            if (!$token_is_visual) {
                $meta_parts [] = $var_meta_defined ? '$var_meta' : $var_meta_expr;
            }
            $token_props = $token->getAllProps();


            $tp_parts = array();

            foreach ($token_props as $tp => $tpval) {
                if (!$token_is_visual && in_array($tp, array('id', 'var_type'))) {
                    continue;
                }
                $token_prop_entry = "'" . $tp . "' => ";
                if ($tp == 'id') {
                    $token_prop_entry .= $var_id;
                } elseif (preg_match("~^\`.+\`$~s", $tpval)) {
                    $token_prop_entry .= trim($tpval, '`');
                } elseif (preg_match("~\{.+\}~", $tpval)) {
                    $ep = self::getExpressionParser();
                    $tpval = preg_replace_callback("~\{([^\}]+)\}~", function($m) use($ep) {
                        return "'.".$ep->build($m[1]).".'";
                    }, $tpval);
                    $token_prop_entry .= "'".$tpval."'";
                } elseif (preg_match("~^\\$~", $tpval)) {
                    $ep = self::getExpressionParser();
                    $tpval = $ep->build($tpval);
                    $token_prop_entry .= $tpval;
                } else {
                    $token_prop_entry .= "'" . addslashes($tpval) . "'";
                }
                $tp_parts[] = $token_prop_entry;
            }
            if (count($tp_parts) > 0) {
                $meta_parts [] = "array(" . join(", ", $tp_parts) . ")";
            }
            $meta_parts [] = '$_is_wrapper_meta';
            
            if ($real_val_defined) {
                $meta_parts [] = 'array("real_value" => ' . $real_val_var . ')';
            }
            $code .= 'array_merge(' . join(", ", $meta_parts) . ')';
            $code .= "\n);\n";
        }
        $code .= "?>";
        return $code;
    }

    protected function getVarMetaExpression($token, $var_token, $ep)
    {
        // Expression to get var meta
        $var_meta_expr = '$context->getVarMeta(';
        // if var is smth like $item['parent']['url'], 
        // it should be get_var_meta('url', fx::dig( $this->v('item'), 'parent'))
        if ($var_token->last_child) {
            if ($var_token->last_child->type == ExpressionParser::T_ARR) {
                $last_index = $var_token->popChild();
                $tale = $ep->compile($last_index) . ', ';
                $tale .= $ep->compile($var_token) . ')';
                $var_meta_expr .= $tale;
            } else {
                $var_meta_expr .= ')';
            }
        } elseif ($var_token->context_offset !== null) {
            $prop_name = array_pop($var_token->name);
            $var_meta_expr .= '"' . $prop_name . '", ' . $ep->compile($var_token);
            $var_meta_expr .= ')';
        } else {
            $var_meta_expr .= '"' . $token->getProp('id') . '")';
        }
        return $var_meta_expr;
    }

    protected function varialize($var)
    {
        //static $counter;
        //return 'v'.$counter++;
        return preg_replace("~^_+|_+$~", '',
            preg_replace(
                '~[^a-z0-9_]+~', '_',
                preg_replace('~(?:\$this\->v|fx\:\:dig)~', '', $var)
            ));
    }

    protected function tokenWithEachToCode(Token $token)
    {
        $expr = self::parseExpression($token->getProp('select'));
        $arr_id = '$' . $this->varialize($expr) . '_items';

        $each_token = new Token('each', 'double', array(
            'select'            => '`' . $arr_id . '`',
            'as'                => $token->getProp('as'),
            'key'               => $token->getProp('key'),
            'check_traversable' => 'false'
        ));

        if (($separator = $this->findSeparator($token))) {
            $each_token->addChild($separator);
        }


        $code = "<?php\n";
        $code .= $arr_id . ' = ' . $expr . ";\n";
        $code .= "if (" . 
                    $arr_id . " && (is_array(" . $arr_id . ") || " . 
                    $arr_id . " instanceof Traversable)) {\n";
        
        $code .= $this->getAdderPlaceholderCode($arr_id);
        
        $code .= "if (count(" . $arr_id . ")) {\n";
        
        $code .= "?>";
        
        $items = array();

        foreach ($token->children as $child) {
            if ($child->name == 'item') {
                $items[] = $child;
            }
        }

        usort($items, function ($a, $b) {
            $ta = $a->getProp('test') ? 1 : 0;
            $tb = $b->getProp('test') ? 1 : 0;
            return $tb - $ta;
        });

        $all_subroot = true;
        $target_token = $each_token;
        foreach ($items as $num => $item) {
            $test = $item->getProp('test');
            $item_subroot = $item->getProp('subroot');
            if (!$item_subroot || $item_subroot == 'false') {
                $all_subroot = false;
            }
            if (!$test) {
                $test = 'true';
            }
            $cond_token = new Token(
                $num == 0 ? 'if' : 'elseif',
                'double',
                array('test' => $test)
            );
            foreach ($item->getChildren() as $item_child) {
                $cond_token->addChild($item_child);
            }
            $target_token->addChild($cond_token);
            $target_token = $cond_token;
        }
        if ($all_subroot) {
            $each_token->setProp('subroot', 'true');
        }

        $in_items = false;
        $each_added = false;
        foreach ($token->children as $child) {
            if ($child->name == 'item' && !$in_items) {
                $in_items = true;
            }
            if (!$in_items) {
                $code .= $this->getTokenCode($child, $token);
                continue;
            }
            if (!$each_added) {
                $code .= $this->getTokenCode($each_token, $token);
                $each_added = true;
            }
            if ($child->name == 'item' || $child->isEmpty()) {
                continue;
            }
            $in_items = false;
            $code .= $this->getTokenCode($child, $token);
        }

        $code .= "<?php\n}\n}\n?>";
        return $code;
    }

    /*
     * Find & remove separator from token children and return it
     * separator is special token {separator}..{/separator} or var {%separator}..{/%}
     */
    protected function findSeparator(Token $token)
    {
        $separator = null;
        if (($separator_text = $token->getProp('separator'))) {
            $separator = new Token('separator', 'double', array());
            $separator_text = new Token('code', 'single', array('value' => $separator_text));
            $separator->addChild($separator_text);
            return $separator;
        }
        foreach ($token->getChildren() as $each_child_num => $each_child) {
            if (
                $each_child->name == 'separator' ||
                ($each_child->name == 'var' && $each_child->getProp('id') == 'separator')
            ) {
                if ($each_child->name == 'var') {
                    $separator = new Token('separator', 'double', array());
                    $separator->addChild($each_child);
                } else {
                    $separator = $each_child;
                }

                $token->setChild(null, $each_child_num);
                break;
            }
        }
        return $separator;
    }

    protected function getItemCode($token, $item_alias, $counter_id = null, $arr_id = 'array()')
    {
        $code = '';
        $is_entity = '$' . $item_alias . "_is_entity";
        $code .= $is_entity . " = \$" . $item_alias . " instanceof \\Floxim\\Floxim\\Template\\Entity;\n";
        $is_complex = 'is_array($' . $item_alias . ') || is_object($' . $item_alias . ')';
        $code .= '$context->push( ' . $is_complex . ' ? $' . $item_alias . " : array());\n";

        $meta_test = "\tif (\$_is_admin && " . $is_entity . " ) {\n";
        $code .= $meta_test;
        $code .= "\t\tob_start();\n";
        $code .= "\t}\n";
        
        if (!$token->getProp('subroot')) {
            $use_counted_subroot = true;
            $has_call = false;
            $subroot_var = '$'.$item_alias.'_counted_subroot';
            foreach ($token->getChildren() as $child) {
                if ($child->name === 'code' && !preg_match("~^\s+$~", $child->getProp('value'))) {
                    $use_counted_subroot = false;
                    break;
                }
                if ($child->name === 'call') {
                    $has_call = true;
                    $child->setProp('extract_subroot', $subroot_var);
                }
            }
            if (!$has_call) {
                $use_counted_subroot = false;
            }
            if ($use_counted_subroot) {
                $code .= $subroot_var ." = false;\n";
            }
        }
        $code .= $this->childrenToCode($token) . "\n";
        $code .= $meta_test;
        $code .= "\t\techo \$" . $item_alias . "->addTemplateRecordMeta(" .
            "ob_get_clean(), " .
            $arr_id . ", " .
            (!is_null($counter_id) ? $counter_id . " - 1, " : '0, ') .
            ($token->getProp('subroot') ? 'true' : ($use_counted_subroot ? $subroot_var : 'false' )) .
            ");\n";
        $code .= "\t}\n";
        $code .= "\$context->pop();\n";
        return $code;
    }
    
    protected function getAdderPlaceholderCode($arr_id)
    {
        $code = '';
        $code .= 'if ($_is_admin ';
        $code .= ' && ' . $arr_id . ' instanceof \\Floxim\\Floxim\\System\\Collection ';
        $code .= ' && isset(' . $arr_id . '->finder)';
        $code .= ' && $this->getMode("add") != "false" ';
        $code .= ' && ' . $arr_id . '->finder instanceof \\Floxim\\Main\\Content\\Finder) {' . "\n";
        $code .= $arr_id . '->finder->createAdderPlaceholder(' . $arr_id . ');' . "\n";
        $code .= "}\n";
        return $code;
    }

    protected function tokenEachToCode(Token $token)
    {
        $code = "<?php\n";
        $select = $token->getProp('select');
        if (empty($select)) {
            $select = '$.items';
        }
        $arr_id = self::parseExpression($select);


        $loop_alias = 'null';
        $item_alias = $token->getProp('as');

        if (!preg_match('~^\$[a-z0-9_]+$~', $arr_id)) {
            $arr_hash_name = '$arr_' . $this->varialize($arr_id);
            $code .= $arr_hash_name . '= ' . $arr_id . ";\n";
            $arr_id = $arr_hash_name;
        }

        if (!$item_alias) {
            $item_alias = $arr_id . '_item';
        } else {
            $loop_alias = '"' . preg_replace('~^\$~', '', $item_alias) . '"';
        }
        $item_alias = preg_replace('~^\$~', '', $item_alias);

        // key for loop
        $loop_key = 'null';

        $item_key = $token->getProp('key');
        if (!$item_key) {
            $item_key = $item_alias . '_key';
        } else {
            $item_key = preg_replace('~^\$~', '', $item_key);
            $loop_key = '"' . $item_key . '"';
        }

        $separator = $this->findSeparator($token);
        $check_traversable = $token->getProp('check_traversable') !== 'false';
        if ($check_traversable) {
            $code .= "if (is_array(" . $arr_id . ") || " . $arr_id . " instanceof Traversable) {\n";
            // add-in-place settings
            $code .= $this->getAdderPlaceholderCode($arr_id);
        }

        $loop_id = '$' . $item_alias . '_loop';
        $code .= $loop_id . ' = new \\Floxim\\Floxim\\Template\\Loop(' . $arr_id . ', ' . $loop_key . ', ' . $loop_alias . ");\n";
        
        $counter_id = $loop_id.'_counter';
        $code .= $counter_id." = 1;\n";
        
        $code .= "\$context->push(" . $loop_id . ", array('transparent' => true));\n";
        $code .= "\nforeach (" . $arr_id . " as \$" . $item_key . " => \$" . $item_alias . ") {\n";
        $code .= $loop_id . "->move();\n";
        // get code for step with scope & meta
        $code .= $this->getItemCode($token, $item_alias, $counter_id, $arr_id);

        if ($separator) {
            $code .= 'if (!' . $loop_id . '->isLast()) {' . "\n";
            $code .= $this->childrenToCode($separator);
            $code .= "\n}\n";
        }
        $code .= $counter_id."++;\n";
        $code .= "}\n"; // close foreach
        $code .= "\$context->pop();\n";
        if ($check_traversable) {
            $code .= "}\n";  // close if
        }
        $code .= "\n?>";
        return $code;
    }

    protected function tokenWithToCode($token)
    {
        $code = "<?php\n";
        $expr = self::parseExpression($token->getProp('select'));
        $item_name = $this->varialize($expr) . '_with_item';
        $code .= '$' . $item_name . ' = ' . $expr . ";\n";
        $code .= "if ($" . $item_name . ") {\n";
        $code .= $this->getItemCode($token, $item_name, 0);
        $code .= "}\n";
        $code .= "?>";
        return $code;
    }

    protected function tokenTemplateToCode($token)
    {
        $this->registerTemplate($token);
    }
    
    
    protected function tokenPresetToCode(Token $token)
    {
        $this->registerTemplate($token);
    }

    protected function tokenSetToCode($token)
    {
        $code = "<?php\n";
        
        $var = $token->getProp('var');
        
        if ($token->getProp('value')) {
            $value = self::parseExpression($token->getProp('value'));
        } else {
            $code .= "ob_start();\n";
            $code .= $this->childrenToCode($token);
            $value = "ob_get_clean()";
        }
        
        $is_default = $token->getProp('default');
        

        if (preg_match("~\.~", $var)) {
            $parts = explode('.', $var, 2);
            $var_name = trim($parts[0], '$');
            $var_path = $parts[1];
            $code .= 'fx::digSet($context->get("' . $var_name . '"), "' . $var_path . '", ' . $value . ");\n";
            $code .= "?>\n";
            return $code;
        }
        
        $var = $this->varialize($var);

        if ($is_default) {
            $code .= "if (is_null(\$context->get('" . $var . "'))) {\n";
        }

        $code .= '$context->set("' . $var . '", ' . $value . ');' . "\n";
        if ($is_default) {
            $code .= "}\n";
        }
        $code .= "?>\n";
        return $code;
    }

    protected static function getAreaLocalTemplates($area_token)
    {
        $templates = array();
        $traverse = function (Token $node) use (&$templates, &$traverse) {
            foreach ($node->getChildren() as $child) {
                if ($child->name === 'area') {
                    continue;
                }
                if ($child->name === 'template') {
                    $templates[] = $child->getProp('id');
                }
                $traverse($child);
            }
        };
        $traverse($area_token);
        return $templates;
    }

    protected function tokenAreaToCode($token)
    {
        //$token_props = var_export($token->get_all_props(),1);
        $token_props_parts = array();
        $local_templates = self::getAreaLocalTemplates($token);
        $parsed_props = array();
        foreach ($token->getAllProps() as $tp => $tpval) {
            $c_part = "'" . $tp . "' => ";
            if ($tp === 'suit') {
                $res_suit = Suitable::compileAreaSuitProp(
                    $tpval,
                    $local_templates,
                    $this->template_set_name
                );
                $c_val = "'" . $res_suit . "'";
            } elseif (preg_match("~^`.+`$~s", $tpval)) {
                $c_val = trim($tpval, '`');
            } elseif (preg_match('~\$~', $tpval)) {
                $c_val = $this->parseExpression($tpval);
            } else {
                $c_val = "'" . addslashes($tpval) . "'";
            }
            $parsed_props[$tp] = $c_val;
            $token_props_parts [] = $c_part . $c_val;
        }
        $token_props = 'array(' . join(", ", $token_props_parts) . ')';
        $res = '';
        $res = '<?php $context->push(array("area_infoblocks" => fx::page()->getAreaInfoblocks(' . $parsed_props['id'] . ")));\n?>";
        $render_called = false;
        foreach ($token->getChildren() as $child_num => $child) {
            if ($child->name == 'template') {
                $child->setProp('area', $token->getProp('id'));
                if (!$render_called) {
                    if ($child_num > 0) {
                        $res =
                            "<?php\n" .
                            'if ($_is_admin) {' . "\n" .
                            'echo self::renderArea(' . $token_props . ', $context, \'marker\');' . "\n" .
                            '}' . "\n?>\n" .
                            $res .
                            '<?php echo self::renderArea(' . $token_props . ', $context, \'data\');?>';
                    } else {
                        $res .= '<?php echo self::renderArea(' . $token_props . ', $context);?>';
                    }
                    $render_called = true;
                }
                $this->registerTemplate($child);
            } else {
                $res .= $this->getTokenCode($child, $token);
            }
        }
        if (!$render_called) {
            $res = '<?php echo self::renderArea(' . $token_props . ', $context);?>' . $res;
        }
        $res .= "<?php \$context->pop();\n?>";
        return $res;
    }

    protected function tokenIfToCode($token)
    {
        $code = "<?php\n";
        $cond = $token->getProp('test');
        $cond = trim($cond);
        $cond = self::parseExpression($cond);
        if (empty($cond)) {
            $cond = 'false';
        }
        $code .= 'if (' . $cond . ") {\n";
        $code .= $this->childrenToCode($token) . "\n";
        $code .= "} ";
        $code .= $this->elsesToCode($token);
        $code .= "\n?>";
        return $code;
    }

    protected function tokenElseToCode($token)
    {
        $code = " else {\n";
        $code .= $this->childrenToCode($token) . "\n";
        $code .= "}\n";
        return $code;
    }


    protected function tokenElseifToCode($token)
    {
        $cond = $token->getProp('test');
        $cond = trim($cond);
        $cond = self::parseExpression($cond);

        $code = ' elseif (' . $cond . ') {' . "\n";
        $code .= $this->childrenToCode($token) . "\n";
        $code .= "} ";
        $code .= $this->elsesToCode($token);
        $code .= "\n";
        return $code;
    }

    protected function tokenJsToCode($token)
    {
        return $this->tokenHeadfileToCode($token, 'js');
    }

    protected function tokenCssToCode($token)
    {
        return $this->tokenHeadfileToCode($token, 'css');
    }
    
    protected function cssBundleToCode($token)
    {
        
        $code = "ob_start();\n";
        // add extra \n to each text child
        foreach ($token->getChildren() as $child) {
            if ($child->name == 'code') {
                $child->setProp('value', "\n ".$child->getProp('value')." \n");
            } elseif ($child->name == 'var'){
                $child->setProp('editable', 'false');
            }
        }
        $code .= $this->childrenToCode($token)."\n";
        $dirs = $token->getProp('extend') ? "\$this->getAllDirs()" : "\$template_dir";
        $code .= 'fx::page()->addCssBundleFromString(ob_get_clean(), '.$dirs.');'."\n";
        return $code;
    }

    protected function tokenHeadfileToCode($token, $type)
    {
        $code = "<?php\n";
        $code .= 'if (!$context->isIdle()) {'."\n";
        if ($token->getProp('bundle') || $token->getProp('extend')) {
            $code .= $this->cssBundleToCode($token);
        } else {
            foreach ($token->getChildren() as $set) {
                $set = preg_split("~[\n]~", $set->getProp('value'));
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
                    } 
                    // @floxim_js/jquery.bem.js
                    elseif (preg_match("~^@~", $file)) {
                        $res_string = 'fx::path()->http("'.$file.'")';
                    } elseif (!preg_match("~^(/|https?://)~", $file)) {
                        $res_string = '$template_dir."' . $file . '"';
                    } else {
                        $res_string = '"' . $file . '"';
                    }
                    if ($alias) {
                        $code .= "if (!fx::page()->hasFileAlias('" . $alias . "', '" . $type . "')) {\n";
                    }
                    $code .= 'fx::page()->add' . fx::util()->underscoreToCamel($type) . 'File(' . $res_string . ");\n";
                    if ($alias) {
                        $code .= "fx::page()->hasFileAlias('" . $alias . "', '" . $type . "', true);\n";
                        $code .= "}\n";
                    }
                }
            }
        }
        $code .= "}\n";
        $code .= "\n?>";
        return $code;
    }

    protected function getTokenCode($token, $parent)
    {
        $method_name = 'token' . fx::util()->underscoreToCamel($token->name) . 'ToCode';
        if (method_exists($this, $method_name)) {
            return call_user_func(array($this, $method_name), $token, $parent);
        }
        return '';
    }

    protected function childrenToCode(Token $token)
    {
        $parts = array();
        $param_parts = array();
        foreach ($token->getChildren() as $child) {
            if ($child->name === 'param') {
                $param_parts []= $this->tokenParamToCode($child);
            } elseif ($child->name !== 'elseif' && $child->name !== 'else') {
                $parts [] = $this->getTokenCode($child, $token);
            }
        }
        if (count($parts) == 0) {
            return '';
        }
        $code = '?>' . join("", $param_parts).join("", $parts) . "<?php ";
        return $code;
    }
    
    protected function tokenParamToCode(Token $token)
    {
        $name = $token->getProp('name');
        $props = $token->getAllProps();
        unset($props['name']);
        
        $val_var = "\$param__".$name."_value";
        
        $code = "<?php\n";
        $code .= $val_var." = \$context->get('".$name."');\n";
        $val_is_null = $val_var."_is_null";
        $code .= $val_is_null . " = is_null(".$val_var.");\n";
        if (isset($props['default'])) {
            // handle computable defaults
            $default_val = "'".$props['default']."'";
            
            $code .= "if (".$val_is_null.") {\n";
            $code .= $val_var." = ".$default_val.";\n";
            $code .= "\$context->set('".$name."', ".$val_var.");\n";
            $code .= "}\n";
        }
        $code .= "if (\$_is_admin ) {\n";
        $exported_props = array(
            "'is_forced' => !".$val_is_null." && \$context->getLastVarLevel() !== 1",
            "'value' => ".$val_var
        );
        foreach ($props as $k => $v) {
            $c_prop = "'".$k."' => ";
            if (preg_match("~^\`.+\`$~", $v)) {
                $c_prop .= trim($v, '`');
            } else {
                $c_prop .= "'".addslashes($v)."'";
            }
            $exported_props []= $c_prop;
        }
        $code .= "\$this->registerParam('".$name."', array(".join(", ", $exported_props).") );\n";
        $code .= "}\n";
        $code .= "?>";
        return $code;
    }

    protected function elsesToCode($token)
    {
        $code = '';
        foreach ($token->getChildren() as $child) {
            if ($child->name == 'elseif' || $child->name == 'else') {
                $code .= $this->getTokenCode($child, $token);
            }
        }
        return $code;
    }
    
    protected static $func_counter = 0;
    protected function makeTemplateCode(&$tpl_props, &$registry)
    {
        self::$func_counter++;
        if (self::$func_counter > 1000) {
            fx::log('More than 1000 functions registered!');
            return;
        }
        $code  = '';
        
        $is_preset = $tpl_props['is_preset_of'];
        
        $token = $tpl_props['_token'];
        if (!$token) {
            fx::log('No template token while processing template code', $tpl_props);
            return;
        }
        $predicate = $token->getProp('test');
        $tags = isset($tpl_props['tags']) ? $tpl_props['tags'] : null;
        
        $priority = $token->getProp('priority');
        if (!is_null($priority)) {
            $tpl_props['priority'] = $priority;
        }
        
        if ($predicate && !isset($tpl_props['_variants']) && !isset($tpl_props['is_variant'])) {
            $tpl_props = array(
                'id' => $tpl_props['id'],
                '_token' => $token,
                '_variants' => array($tpl_props)
            );
        }
        
        if (isset($tpl_props['_variants'])) {
            foreach ($tpl_props['_variants'] as &$v) {
                if (!$v['_token']->getProp('test')) {
                    $v['_token']->setProp('test', 'true');
                }
                $v['is_variant'] = true;
                $code .= $this->makeTemplateCode($v, $registry);
            }
            $code .= $this->makeSolveCode($tpl_props['_variants'], $tpl_props['id']);
            return $code;
        }
        $tpl_id = $tpl_props['id'];
        $method_name = 'tpl_'.$tpl_id;
        $hash = '';
        if ($predicate) {
            $hash .= $predicate;
            $code .= '// predicate: '.$predicate."\n";
        }
        if ($tags) {
            $hash .= join(", ", $tags);
            $code .= '// tags: '.join(", ", $tags)."\n";
        }
        if (isset($tpl_props['variant_number'])) {
            $hash .= ' '.$tpl_props['variant_number'];
            $code .= '// variant number: '.$tpl_props['variant_number']."\n";
        }
        
        if ($hash) {
            $method_name .= '_'. md5($hash);
        }
        $tpl_props['method'] = $method_name;
        $children_code = $tpl_props['_code'];

        $code .= "public function " . $method_name . '($context) {' . "\n";
        $code .= "fx::env()->addCurrentTemplate(\$this);\n";

        if ($is_preset) {
            $code .= "\$this->context->push(array(), array('transparent' => true));\n";
        }
        //if (strstr($children_code, "\$template_dir")) {
            $template_dir = preg_replace("~[^/]+$~", '', $tpl_props['file']);
            $code .= "\$template_dir = '" . $template_dir . "';\n";
            $code .= "\$this->current_template_dir = \$template_dir;\n";
        //}
        $has_meta_var = strstr($children_code, "\$_is_wrapper_meta");
        $has_admin_var = strstr($children_code, "\$_is_admin") || $has_meta_var;
        if ($has_admin_var) {
            $code .= "\$_is_admin = \$this->isAdmin();\n";
        }
        if ($has_meta_var) {
            $code .= 'if ($_is_admin) {' . "\n";
            $code .= "\$_is_wrapper_meta = \$this->isWrapper() ? array('template_is_wrapper' => 1) : array();\n";
            $code .= "}\n";
        }
        
        $token = $tpl_props['_token'];
        $is_subroot = $token->getProp('subroot') ? 'true' : 'false';
        $code .= "\t\$this->is_subroot = " . ($is_subroot) . ";\n";
        $code .= $children_code;
        if ($is_preset) {
            $code .= "\$this->context->pop();\n";
        }
        $code .= "fx::env()->popCurrentTemplate();\n";
        $code .= "\n}\n";
        /*
        unset($tpl_props['_token']);
        unset($tpl_props['_variants']);
        unset($tpl_props['_code']);
         * 
         */
        $tpl_props['method'] = $method_name;
        $registry []= array_diff_key($tpl_props, array_flip(array('_token', '_variants', '_code')));
        return $code;
    }
    
    protected function makeSolveCode($variants, $variant_id) {
        $code = '';
        $code .= 'public function solve_'.$variant_id."(\$context, \$tags = null) {\n";
        $code .= "\$count_tags = \$tags ? count(\$tags) : null;\n";
        foreach ($variants as &$v) {
            $t = $v['_token'];
            if (!($prior = $t->getProp('priority'))) {
                $prior = $t->getProp('test') ? 0.5 : 0;
            }
            if ($v['is_imported']) {
                $prior -= 0.2;
            }
            if ($v['import_level']) {
                $prior += $v['import_level']/100;
            }
            $v['_priority'] = $prior;
        }

        @ usort($variants, function ($a, $b) {
            $ap = $a['_priority'];
            $bp = $b['_priority'];
            $diff = round(($bp - $ap) * 100);
            return $diff;
        });

        foreach ($variants as $var_num => $var) {
            $token = $var['_token'];
            $test = $token->getProp('test');
            if (!$test) {
                $test = 'true';
            }
            $code .= $var_num == 0 ? 'if' : 'elseif';
            $code .= "( (!\$tags ";
            if (isset($var['tags']) && $var['tags'] && count($var['tags'])) {
                $code .= " || count(array_intersect(\$tags, array('".join("', '", $var['tags'])."'))) == \$count_tags";
            }
            $code .= ") && ";
            $code .= '(' . self::parseExpression($test) . ") )";
            $code .= "{\n";
            $code .= "return array('".$var['method']."', ".$var['_priority'].");\n";
            $code .= "}\n";
        }
        $code .= "}\n";
        return $code;
    }

    protected function getTemplateProps(Token $token)
    {
        $tpl_props = array();
        
        $com_name = $this->template_set_name;
        
        $tpl_id = $token->getProp('id');
        if (preg_match("~#(.+)$~", $tpl_id, $tpl_tags)) {
            $tpl_id = preg_replace("~#.+$~", '', $tpl_id);
            $tags = preg_split("~\,\s*~", $tpl_tags[1]);
            if (count($tags) > 0) {
                $tpl_props['tags'] = $tags;
            }
        }
        if (preg_match("~(^.+?)\:(.+)~", $tpl_id, $id_parts)) {
            $external_com_name = $id_parts[1];
            $own_name = $id_parts[2];
            if ($external_com_name == $com_name) {
                $tpl_id = $own_name;
            } else {
                $tpl_props['overrides'] = $tpl_id;
                $tpl_id = str_replace(".", '_', $external_com_name) . '__'.$own_name;
            }
        }
        
        $tpl_props['id'] =  $tpl_id;
        $tpl_props['file'] = fx::path()->http($this->current_source_file);
        $tpl_props['is_imported'] = $this->current_source_is_imported;
        $i_level = $this->current_source_import_level;
        $tpl_props['import_level'] = $i_level;
        
        if ($this->current_source_is_imported) {
            if (!isset($tpl_props['tags']) || !$tpl_props['tags']) {
                $tpl_props['tags'] = array();
            }
            $tpl_props['tags'][]= 'imported'.($i_level > 0 ? $i_level : '');
        }
        
        if (($offset = $token->getProp('offset'))) {
            $tpl_props['offset'] = $offset;
        }
        if (($size = $token->getProp('size'))) {
            $tpl_props['size'] = $size;
        }
        if (($suit = $token->getProp('suit'))) {
            $tpl_props['suit'] = $suit;
        }
        if (($area_id = $token->getProp('area'))) {
            $tpl_props['area'] = $area_id;
        }

        if (!($name = $token->getProp('name'))) {
            $name = $token->getProp('id');
        }

        $tpl_props['full_id'] = $com_name . ':' . $tpl_props['id'];

        $of = $token->getProp('of');
        // todo: psr0 need fix
        $of_map = array(
            'menu'     => 'section:list',
            'wrapper'  => 'floxim.layout.wrapper:show', // fake widget!
            'blockset' => 'blockset:show',
            'grid'     => 'grid:show',
            'block'    => 'block:show' // no implementation yet
        );

        if ($of and $of != 'false') {
            $of_parts = explode(',', $of);
            array_walk($of_parts, function (&$v) {
                $v = trim($v);
            });
            foreach ($of_parts as $key => $value) {
                if (isset($of_map[$value])) {
                    $value = $of_map[$value];
                }
                $c_of = explode(":", $value);
                // no component, e.g. fx:of="list"
                if (count($c_of) === 1) {
                    $of_parts[$key] = fx::getComponentFullName($com_name).':'.$value;
                } else {
                    $of_parts[$key] = fx::getComponentFullName($c_of[0]).':'.$c_of[1];
                }
            }
            $of = join(',', $of_parts);
        } else {
            $of = false;
        }

        $tpl_props += array(
            'name'   => $name,
            'of'     => $of,
            'is_preset_of' => $token->getProp('is_preset_of'),
            'replace_original' => $token->getProp('replace_original'),
            'is_abstract' => $token->getProp('is_abstract'),
            '_token' => $token
        );
        return $tpl_props;
    }

    protected function registerTemplate(Token $token)
    {
        $is_preset = false;
        if ($token->name === 'preset') {
            $token = $this->presetToTemplate($token);
            $is_preset = true;
        } 
        if (!$is_preset && $token->name != 'template') {
            return;
        }
        if (!$is_preset && !$token->getProp('subroot')) {
            $apply_token = null;
            foreach ($token->getChildren() as $child) {
                if ($child->name === 'call') {
                    if ($apply_token === null) {
                        $apply_token = $child;
                    } else {
                        // more than one {apply} in the block
                        $apply_token = null;
                        break;
                    }
                    continue;
                } 
                if (
                    $child->name === 'var' ||
                    ($child->name === 'code' && trim($child->getProp('value')) !== '')
                ) {
                    // some output found
                    $apply_token = null;
                    break;
                }
            }
            if ($apply_token) {
                $apply_token->setProp('extract_subroot', '$this->is_subroot');
            }
        }
        
        
        $tpl_props = $this->getTemplateProps($token);
        
        $tpl_id = $tpl_props['id'];
        
        $tpl_props['_code'] = $this->childrenToCode($token);

        if (isset($this->templates[$tpl_id])) {
            // this is the second template with the same name
            if (!isset($this->templates[$tpl_id]['_variants'])) {
                $first_tpl = $this->templates[$tpl_id];
                $first_tpl['variant_number'] = 0;
                $this->templates[$tpl_id] = $first_tpl + array(
                    '_variants' => array($first_tpl)
                );
            }
            $tpl_props['variant_number'] = count($this->templates[$tpl_id]['_variants']);
            $this->templates[$tpl_id]['_variants'][] = $tpl_props;
        } else {
            $this->templates[$tpl_id] = $tpl_props;
        }
    }

    /*
     * Passes through the upper level, starting the collection of templates deep
     */
    protected function collectTemplates($root)
    {
        foreach ($root->getChildren() as $template_file_token) {
            $this->current_source_file = $template_file_token->getProp('source');
            $c_imported = $template_file_token->getProp('is_imported');
            $this->current_source_is_imported = $c_imported !== 'false';
            
            $this->current_source_import_level = $this->current_source_is_imported ? $c_imported : null;
            
            foreach ($template_file_token->getChildren() as $template_token) {
                $this->registerTemplate($template_token);
            }
        }
    }

    protected function  makeCode(Token $tree, $class_name)
    {
        // Name of the class/template group
        $this->template_set_name = $tree->getProp('name');
        $this->is_aliased = $tree->getProp('is_aliased') == 'true';
        $this->collectTemplates($tree);
        //ob_start();
        $code = '';
        $code .= "<?php\n";
        $code .= 'class ' . $class_name . " extends \\Floxim\\Floxim\\Template\\Template {\n";

        $template_source_dirs = array();
        
        $registry = array();
        foreach ($this->templates as $meta) {
            if (isset($meta['file'])) {
                $template_source_dirs[]= dirname($meta['file']);
            }
            $code .= $this->makeTemplateCode($meta, $registry);
        }
        
        $template_source_dirs = array_unique($template_source_dirs);
        
        $action_map = array();
        $overrides = array();
        
        
        
        foreach ($registry as $t) {
            $id = $t['id'];
            if (!isset($action_map[$id])) {
                $action_map[$id] = $t['method'];
            } else {
                if (!is_array($action_map[$id])) {
                    $action_map[$id] = array($action_map[$id]);
                }
                $action_map[$id] []= $t['method'];
            }
            if (isset($t['overrides']) && !isset($overrides[$t['overrides']])) {
                $overrides[$t['overrides']]= array(
                    $t['id'], 
                    isset($t['is_variant']) && $t['is_variant'],
                    isset($t['priority']) ? $t['priority'] : 0.5
                );
            }
        }
        $code .= 'protected static $templates = ' . var_export($registry, 1) . ";\n";
        $code .= 'protected static $action_map = '. var_export($action_map,1).";\n";
        
        $code .= 'protected static $template_source_dirs = '.var_export($template_source_dirs,1).";\n";
        
        if (isset($this->config_entries) && is_array($this->config_entries)) {
            $code .= 'protected static $template_config_entries = '.var_export($this->config_entries,1).";\n";
        }
        
        $code .= "public static function init() {\n";
        if (count($overrides) > 0) {
            $code .= "fx::listen('loadTemplate', function(\$e) {\n";
                $code .= "switch (\$e['full_name']) {\n";
                foreach ($overrides as $remote => $local_info) {
                    list($local, $is_variant, $priority) = $local_info;
                    $code .= "case '".$remote."':\n";
                    if ($is_variant) {
                        $code .= "if ( (\$solved = ".$class_name."::solve_".$local."(\$e['context'], \$e['tags']) ) ) {\n";
                        $code .= "\$e->pushResult(\$solved + array(2 => '".$class_name."'));\n";
                        $code .= "return;\n";
                        $code .= "}\n";
                    } else {
                        $code .= "\$e->pushResult(array('tpl_".$local."', ".$priority.", '".$class_name."'));\n";
                        $code .= "return;\n";
                    }
                    $code .= "break;\n";
                }
                $code .= "}\n";
                $code .= "});\n";
        }
        $code .= "}\n";
        $code .= "}";
        return $code;
    }

    /*
     * From comments: http://php.net/manual/en/function.php-check-syntax.php
     */
    public static function isPhpSyntaxCorrect($code)
    {
        $braces = 0;
        $inString = 0;
        $code = preg_replace("~^\s*\<\?(php)?~", '', $code);
        $code = preg_replace("~\?>\s*$~", '', $code);
        // First of all, we need to know if braces are correctly balanced.
        // This is not trivial due to variable interpolation which
        // occurs in heredoc, backticked and double quoted strings
        $all_tokens = token_get_all('<?php ' . $code);
        foreach ($all_tokens as $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                    case T_START_HEREDOC:
                        ++$inString;
                        break;
                    case T_END_HEREDOC:
                        --$inString;
                        break;
                }
            } else {
                if ($inString & 1) {
                    switch ($token) {
                        case '`':
                        case '"':
                            --$inString;
                            break;
                    }
                } else {
                    switch ($token) {
                        case '`':
                        case '"':
                            ++$inString;
                            break;
                        case '{':
                            ++$braces;
                            break;
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
        
        register_shutdown_function(function() use ($code) {
            if (!fx::env('complete_ok')) {
                fx::log(
                    'Died while compiling template', $code
                );
            }
        });
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
                $error_data[2] = (int)$error_data[2];
                $error_data = $error_data[2] <= $braces
                    ? array_slice($error_data, 1)
                    : array('unexpected $end' . substr($error_data[1], 14), $braces);
            }
            $error_data['raw_output'] = $buffer_output;
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