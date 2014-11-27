<?php

namespace Floxim\Floxim\Template;

class HtmlToken
{
    public $type;
    public $name;
    public $source;
    
    public $offset;
    
    public $end_offset;

    /*
     * Create html-token from source
     * @param string $source string with the html tag
     * @return fx_template_html_token
     */
    public static function create($source)
    {
        $token = new self();
        $single = array('input', 'img', 'link', 'br', 'meta');
        if (preg_match("~^<(/?)([a-z0-9]+).*?(/?)>$~is", $source, $parts)) {
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
        $tag_start = '';
        if ($this->name != 'root' && !$omit) {
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
        if ($omit_conditional) {
            $res .= '<?php } ?>';
        }

        // is finished collecting the tag
        if (isset($this->children)) {
            foreach ($this->children as $child) {
                $res .= $child->serialize();
            }
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


    public function apply($callback)
    {
        call_user_func($callback, $this);
        foreach ($this->getChildren() as $child) {
            $child->apply($callback);
        }
    }
}