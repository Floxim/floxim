<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class Field
{
    protected $_value = null;

    protected $_meta = array();

    public function __construct($value = null, $meta = array())
    {
        $this->_value = $value;
        $this->_meta = $meta;
    }

    public function getValue()
    {
        return $this->_value;
    }

    public function setValue($value)
    {
        $this->_value = $value;
    }

    public function setMeta($key, $value)
    {
        $this->_meta[$key] = $value;
    }

    public function getMeta($key)
    {
        return isset($this->_meta[$key]) ? $this->_meta[$key] : null;
    }

    public static $replacements = array();
    public static $count_replacements = 0;

    public function __toString()
    {
        $val = isset($this->_meta['display_value']) ? $this->_meta['display_value'] : $this->_value;
        if (
            isset($this->_meta['editable']) &&
            (!$this->_meta['editable'] || $this->_meta['editable'] == 'false')
        ) {
            return (string)$val;
        }
        if (!$this->_meta['real_value'] && $this->_meta['var_type'] == 'visual' && $this->_meta['inatt']) {
            $this->_meta['value'] = $val;
        }
        if ($this->_meta['in_att'] && !isset($this->_meta['value'])) {
            $this->_meta['value'] = $val;
        }
        //$this->_meta['value'] = $this->_value;
        self::$replacements [] = array($this->_meta['id'], $this->_meta, $val);
        return '###fxf' . (self::$count_replacements++) . '###';
    }

    public static $fields_to_drop = array();


    /**
     * Postprocessing fields
     * @param string $html
     */
    public static function replaceFields($html)
    {
        if (!strpos($html, '#fxf')) {
            return $html;
        }
        $html = self::replaceFieldsInAtts($html);
        $html = self::replaceFieldsWrappedByTag($html);
        $html = self::replaceFieldsInText($html);
        foreach (self::$fields_to_drop as $id) {
            unset(self::$replacements[$id]);
        }
        self::$fields_to_drop = array();
        return $html;
    }

    protected static function replaceFieldsInAtts($html)
    {
        $html = preg_replace_callback(
            "~<[^>]+###fxf\d+###[^>]+?>~",
            function ($tag_matches) {
                $att_fields = array();
                $tag = preg_replace_callback(
                    '~###fxf(\d+)###~',
                    function ($field_matches) use (&$att_fields) {
                        $replacement = Field::$replacements[$field_matches[1]];
                        $att_fields[$replacement[0]] = $replacement[1];
                        //fx_template_field::$replacements[$field_matches[1]] = null;
                        Field::$fields_to_drop[] = $field_matches[1];
                        return $replacement[2];
                    },
                    $tag_matches[0]
                );
                $tag_meta = array('class' => 'fx_template_var_in_att');
                foreach ($att_fields as $afk => $af) {
                    $tag_meta['data-fx_template_var_' . $afk] = $af;
                }
                $tag = HtmlToken::createStandalone($tag);
                $tag->addMeta($tag_meta);
                $tag = $tag->serialize();
                return $tag;
            },
            $html
        );
        return $html;
    }

    protected static function replaceFieldsWrappedByTag($html)
    {
        $html = preg_replace_callback(
            "~(<[a-z0-9_-]+[^>]*?>)(\s*?)###fxf(\d+)###(\s*?</[a-z0-9_-]+>)~",
            function ($matches) {
                $replacement = Field::$replacements[$matches[3]];
                $tag = HtmlToken::createStandalone($matches[1]);
                $tag->addMeta(array(
                    'class'       => 'fx_template_var',
                    'data-fx_var' => $replacement[1]
                ));
                $tag = $tag->serialize();
                //fx_template_field::$replacements[$matches[3]] = null;
                Field::$fields_to_drop[] = $matches[3];
                return $tag . $matches[2] . $replacement[2] . $matches[4];
            },
            $html
        );
        return $html;
    }

    protected static function replaceFieldsInText($html)
    {
        $html = preg_replace_callback(
            "~###fxf(\d+)###~",
            function ($matches) {
                $replacement = Field::$replacements[$matches[1]];

                if (
                    (isset($replacement[1]['html']) && $replacement[1]['html']) ||
                    (isset($replacement[1]['type']) && $replacement[1]['type'] == 'html')
                ) {
                    $tag_name = 'div';
                } else {
                    $tag_name = Html::getWrapperTag($replacement[2]);
                }
                $tag = HtmlToken::createStandalone('<' . $tag_name . '>');
                $tag->addMeta(array(
                    'class'       => 'fx_template_var',
                    'data-fx_var' => $replacement[1]
                ));
                //fx_template_field::$replacements[$matches[1]] = null;
                Field::$fields_to_drop [] = $matches[1];
                $res = $tag->serialize() . $replacement[2] . '</' . $tag_name . '>';
                return $res;
            },
            $html
        );
        return $html;
    }

}