<?php

namespace Floxim\Floxim\Field;
use Floxim\Floxim\System\Fx as fx;

class Text extends Baze {
    
    public function getJsField($content) {
        parent::getJsField($content);
        if (isset($this['format']) && isset($this['format']['html']) && $this['format']['html']) {
            $this->_js_field['wysiwyg'] = true;
            $this->_js_field['nl2br'] = $this['format']['nl2br'];
        }

        return $this->_js_field;
    }

    public function formatSettings() {
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