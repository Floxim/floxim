<?php

namespace Floxim\Floxim\Field;

use \Floxim\Floxim\System\Fx as fx;

class Image extends File
{

    public function getJsField($content)
    {
        parent::getJsField($content);
        $this->_js_field['type'] = 'image';
        $f = $this->_js_field;
        if (isset($f['value']) && isset($f['value']['path']) && $f['value']['path']) {
            $thumb = new \Floxim\Floxim\System\Thumb($f['value']['path']);
            $info = $thumb->getInfo();
            if ($info && isset($info['width']) && isset($info['height'])) {
                $this->_js_field['value'] = array_merge(
                    $f['value'],
                    array(
                        'width' => $info['width'],
                        'height' => $info['height']
                    )
                );
            }
        }

        return $this->_js_field;
    }

    public function fakeValue()
    {
        static $num = 1;
        $num = $num === 1 ? 2 : 1;
        return '/vendor/Floxim/Floxim/Admin/style/images/stub_' . $num . '.jpg';
    }
}