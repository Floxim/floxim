<?php
class fx_template_html {
    protected $_string = null;
    public function __construct($string) {
        $string = $string;
        $this->_string = $string;
    }
    
    public function tokenize() {
        $tokenizer = new fx_template_html_tokenizer();
        $tokens = $tokenizer->parse($this->_string);
        return $tokens;
    }
    
    public function add_meta($meta = array(), $skip_parsing = false) {
        // add immediately wrap
        if ($skip_parsing) {
            fx::log('skip parsing', $meta, $this);
            return $this->add_meta_wrapper($meta);
        }
        $tree = $this->make_tree($this->tokenize());
        $children = $tree->get_children();
        $not_empty_children = array();
        foreach ($children as $child) {
            if ($child->name == 'text' && preg_match("~^\s*$~", $child->source)) {
                continue;
            }
            $not_empty_children []= $child;
        }
        if (count($not_empty_children) == 1 && $not_empty_children[0]->name != 'text') {
            $not_empty_children[0]->add_meta($meta);
            return $tree->serialize();
        }
        return $this->add_meta_wrapper($meta);
    }
    
    public function add_meta_wrapper($meta) {
        $tag = self::get_wrapper_tag($this->_string);
        $wrapper = fx_template_html_token::create_standalone('<'.$tag.' class="fx_wrapper">');
        $wrapper->add_meta($meta);
        return $wrapper->serialize().$this->_string."</".$tag.">";
    }


    public static function get_wrapper_tag($html) {
        return preg_match("~<(?:div|ul|li|table|p|h\d)~i", $html) ? 'div' : 'span';
    }
    
