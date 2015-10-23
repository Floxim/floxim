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
        $abs = fx::path()->abs($val);
        if (fx::path()->exists($abs)) {
            $this->_js_field['value'] = array(
                'path'     => $val,
                'filename' => fx::path()->fileName($abs),
                'size'     => fx::files()->readableSize($abs)
            );
        }
        return $this->_js_field;
    }

    public function getSavestring(System\Entity $content = null)
    {
        $old_value = $content[$this['keyword']];
        $old_path = FX_BASE_URL.$old_value;
        
        if ($old_path === $this->value) {
            return $this->value;
        }
        $res = '';
        if (!empty($this->value)) {
            $c_val = fx::path()->abs($this->value);
            if (file_exists($c_val) && is_file($c_val)) {
                $file_name = fx::path()->fileName($c_val);
                $path = fx::path(
                    '@content_files/' . 
                    $content['site_id'].'/'.
                    $content['type'].'/'. 
                    $this['keyword'].'/'.
                    $file_name
                );
                $path = fx::files()->getPutFilePath($path);
                fx::files()->move($c_val, $path);
                $res = fx::path()->removeBase(fx::path()->http($path));
            }
        }
        if (!empty($old_value)) {
            $old_value = fx::path()->abs($old_value);
            if (file_exists($old_value) && is_file($old_value)) {
                fx::files()->rm($old_value);
            }
        }
        return $res;
    }

    public function getSqlType()
    {
        return "VARCHAR(255)";
    }
}