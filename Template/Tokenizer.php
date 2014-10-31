<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class Tokenizer extends Fsm
{
    public $split_regexp = '~(\\\\{|\\\\}|\{/?raw}|\{|\}|<\?(?:php)?|\?>|<\!--|-->)~';

    const JS = 1;
    const CSS = 2;
    const PHP = 3;
    const HTML = 4;
    const COMMENT = 5;
    const RAW = 6;
    const FX = 7;

    protected $stack = '';
    protected $fx_level = 0;

    public function __construct()
    {
        $this->init_state = self::HTML;
        $this->res = array();

        $this->addRule(self::HTML, '~^\{raw}$~s', self::RAW);
        $this->addRule(self::RAW, '~^\{/raw}$~s', self::HTML);

        $this->addRule(array(self::HTML, self::FX), '{', self::FX, 'startFx');
        $this->addRule(self::FX, '}', self::HTML, 'stopFx');
        
        $this->addRule(self::HTML, '~^<\?~', self::PHP);
        $this->addRule(self::PHP, '~^\?>~', self::HTML);

        $this->addRule(self::HTML, '~^<\!--~', self::COMMENT);
        $this->addRule(self::COMMENT, '~^-->~', self::HTML);
    }

    public function parse($string) 
    {
        parent::parse($string);
        $this->popToken();
        return $this->res;
    }
    
    protected function startFx($ch) 
    {
        if ($this->fx_level === 0) {
            $this->popToken();
        }
        $this->fx_level++;
        $this->defaultCallback($ch);
    }
    
    protected function stopFx($ch) 
    {
        $this->fx_level--;
        if ($this->fx_level === 0) {
            $this->defaultCallback($ch);
            $this->popToken();
        } else {
            return false;
        }
    }
    
    protected function popToken() 
    {
        $src = $this->stack;
        $this->stack = '';
        if (!empty($src)) {
            $this->addToken($src);
        }
    }


    protected function addToken($source) 
    {
        $this->res []= Token::create($source);
    }

    public function defaultCallback($ch)
    {
        if (preg_match("~^\{/?raw\}$~", $ch)) {
            return;
        }
        if ($this->state !== self::RAW && ($ch === '\{' || $ch === '\}')) {
            $ch = str_replace("\\", '', $ch);
        }
        $this->stack .= $ch;
    }
}