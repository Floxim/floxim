<?php
class fx_filetable extends fx_essence {
    public static function get_path($id) {
        if (!is_numeric($id)) {
            return $id;
        }
        $file = fx::data('filetable', $id);
        if (!$file) {
            return null;
        }
        return $file->get_http_path();
    }
    
    protected function _after_delete() {
        $this->delete_file();
    }
    
    public function delete_file() {
        $path = $this->get_full_path();
        if (file_exists($path) && is_file($path)) {
            unlink($path);
        }
    }


    public function get_http_path() {
        return fx::config()->HTTP_FILES_PATH.$this['path'];
    }
    
    public function get_full_path() {
        return fx::config()->DOCUMENT_ROOT .$this->get_http_path();
    }
}
?>