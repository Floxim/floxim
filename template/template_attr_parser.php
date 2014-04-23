<?php
class fx_template_attr_parser extends fx_template_html_tokenizer {
    
    public function parse_atts(fx_template_html_token $token) {
        $token->attributes = array();
        $s = $token->source;
        if (!$s || !preg_match("~\s~", $s)) {
            return;
        }
        $s = preg_replace("~/>$~", ' />', $s);
        $this->token = $token;
        $this->c_att = array('name' => null, 'value' => null);
        $this->parse($s);
    }
    
    protected function _add_att() {
        if (!$this->c_att['name'] && !preg_match("~^<~", $this->stack)) {
            $this->c_att['name'] = $this->stack;
        }
        if ($this->c_att) {
            $att_name = trim($this->c_att['name']);
            // skip trailing backslash
            if ($att_name && $att_name != '/') {
                $att_val = $this->c_att['value'];
                if ($this->c_quote) {
                    $this->token->att_quotes[$att_name] = $this->c_quote;
                    //$att_val = str_replace("\\".$this->c_quote, $this->c_quote, $att_val);
                }
                $this->token->attributes[$att_name] = $att_val;
            }
        }
        $this->c_att = array('name' => null, 'value' => null);
    }
    
    public function att_name_start($ch) {
        $this->_add_att();
        $this->stack = '';
        parent::att_name_start($ch);
        $this->c_att = array('name' => '', 'value' => null);
    }
    
    public function att_value_start($ch) {
        $this->c_att['name'] = $this->stack;
        parent::att_value_start($ch);
        $this->stack = '';
    }
    
    public function att_value_end($ch) {
        $this->c_quote = $this->att_quote;
        $c_val = $this->stack;
        $res = parent::att_value_end($ch);
        if ($this->debug) {
            fx::debug($res === false, $c_val);
        }
        if ($res !== false) {
            $this->c_att['value'] = $c_val;
            $this->_add_att();
            $this->stack = '';
        }
        $this->c_quote = null;
        return $res;
    }
    
    public function tag_to_text($ch) {
        if (!empty($this->stack)) {
            $this->c_att['name'] = $this->stack;
            $this->_add_att();
        }
    }
}