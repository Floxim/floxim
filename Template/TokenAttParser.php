<?php

namespace Floxim\Floxim\Template;

class TokenAttParser extends Fsm {
    public static function getAtts(&$source) {
        $p = new self();
        $res = $p->parse($source);
        $source = !empty($p->modifiers) ? ' |'.$p->modifiers : '';
        return $res;
    }

    public $split_regexp = '~((?<!\\\["\'])|\s+|[a-z0-9_-]+\=|\|)~s';

    const INIT = 1;
    const ATT_NAME = 2;
    const ATT_VAL = 3;
    const MODIFIERS = 4;

    public $modifiers = '';

    public function __construct() {
        //$this->debug = true;
        $this->init_state = self::INIT;
        $this->addRule(self::INIT, '~.+=$~', self::ATT_NAME, 'readAttName');
        $this->addRule(self::ATT_NAME, array('"', "'"), self::ATT_VAL, 'startAttVal');
        $this->addRule(self::ATT_VAL, array('"', "'"), self::INIT, 'endAttVal');
        $this->addRule(self::INIT, '|', self::MODIFIERS);
    }

    public function readAttName($ch) {
        $this->c_att = array(
            'name' => trim($ch, '='),
            'value' => ''
        );
    }

    public function startAttVal($ch) {
        $this->c_att['quot'] = $ch;
    }

    public function endAttVal($ch) {
        if ($ch !== $this->c_att['quot']) {
            return false;
        }
        $this->c_att['value'] = str_replace("\\".$this->c_att['quot'], $this->c_att['quot'], $this->c_att['value']);
        $this->res [$this->c_att['name']] = $this->c_att['value'];
    }

    public function defaultCallback($ch) {
        switch($this->state) {
            case self::ATT_VAL:
                $this->c_att['value'] .= $ch;
                break;
            case self::ATT_NAME:
                $this->c_att['value'] = $ch;
                $this->c_att['quot'] = '';
                $this->endAttVal('');
                $this->setState(self::INIT);
                break;
            case self::MODIFIERS:
                $this->modifiers .= $ch;
                break;
        }
    }
}