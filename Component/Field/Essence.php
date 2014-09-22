<?php

namespace Floxim\Floxim\Component\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Essence extends System\Essence {

    protected $format, $type_id;
    
    const FIELD_STRING = 1;
    const FIELD_INT = 2;
    const FIELD_TEXT = 3;
    const FIELD_SELECT = 4;
    const FIELD_BOOL = 5;
    const FIELD_FILE = 6;
    const FIELD_FLOAT = 7;
    const FIELD_DATETIME = 8;
    const FIELD_COLOR = 9;
    const FIELD_IMAGE = 11;
    const FIELD_LINK = 13;
    const FIELD_MULTILINK = 14;
    
    const EDIT_ALL = 1;
    const EDIT_ADMIN = 2;
    const EDIT_NONE = 3;
    
    public static function get_type_by_id($id) {

        static $res = array();
        if (empty($res)) {
            $reflection = new \ReflectionClass(get_class($this));
            $constants = $reflection->getConstants();
            foreach ($constants as $name => $value) {
                if (preg_match("~^FIELD_~", $name)) {
                    $res[$value] = strtolower(substr($name, 6));
                }
            }
        }

        return $id ? $res[$id] : $res;
    }

    public function __construct($input = array()) {
        parent::__construct($input);

        $this->format = $this['format'];
        $this->type_id = $this['type'];

        $this->type = self::get_type_by_id($this->type_id);
        $this->_edit_jsdata = array('type' => 'input');
    }
    
    public function get_type_keyword() {
        return $this->type;
    }
    
    public function get_type_id() {
        return $this->type_id;
    }

    public function is_not_null() {
        return $this['not_null'];
    }

    public function validate() {
        $res = true;
        if (!$this['keyword']) {
            $this->validate_errors[] = array(
                'field' => 'keyword', 
                'text' => fx::alang('Specify field keyword','system')
            );
            $res = false;
        }
        if ($this['keyword'] && !preg_match("/^[a-z][a-z0-9_]*$/i", $this['keyword'])) {
            $this->validate_errors[] = array(
                'field' => 'keyword', 
                'text' => fx::alang('Field keyword can contain only letters, numbers, and the underscore character','system')
            );
            $res = false;
        }

        $modified = $this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword'];

        if ($this['component_id'] && ( $modified || !$this['id'])) {
            
            /// Edit here
            $component = fx::data('component')->where('id',$this['component_id'])->one();
            $chain = $component->get_chain();
            foreach ( $chain as $c_level ) {
                if ( fx::db()->column_exists( $c_level->get_content_table(), $this->data['keyword']) ) {
                    $this->validate_errors[] = array(
                        'field' => 'keyword', 
                        'text' => fx::alang('This field already exists','system')
                    );
                    $res = false;
                }
            }
            if (fx::db()->column_exists($this->get_table(), $this->data['keyword'])) {
                $this->validate_errors[] = array(
                    'field' => 'keyword', 
                    'text' => fx::alang('This field already exists','system')
                );
                $res = false;
            }
        }


        if (!$this['name']) {
            $this->validate_errors[] = array(
                'field' => 'name', 
                'text' => fx::alang('Specify field name','system')
            );
            $res = false;
        }

        return $res;
    }
    
    public function is_multilang() {
        return $this['format']['is_multilang'];
    }

    protected function get_table() {
        return fx::data('component')->where('id',$this['component_id'])->one()->get_content_table();
    }

    protected function _after_insert() {
        $this->_drop_meta_cache();
        if (!$this['component_id']) {
            return;
        }
        $type = $this->get_sql_type();
        if (!$type) {
            return;
        }
        
        fx::db()->query("ALTER TABLE `{{".$this->get_table()."}}`
            ADD COLUMN `".$this['keyword']."` ".$type);
    }

    protected function _after_update() {
        if ($this['component_id']) {
            $type = self::get_sql_type_by_type($this->data['type']);
            if ($type) {
                if ($this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword']) {
                    fx::db()->query("ALTER TABLE `{{".$this->get_table()."}}` 
                    CHANGE `".$this->modified_data['keyword']."` `".$this->data['keyword']."` ".$type);
                } else if ($this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword']) {
                    fx::db()->query("ALTER TABLE `{{".$this->get_table()."}}`
                    MODIFY `".$this->data['keyword']."` ".$type);
                }
            }
        }
        $this->_drop_meta_cache();
    }

    protected function _after_delete() {
        if ($this['component_id']) {
            if (self::get_sql_type_by_type($this->data['type'])) {
                fx::db()->query("ALTER TABLE `{{".$this->get_table()."}}` DROP COLUMN `".$this['keyword']."`");
            }
        }
        $this->_drop_meta_cache();
    }

    /* -- for admin interface -- */

    public function format_settings() {
        return array();
    }

    public function get_sql_type() {
        return "TEXT";
    }

    public function check_rights() {
        if ($this['type_of_edit'] == Essence::EDIT_ALL || empty($this['type_of_edit'])) {
            return true;
        }
        if ($this['type_of_edit'] == Essence::EDIT_ADMIN) {
            return fx::is_admin();
        }

        return false;
    }

    static public function get_sql_type_by_type($type_id) {
        $type = self::get_type_by_id($type_id);
        $classname = 'Floxim\\Floxim\\Field\\'.ucfirst($type);

        $field = new $classname();
        return $field->get_sql_type();
    }
    
    public function fake_value() {
        $c_type = preg_replace("~\(.+?\)~", '', $this->get_sql_type());
        $val = '';
        switch ($c_type) {
            case 'VARCHAR':
                $val = $this['name'];
                break;
            case 'TEXT':
                $val = $this['name'].' '.str_repeat(mb_strtolower($this['name']).' ', rand(10, 15));
                break;
            case 'INT': case 'TINYINT': case 'FLOAT':
                $val = rand(0, 1000);
                break;
            case 'DATETIME':
                $val = date('r');
                break;
        }
        return $val;
    }
    
    protected function _drop_meta_cache() {
        fx::files()->rm( fx::path('files', 'cache/meta_cache.php') );
    }

}