    public function transform_to_floxim() {
        $tokens = $this->tokenize();
        $tree = $this->make_tree($tokens);
        
        $unnamed_replaces = array();
        
        $tree->apply( function(fx_template_html_token $n) use (&$unnamed_replaces) {
            if ($n->name == 'text') {
                return;
            }
            if (preg_match('~\{[\%|\$]~', $n->source)) {
                $n->source = fx_template_html::parse_floxim_vars_in_atts($n->source);
            }
            $subroot = $n->has_attribute('fx:omit') ? '' : ' subroot="true"';
            if ($n->name == 'meta' && ($layout_id = $n->get_attribute('fx:layout'))) {
                $layout_name = $n->get_attribute('fx:name');
                $tpl_tag = '{template id="'.$layout_id.'" name="'.$layout_name.'" of="layout.show"}';
                $tpl_tag .= '{apply id="_layout_body"}';
                $content = $n->get_attribute('content');
                $vars = explode(",", $content);
                foreach ($vars as $var) {
                    $var = trim($var);
                    $negative = false;
                    if (preg_match("~^!~", $var)) {
                        $negative = true;
                        $var = preg_replace("~^!~", '', $var);
                    }
                    $tpl_tag .= '{$'.$var.' select="'.($negative ? 'false' : 'true').'" /}';
                }
                $tpl_tag .= '{/call}{/template}';
                $n->parent->add_child_before(fx_template_html_token::create($tpl_tag), $n);
                $n->remove();
                return;
            }
            if ( ($fx_replace = $n->get_attribute('fx:replace')) ){
                $replace_atts = explode(",", $fx_replace);
                foreach ($replace_atts as $replace_att) {
                    if (!isset($unnamed_replaces[$replace_att])) {
                        $unnamed_replaces[$replace_att] = 0;
                    }
                    $var_name = 'replace_'.$replace_att.'_'.$unnamed_replaces[$replace_att];
                    $unnamed_replaces[$replace_att]++;
                    $default_val = $n->get_attribute($replace_att);
                    switch($replace_att) {
                        case 'src':
                            $var_title = fx::alang('Picture','system');
                            break;
                        case 'href':
                            $var_title = fx::alang('Link','system');
                            break;
                        default:
                            $var_title = $replace_att;
                            break;
                    }
                    $n->set_attribute($replace_att, '{%'.$var_name.' title="'.$var_title.'"}'.$default_val.'{/%'.$var_name.'}');
                    $n->remove_attribute('fx:replace');
                }
            }
            if ( ($var_name = $n->get_attribute('fx:var')) ) {
                if (!preg_match("~^[\$\%]~", $var_name)) {
                    $var_name = '%'.$var_name;
                }
                $n->add_child_first(fx_template_html_token::create('{'.$var_name.'}'));
                $n->add_child(fx_template_html_token::create('{/'.$var_name.'}'));
                $n->remove_attribute('fx:var');
            }
            
            
            $tpl_id = $n->get_attribute('fx:template');
            $macro_id = $n->get_attribute('fx:macro');
            if ( $tpl_id || $macro_id) {
                if ($macro_id) {
                    $tpl_id = $macro_id;
                }
                
                if (preg_match("~\[(.+?)\]~", $tpl_id, $tpl_test)) {
                    $tpl_test = $tpl_test[1];
                    $tpl_id = preg_replace("~\[.+?\]~", '', $tpl_id);
                }
                    
                
                $tpl_macro_tag = '{template id="'.$tpl_id.'" ';
                if ($macro_id) {
                    $tpl_macro_tag .= ' is_macro="true" ';
                }
                $tpl_macro_tag .= $subroot;
                
                if ( ($tpl_for = $n->get_attribute('fx:of')) ) {
                    $tpl_macro_tag .= ' of="'.$tpl_for.'"';
                    $n->remove_attribute('fx:of');
                }
                if ($tpl_test || ($tpl_test = $n->get_attribute('fx:test'))) {
                    $tpl_macro_tag .= ' test="'.$tpl_test.'" ';
                    $n->remove_attribute('fx:test');
                }
                if ( ($tpl_name = $n->get_attribute('fx:name'))) {
                    $tpl_macro_tag .= ' name="'.$tpl_name.'"';
                    $n->remove_attribute('fx:name');
                }
                if ( $n->offset && $n->end_offset) {
                    $tpl_macro_tag .= ' offset="'.$n->offset[0].','.$n->end_offset[1].'" ';
                }
                if ( ($tpl_size = $n->get_attribute('fx:size'))) {
                    $tpl_macro_tag .= ' size="'.$tpl_size.'" ';
                    $n->remove_attribute('fx:size');
                }
                if ( ($tpl_suit = $n->get_attribute('fx:suit'))) {
                    $tpl_macro_tag .= ' suit="'.$tpl_suit.'"';
                    $n->remove_attribute('fx:suit');
                }
                $tpl_macro_tag .= '}';
                $n->wrap($tpl_macro_tag, '{/template}');
                $n->remove_attribute('fx:template');
                $n->remove_attribute('fx:macro');
            }
            if ( $n->has_attribute('fx:each') ) {
                $each_id = $n->get_attribute('fx:each');
                $each_id = trim($each_id, '{}');
                $each_id = str_replace('"', '\\"', $each_id);
                $each_macro_tag = '{each ';
                $each_macro_tag .= $subroot;
                $each_macro_tag .= ' select="'.$each_id.'"';

                if ( ($each_as = $n->get_attribute('fx:as'))) {
                    $each_macro_tag .= ' as="'.$each_as.'"';
                    $n->remove_attribute('fx:as');
                }
                if (($each_key = $n->get_attribute('fx:key'))) {
                    $each_macro_tag .= ' key="'.$each_key.'"';
                    $n->remove_attribute('fx:key');
                }
                if (( $prefix = $n->get_attribute('fx:prefix')) ) {
                    $each_macro_tag .= ' prefix="'.$prefix.'"';
                    $n->remove_attribute('fx:prefix');
                }
                if ( ($extract = $n->get_attribute('fx:extract'))) {
                    $each_macro_tag .= ' extract="'.$extract.'"';
                    $n->remove_attribute('fx:extract');
                }
                if ( ($separator = $n->get_attribute('fx:separator'))) {
                    $each_macro_tag .= ' separator="'.$separator.'"';
                    $n->remove_attribute('fx:separator');
                }
                $each_macro_tag .= '}';
                $n->wrap($each_macro_tag, '{/each}');
                $n->remove_attribute('fx:each');
            }
            if ( ($area_id = $n->get_attribute('fx:area'))) {
                $n->remove_attribute('fx:area');
                $area = '{area id="'.$area_id.'" ';
                if ( ($area_size = $n->get_attribute('fx:size')) ) {
                    $area .= 'size="'.$area_size.'" ';
                    $n->remove_attribute('fx:size');
                }
                if ( ($area_suit = $n->get_attribute('fx:suit'))) {
                    $area .= 'suit="'.$area_suit.'" ';
                    $n->remove_attribute('fx:suit');
                }
                if ( ($area_render = $n->get_attribute('fx:area-render'))) {
                    $area .= 'render="'.$area_render.'" ';
                    $n->remove_attribute('fx:area-render');
                }
                if ( ($area_name = $n->get_attribute('fx:area-name'))) {
                    $area .= 'name="'.$area_name.'" ';
                    $n->remove_attribute('fx:area-name');
                }
                $area .= '}';
                $n->add_child_first(fx_template_html_token::create($area));
                $n->add_child(fx_template_html_token::create('{/area}'));
            }
            if ( $n->has_attribute('fx:item') ) {
                $item_att = $n->get_attribute('fx:item');
                $n->remove_attribute('fx:item');
                $n->wrap(
                    '{item'.($item_att ? ' test="'.$item_att.'"' : '').$subroot.'}',
                    '{/item}'
                );
            }
            if ( ($if_test = $n->get_attribute('fx:if'))) {
                $n->remove_attribute('fx:if');
                $n->wrap(
                    '{if test="'.$if_test.'"}',
                    '{/if}'
                );
            }
            if ( ($with_each = $n->get_attribute('fx:with-each'))) {
                $n->remove_attribute('fx:with-each');
                $n->wrap(
                    '{with-each '.$with_each.'}',
                    '{/with-each}'
                );
            }
            if ( ($with = $n->get_attribute('fx:with'))) {
                $n->remove_attribute('fx:with');
                $n->wrap(
                    '{with select="'.$with.'" '.$subroot.'}',
                    '{/with}'
                );
            }
            if ($n->has_attribute('fx:separator')) {
                $n->wrap('{separator}', '{/separator}');
                $n->remove_attribute('fx:separator');
            }
            if ( ($elseif_test = $n->get_attribute('fx:elseif'))) {
                $n->remove_attribute('fx:elseif');
                $n->wrap(
                    '{elseif test="'.$elseif_test.'"}',
                    '{/elseif}'
                );
            }
            if ( $n->has_attribute('fx:else') ) {
                $n->remove_attribute('fx:else');
                $n->wrap('{else}', '{/else}');
            }
            if ( $n->has_attribute('fx:omit')) {
                $omit = $n->get_attribute('fx:omit');
                if (empty($omit) || $omit == 'true') {
                    $omit = true;
                } else {
                    $ep = new fx_template_expression_parser();
                    $omit = $ep->compile($ep->parse($omit));
                }
                $n->omit = $omit;
                $n->remove_attribute('fx:omit');
            }
        });
        $res = $tree->serialize();
        return $res;
    }
    
