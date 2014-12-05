<?php

namespace Floxim\Floxim\Field;

class Image extends File
{

    public function getJsField($content)
    {
        parent::getJsField($content);
        $this->_js_field['type'] = 'image';

        return $this->_js_field;
    }

    public function fakeValue()
    {
        static $num = 1;
        $num = $num === 1 ? 2 : 1;
        return '/vendor/Floxim/Floxim/Admin/style/images/stub_' . $num . '.jpg';
    }
}