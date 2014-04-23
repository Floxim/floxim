<?
class fx_template_html_tokenizer extends fx_template_fsm {
    const TEXT = 1;
    const TAG = 2;
    const PHP = 3;
    const ATT_NAME = 5;
    const ATT_VAL = 6;
    const FX = 7;
    const FX_COMMENT = 8;
    
    public function __construct() {
        $this->init_state = self::TEXT;
        // fx comments
        $this->add_rule(self::STATE_ANY, '{*', self::FX_COMMENT, 'fx_comment_start');
        $this->add_rule(self::FX_COMMENT, '*}', false, 'fx_comment_end');

        // php
        $this->add_rule(self::STATE_ANY, '<?', self::PHP, 'php_start');
        $this->add_rule(self::PHP, '?>', false, 'php_end');

        $this->add_rule(self::TAG, '{', self::FX, 'fx_start');
        $this->add_rule(
            array(self::TEXT,self::ATT_NAME,self::ATT_VAL), 
            '{', self::FX, 'fx_start'
        );
        $this->add_rule(self::FX, '}', false, 'fx_end');

        $this->add_rule(self::TEXT, '~^<~', self::TAG, 'text_to_tag');
        $this->add_rule(array(self::TAG, self::ATT_NAME), '>', self::TEXT, 'tag_to_text');
        $this->add_rule(self::TAG, '~\s+~', self::ATT_NAME, 'att_name_start');
        $this->add_rule(self::ATT_NAME, "~\s*=\s*[\'\"]~", self::ATT_VAL, 'att_value_start');
        $this->add_rule(self::ATT_NAME, "~\s+~", false, 'att_name_start');
        
        $this->add_rule(self::ATT_VAL, array('"', "'", ' ', '>'), self::TAG, 'att_value_end');
    }
    
    protected $stack = '';
    
    public function get_split_regexp() {
        return "~(<[a-z0-9\/]+|>|\{\*|\*\}|<\?|\?>|[\{\}]|[\'\"]|\s*=\s*[\'\"]?|\s+)~";
    }

    public function parse($string) {
        parent::parse($string);
        if (!empty($this->stack)) {
            $this->text_to_tag('');
        }
        return $this->res;
    }
    
    public function default_callback($ch) {
        $this->stack .= $ch;
    }

    protected $res = array();
    
    protected function _add_token($source, $end) {
    	$start = $end - mb_strlen($source);
        $token = fx_template_html_token::create($source);
        $token->offset = array($start, $end);
        $this->res []= $token;
    }
    
    protected function text_to_tag($ch) {
        if ($this->stack !== '') {
            $this->_add_token($this->stack, $this->position- mb_strlen($ch));
        }
        $this->stack = $ch;
    }
	
    protected function tag_to_text($ch) {
        $this->_add_token($this->stack.$ch, $this->position);
        $this->stack = '';
    }
    
    protected function fx_comment_start($ch) {
        if ($this->state == self::PHP) {
            return false;
        }
        $this->prev_stack = $this->stack;
    }
    
    protected function fx_comment_end($ch) {
        $this->stack = $this->prev_stack;
        $this->set_state($this->prev_state);
    }
	
    protected function php_start($ch) {
        if ($this->state == self::FX_COMMENT) {
            return false;
        }
        $this->stack .= $ch;
    }
	
    protected function php_end($ch) {
        $this->stack .= $ch;
        $this->set_state($this->prev_state);
    }
    
    protected  function fx_start($ch) {
        $this->stack .= $ch;
    }
    
    protected function fx_end($ch) {
        $this->stack .= $ch;
        $this->set_state($this->prev_state);
    }
    
    protected function att_name_start($ch) {
        $this->stack .= $ch;
    }

    protected $att_quote = null;
    protected function att_value_start($ch) {
        if (preg_match("~[\'\"]$~", $ch, $att_quote)) {
            $this->att_quote = $att_quote[0];
        }
        $this->stack .= $ch;
    }
	
    protected function att_value_end($ch) {
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
            $this->tag_to_text($ch);
        } else {
            $this->stack .= $ch;
        }
    }
}