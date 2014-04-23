<?php
class fx_template_attrtype_parser extends fx_template_fsm {

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

    public function __construct() {
        $this->add_rule(self::TAG, '~^\s+$~', self::ATT_NAME, null);

        $this->add_rule(self::ATT_NAME, '~^fx:$~', self::FX, null);
        $this->add_rule(self::FX, '~^=[\'\"]$~', self::FX_VAL, 'start_val');

        $this->add_rule(self::ATT_NAME, '~^=[\'\"]$~', self::ATT_VAL, 'start_val');

        $this->add_rule(self::ATT_NAME, '~^style$~', self::STYLE, null);
        $this->add_rule(self::STYLE, '~^=[\'\"]$~', self::STYLE_VAL, 'start_val');
        $this->add_rule(self::STYLE_VAL, '~^background$~', self::STYLE_BACKGROUND, null);
        $this->add_rule(self::STYLE_VAL, '~^background-image$~', self::STYLE_BACKGROUND_URL, null);
        $this->add_rule(self::STYLE_VAL, '~^background-color|color$~', self::STYLE_BACKGROUND, null);
        $this->add_rule(self::STYLE_BACKGROUND, '~^url\([\'\"]?$~', self::STYLE_BACKGROUND_URL, null);
        $this->add_rule(self::STYLE_BACKGROUND_URL, '~^\{[\%\$]~', self::STYLE_VAL, 'set_image_var');
        $this->add_rule(self::STYLE_BACKGROUND, '~^\{[\%\$]~', self::STYLE_VAL, 'set_color_var');

        $this->add_rule(self::ATT_NAME, '~^src$~', self::SRC, null);
        $this->add_rule(self::SRC, '~^=[\'\"]$~', self::SRC_VAL, 'start_val');
        $this->add_rule(self::SRC_VAL, '~^\{[\%\$]~', null, 'set_image_var');

        $this->add_rule(self::ATT_NAME, '~^href|title|alt$~', self::HREF, null);
        $this->add_rule(self::HREF, '~^=[\'\"]$~', self::HREF_VAL, 'start_val');
        $this->add_rule(self::HREF_VAL, '~^\{[\%\$]~', null, 'set_href_var');


        $this->add_rule(self::ATT_VAL, '~^\{[\%\$]~', null, 'start_var');
        $this->add_rule(array(self::ATT_VAL, self::FX_VAL, self::STYLE_VAL, self::SRC_VAL, self::HREF_VAL), '~^\s+|[\'\"]$~', self::TAG, 'end_att');
        $this->init_state = self::TAG;
    }


    public function start_att($ch) {
        $this->res .= $ch;
    }

    public function start_val($ch) {
        $this->res .= $ch;
        if (preg_match("~[\'\"]$~", $ch, $att_quote)) {
            $this->att_quote = $att_quote[0];
        }
    }


    public function start_var ($ch) {
        $this->res .= $this->set_prop_val(array('inatt'=>'true'), $ch);
    }


    protected  function  set_prop_val ($props = array(), $ch) {
        foreach($props as $prop => $value) {
            $c_type = '';
            if (!preg_match('~'.$prop.'=[\'\"]?\w+[\'\"]?~', $ch)) {
                $c_type = ' '.$prop.'="'.$value.'"';
            }

            $ch = preg_replace("~^([^\s\|\}]+)~", '\1 '.$c_type, $ch);
        }
        return $ch;
    }

    public function set_image_var ($ch) {
        $this->res .= $this->set_prop_val(array('inatt'=>'true', 'type'=> 'image'), $ch);
    }

    public function set_href_var ($ch) {
        $c_editable = 'true';
        if (
            !preg_match("~^\{\%~", $ch)
        ) {
            $c_editable = 'false';
        }

        $this->res .= $this->set_prop_val(array('inatt'=>'true', 'editable'=> $c_editable), $ch);
    }

    public function set_color_var ($ch) {
        $this->res .= $this->set_prop_val(array('inatt'=>'true', 'type'=> 'color'), $ch);
    }

    public function end_att ($ch) {
        switch ($ch) {
            case '"': case "'":
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

    public function default_callback($ch) {
        $this->res .= $ch;
    }

} 