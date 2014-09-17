<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class File extends Admin {

    public function upload_save($input) {
        $path = 'content';
        $result = fx::files()->save_file($input['file'], $path);
        return $result;
    }
}