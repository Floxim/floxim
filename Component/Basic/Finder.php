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
        $base_component = $this->getComponent();
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

    public static function getTables() 
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])){
            return $cache[$class];
        }
        $tables = array();
        $chain = static::getComponent()->getChain();
        foreach ($chain as $com) {
            $tables []= $com->getContentTable();
        }
        $cache[$class] = $tables;
        return $tables;
    }
    
    protected function prepareComplexField($field, $operator = 'where')
    {
        if (!strstr($field, ':')) {
            return parent::prepareComplexField($field, $operator);
        }
        $parts = explode(".", $field, 2);
        $subtype = str_replace(":", ".", $parts[0]);
        $field_keyword = $parts[1];
        $sub_finder = fx::data($subtype);
        $sub_rels = $sub_finder->relations();
        // @todo: smth. like $f->where('parent.tags', 123) 
        if (isset($sub_rels[$field_keyword])) {
            fx::cdebug($this, $sub_rels[$field_keyword]);    
        } else {
            $subtype_table = $sub_finder->getColTable($field_keyword);
            $alias = 'self__'.$subtype_table;
            $this->join(array($subtype_table, $alias), $alias.'.id = {{'.$this->getTable().'}}.id', 'left');
        }
        return $alias.'.`'.$field_keyword.'`';
    }

    public function relations()
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])) {
            return $cache[$class];
        }

        $relations = array();
        $fields = $this->getComponent()
                       ->getAllFields()
                        ->find(
                            'type', 
                            array(
                                'link', 'multilink'
                            )
                        );
        foreach ($fields as $f) {
            if (!($relation = $f->getRelation())) {
                continue;
            }
            $relations[$f->getPropertyName()] = $relation;
        }
        $cache[$class] = $relations;
        return $relations;
    }
    
    public static function getTable() 
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])){
            return $cache[$class];
        }
        $table = static::getComponent()->getContentTable();
        $cache[$class] = $table;
        return $table;
    }
    
    public static function getKeyword() 
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])){
            return $cache[$class];
        }
        $keyword = static::getComponent()->get('keyword');
        $cache[$class] = $keyword;
        return $keyword;
    }
    
    public static function getComponent()
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])){
            return $cache[$class];
        }
        $com_keyword = fx::getComponentNameByClass($class);
        $com = fx::getComponentByKeyword($com_keyword);
        $cache[$class] = $com;
        return $com;
    }
    
    /**
     * Create new content entity
     * @param array $data Initial params
     * @return New content entity (not saved yet, without ID)
     */
    public function create($data = array())
    {
        $obj = parent::create($data);

        $component = static::getComponent();
        if (!isset($data['created'])) {
            $obj['created'] = date("Y-m-d H:i:s");
        }
        if ($component['keyword'] != 'floxim.user.user' && ($user = fx::env()->getUser())) {
            $obj['user_id'] = $user['id'];
        }
        $obj['type'] = $component['keyword'];
        if (!isset($data['site_id'])) {
            $site = fx::env('site');
            if ($site) {
                $obj['site_id'] = $site['id'];
            }
        }
        $fields = $component->getAllFields()->find('default', '', System\Collection::FILTER_NEQ);
        foreach ($fields as $f) {
            if ($f['default'] === 'null') {
                continue;
            }
            if (!isset($data[$f['keyword']])) {
                if ($f['type'] === 'datetime') {
                    $obj[$f['keyword']] = date('Y-m-d H:i:s');
                } else {
                    $obj[$f['keyword']] = $f['default'];
                }
            }
        }
        return $obj;
    }
    
    public function getEntityClassName($data = null)
    {
        $c_type = !is_null($data) && isset($data['type']) ? $data['type'] : static::getKeyword();
        static $cache = array();
        if (isset($cache[$c_type])) {
            return $cache[$c_type];
        }
        
        $class_namespace = fx::getComponentNamespace($c_type);
        $class_name = $class_namespace.'\\Entity';
        $cache[$c_type] = $class_name;
        return $class_name;
    }

    /**
     * Returns the entity
     * @param array $data
     * @return \Floxim\Floxim\Component\Basic\Entity
     */
    public function entity($data = array())
    {
        
        $id = isset($data['id']) ? $data['id'] : null;
        
        $registry = $this->getRegistry();
        
        if ( $id && ($obj = $registry->get($id)) ) {
            return $obj;
        }
        $classname = $this->getEntityClassName($data);
        
        
        if (isset($data['type'])) {
            $component_id = fx::getComponentByKeyword($data['type'])->offsetGet('id');
        } else {
            $component_id = $this->getComponent()->offsetGet('id');
        }
        
        $obj = new $classname($data, $component_id);
        if ($id) {
            $this->registerEntity($obj, $id);
        }
        return $obj;
    }
    
    public function registerEntity($entity, $id)
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])) {
            foreach ($cache[$class] as $registry) {
                $registry->register($entity, $id);
            }
            return;
        } 
        
        $registries = array();
        $chain = $this->getComponent()->getChain();
        foreach ($chain as $com) {
            $registry = fx::data($com['keyword'])->getRegistry();
            $registries [$com['keyword']]= $registry;
            $registry->register($entity, $id);
        }
        $cache[$class] = $registries;
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
        if (!is_array($props)) {
            fx::log($props, fx::debug()->backtrace());
        }
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
        $chain = $this->getComponent()->getChain();
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