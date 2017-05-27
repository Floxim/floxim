<?php

namespace Floxim\Floxim\Template;

use \Floxim\Floxim\System\Fx as fx;

class HtmlToken
{
    public $type;
    public $name;
    public $source;
    
    public $offset;
    
    public $end_offset;
    
    protected $payload = [];
    
    public function setPayload($k, $v) 
    {
        $this->payload[$k] = $v;
    }
    
    public function getPayload($k) 
    {
        return isset($this->payload[$k]) ? $this->payload[$k] : null;
    }

    /*
     * Create html-token from source
     * @param string $source string with the html tag
     * @return fx_template_html_token
     */
    public static function create($source)
    {
        $token = new self();
        $single = array('input', 'img', 'link', 'br', 'meta');
        if (preg_match("~^<(/?)([a-z0-9\:]+).*?(/?)>$~is", $source, $parts)) {
            $token->name = strtolower($parts[2]);
            $token->original_name = $parts[2];
            if (in_array($token->name, $single) || $parts[3]) {
                $token->type = 'single';
            } else {
                $token->type = $parts[1] ? 'close' : 'open';
            }
        } else {
            $token->type = 'single';
            $token->name = 'text';
        }
        $token->source = $source;
        return $token;
    }

    /*
     * Create html-token and assign a type to "out of tree"
     * @param $source string with the html tag
     * @return fx_template_html_token
     */
    public static function createStandalone($source)
    {
        $token = self::create($source);
        $token->type = 'standalone';
        return $token;
    }

    public function remove()
    {
        foreach ($this->parent->children as $child_index => $child) {
            if ($child == $this) {
                unset($this->parent->children[$child_index]);
                break;
            }
        }
    }

    public function addChild(HtmlToken $token, $before_index = null)
    {
        if (!isset($this->children)) {
            $this->children = array();
        }
        if (!is_null($before_index)) {
            array_splice($this->children, $before_index, 0, array($token));
        } else {
            $this->children[] = $token;
        }
        $token->parent = $this;
    }

    public function addChildFirst(HtmlToken $token)
    {
        $this->addChild($token, 0);
    }

    public function addChildBefore(HtmlToken $new_child, HtmlToken $ref_child)
    {
        if (($ref_index = $this->getChildIndex($ref_child)) === null) {
            return;
        }
        $this->addChild($new_child, $ref_index);
    }


    public function wrap($code_before, $code_after)
    {
        $this->parent->addChildBefore(HtmlToken::create($code_before), $this);
        $this->parent->addChildAfter(HtmlToken::create($code_after), $this);
    }


    public function addChildAfter(HtmlToken $new_child, HtmlToken $ref_child)
    {
        if (($ref_index = $this->getChildIndex($ref_child)) === null) {
            return;
        }
        $this->addChild($new_child, $ref_index + 1);
    }

    public function getChildIndex(HtmlToken $ref_child)
    {
        if (($index = array_search($ref_child, $this->getChildren())) === false) {
            return null;
        }
        return $index;
    }
    
    public function prettyPrint($level = -1)
    {
        $pad = str_repeat("    ", max($level,0));
        if ($this->name === 'text') {
            $val = trim($this->source);
            if (!empty($val)) {
                $val = $pad.$val."\n";
            }
            return $val;
        }
        $res = '';
        $is_tag = $this->name !== 'root' && $this->name !== 'text';
        $is_double = $is_tag && $this->type !== 'single';
        
        if ($is_tag) {
            $res .= $pad."<".$this->name;
            $base_len = mb_strlen($res);
            $att_pad = str_repeat(" ", $base_len+3);
            $atts = $this->getAttributes();
            $att_sep = count($atts) > 1 ? "\n".str_repeat(" ", $base_len) : ' ';
            foreach ($atts as $k => $v) {
                $v =  trim($v);
                if ($k === 'class') {
                    $classes = explode(" ", $v);
                    if (count($classes) > 1) {
                        $v = "\n".$att_pad.join("\n".$att_pad, $classes);
                    }
                }
                $res .= $att_sep.$k.'="'.$v.'"';
            }
            $res .= ">\n";
        }
        
        if (isset($this->children)) {
            foreach ($this->children as $child) {
                $res .= $child->prettyPrint($level+1);
            }
        }
        
        if ($is_double) {
            $res .= $pad."</".$this->name.">\n";
        }
        return $res;
    }

