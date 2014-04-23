<?php
class fx_controller_admin_file extends fx_controller_admin {

    public function upload_save($input) {
        $path = 'content';
        $result = fx::files()->save_file($input['file'], $path);
        return $result;
    }
}