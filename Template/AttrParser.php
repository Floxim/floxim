<?php

namespace Floxim\Floxim\Template;

class AttrParser extends HtmlTokenizer
{

    public function parseAtts(HtmlToken $token)
    {
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

    protected $_count_injections = 0;
    protected $c_quote = null;

    protected function addAtt()
    {
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
                if (preg_match("~\{.+~", $att_name) && !$att_val) {
                    $this->_count_injections++;
                    $att_val = $att_name;
                    $att_name = '#inj' . $this->_count_injections;
                }
                $this->token->attributes[$att_name] = $att_val;
            }
        }
        $this->c_att = array('name' => null, 'value' => null);
    }

    public function attNameStart($ch)
    {
        $this->addAtt();
        $this->stack = '';
        parent::attNameStart($ch);
        $this->c_att = array('name' => '', 'value' => null);
    }

    public function attValueStart($ch)
    {
        $this->c_att['name'] = $this->stack;
        parent::attValueStart($ch);
        $this->stack = '';
    }

    public function attValueEnd($ch)
    {
        $this->c_quote = $this->att_quote;
        $c_val = $this->stack;
        $res = parent::attValueEnd($ch);
        if ($this->debug) {
            fx::debug($res === false, $c_val);
        }
        if ($res !== false) {
            $this->c_att['value'] = $c_val;
            $this->addAtt();
            $this->stack = '';
        }
        $this->c_quote = null;
        return $res;
    }

    public function tagToText($ch)
    {
        if (!empty($this->stack)) {
            $this->c_att['name'] = $this->stack;
            $this->addAtt();
        }
    }
}