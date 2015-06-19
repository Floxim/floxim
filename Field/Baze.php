<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\Component\Field;
use Floxim\Floxim\System\Fx as fx;

/**
 * Base class for the various field types
 */
class Baze extends Field\Entity
{

    protected $value, $error, $is_error = false;
    protected $_js_field = array();

    public function getJsField($content)
    {

        $name = $this['keyword'];
        $this->_js_field = array(
            'id'    => $name,
            'name'  => $name,
            'label' => $this['name'],
            'type'  => $this->getTypeKeyword()
        );
        $this->_js_field['value'] = $this['default'];
        if ( isset($content[$name]) ) {
            $this->_js_field['value'] = $content[$name];
        }
        return $this->_js_field;
    }


    public function getHtml($opt = '')
    {
        $asterisk = $this['not_null'] ? '<span class="fx_field_asterisk">*</span>' : '';
        return '<div class="' . $this->getWrapCssClass() . '"><label>' . $this['description'] . $asterisk . '</label>:' . $this->get_input($opt) . '</div>';
    }

    protected function getCssClass()
    {
        return "fx_form_field fx_form_field_" . Field\Entity::getTypeById($this->type_id) . ($this->is_error ? " fx_form_field_error" : "");
    }

    protected function getWrapCssClass()
    {
        return "fx_form_wrap fx_form_wrap_" . Field\Entity::getTypeById($this->type_id);
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function validateValue($value)
    {
        if (!is_array($value) && !is_object($value)) {
            $value = trim($value);
        }
        if ($this['is_required'] && !$value) {
            $this->error = sprintf(fx::alang('Field "%s" is required'), $this['name']);
            return false;
        }
        return true;
    }

    public function getSavestring()
    {
        return $this->value;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setError()
    {
        $this->is_error = true;
    }

    public function getExportValue($value, $dir = '')
    {
        return $value;
    }

    public function getImportValue($content, $value, $dir = '')
    {
        return $value;
    }
}