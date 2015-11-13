<?php

namespace Floxim\Floxim\Component\Basic;

use Floxim\Floxim\System;
use Floxim\Floxim\Component\Field;
use Floxim\Floxim\Component\Lang;
use Floxim\Floxim\System\Fx as fx;

/**
 * This is a basic Finder class for all models handled by Component subsystem
 */
abstract class Finder extends \Floxim\Floxim\System\Finder {
    public function getData()
    {
        $data = parent::getData();
        $types_by_id = $data->getValues('type', 'id');
        unset($types_by_id['']);
        if (count($types_by_id) == 0) {
            return $data;
        }
        $base_component = fx::component($this->component_id);
        $base_type = $base_component['keyword'];
        $base_table = $base_component->getContentTable();
        $types = array();
        foreach ($types_by_id as $id => $type) {
            if ($type != $base_type) {
                if (!isset($types[$type])) {
                    $types[$type] = array();
                }
                $types[$type] [] = $id;
            }
        }
        
        foreach ($types as $type => $ids) {
            if (!$type) {
                continue;
            }
            $type_tables = array_reverse(fx::data($type)->getTables());
            $missed_tables = array();
            foreach ($type_tables as $table) {
                if ($table == $base_table) {
                    break;
                }
                $missed_tables [] = $table;
            }
            $base_missed_table = array_shift($missed_tables);
            if (!$base_missed_table) {
                fx::log('empty base table');
                continue;
            }
            $q = "SELECT * FROM `{{" . $base_missed_table . "}}` \n";
            foreach ($missed_tables as $mt) {
                $q .= " INNER JOIN `{{" . $mt . '}}` ON `{{' . $mt . '}}`.id = `{{' . $base_missed_table . "}}`.id\n";
            }
            $q .= "WHERE `{{" . $base_missed_table . "}}`.id IN (" . join(", ", $ids) . ")";
            
            $extensions = fx::db()->getIndexedResults($q);

            foreach ($data as $data_index => $data_item) {
                if (isset($extensions[$data_item['id']])) {
                    $data[$data_index] = array_merge($data_item, $extensions[$data_item['id']]);
                }
            }
        }
        return $data;
    }

    protected static $_com_tables_cache = array();

    public function getTables()
    {
        if (isset(self::$_com_tables_cache[$this->component_id])) {
            $cached = self::$_com_tables_cache[$this->component_id];
            return $cached;
        }
        $chain = $this->getComponent()->getChain();
        $tables = array();
        foreach ($chain as $comp) {
            $tables [] = $comp->getContentTable();
        }
        self::$_com_tables_cache[$this->component_id] = $tables;
        return $tables;
    }

    static $stored_relations = array();
    
    public function relations()
    {
        $class = get_called_class();
        if (isset(self::$stored_relations[$class])) {
            return static::$stored_relations[$class];
        }
        
        $relations = array();
        $fields = fx::component($this->component_id)->
                getAllFields()->
                find('type', array(Field\Entity::FIELD_LINK, Field\Entity::FIELD_MULTILINK));
        foreach ($fields as $f) {
            if (!($relation = $f->getRelation())) {
                continue;
            }
            switch ($f['type']) {
                case Field\Entity::FIELD_LINK:
                    $relations[$f->getPropName()] = $relation;
                    break;
                case Field\Entity::FIELD_MULTILINK:
                    $relations[$f['keyword']] = $relation;
                    break;
            }
        }
        /*
        $relations ['component'] = array(
            self::BELONGS_TO,
            'component',
            'type',
            'keyword'
        );
        */
        self::$stored_relations[$class] = $relations;
        return $relations;
    }
    
    protected $component_id = null;
    
    public function __construct($table = null)
    {
        parent::__construct($table);

        $this->setComponent(fx::getComponentNameByClass(get_class($this)));
    }

    public function setComponent($component_id_or_code)
    {
        $component = fx::component($component_id_or_code);
        if (!$component) {
            die("Component not found: " . $component_id_or_code);
        }
        $this->component_id = $component['id'];
        $this->table = $component->getContentTable();
        return $this;
    }

