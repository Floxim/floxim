<?php
class fx_template_tokenizer extends fx_template_fsm {
    public $split_regexp = '~(\{[\$\%\/a-z0-9]+[^\{]*?\}|</?(?:script|style)[^>]*?>|<\?(?:php)?|\?>|<\!--|-->)~';
    
    const JS = 1;
    const CSS = 2;
    const PHP = 3;
    const HTML = 4;
    const COMMENT = 5;
    
    protected $stack = '';
    
    public function __construct() {
        $this->init_state = self::HTML;
        $this->res = array();
        
        
        $this->add_rule(self::HTML, '~^\{.+\}$~s', self::HTML, 'add_token');
        
        $this->add_rule(self::HTML, '~^<script~', self::JS);
        $this->add_rule(self::JS, '~^</script~', self::HTML);
        
        $this->add_rule(self::HTML, '~^<style~', self::CSS);
        $this->add_rule(self::CSS, '~^</style~', self::HTML);
        
        $this->add_rule(self::HTML, '~^<\?~', self::PHP);
        $this->add_rule(self::PHP, '~^\?>~', self::HTML);
        
        $this->add_rule(self::HTML, '~^<\!--~', self::COMMENT);
        $this->add_rule(self::COMMENT, '~^-->~', self::HTML);
    }
    
    protected function add_token($ch) {
        if (!empty($this->stack)) {
            $this->res []= fx_template_token::create($this->stack);
            $this->stack = '';
        }
        $this->res []= fx_template_token::create($ch);
    }
    
    public function default_callback($ch) {
        $this->stack .= $ch;
    }
}