    public function serialize()
    {
        $res = '';
        // omit property is added from transform_to_floxim
        $omit = false;
        $omit_conditional = false;
        if (isset($this->omit)) {
            if ($this->omit == 'true') {
                $omit = true;
            } else {
                $omit_conditional = true;
                $omit_var_name = '$omit_' . md5($this->omit);
                $res .= '<?php ' . $omit_var_name . ' = ' . $this->omit . '; if (!' . $omit_var_name . ') {?>';
            }
        }
        if ($this->hasAttribute('fx:element-name')) {
            $el_name = $this->getAttribute('fx:element-name');
            $el_name_var = '$el_name_'.md5($el_name);
            $res .= '<?php ob_start();?>';
            $res .= $el_name;
            $res .= '<?php '.$el_name_var.' = ob_get_clean();?>';
            $this->original_name = '<'.'?= '.$el_name_var. '?>';
            $this->removeAttribute('fx:element-name');
        }
        $tag_start = '';
        $hide_empty = $this->hasAttribute('fx:hide-empty') && isset($this->children) && count($this->children) > 0;
        if ($this->name != 'root' && !$omit) {
            if ($hide_empty) {
                $this->addClass('fx-hide-empty');
                $this->node_id = md5(rand(0,999999999).time());
                $hide_empty = true;
                $this->removeAttribute('fx:hide-empty');
                $res .= "<?php\nob_start();?>";
            }
            if (isset($this->attributes) && isset($this->attributes_modified)) {
                $tag_start .= '<' . $this->original_name;
                foreach ($this->attributes as $att_name => $att_val) {
                    // floxim injections are some sort of specially marked pseudo-atts
                    if (preg_match("~^#inj\d~", $att_name)) {
                        $tag_start .= ' ' . $att_val;
                        continue;
                    }
                    $tag_start .= ' ' . $att_name;

                    if ($att_val === null) {
                        continue;
                    }
                    //$quot = in_array($att_name, $this->fx_meta_atts) ? "'" : '"';
                    $quot = isset($this->att_quotes[$att_name])
                        ? $this->att_quotes[$att_name]
                        : '"';
                    $tag_start .= '=' . $quot . $att_val . $quot;
                }
                if ($this->type == 'single') {
                    $tag_start .= ' /';
                }
                $tag_start .= '>';
            } else {
                $tag_start .= $this->source;
            }
        }

        $res .= $tag_start;
        if ($hide_empty) {
            $res .= "<?php \$start_".$this->node_id." = ob_get_clean(); ob_start(); ?>";
        }
        if ($omit_conditional) {
            $res .= '<?php } ?>';
        }

        // is finished collecting the tag
        if (isset($this->children)) {
            foreach ($this->children as $child) {
                $res .= $child->serialize();
            }
        }
        if ($hide_empty) {
            $res .= "<?php \n\$data_".$this->node_id ." = ob_get_clean();\n";
            $res .= "if (trim(\$data_".$this->node_id .") !== '') {\n";
            $res .= "echo \$start_".$this->node_id.";\n";
            $res .= "echo \$data_".$this->node_id.";\n";
            $res .= "?>";
        }
        if ($this->type == 'open' && $this->name != 'root' && !$omit) {
            if ($omit_conditional) {
                $res .= '<?php if (!' . $omit_var_name . ') {?>';
            }
            $res .= "</" . $this->original_name . ">";

            if ($omit_conditional) {
                $res .= '<?php } ?>';
            }
        }
        if ($hide_empty) {
            $res .= "<?php \n}\n ?>";
        }
        return $res;
    }

    public function getChildren()
    {
        if (!isset($this->children)) {
            return array();
        }
        return $this->children;
    }

    protected static $attr_parser = null;

    protected function parseAttributes()
    {
        if (!self::$attr_parser) {
            self::$attr_parser = new AttrParser();
        }
        self::$attr_parser->parseAtts($this);
    }
    
    public function getAttributes()
    {
        if (!isset($this->attributes)) {
            $this->parseAttributes();
        }
        return $this->attributes;
    }

    public function hasAttribute($att_name)
    {
        if ($this->name == 'text') {
            return null;
        }
        if (!isset($this->attributes)) {
            $this->parseAttributes();
        }
        return array_key_exists($att_name, $this->attributes);
    }

    public function getAttribute($att_name)
    {
        if ($this->name == 'text') {
            return null;
        }
        if (!isset($this->attributes)) {
            $this->parseAttributes();
        }
        if (!isset($this->attributes[$att_name])) {
            return null;
        }
        $att = $this->attributes[$att_name];
        return $att;
    }

    public function setAttribute($att_name, $att_value)
    {
        if ($this->name == 'text') {
            return;
        }
        if (!isset($this->attributes)) {
            $this->parseAttributes();
        }
        $this->attributes[$att_name] = $att_value;
        $this->attributes_modified = true;
    }

    public function addClass($class)
    {
        if (!($c_class = $this->getAttribute('class'))) {
            $this->setAttribute('class', $class);
            return;
        }
        $c_class = preg_split("~\s+~", $c_class);
        if (in_array($class, $c_class)) {
            return;
        }
        $this->setAttribute('class', join(" ", $c_class) . " " . $class);
    }

    public function removeAttribute($att_name)
    {
        if (!isset($this->attributes)) {
            $this->parseAttributes();
        }
        unset($this->attributes[$att_name]);
        $this->attributes_modified = true;
    }

    public $att_quotes = array();

    public function addMeta($meta)
    {
        foreach ($meta as $k => $v) {
            if ($k == 'class') {
                $this->addClass($v);
            } else {
                if (is_array($v) || is_object($v)) {
                    $v = htmlentities(json_encode($v));

                    $v = str_replace("'", '&apos;', $v);
                    $v = str_replace("&quot;", '"', $v);
                    $this->att_quotes[$k] = "'";
                }
                $this->setAttribute($k, $v);
            }
        }
    }


    public function apply($callback, $post_callback = null)
    {
        call_user_func($callback, $this);
        foreach ($this->getChildren() as $child) {
            $child->apply($callback, $post_callback);
        }
        if ($post_callback) {
            call_user_func($post_callback, $this);
        }
    }
}