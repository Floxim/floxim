<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class FieldFile extends \Floxim\Floxim\Component\Field\Entity
{

    protected $_to_delete_id = 0;

    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        $res['type'] = 'file';
        $res['field_id'] = $this['id'];
        $val = $res['value'];
        $val_array = static::prepareValue($val);
        if ($val_array) {
            $res['value'] = $val_array;
        }
        return $res;
    }
    
    public static function prepareValue($val) 
    {
        $abs = fx::path()->abs($val);
        if (!fx::path()->exists($abs)) {
            return;
        }
        return array(
            'path'     => $val,
            'http'     => fx::path()->http($val),
            'filename' => fx::path()->fileName($abs),
            'size'     => fx::files()->readableSize($abs)
        );
    }

    public function getSavestring($content = null)
    {
        $old_value = $content[$this['keyword']];
        $old_path = FX_BASE_URL.$old_value;
        
        if ($old_path === $this->value) {
            return $this->value;
        }
        $res = '';
        
        $move = null;
        $drop = null;
        
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
                
                $res = fx::path()->storable($path);

                $move = [$c_val, $path];
            }
        }
        if (!empty($old_value) && $old_value !== $res) {
            $old_value = fx::path()->abs($old_value);
            if (file_exists($old_value) && is_file($old_value)) {
                $drop = $old_value;
            }
        }
        
        // move / drop files only after entity is saved
        if ($drop || $move) {
            fx::listen('after_save', function($e) use ($content, $move, $drop) {
                if ($e['entity'] !== $content) {
                    return;
                }
                if ($move) {
                    fx::files()->move($move[0], $move[1]);
                }
                if ($drop) {
                    fx::files()->rm($drop);
                }
            });
        }
        return $res;
    }

    public function getSqlType()
    {
        return "VARCHAR(255)";
    }
}