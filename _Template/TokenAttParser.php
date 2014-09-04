<?php

namespace Floxim\Floxim\Template;

class TokenAttParser extends Fsm {
    public static function get_atts(&$source) {
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
        $this->add_rule(self::INIT, '~.+=$~', self::ATT_NAME, 'read_att_name');
        $this->add_rule(self::ATT_NAME, array('"', "'"), self::ATT_VAL, 'start_att_val');
        $this->add_rule(self::ATT_VAL, array('"', "'"), self::INIT, 'end_att_val');
        $this->add_rule(self::INIT, '|', self::MODIFIERS);
    }

    public function read_att_name($ch) {
        $this->c_att = array(
            'name' => trim($ch, '='),
            'value' => ''
        );
    }

    public function start_att_val($ch) {
        $this->c_att['quot'] = $ch;
    }

    public function end_att_val($ch) {
        if ($ch !== $this->c_att['quot']) {
            return false;
        }
        $this->c_att['value'] = str_replace("\\".$this->c_att['quot'], $this->c_att['quot'], $this->c_att['value']);
        $this->res [$this->c_att['name']] = $this->c_att['value'];
    }

    public function default_callback($ch) {
        switch($this->state) {
            case self::ATT_VAL:
                $this->c_att['value'] .= $ch;
                break;
            case self::ATT_NAME:
                $this->c_att['value'] = $ch;
                $this->c_att['quot'] = '';
                $this->end_att_val('');
                $this->set_state(self::INIT);
                break;
            case self::MODIFIERS:
                $this->modifiers .= $ch;
                break;
        }
    }
}