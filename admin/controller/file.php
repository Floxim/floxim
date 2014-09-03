<?php

namespace Floxim\Floxim\Admin\Controller;

class File extends Admin {

    public function upload_save($input) {
        $path = 'content';
        $result = fx::files()->save_file($input['file'], $path);
        return $result;
    }
}