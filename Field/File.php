<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class File extends Baze
{

    protected $_to_delete_id = 0;

    public function getJsField($content)
    {
        parent::getJsField($content);
        $this->_js_field['type'] = 'file';
        $this->_js_field['field_id'] = $this['id'];
        $val = $this->_js_field['value'];
        $abs = fx::path()->toAbs($val);
        if (fx::path()->exists($abs)) {
            $this->_js_field['value'] = array(
                'path'     => $val,
                'filename' => fx::path()->fileName($abs),
                'size'     => fx::files()->readableSize($abs)
            );
            //$this->_js_field['filename'] = fx::path()->file_name($abs);
            //$this->_js_field['size'] = fx::files()->readable_size($abs);
        }
        return $this->_js_field;
    }

    public function getSavestring(System\Entity $content = null)
    {
        $old_value = $content[$this['keyword']];
        if ($old_value != $this->value) {
            if (!empty($old_value)) {
                $old_value = fx::path()->toAbs($old_value);
                if (file_exists($old_value) && is_file($old_value)) {
                    fx::files()->rm($old_value);
                }
            }
            if (!empty($this->value)) {
                $c_val = fx::path()->toAbs($this->value);
                if (file_exists($c_val) && is_file($c_val)) {
                    preg_match("~[^" . preg_quote(DIRECTORY_SEPARATOR) . ']+$~', $c_val, $fn);

                    $path = fx::path()->http('content_files', $content['type'] . '/' . $this['keyword'] . '/' . $fn[0]);

                    $try = 0;
                    while (fx::path()->exists($path)) {
                        $file_name = preg_replace("~(\.[^\.]+)$~", "_" . $try . "\$1", $fn[0]);
                        $try++;
                        $path = fx::path()->http('content_files',
                            $content['type'] . '/' . $this['keyword'] . '/' . $file_name);
                    }

                    fx::files()->move($c_val, $path);
                }
            }
        }

        return isset($path) ? $path : $this->value;
    }

    public function getSqlType()
    {
        return "VARCHAR(255)";
    }
}