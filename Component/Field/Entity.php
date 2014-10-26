<?php

namespace Floxim\Floxim\Component\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{

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

    public static function getTypeById($id)
    {

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

    public function __construct($input = array())
    {
        parent::__construct($input);

        $this->format = $this['format'];
        $this->type_id = $this['type'];

        $this->type = self::getTypeById($this->type_id);
        $this->_edit_jsdata = array('type' => 'input');
    }

    public function getTypeKeyword()
    {
        return $this->type;
    }

    public function getTypeId()
    {
        return $this->type_id;
    }

    public function isNotNull()
    {
        return $this['not_null'];
    }

    public function validate()
    {
        $res = true;
        if (!$this['keyword']) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Specify field keyword', 'system')
            );
            $res = false;
        }
        if ($this['keyword'] && !preg_match("/^[a-z][a-z0-9_]*$/i", $this['keyword'])) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Field keyword can contain only letters, numbers, and the underscore character',
                    'system')
            );
            $res = false;
        }

        $modified = $this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword'];

        if ($this['component_id'] && ($modified || !$this['id'])) {

            /// Edit here
            $component = fx::data('component')->where('id', $this['component_id'])->one();
            $chain = $component->getChain();
            foreach ($chain as $c_level) {
                if (fx::db()->columnExists($c_level->getContentTable(), $this->data['keyword'])) {
                    $this->validate_errors[] = array(
                        'field' => 'keyword',
                        'text'  => fx::alang('This field already exists', 'system')
                    );
                    $res = false;
                }
            }
            if (fx::db()->columnExists($this->getTable(), $this->data['keyword'])) {
                $this->validate_errors[] = array(
                    'field' => 'keyword',
                    'text'  => fx::alang('This field already exists', 'system')
                );
                $res = false;
            }
        }


        if (!$this['name']) {
            $this->validate_errors[] = array(
                'field' => 'name',
                'text'  => fx::alang('Specify field name', 'system')
            );
            $res = false;
        }

        return $res;
    }

    public function isMultilang()
    {
        return $this['format']['is_multilang'];
    }

    protected function getTable()
    {
        return fx::data('component')->where('id', $this['component_id'])->one()->getContentTable();
    }

    protected function afterInsert()
    {
        $this->dropMetaCache();
        if (!$this['component_id']) {
            return;
        }
        $type = $this->getSqlType();
        if (!$type) {
            return;
        }

        fx::db()->query("ALTER TABLE `{{" . $this->getTable() . "}}`
            ADD COLUMN `" . $this['keyword'] . "` " . $type);
    }

    protected function afterUpdate()
    {
        if ($this['component_id']) {
            $type = self::getSqlTypeByType($this->data['type']);
            if ($type) {
                if ($this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword']) {
                    fx::db()->query("ALTER TABLE `{{" . $this->getTable() . "}}`
                    CHANGE `" . $this->modified_data['keyword'] . "` `" . $this->data['keyword'] . "` " . $type);
                } else {
                    if ($this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword']) {
                        fx::db()->query("ALTER TABLE `{{" . $this->getTable() . "}}`
                    MODIFY `" . $this->data['keyword'] . "` " . $type);
                    }
                }
            }
        }
        $this->dropMetaCache();
    }

    protected function afterDelete()
    {
        if ($this['component_id']) {
            if (self::getSqlTypeByType($this->data['type'])) {
                fx::db()->query("ALTER TABLE `{{" . $this->getTable() . "}}` DROP COLUMN `" . $this['keyword'] . "`");
            }
        }
        $this->dropMetaCache();
    }

    /* -- for admin interface -- */

    public function formatSettings()
    {
        return array();
    }

    public function getSqlType()
    {
        return "TEXT";
    }

    public function checkRights()
    {
        if ($this['type_of_edit'] == Entity::EDIT_ALL || empty($this['type_of_edit'])) {
            return true;
        }
        if ($this['type_of_edit'] == Entity::EDIT_ADMIN) {
            return fx::isAdmin();
        }

        return false;
    }

    static public function getSqlTypeByType($type_id)
    {
        $type = self::getTypeById($type_id);
        $classname = 'Floxim\\Floxim\\Field\\' . ucfirst($type);

        $field = new $classname();
        return $field->getSqlType();
    }

    public function fakeValue()
    {
        $c_type = preg_replace("~\(.+?\)~", '', $this->getSqlType());
        $val = '';
        switch ($c_type) {
            case 'VARCHAR':
                $val = $this['name'];
                break;
            case 'TEXT':
                $val = $this['name'] . ' ' . str_repeat(mb_strtolower($this['name']) . ' ', rand(10, 15));
                break;
            case 'INT':
            case 'TINYINT':
            case 'FLOAT':
                $val = rand(0, 1000);
                break;
            case 'DATETIME':
                $val = date('r');
                break;
        }
        return $val;
    }

    protected function dropMetaCache()
    {
        fx::files()->rm(fx::path('files', 'cache/meta_cache.php'));
    }

}