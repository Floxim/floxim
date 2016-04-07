<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class File extends Admin
{

    public function uploadSave($input)
    {
        $path = 'upload';
        $result = fx::files()->saveFile($input['file'], $path);
        if (!$result) {
            $result = array(
               'error_message' => 'Can not load this file' 
            );
            fx::http()->status(500);
        }
        if (isset($input['format']) && !empty($input['format']) && isset($result['path'])) {
            $format = trim($input['format'], "'");
            $result['formatted_value'] = fx::image($result['path'], $format);
        }
        return $result;
    }
}