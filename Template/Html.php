<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class Html
{
    protected $_string = null;

    public function __construct($string)
    {
        $string = $string;
        $this->_string = $string;
    }

    public function tokenize()
    {
        $tokenizer = new HtmlTokenizer();
        $tokens = $tokenizer->parse($this->_string);
        return $tokens;
    }

    public function addMeta($meta = array(), $skip_parsing = false)
    {
        // add immediately wrap
        if ($skip_parsing) {
            return $this->addMetaWrapper($meta);
        }
        $tree = $this->makeTree($this->tokenize());
        $children = $tree->getChildren();
        $not_empty_children = array();
        foreach ($children as $child) {
            if ($child->name == 'text' && preg_match("~^\s*$~", $child->source)) {
                continue;
            }
            $not_empty_children [] = $child;
        }
        if (count($not_empty_children) == 1 && $not_empty_children[0]->name != 'text') {
            $not_empty_children[0]->addMeta($meta);
            return $tree->serialize();
        }
        return $this->addMetaWrapper($meta);
    }

    public function addMetaWrapper($meta)
    {
        $tag = self::getWrapperTag($this->_string);
        $wrapper = HtmlToken::createStandalone('<' . $tag . ' class="fx_wrapper">');
        $wrapper->addMeta($meta);
        return $wrapper->serialize() . $this->_string . "</" . $tag . ">";
    }


    public static function getWrapperTag($html)
    {
        return preg_match("~<(?:div|ul|li|table|p|h\d)~i", $html) ? 'div' : 'span';
    }

    public function transformToFloxim()
    {
        $tokens = $this->tokenize();
        $tree = $this->makeTree($tokens);

        $unnamed_replaces = array();

        $tree->apply(function (HtmlToken $n) use (&$unnamed_replaces) {
            if ($n->name == 'text') {
                return;
            }
            if (preg_match('~\{[\%|\$]~', $n->source)) {
                $n->source = Html::parseFloximVarsInAtts($n->source);
            }
            $subroot = $n->hasAttribute('fx:omit') ? '' : ' subroot="true"';
            if (($n->name == 'script' || $n->name == 'style') && !$n->hasAttribute('fx:raw')) {
                $n->setAttribute('fx:raw', 'true');
            }
            if ($n->hasAttribute('fx:raw')) {
                $raw_value = $n->getAttribute('fx:raw');
                if ($raw_value != 'false') {
                    $n->addChildFirst(HtmlToken::create('{raw}'));
                    $n->addChild(HtmlToken::create('{/raw}'));
                }
                $n->removeAttribute('fx:raw');
            }
            
            $tpl_id = $n->getAttribute('fx:template');
            $macro_id = $n->getAttribute('fx:macro');
            if ($tpl_id || $macro_id) {
                if ($macro_id) {
                    $tpl_id = $macro_id;
                }

                if (preg_match("~\[(.+?)\]~s", $tpl_id, $tpl_test)) {
                    $tpl_test = preg_replace("~[\r\n]~", ' ', $tpl_test[1]);
                    $tpl_id = preg_replace("~\[.+?\]~s", '', $tpl_id);
                }
                
                $tpl_macro_tag = '{template id="' . $tpl_id . '" ';
                if ($macro_id) {
                    $tpl_macro_tag .= ' is_macro="true" ';
                }
                $tpl_macro_tag .= $subroot;
                
                if ($n->hasAttribute('fx:apply')) {
                    $tpl_macro_tag .= ' apply="true" ';
                    $n->removeAttribute('fx:apply');
                }
                
                if ($n->hasAttribute('fx:abstract')) {
                    $tpl_macro_tag .= ' is_abstract="true" ';
                    $n->removeAttribute('fx:abstract');
                }

                if (($tpl_for = $n->getAttribute('fx:of'))) {
                    $tpl_macro_tag .= ' of="' . $tpl_for . '"';
                    $n->removeAttribute('fx:of');
                }
                if ($tpl_test || ($tpl_test = $n->getAttribute('fx:test'))) {
                    $tpl_macro_tag .= ' test="' . $tpl_test . '" ';
                    $n->removeAttribute('fx:test');
                }
                if (($tpl_name = $n->getAttribute('fx:name'))) {
                    $tpl_macro_tag .= ' name="' . $tpl_name . '"';
                    $n->removeAttribute('fx:name');
                }
                if ($n->offset && $n->end_offset) {
                    $tpl_macro_tag .= ' offset="' . $n->offset[0] . ',' . $n->end_offset[1] . '" ';
                }
                if (($tpl_size = $n->getAttribute('fx:size'))) {
                    $tpl_macro_tag .= ' size="' . $tpl_size . '" ';
                    $n->removeAttribute('fx:size');
                }
                if (($tpl_suit = $n->getAttribute('fx:suit'))) {
                    $tpl_macro_tag .= ' suit="' . $tpl_suit . '"';
                    $n->removeAttribute('fx:suit');
                }
                if ($n->hasAttribute('fx:priority')) {
                    $tpl_priority = $n->getAttribute('fx:priority');
                    $tpl_macro_tag .= ' priority="'.$tpl_priority.'" ';
                    $n->removeAttribute('fx:priority');
                }
                
                $tpl_macro_tag .= '}';
                $n->wrap($tpl_macro_tag, '{/template}');
                $n->removeAttribute('fx:template');
                $n->removeAttribute('fx:macro');
            }
            if ($n->hasAttribute('fx:each')) {
                $each_id = $n->getAttribute('fx:each');
                $each_id = trim($each_id, '{}');
                $each_id = str_replace('"', '\\"', $each_id);
                $each_macro_tag = '{each ';
                $each_macro_tag .= $subroot;
                $each_macro_tag .= ' select="' . $each_id . '"';
                
                if ( $n->hasAttribute('fx:scope') ) {
                    $n->removeAttribute('fx:scope');
                    $each_macro_tag .= ' scope="true" ';
                }
                
                if ( ($each_add = $n->getAttribute('fx:add'))) {
                    $n->removeAttribute('fx:add');
                    $each_macro_tag .= ' add="'.$each_add.'"';
                }

                if (($each_as = $n->getAttribute('fx:as'))) {
                    $each_macro_tag .= ' as="' . $each_as . '"';
                    $n->removeAttribute('fx:as');
                }
                if (($each_key = $n->getAttribute('fx:key'))) {
                    $each_macro_tag .= ' key="' . $each_key . '"';
                    $n->removeAttribute('fx:key');
                }
                if (($prefix = $n->getAttribute('fx:prefix'))) {
                    $each_macro_tag .= ' prefix="' . $prefix . '"';
                    $n->removeAttribute('fx:prefix');
                }
                if (($extract = $n->getAttribute('fx:extract'))) {
                    $each_macro_tag .= ' extract="' . $extract . '"';
                    $n->removeAttribute('fx:extract');
                }
                if (($separator = $n->getAttribute('fx:separator'))) {
                    $each_macro_tag .= ' separator="' . $separator . '"';
                    $n->removeAttribute('fx:separator');
                }
                $each_macro_tag .= '}';
                $n->wrap($each_macro_tag, '{/each}');
                $n->removeAttribute('fx:each');
            }
            
            if ($n->hasAttribute('fx:scope')) {
                $scope = $n->getAttribute('fx:scope');
                $scope_start_tag = '{scope mode="start"}'.$scope.'{/scope}';
                $n->wrap($scope_start_tag, '{scope mode="end" /}');
                $n->removeAttribute('fx:scope');
            }
            
            $container_hash = false;
            if ( ($container_id = $n->getAttribute('fx:container') )) {
                $container_hash = md5($container_id.time().rand(0,99999999));
                $n->wrap(
                    "{container id='".$container_hash."' mode='start'}".$container_id."{/container}",
                    "{container id='".$container_hash."' mode='stop' /}"
                );
                $n->addClass("{container id='".$container_hash."' mode='class' /}");
                $n->removeAttribute('fx:container');
                $n->setAttribute(
                    '#inj200',
                    " {container id='".$container_hash."' mode='meta' /}"
                );
            }
            if (($area_id = $n->getAttribute('fx:area'))) {
                $n->removeAttribute('fx:area');
                $area = '{area id="' . $area_id . '" ';
                if (($area_size = $n->getAttribute('fx:size'))) {
                    $area .= 'size="' . $area_size . '" ';
                    $n->removeAttribute('fx:size');
                } elseif (($area_size = $n->getAttribute('fx:area-size'))) {
                    // use when fx:area and fx:template are placed on the same node
                    $area .= 'size="' . $area_size . '" ';
                    $n->removeAttribute('fx:area-size');
                }
                if ( ($area_scope = $n->getAttribute('fx:area-scope')) ) {
                    $area .= ' scope="'.$area_scope.'" ';
                    $n->removeAttribute('fx:area-scope');
                }
                if (($area_suit = $n->getAttribute('fx:suit'))) {
                    $area .= 'suit="' . $area_suit . '" ';
                    $n->removeAttribute('fx:suit');
                }
                if (($area_render = $n->getAttribute('fx:area-render'))) {
                    $area .= 'render="' . $area_render . '" ';
                    $n->removeAttribute('fx:area-render');
                }
                if (($area_name = $n->getAttribute('fx:area-name'))) {
                    $area .= 'name="' . $area_name . '" ';
                    $n->removeAttribute('fx:area-name');
                }
                $area .= '}';
                $n->addChildFirst(HtmlToken::create($area));
                $n->addChild(HtmlToken::create('{/area}'));
            }
            if ($n->hasAttribute('fx:item')) {
                $item_att = $n->getAttribute('fx:item');
                $n->removeAttribute('fx:item');
                $n->wrap(
                    '{item' . ($item_att ? ' test="' . $item_att . '"' : '') . $subroot . '}',
                    '{/item}'
                );
            }
            if ($n->hasAttribute('fx:aif')) {
                $if_test = $n->getAttribute('fx:aif');
                $ep = new ExpressionParser();
                $empty_cond = $ep->build($if_test);
                $class_code = '<?php echo (' . $empty_cond . ' ? "" : " fx_view_hidden ");?>';
                $n->addClass($class_code);
                $n->removeAttribute('fx:aif');
                $if_test .= ' || $_is_admin';

                $n->wrap(
                    '{if test="' . $if_test . '"}',
                    '{/if}'
                );

            }
            if ($n->hasAttribute('fx:if')) {
                $if_test = $n->getAttribute('fx:if');
                $n->removeAttribute('fx:if');
                $n->wrap(
                    '{if test="' . $if_test . '"}',
                    '{/if}'
                );
            }
            if (($with_each = $n->getAttribute('fx:with-each'))) {
                $n->removeAttribute('fx:with-each');
                $weach_macro_tag = '{with-each ' . $with_each . '}';
                if (($separator = $n->getAttribute('fx:separator'))) {
                    $weach_macro_tag .= '{separator}' . $separator . '{/separator}';
                    $n->removeAttribute('fx:separator');
                }
                $n->wrap(
                    $weach_macro_tag,
                    '{/with-each}'
                );
            }
            if (($with = $n->getAttribute('fx:with'))) {
                $n->removeAttribute('fx:with');
                $n->wrap(
                    '{with select="' . $with . '" ' . $subroot . '}',
                    '{/with}'
                );
            }
            if ($n->hasAttribute('fx:separator')) {
                $n->wrap('{separator}', '{/separator}');
                $n->removeAttribute('fx:separator');
            }
            if (($elseif_test = $n->getAttribute('fx:elseif'))) {
                $n->removeAttribute('fx:elseif');
                $n->wrap(
                    '{elseif test="' . $elseif_test . '"}',
                    '{/elseif}'
                );
            }
            if ($n->hasAttribute('fx:else')) {
                $n->removeAttribute('fx:else');
                $n->wrap('{else}', '{/else}');
            }
            if ($n->name === 'fx:a' || $n->hasAttribute('fx:link-if')) {
                if (!$n->hasAttribute('href')) {
                    $n->setAttribute('href', '{$url}');
                }
                $url_value = preg_replace("~^\{|\}$~", '', $n->getAttribute('href'));
                if ($n->hasAttribute('fx:link-if')) {
                    $test_value = $n->getAttribute('fx:link-if').' && '.$url_value;
                    $n->removeAttribute('fx:link-if');
                } else {
                    $test_value = $url_value;
                }
                $n->setAttribute('fx:element-name', '{= '.$test_value. ' ? "a" : "div" /}');
                $n->removeAttribute('href');
                $n->setAttribute('{if '.$test_value.'}href', '{= '.$url_value.' /}');
                $n->setAttribute('#inj100', '{/if}');
            }
            if ($n->hasAttribute('fx:add')) {
                $add_mode = $n->getAttribute('fx:add');

                $n->removeAttribute('fx:add');
                $n->wrap(
                    '<?php $this->pushMode("add", "' . $add_mode . '"); ?>',
                    '<?php $this->popMode("add"); ?>'
                );
            }
            if ($n->hasAttribute('fx:omit')) {
                $omit = $n->getAttribute('fx:omit');
                if (empty($omit) || $omit == 'true') {
                    $omit = true;
                } else {
                    $ep = new ExpressionParser();
                    $omit = $ep->compile($ep->parse($omit));
                }
                $n->omit = $omit;
                $n->removeAttribute('fx:omit');
            }
            
            $style_is_inline = false;
            
            if ($n->hasAttribute('fx:styled-inline')) {
                $n->setAttribute('fx:styled', $n->getAttribute('fx:styled-inline'));
                $n->removeAttribute('fx:styled-inline');
                $style_is_inline = true;
            }
            
            $styled_call = null;
            if ($n->hasAttribute('fx:styled')) {
                $styled_value = trim($n->getAttribute('fx:styled'));
                $n->removeAttribute('fx:styled');
                
                $styled_call = '{styled %s ';
                if ($style_is_inline) {
                    $styled_call .= ' inline="true" ';
                }
                if ($styled_value !== '' && !preg_match("~(?:[a-z_-]+\:|\{)~", $styled_value)) {
                    $styled_call .= 'label="'.$styled_value.'"}';
                } else {
                    $styled_call .= '}'.$styled_value;
                }
                $styled_call .= '{/styled}';
            }
            
            if ($n->hasAttribute('fx:e')) {
                $el_value = $n->getAttribute('fx:e');
                if ($styled_call && !$n->hasAttribute('fx:b')) {
                    $el_parts = explode(" ", trim($el_value));
                    $el_name = $el_parts[0];
                    $styled_call = sprintf($styled_call, 'element="'.$el_name.'"');
                    $el_value .= $styled_call;
                }
                $n->addClass('{bem_element}'.$el_value.'{/bem_element}');
                $n->removeAttribute('fx:e');
            }
            
            if ($n->hasAttribute('fx:b')) {
                $b_value = $n->getAttribute('fx:b');
                if ($styled_call) {
                    $block_parts = explode(" ", trim($b_value));
                    $block_name = $block_parts[0];
                    $styled_call = sprintf($styled_call, 'block="'.$block_name.'"');
                    $b_value .= $styled_call;
                }
                $n->addClass('{bem_block '.($container_hash ? 'container="1"' : '').'}'.$b_value.'{/bem_block}');
                $n->removeAttribute('fx:b');
                $n->parent->addChildAfter(HtmlToken::create('<?php $this->bemStopBlock(); ?>'), $n);
            }
            
            
        });
        $res = $tree->serialize();
        return $res;
    }

    public static function parseFloximVarsInAtts($input_source)
    {
        $ap = new AttrtypeParser();
        $res = $ap->parse($input_source);
        return $res;
    }

    public function makeTree($tokens)
    {
        $root = new HtmlToken();
        $root->name = 'root';
        $stack = array($root);
        $token_index = -1;
        while ($token = array_shift($tokens)) {
            $token_index++;
            switch ($token->type) {
                case 'open':
                    if (count($stack) > 0) {
                        end($stack)->addChild($token);
                    }
                    $stack [] = $token;
                    break;
                case 'close':
                    $closed_tag = array_pop($stack);
                    if ($closed_tag->name != $token->name) {
                        $start_offset = $closed_tag->offset[0];
                        $end_offset = $token->offset[0];

                        $start_line = mb_substr_count(mb_substr($this->_string, 0, $start_offset), "\n") + 1;
                        $end_line = mb_substr_count(mb_substr($this->_string, 0, $end_offset), "\n") + 1;
                        $msg = "HTML parser error: " .
                            "start tag " . $closed_tag->source .
                            " (line " . $start_line . ") " .
                            "doesn't match end tag </" . $token->name . '> (line ' . $end_line . ')';
                        
                        $e = new \Exception($msg);
                        $e->html = $this->_string;
                        throw $e;
                    }
                    if ($token->offset) {
                        $closed_tag->end_offset = $token->offset;
                    }
                    break;
                case 'single':
                default:
                    $stack_last = end($stack);
                    if (!$stack_last) {
                        fx::log("fx_template_html tree error", $tokens, $root);
                        fx::debug(
                            "fx_template_html error: stack empty, trying to add: ",
                            '#' . $token_index,
                            $token,
                            $tokens,
                            $root
                        );
                        echo "fx_template_html error: stack empty, trying to add: ";
                        echo "<pre>" . htmlspecialchars(print_r($token, 1)) . "</pre>";
                        die();
                    }
                    $stack_last->addChild($token);
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

    public static function addClassToTag($tag_html, $class)
    {
        if (preg_match("~class\s*=[\s\'\"]*[^\'\"\>]+~i", $tag_html, $class_att)) {
            $class_att_new = preg_replace(
                "~class\s*=[\s\'\"]*~",
                '$0' . $class . ' ',
                $class_att[0]
            );
            $tag_html = str_replace($class_att, $class_att_new, $tag_html);
        } else {
            $tag_html = self::addAttToTag($tag_html, 'class', $class);
        }
        return $tag_html;
    }

    public static function addAttToTag($tag_html, $att, $value)
    {
        $tag_html = preg_replace("~^<[^\s>]+~", '$0 ' . $att . '="' . htmlentities($value) . '"', $tag_html);
        return $tag_html;
    }
}
