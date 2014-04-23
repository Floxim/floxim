<?php
class fx_template_html_token {
    public $type;
    public $name;
    public $source;
    
    /*
     * Create html-token from source
     * @param string $source string with the html tag
     * @return fx_template_html_token
     */
    public static function create($source) {
        $token = new self();
        $single = array('input', 'img', 'link', 'br', 'meta');
        if (preg_match("~^<(/?)([a-z0-9]+).*?(/?)>$~is", $source, $parts)) {
            $token->name = strtolower($parts[2]);
            $token->original_name = $parts[2];
            if (in_array($token->name, $single)||$parts[3]) {
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
    public static function create_standalone($source) {
        $token = self::create($source);
        $token->type = 'standalone';
        return $token;
    }
    
    public function remove() {
        foreach ($this->parent->children as $child_index => $child) {
            if ($child == $this) {
                unset($this->parent->children[$child_index]);
                break;
            }
        }
    }
    
    public function add_child(fx_template_html_token $token, $before_index = null) {
        if (!isset($this->children)) {
            $this->children = array();
        }
        if (!is_null($before_index)) {
            array_splice($this->children, $before_index, 0, array($token));
        } else {
            $this->children[]= $token;
        }
        $token->parent = $this;
    }
    
    public function add_child_first(fx_template_html_token $token) {
        $this->add_child($token, 0);
    }
    
    public function add_child_before(fx_template_html_token $new_child, fx_template_html_token $ref_child) {
        if (($ref_index = $this->get_child_index($ref_child)) === null ) {
            return;
        }
        $this->add_child($new_child, $ref_index);
    }
    
    
    public function wrap($code_before, $code_after) {
        $this->parent->add_child_before(fx_template_html_token::create($code_before), $this);
        $this->parent->add_child_after(fx_template_html_token::create($code_after), $this);
    }


    public function add_child_after(fx_template_html_token $new_child, fx_template_html_token $ref_child) {
        if (($ref_index = $this->get_child_index($ref_child)) === null ) {
            return;
        }
        $this->add_child($new_child, $ref_index+1);
    }
    
    public function get_child_index(fx_template_html_token $ref_child) {
        if ( ($index = array_search($ref_child, $this->get_children())) === false) {
            return null;
        }
        return $index;
    }
    
    public function serialize() {
        $res = '';
        // omit property is added from transform_to_floxim
        $omit = false;
        $omit_conditional = false;
        if ( isset($this->omit) ) {
            if ($this->omit =='true') {
                $omit = true;
            } else {
                $omit_conditional = true;
                $omit_var_name = '$omit_'.md5($this->omit);
                $res .= '<?'.$omit_var_name.' = '.$this->omit.'; if (!'.$omit_var_name.') {?>';
            }
        }
        $tag_start = '';
        if ($this->name != 'root' && !$omit)  {
            if (isset($this->attributes) && isset($this->attributes_modified)) {
                $tag_start .= '<'.$this->original_name;
                foreach ($this->attributes as $att_name => $att_val) {
                    $tag_start .= ' '.$att_name;
                    
                    if ($att_val === null) {
                        continue;
                    }
                    //$quot = in_array($att_name, $this->fx_meta_atts) ? "'" : '"';
                    $quot = isset($this->att_quotes[$att_name]) 
                                ? $this->att_quotes[$att_name] 
                                : '"';
                    $tag_start .= '='.$quot.$att_val.$quot;
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
            $res .= '<?}?>';
        }
        
        // is finished collecting the tag
        if (isset($this->children)) {
            foreach ($this->children as $child) {
                $res .= $child->serialize();
            }
        }
        if ($this->type == 'open' && $this->name != 'root' && !$omit) {
            if ($omit_conditional) {
                $res .= '<?if (!'.$omit_var_name.') {?>';
            }
            $res .= "</".$this->original_name.">";
            
            if ($omit_conditional) {
                $res .= '<?}?>';
            }
        }
        return $res;
    }
    
    public function get_children() {
        if (!isset($this->children)) {
            return array();
        }
        return $this->children;
    }
    
    protected static $attr_parser = null;
    
    protected function _parse_attributes() {
        if (!self::$attr_parser) {
            self::$attr_parser = new fx_template_attr_parser();
        }
        self::$attr_parser->parse_atts($this);
    }
    
    public function has_attribute($att_name) {
        if ($this->name == 'text') {
            return null;
        }
        if (!isset($this->attributes)) {
            $this->_parse_attributes();
        }
        return array_key_exists($att_name, $this->attributes);
    }
    
    public function get_attribute($att_name) {
        if ($this->name == 'text') {
            return null;
        }
        if (!isset($this->attributes)) {
            $this->_parse_attributes();
        }
        if (!isset($this->attributes[$att_name])) {
            return null;
        }
        $att = $this->attributes[$att_name];
        return $att;
    }
    
    public function set_attribute($att_name, $att_value) {
        if ($this->name == 'text') {
            return;
        }
        if (!isset($this->attributes)) {
            $this->_parse_attributes();
        }
        $this->attributes[$att_name] = $att_value;
        $this->attributes_modified = true;
    }
    
    public function add_class($class) {
        if (! ($c_class = $this->get_attribute('class')) ) {
            $this->set_attribute('class', $class);
			return;
        }
        $c_class = preg_split("~\s+~", $c_class);
        if (in_array($class, $c_class)) {
            return;
        }
        $this->set_attribute('class', join(" ", $c_class)." ".$class);
    }
    
    public function remove_attribute($att_name) {
        if (!isset($this->attributes)) {
            $this->_parse_attributes();
        }
        unset($this->attributes[$att_name]);
        $this->attributes_modified = true;
    }
    
    public $att_quotes = array();
    public function add_meta($meta) {
        foreach ($meta as $k => $v) {
            if ($k == 'class') {
                $this->add_class($v);
            } else {
                if (is_array($v) || is_object($v)) {
                    $v = htmlentities(json_encode($v));
                    
                    $v = str_replace("'", '&apos;', $v);
                    $v = str_replace("&quot;", '"', $v);
                    $this->att_quotes[$k] = "'";
                }
                $this->set_attribute($k, $v);
            }
        }
    }


    public function apply($callback) {
        call_user_func($callback, $this);
        foreach ($this->get_children() as $child) {
            $child->apply($callback);
        }
    }
}