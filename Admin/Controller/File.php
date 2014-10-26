<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class File extends Admin
{

    public function uploadSave($input)
    {
        $path = 'content';
        $result = fx::files()->saveFile($input['file'], $path);
        return $result;
    }
}