<?php

namespace Floxim\Floxim\Helper\Form\Field;

class Captcha extends Field {
    public function __construct($params = array()) {
        if (!session_id()) {
            session_start();
        }

        if (!isset($params['label'])) {
            $params['label'] = 'Aren\'t you a robot?';
        }
        if (!isset($params['valid_for'])) {
            $params['valid_for'] = 6*5; // 3 min
        }

        parent::__construct($params);
        // todo: psr0 need fix path
        $url = fx::path()->to_http(dirname(realpath(__FILE__)));
        $url .= '/captcha.php?fx_field_name='.urlencode($this->get_id());
        $url .= '&rand='.rand(0, 1000000);
        $this['captcha_url'] = $url;

        if (!$this->was_valid()) {
            $this['required'] = true;
            $this->add_validator('captcha');
        }
        $field = $this;
        $this->get_form()->on_finish(function($f) use ($field) {
            $field->was_valid(false);
        });
    }

    public function validate_captcha() {
        if ($this->was_valid()) {
            return;
        }
        if ($_SESSION['captcha_code_'.$this->get_id()] != $this->get_value()) {
            return 'Invalid code';
        }
        $this->was_valid(true);
    }

    public function was_valid($set = null) {
        $prop = 'captcha_was_valid_'.$this->get_id();
        if ($set === null) {
            $was = isset($_SESSION[$prop]) && $_SESSION[$prop] + $this['valid_for'] > time();
            if ($was) {
                $this['was_valid'] = true;
                $this['was_valid_time_left'] = $_SESSION[$prop] + $this['valid_for'] - time();
            }
            return $was;
        }
        if ($set === true) {
            $this['was_valid'] = true;
            $_SESSION[$prop] = time();
        } else {
            unset($_SESSION[$prop]);
        }
        return $this;
    }
}