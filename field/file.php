<?php

class fx_field_file extends fx_field_baze {

    protected $_to_delete_id = 0;

    /*
    protected static $filetable = array();

    protected function get_fileinfo (  fx_infoblock_content $infoblock, $file_id ) {
        $infoblock_id = $infoblock['id'];
        if ( !isset(self::$filetable[$infoblock_id.'-'.$this['id']]) ) {
            $this->init_filetable($infoblock);
        }

        return self::$filetable[$infoblock_id.'-'.$this['id']][$file_id];
    }

    protected function init_filetable ( fx_infoblock_content $infoblock ) {
        $infoblock_id = $infoblock['id'];
        self::$filetable[$infoblock_id.'-'.$this['id']] = array();
        $db_res = $infoblock->get_db_result();
        if ( $db_res ) {
            $file_ids = array();
            foreach ( $db_res as $res ) {
                $file_ids[] = intval($res[$this->name]);
            }
            if ( $file_ids ) {
                $info = fx::data('filetable', $file_ids);
                foreach ( $info as $v ) {
                    self::$filetable[$infoblock_id.'-'.$this['id']][$v['id']] = $v->get();
                }
            }
        }

    }
     * 
     */

    protected function get_field_vars($info) {
        return new fx_field_vars_file($info);
    }

    /*
    public function get_edit_jsdata($content) {
        parent::get_edit_jsdata($content);

        $this->_edit_jsdata['type'] = 'file';

        $this->_edit_jsdata['fileinfo']['field_id'] = $this['id'];


        $file_id = $content[$this->name];
        if ($file_id) {
            $sql = "SELECT * FROM `{{filetable}}` WHERE `id` = '".$file_id."'";
            $fileinfo = fx::db()->get_row($sql);
            $this->_edit_jsdata['fileinfo']['real_name'] = $fileinfo['real_name'];
            $this->_edit_jsdata['fileinfo']['path'] = fx::config()->HTTP_FILES_PATH.$fileinfo['path'];
            $this->_edit_jsdata['fileinfo']['old'] = $file_id;
        }

        return $this->_edit_jsdata;
    }
     * 
     */

    public function get_js_field($content) {
        parent::get_js_field($content);
        $this->_js_field['type'] = 'file';
        $this->_js_field['field_id'] = $this['id'];

        /*
        $file_id = $content[$this->name];
        if ($file_id) {
            $sql = "SELECT * FROM `{{filetable}}` WHERE `id` = '".$file_id."'";
            $fileinfo = fx::db()->get_row($sql);
            $this->_js_field['real_name'] = $fileinfo['real_name'];
            $this->_js_field['path'] = fx::config()->HTTP_FILES_PATH.$fileinfo['path'];
            $this->_js_field['old'] = $file_id;
        }
         * 
         */
        return $this->_js_field;
    }

    public function get_savestring(fx_essence $content = null) {
        $old_value = $content[$this['name']];
        if ($old_value != $this->value) {
            if (!empty($old_value)) {
                $old_value = fx::path()->to_abs($old_value);
                if (file_exists($old_value) && is_file($old_value)) {
                    fx::files()->rm($old_value);
                }
            }
            if (!empty($this->value)) {
                $c_val = fx::path()->to_abs($this->value);
                if (file_exists($c_val) && is_file($c_val)) {
                    preg_match("~[^".preg_quote(DIRECTORY_SEPARATOR).']+$~', $c_val, $fn);

                    $path = fx::path()->http(
                        'content_files', 
                        $content['type'].'/'.$this->name.'/'.$fn[0]
                    );

                    fx::files()->move($c_val, $path);
                }
            }
        }

        return isset($path) ? $path : $this->value;
    }

    public function post_save($content) {
        if ($this->_to_delete_id) {
            $file_to_delete = fx::data('filetable')->get_by_id($this->_to_delete_id);
            $file_to_delete->set('to_delete', 1)->save();
        }
    }

    public function get_sql_type() {
        return "VARCHAR(255)";
    }

    public function get_export_value($value, $dir = '') {
        
        if ( !$value ) {
            return 0;
        }

        $fileinfo = fx::data('filetable')->get_by_id($value);
        if ( !$fileinfo || $fileinfo['to_delete'] ) {
            return 0;
        }

        $new_name = md5(rand().time().$fileinfo['real_name']).'_'.$fileinfo['real_name'];
        fx::files()->copy( fx::config()->HTTP_FILES_PATH.$fileinfo['path'], $dir.'/'.$new_name);

        $result = new stdClass();
        $result->real_name = $fileinfo['real_name'];
        $result->type = $fileinfo['type'];
        $result->size = $fileinfo['size'];
        $result->path = $new_name;


        return $result;
    }

    public function get_import_value ( $content, $value, $dir = '' ) {
        if ( $value && is_array($value) ) {
            $value['path'] = $dir.$value['path'];
            $r = fx::files()->save_file($value, $content->get_upload_folder());
            return $r['id'];
        }
        else {
            return 0;
        }
    }

}