    public function getComponent()
    {
        return fx::getComponentById($this->component_id);
    }
    
    // array access: $com = $entity['component']
    public function _getComponent()
    {
        return fx::getComponentById($this->component_id);
    }
    
        /**
     * Create new content entity
     * @param array $data Initial params
     * @return New content entity (not saved yet, without ID)
     */
    public function create($data = array())
    {
        $obj = parent::create($data);

        $component = fx::component($this->component_id);
        if (!isset($data['created'])) {
            $obj['created'] = date("Y-m-d H:i:s");
        }
        if ($component['keyword'] != 'floxim.user.user' && ($user = fx::env()->getUser())) {
            $obj['user_id'] = $user['id'];
        }
        $obj['type'] = $component['keyword'];
        if (!isset($data['site_id'])) {
            $obj['site_id'] = fx::env('site')->get('id');
        }
        $fields = $component->getAllFields()->find('default', '', System\Collection::FILTER_NEQ);
        foreach ($fields as $f) {
            if ($f['default'] === 'null') {
                continue;
            }
            if (!isset($data[$f['keyword']])) {
                if ($f['type'] == Field\Entity::FIELD_DATETIME) {
                    $obj[$f['keyword']] = date('Y-m-d H:i:s');
                } else {
                    $obj[$f['keyword']] = $f['default'];
                }
            }
        }
        return $obj;
    }
    
    protected static $content_classes = array();

    public function getEntityClassName($data = null)
    {
        if (!is_null($data) && isset($data['type'])) {
            $c_type = $data['type'];
        } else {
            $component = fx::component($this->component_id);
            $c_type = $component['keyword'];
        }
        
        if (isset(Finder::$content_classes[$c_type])) {
            return Finder::$content_classes[$c_type];
        }
        
        $class_namespace = fx::getComponentNamespace($c_type);
        $class_name = $class_namespace.'\\Entity';
        Finder::$content_classes[$c_type] = $class_name;
        return $class_name;
    }

    /**
     * Returns the entity
     * @param array $data
     * @return \Floxim\Floxim\Component\Basic\Entity
     */
    public function entity($data = array())
    {
        if (isset($data['id'])) {
            $cached = static::getFromStaticCache($data['id']);
            if ($cached) {
                return $cached;
            }
        }
        
        $classname = $this->getEntityClassName($data);
        
        if (isset($data['type'])) {
            $com = fx::getComponentByKeyword($data['type']);
            $component_id = $com['id'];
        } else {
            $component_id = $this->component_id;
        }
        
        $obj = new $classname(array(
            'data'         => $data,
            'component_id' => $component_id
        ));
        
        $this->addToStaticCache($obj);
        return $obj;
    }
    
    
    public function nextPriority()
    {
        return fx::db()->getVar(
            "SELECT MAX(`priority`)+1 FROM `{{floxim_main_content}}`"
        );
    }

    

    public function update($data, $where = array())
    {
        $wh = array();
        foreach ($where as $k => $v) {
            $wh[] = "`" . fx::db()->escape($k) . "` = '" . fx::db()->escape($v) . "' ";
        }

        $update = $this->setStatement($data);
        foreach ($update as $table => $props) {
            $q = 'UPDATE `{{' . $table . '}}` SET ' . $this->compileSetStatement($props); //join(', ', $props);
            if ($wh) {
                $q .= " WHERE " . join(' AND ', $wh);
            }
            fx::db()->query($q);
        }
    }

    public function delete($cond_field = null, $cond_val = null)
    {
        if (func_num_args() === 0) {
            parent::delete();
        }
        if ($cond_field != 'id' || !is_numeric($cond_val)) {
            throw new Exception("Content can be killed only by id!");
        }
        $tables = $this->getTables();

        $q = 'DELETE {{' . join("}}, {{", $tables) . '}} ';
        $q .= 'FROM {{' . join("}} INNER JOIN {{", $tables) . '}} ';
        $q .= ' WHERE ';
        $base_table = array_shift($tables);
        foreach ($tables as $t) {
            $q .= ' {{' . $t . '}}.id = {{' . $base_table . '}}.id AND ';
        }
        $q .= ' {{' . $base_table . '}}.id = "' . fx::db()->escape($cond_val) . '"';
        fx::db()->query($q);
    }

