<?php

namespace Floxim\Floxim\Component\Filetable;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    public static function getPath($id)
    {
        if (!is_numeric($id)) {
            return $id;
        }
        $file = fx::data('filetable', $id);
        if (!$file) {
            return null;
        }
        return $file->getHttpPath();
    }

    protected function afterDelete()
    {
        $this->deleteFile();
    }

    public function deleteFile()
    {
        $path = $this->getFullPath();
        if (file_exists($path) && is_file($path)) {
            unlink($path);
        }
    }


    public function getHttpPath()
    {
        return fx::config()->HTTP_FILES_PATH . $this['path'];
    }

    public function getFullPath()
    {
        return fx::config()->DOCUMENT_ROOT . $this->getHttpPath();
    }
}