    public static function parse_floxim_vars_in_atts($input_source) {
        $ap = new fx_template_attrtype_parser();
        $res = $ap->parse($input_source);
        return $res;
    }
    
    public function make_tree($tokens) {
        $root = new fx_template_html_token();
        $root->name = 'root';
        $stack = array($root);
        $token_index = -1;
        while ($token = array_shift($tokens)) {
            $token_index++;
            switch ($token->type) {
                case 'open':
                    if (count($stack) > 0) {
                        end($stack)->add_child($token);
                    }
                    $stack []= $token;
                    break;
                case 'close':
                    $closed_tag = array_pop($stack);
                    if ($closed_tag->name != $token->name) {
                        $start_offset = $closed_tag->offset[0] ;
                        $end_offset = $token->offset[0];
                        //$before = substr($this->_string, 0, $start_offset);
                        $start_line = substr_count($this->_string, "\n", 0, $start_offset) + 1;
                        $end_line = substr_count($this->_string, "\n", 0, $end_offset) + 1;
                        $msg = "HTML parser error: ".
                                "start tag ".$closed_tag->source.
                                //$closed_tag->offset[0]."-".$closed_tag->offset[1].")".
                                " (line ".$start_line.") ".
                                "doesn't match end tag </".$token->name.'> (line '.$end_line.')';
                        
                        throw new Exception($msg);
                    }
                    if ($token->offset) {
                        $closed_tag->end_offset = $token->offset;
                    }
                    break;
                case 'single': default:
                    $stack_last = end($stack);
                    if (!$stack_last) {
                        fx::log("fx_template_html tree error", $tokens, $root);
                        echo fx_debug(
                                "fx_template_html error: stack empty, trying to add: ",
                                '#'.$token_index,
                                $token,
                                $tokens,
                                $root);
                        echo "fx_template_html error: stack empty, trying to add: ";
                        echo "<pre>" . htmlspecialchars(print_r($token, 1)) . "</pre>";
                        die();
                    }
                    $stack_last->add_child($token);
                    break;
            }
        }
        // in the stack should be kept only for the <root>
        if (count($stack) > 1) {
            fx::log("All closed, but stack not empty!", $stack);
            //die();
        }
        return $root;
    }
    
    public static function add_class_to_tag($tag_html, $class) {
        if (preg_match("~class\s*=[\s\'\"]*[^\'\"\>]+~i", $tag_html, $class_att)) {
            $class_att_new = preg_replace(
                "~class\s*=[\s\'\"]*~", 
                '$0'.$class.' ', 
                $class_att[0]
            );
            $tag_html = str_replace($class_att, $class_att_new, $tag_html);
        } else {
            $tag_html = self::add_att_to_tag($tag_html, 'class', $class);
        }
        return $tag_html;
    }
    
    public static function add_att_to_tag($tag_html, $att, $value) {
        $tag_html = preg_replace("~^<[^\s>]+~", '$0 '.$att.'="'.htmlentities($value).'"', $tag_html);
        return $tag_html;
    }
}