    /**
     * Generate SET statement from field-value array
     * @param array $props Array with field names as keys and data as values (both quoted)
     * e.g. array('`id`' => "1", '`name`' => "'My super name'")
     * @return string
     * joined pairs (with no SET keyword)
     * e.g. "`id` = 1, `name` = 'My super name'"
     */
    protected function  compileSetStatement($props)
    {
        $res = array();
        foreach ($props as $p => $v) {
            $res [] = $p . ' = ' . $v;
        }
        return join(", ", $res);
    }

    public function insert($data)
    {
        if (!isset($data['type'])) {
            throw  new \Exception('Can not save entity with no type specified');
        }
        $set = $this->setStatement($data);

        $tables = $this->getTables();
        
        $com = $this->getComponent();
        
        $priority_field = $com->getFieldByKeyword('priority');
        
        $base_table = array_shift($tables);
        $root_set = $set[$base_table];
        $q = "INSERT INTO `{{" . $base_table . "}}` ";
        if ($priority_field && !isset($data['priority'])) {
            $q .= ' ( `priority`, ' . join(", ", array_keys($root_set)) . ') ';
            $q .= ' SELECT MAX(`priority`)+1, ';
            $q .= join(", ", $root_set);
            $q .= ' FROM {{' . $base_table . '}}';
        } else {
            $q .= "SET " . $this->compileSetStatement($root_set);
        }

        $tables_inserted = array();

        $q_done = fx::db()->query($q);
        $id = fx::db()->insertId();
        if ($q_done) {
            // remember, whatever table has inserted
            $tables_inserted [] = $base_table;
        } else {
            return false;
        }

        foreach ($tables as $table) {

            $table_set = isset($set[$table]) ? $set[$table] : array();

            $table_set['`id`'] = "'" . $id . "'";
            $q = "INSERT INTO `{{" . $table . "}}` SET " . $this->compileSetStatement($table_set);

            $q_done = fx::db()->query($q);
            if ($q_done) {
                // remember, whatever table has inserted
                $tables_inserted [] = $table;
            } else {
                // could not be deleted from all previous tables
                foreach ($tables_inserted as $tbl) {
                    fx::db()->query("DELETE FROM {{" . $tbl . "}} WHERE id  = '" . $id . "'");
                }
                // and return false
                return false;
            }
        }
        return $id;
    }

    protected function setStatement($data)
    {
        $res = array();
        $chain = fx::component($this->component_id)->getChain();
        foreach ($chain as $level_component) {
            $table_res = array();
            $fields = $level_component->fields();
            $field_keywords = $fields->getValues('keyword');
            // while the underlying field content manually prescription
            if ($level_component['keyword'] == 'floxim.main.content') {
                $field_keywords = array_merge($field_keywords, array(
                    'priority',
                    'last_updated',
                    'type',
                    'infoblock_id',
                    'materialized_path',
                    'level'
                ));
            }
            $table_name = $level_component->getContentTable();
            $table_cols = $this->getColumns($table_name);
            foreach ($field_keywords as $field_keyword) {
                if (!in_array($field_keyword, $table_cols)) {
                    continue;
                }

                $field = $fields->findOne('keyword', $field_keyword);
                // put only if the sql type of the field is not false (e.g. multilink)
                if ($field && !$field->getSqlType()) {
                    continue;
                }

                //if (isset($data[$field_keyword]) ) {
                if (array_key_exists($field_keyword, $data)) {
                    $field_val = $data[$field_keyword];
                    $sql_val = is_null($field_val) ? 'NULL' : "'" . fx::db()->escape($field_val) . "'";
                    $table_res['`' . fx::db()->escape($field_keyword) . '`'] = $sql_val;
                }
            }
            if (count($table_res) > 0) {
                $res[$table_name] = $table_res;
            }
        }
        return $res;
    }

}