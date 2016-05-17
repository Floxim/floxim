<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class AttrtypeParser extends Fsm
{

    public $split_regexp = "~(\s|=[\'\"]|fx:|:|\"|<\?.+?\?>|\{[^\}]+?\})~";

    const TAG = 1;
    const PHP = 2;
    const FX = 3;
    const FX_VAL = 4;
    const ATT = 5;
    const ATT_NAME = 6;
    const ATT_VAL = 7;
    const STYLE = 8;
    const STYLE_BACKGROUND = 9;
    const STYLE_BACKGROUND_URL = 10;
    const STYLE_VAL = 11;
    const SRC = 12;
    const SRC_VAL = 13;
    const HREF = 14;
    const HREF_VAL = 15;


    protected $res = '';

    protected $att_quot;

    public function __construct()
    {
        $this->addRule(self::TAG, '~^\s+$~', self::ATT_NAME, null);

        $this->addRule(self::ATT_NAME, '~^fx:$~', self::FX, null);
        $this->addRule(self::FX, '~^=[\'\"]$~', self::FX_VAL, 'startVal');
        $this->addRule(self::FX, '~^\s+$~', self::ATT_NAME, null);

        $this->addRule(self::ATT_NAME, '~^=[\'\"]$~', self::ATT_VAL, 'startVal');

        $this->addRule(self::ATT_NAME, '~^style$~', self::STYLE, null);
        $this->addRule(self::STYLE, '~^=[\'\"]$~', self::STYLE_VAL, 'startVal');
        $this->addRule(self::STYLE_VAL, '~^background$~', self::STYLE_BACKGROUND, null);
        $this->addRule(self::STYLE_VAL, '~^background-image$~', self::STYLE_BACKGROUND_URL, null);
        $this->addRule(self::STYLE_VAL, '~^background-color|color$~', self::STYLE_BACKGROUND, null);
        $this->addRule(self::STYLE_BACKGROUND, '~^url\([\'\"]?$~', self::STYLE_BACKGROUND_URL, null);
        $this->addRule(self::STYLE_BACKGROUND_URL, '~^\{[\%\$]~', self::STYLE_VAL, 'setImageVar');
        $this->addRule(self::STYLE_BACKGROUND, '~^\{[\%\$]~', self::STYLE_VAL, 'setColorVar');

        $this->addRule(self::ATT_NAME, '~^src$~', self::SRC, null);
        $this->addRule(self::SRC, '~^=[\'\"]$~', self::SRC_VAL, 'startVal');
        $this->addRule(self::SRC_VAL, '~^\{[\%\$]~', null, 'setImageVar');

        $this->addRule(self::ATT_NAME, '~^href|title|alt$~', self::HREF, null);
        $this->addRule(self::HREF, '~^=[\'\"]$~', self::HREF_VAL, 'startVal');
        $this->addRule(self::HREF_VAL, '~^\{[\%\$]~', null, 'setHrefVar');


        $this->addRule(self::ATT_VAL, '~^\{[\%\$]~', null, 'startVar');
        $this->addRule(array(self::ATT_VAL, self::FX_VAL, self::STYLE_VAL, self::SRC_VAL, self::HREF_VAL),
            '~^\s+|[\'\"]$~', self::TAG, 'endAtt');
        $this->init_state = self::TAG;
    }


    public function startAtt($ch)
    {
        $this->res .= $ch;
    }

    public function startVal($ch)
    {
        $this->res .= $ch;
        if (preg_match("~[\'\"]$~", $ch, $att_quote)) {
            $this->att_quote = $att_quote[0];
        }
    }


    public function startVar($ch)
    {
        $this->res .= $this->setPropVal(array('inatt' => 'true'), $ch);
    }


    protected function  setPropVal($props, $ch)
    {
        $token = Token::create($ch);
        foreach ($props as $prop => $value) {
            $token->setProp($prop, $value);
        }
        $res = $token->dump();
        return $res;
    }

    public function setImageVar($ch)
    {
        $props = array('inatt' => 'true', 'type' => 'image');
        if ($this->state === self::SRC_VAL) {
            $props['att'] = 'src';
        } elseif ($this->state === self::STYLE_BACKGROUND_URL) {
            $props['att'] = 'style:background-image';
        }
        $this->res .= $this->setPropVal($props, $ch);
    }

    public function setHrefVar($ch)
    {
        $c_editable = 'true';
        if (
        !preg_match("~^\{\%~", $ch)
        ) {
            $c_editable = 'false';
        }

        $this->res .= $this->setPropVal(array('inatt' => 'true', 'editable' => $c_editable), $ch);
    }

    public function setColorVar($ch)
    {
        $this->res .= $this->setPropVal(array('inatt' => 'true', 'type' => 'color'), $ch);
    }

    public function endAtt($ch)
    {
        switch ($ch) {
            case '"':
            case "'":
                if ($this->att_quote !== $ch) {
                    return false;
                }
                break;
            case ' ':
                if ($this->att_quote) {
                    return false;
                }
                break;
            case '>':
                if ($this->att_quote) {
                    return false;
                }
                break;
        }
        $this->att_quote = null;
        $this->res .= $ch;

    }

    public function defaultCallback($ch)
    {
        $this->res .= $ch;
    }
} 