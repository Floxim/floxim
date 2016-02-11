<?php

namespace Floxim\Floxim\Field;

use \Floxim\Floxim\System\Fx as fx;

class Image extends \Floxim\Floxim\Field\File
{

    public function getJsField($content)
    {
        $f = parent::getJsField($content);
        $f['type'] = 'image';
        if (isset($f['value']) && isset($f['value']['path']) && $f['value']['path']) {
            $thumb = new \Floxim\Floxim\System\Thumb($f['value']['path']);
            $info = $thumb->getInfo();
            if ($info && isset($info['width']) && isset($info['height'])) {
                $f['value'] = array_merge(
                    $f['value'],
                    array(
                        'width' => $info['width'],
                        'height' => $info['height']
                    )
                );
            }
        }

        return $f;
    }

    public function fakeValue()
    {
        static $num = 1;
        $num = $num === 1 ? 2 : 1;
        return fx::path()->http('@floxim/Admin/style/images/stub_' . $num . '.jpg');
    }
}