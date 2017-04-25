<?php

namespace Floxim\Floxim\Field;

use \Floxim\Floxim\System\Fx as fx;

class FieldImage extends \Floxim\Floxim\Field\FieldFile
{

    public function getJsField($content)
    {
        $f = parent::getJsField($content);
        $f['type'] = 'image';
        return $f;
    }
    
    public static function prepareValue($val) {
        $res = parent::prepareValue($val);
        
        if (!$res || !is_array($res) || !isset($res['path']) || empty($res['path'])) {
            return $res;
        }
        $abs = $res['path'];
        $thumb = new \Floxim\Floxim\System\Thumb($abs);
        $info = $thumb->getInfo();
        
        if ($info && isset($info['width']) && isset($info['height'])) {
            $res = array_merge(
                $res,
                array(
                    'width' => $info['width'],
                    'height' => $info['height']
                )
            );
        }
        return $res;
    }

    public function fakeValue($entity = null)
    {
        static $num = 1;
        $num = $num === 1 ? 2 : 1;
        return fx::path()->http('@floxim/Admin/style/images/stub_' . $num . '.jpg');
    }
}