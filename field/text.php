<?php

namespace Floxim\Floxim\Field;

class Text extends Baze {
    
    public function get_js_field($content) {
        parent::get_js_field($content);
        if (isset($this['format']) && isset($this['format']['html']) && $this['format']['html']) {
            $this->_js_field['wysiwyg'] = true;
            $this->_js_field['nl2br'] = $this['format']['nl2br'];
        }

        return $this->_js_field;
    }

    public function format_settings() {
        $fields = array(
            array(
                'type' => 'checkbox', 
                'name' => 'format[html]', 
                'label' => fx::alang('allow HTML tags','system'), 
                'value' => $this['format']['html']
            ),
            array(
                'type' => 'checkbox', 
                'name' => 'format[nl2br]', 
                'label' => fx::alang('replace newline to br','system'), 
                'value' => $this['format']['nl2br']
            )
        );
        return $fields;
    }
}