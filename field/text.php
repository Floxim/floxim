<?php

class fx_field_text extends fx_field_baze {
    /*
    public function get_input() {
        return "<textarea class='".$this->get_css_class()."' type='text' name='f_".$this->name."'' value='' >".htmlspecialchars($this->value, ENT_QUOTES)."</textarea>";
    }
     * 
     */

    public function get_js_field($content) {
        parent::get_js_field($content);
        if (isset($this['format']) && isset($this['format']['html']) && $this['format']['html']) {
            $this->_js_field['wysiwyg'] = true;
        }

        return $this->_js_field;
    }

    public function format_settings() {
        $fields[] = array('type' => 'checkbox', 'name' => 'format[html]', 'label' => fx::alang('allow HTML tags','system'), 'value' => $this['format']['html']);
        $fields[] = array('type' => 'checkbox', 'name' => 'format[nl2br]', 'label' => fx::alang('replace newline to br','system'), 'value' => $this['format']['nl2br']);
        return $fields;
    }

}

?>
