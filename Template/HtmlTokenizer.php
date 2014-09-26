<?php

namespace Floxim\Floxim\Template;

class HtmlTokenizer extends Fsm {
    const TEXT = 1;
    const TAG = 2;
    const PHP = 3;
    const ATT_NAME = 5;
    const ATT_VAL = 6;
    const FX = 7;
    const FX_COMMENT = 8;
    const HTML_COMMENT = 9;
    
    public function __construct() {
        $this->init_state = self::TEXT;
        // fx comments
        $this->addRule(self::STATE_ANY, '{*', self::FX_COMMENT, 'fx_comment_start');
        $this->addRule(self::FX_COMMENT, '*}', false, 'fx_comment_end');
        
        // html comments
        $this->addRule(self::TEXT, '<!--', self::HTML_COMMENT);
        $this->addRule(self::HTML_COMMENT, '>', false, 'html_comment_check_end');
        $this->addRule(self::HTML_COMMENT, '-->', self::TEXT);

        // php
        $this->addRule(self::STATE_ANY, '<?', self::PHP, 'php_start');
        $this->addRule(self::PHP, '?>', false, 'php_end');

        $this->addRule(self::TAG, '{', self::FX, 'fx_start');
        $this->addRule(
            array(self::TEXT,self::ATT_NAME,self::ATT_VAL), 
            '{', self::FX, 'fx_start'
        );
        $this->addRule(self::FX, '}', false, 'fx_end');

        $this->addRule(self::TEXT, '~^<~', self::TAG, 'text_to_tag');
        $this->addRule(array(self::TAG, self::ATT_NAME), '>', self::TEXT, 'tag_to_text');
        $this->addRule(self::TAG, '~\s+~', self::ATT_NAME, 'att_name_start');
        $this->addRule(self::ATT_NAME, "~\s*=\s*[\'\"]~", self::ATT_VAL, 'att_value_start');
        $this->addRule(self::ATT_NAME, "~\s+~", false, 'att_name_start');
        
        $this->addRule(self::ATT_VAL, array('"', "'", ' ', '>'), self::TAG, 'att_value_end');
    }
    
    protected $stack = '';
    
    public function getSplitRegexp() {
        return "~(\-\->|<!\-\-|<[a-z0-9\/]+|>|\{\*|\*\}|<\?|\?>|[\{\}]|[\'\"]|\s*=\s*[\'\"]?|\s+)~";
    }

    public function parse($string) {
        parent::parse($string);
        if (!empty($this->stack)) {
            $this->textToTag('');
        }
        return $this->res;
    }
    
    /**
     * Handle case like "<!--[if gt IE 8]><!-->"
     * Here the end of comment will not be caught by splitter because "<!--" goes first
     * So on every ">" inside html comment, we check if it's preceded by "--"
     * @param type $ch
     */
    protected function htmlCommentCheckEnd($ch) {
        $is_comment_end = mb_substr($this->stack, -2) === '--';
        $this->stack .= $ch;
        if ($is_comment_end) {
            $this->state = self::TEXT;
        }
    }
    
    public function defaultCallback($ch) {
        $this->stack .= $ch;
    }

    protected $res = array();
    
    protected function addToken($source, $end) {
    	$start = $end - mb_strlen($source);
        $token = HtmlToken::create($source);
        $token->offset = array($start, $end);
        $this->res []= $token;
    }
    
    protected function textToTag($ch) {
        if ($this->stack !== '') {
            $this->addToken($this->stack, $this->position- mb_strlen($ch));
        }
        $this->stack = $ch;
    }
	
    protected function tagToText($ch) {
        $this->addToken($this->stack.$ch, $this->position);
        $this->stack = '';
    }
    
    protected function fxCommentStart($ch) {
        if ($this->state == self::PHP) {
            return false;
        }
        $this->prev_stack = $this->stack;
    }
    
    protected function fxCommentEnd($ch) {
        $this->stack = $this->prev_stack;
        $this->setState($this->prev_state);
    }
	
    protected function phpStart($ch) {
        if ($this->state == self::FX_COMMENT) {
            return false;
        }
        $this->stack .= $ch;
    }
	
    protected function phpEnd($ch) {
        $this->stack .= $ch;
        $this->setState($this->prev_state);
    }
    
    protected  function fxStart($ch) {
        $this->stack .= $ch;
    }
    
    protected function fxEnd($ch) {
        $this->stack .= $ch;
        $this->setState($this->prev_state);
    }
    
    protected function attNameStart($ch) {
        $this->stack .= $ch;
    }

    protected $att_quote = null;
    protected function attValueStart($ch) {
        if (preg_match("~[\'\"]$~", $ch, $att_quote)) {
            $this->att_quote = $att_quote[0];
        }
        $this->stack .= $ch;
    }
	
    protected function attValueEnd($ch) {
        switch ($ch) {
            case '"': case "'":
                if ($this->att_quote !== $ch) {
                    return false;
                }
                break;
            case '>':
                if ($this->att_quote) {
                    return false;
                }
                break;
        }
        if (preg_match("~^\s+$~s", $ch)) {
            if ($this->att_quote) {
                return false;
            }
        }
        $this->att_quote = null;
        if ($ch == '>') {
            $this->tagToText($ch);
        } else {
            $this->stack .= $ch;
        }
    }
}