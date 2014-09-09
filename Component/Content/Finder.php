<?php

namespace Floxim\Floxim\Component\Content;

use Floxim\Floxim\System;
use Floxim\Floxim\Component\Field;
use Floxim\Floxim\Component\Lang;
use fx;

class Finder extends System\Data {
    
    public function relations() {
        $relations = array();
        $fields = fx::data('component', $this->component_id)->
                    all_fields()->
                    find('type', array(Field\Essence::FIELD_LINK, Field\Essence::FIELD_MULTILINK));
        foreach ($fields as $f) {
            if ( !($relation = $f->get_relation()) ) {
                continue;
            }
            switch ($f['type']) {
                case Field\Essence::FIELD_LINK:
                    $relations[$f->get_prop_name()] = $relation;
                    break;
                case Field\Essence::FIELD_MULTILINK:
                    $relations[$f['keyword']] = $relation;
                    break;
            }
        }
        return $relations;
    }
    
    protected function _get_default_relation_finder($rel) {
        $finder = parent::_get_default_relation_finder($rel);
        if ( ! $finder instanceof Lang\Finder ) {
            $finder->order('priority');
        }
        return $finder;
    }
    
    public function get_data() {
        $data = parent::get_data();
        $types_by_id = $data->get_values('type', 'id');
        unset($types_by_id['']);
        if (count($types_by_id) == 0) {
            return $data;
        }
        $base_component = fx::data('component', $this->component_id);
        $base_type = $base_component['keyword'];
        $base_table = $base_component->get_content_table();
        $types = array();
        foreach ($types_by_id as $id => $type) {
            if ($type != $base_type) {
                if (!isset($types[$type])) {
                    $types[$type] = array();
                }
                $types[$type] []= $id;
            }
        }
        foreach ($types as $type => $ids) {
            if (!$type) {
                continue;
            }
            $type_tables = array_reverse(fx::data($type)->get_tables());
            $missed_tables = array();
            foreach ($type_tables as $table) {
                if ($table == $base_table) {
                    break;
                }
                $missed_tables []= $table;
            }
            $base_missed_table = array_shift($missed_tables);
            $q = "SELECT * FROM `{{".$base_missed_table."}}` \n";
            foreach ($missed_tables as $mt) {
                $q .= " INNER JOIN `{{".$mt.'}}` ON `{{'.$mt.'}}`.id = `{{'.$base_missed_table."}}`.id\n";
            }
            $q .= "WHERE `{{".$base_missed_table."}}`.id IN (".join(", ", $ids).")";
            $extensions = fx::db()->get_indexed_results($q);
            
            foreach ($data as $data_index => $data_item) {
                $extension = $extensions[$data_item['id']];
                if ($extension) {
                    $data[$data_index] = array_merge($data_item, $extension);
                }
            }
        }
        return $data;
    }
    
    protected static $_com_tables_cache = array();
    
    public function get_tables() {
        if (isset(self::$_com_tables_cache[$this->component_id])) {
            return self::$_com_tables_cache[$this->component_id];
        }
        $chain = fx::data('component', $this->component_id)->get_chain();
        $tables = array();
        foreach ($chain as $comp) {
            $tables []= $comp->get_content_table();
        }
        self::$_com_tables_cache[$this->component_id] = $tables;
        return $tables;
    }
    
    protected $component_id = null;
    
    public function __construct($table = null) {
        parent::__construct($table);
        ///$content_type = null;
        // todo: psr0 need fix
        
        $class = array_reverse(explode("\\", get_class($this)));
        $com = strtolower($class[1]);
        $this->set_component($com);
        /*
        if (preg_match("~^fx_data_content_(.+)$~", get_class($this), $content_type)) {
            $this->set_component($content_type[1]);
        }
         * 
         */
    }
    
    public function set_component($component_id_or_code) {
        $component = fx::data('component', $component_id_or_code);
        if (!$component) {
            die("Component not found: ".$component_id_or_code);
        }
        $this->component_id = $component['id'];
        $this->table = $component->get_content_table();
        return $this;
    }
    
    public function get_component() {
        return fx::data('component', $this->component_id);
    }
    
    public function content_exists() {
        static $content_by_type = null;
        if (is_null($content_by_type)) {
            $res = fx::db()->get_results(
                'select `type`, count(*) as cnt '
                    . 'from {{content}} '
                    . 'where site_id = "'.fx::env('site_id').'" '
                    . 'group by `type`'
            );
            $content_by_type = fx::collection($res)->get_values('cnt', 'type');
        }
        return isset($content_by_type[$this->get_component()->get('keyword')]);
    }

    /**
     * Create new content essence
     * @param array $data Initial params
     * @return fx_content New content essence (not saved yet, without ID)
     */
    public function create($data = array()) {
        $obj = parent::create($data);
        
        $component = fx::data('component', $this->component_id);
        
        $obj['created'] = date("Y-m-d H:i:s");
        if ($component['keyword'] != 'user' && ($user = fx::env()->get_user())) {
            $obj['user_id'] = $user['id'];
        }
        $obj['checked'] = 1;
        $obj['type'] = $component['keyword'];
        if (!isset($data['site_id'])) {
            $obj['site_id'] = fx::env('site')->get('id');
        }
        $fields = $component->all_fields()->find('default', '', System\Collection::FILTER_NEQ);
        foreach ($fields as $f) {
            if (!isset($obj[$f['keyword']])) {
                if ($f['type'] == Field\Essence::FIELD_DATETIME) {
                    $obj[$f['keyword']] = date('Y-m-d H:i:s');
                } else {
                    $obj[$f['keyword']] = $f['default'];
                }
            }
        }
        return $obj;
    }

    public function next_priority () {
        return fx::db()->get_var(
                "SELECT MAX(`priority`)+1 FROM `{{content}}`"
        );
    }
    
    protected static $content_classes = array();
    
    public function get_class_name($data = null) {
        if ($data && isset($data['type'])) {
            if (isset(Finder::$content_classes[$data['type']])) {
                return Finder::$content_classes[$data['type']];
            }
            $c_type = $data['type'];
            $component = fx::data('component', $c_type);
        } else {
            $component = fx::data('component', $this->component_id);
            $c_type = $component['keyword'];
        }
        if (!$component) {
            throw new Exception("No component: ".$c_type);
        }
        $chain = array_reverse($component->get_chain());
        
        $exists = false;
        
        while(!$exists && count($chain) > 0) {
            $c_level = array_shift($chain);
            // todo: psr0 need fix
            $class_name = '\Floxim\Floxim\Component\\'.ucfirst($c_level['keyword']).'\Essence';
            try {
                $exists = class_exists($class_name);
            } catch (Exception $e) {}
        }
        Finder::$content_classes[$data['type']] = $class_name;
        return $class_name;
    }
    
    /**
     * Returns the essence installed component_id
     * @param array $data
     * @return fx_content
     */
    public function essence($data = array()) {
        $classname = $this->get_class_name($data);
        if (isset($data['type'])) {
            $component_id = fx::data('component', $data['type'])->get('id');
        } else {
            $component_id = $this->component_id;
        }
        
        $obj = new $classname(array(
            'data' => $data,
            'component_id' => $component_id
        ));
        return $obj;
    }
    
    public function update($data, $where = array()) {
        $wh = array();
        foreach ($where as $k => $v) {
            $wh[] = "`".fx::db()->escape($k)."` = '".fx::db()->escape($v)."' ";
        }

        $update = $this->_set_statement($data);
        foreach ($update as $table => $props) {
            $q = 'UPDATE `{{'.$table.'}}` SET '.$this->_compile_set_statement($props); //join(', ', $props);
            if ($wh) {
                $q .= " WHERE ".join(' AND ', $wh);
            }
            fx::db()->query($q);
        }
    }
    
    public function delete($cond_field = null, $cond_val = null) {
        if (func_num_args() === 0) {
            parent::delete();
        }
        if ($cond_field != 'id' || !is_numeric($cond_val)) {
            throw new Exception("Content can be killed only by id!");
        }
        $tables = $this->get_tables();
        
        $q = 'DELETE {{'.join("}}, {{", $tables).'}} ';
        $q .= 'FROM {{'.join("}} INNER JOIN {{", $tables).'}} ';
        $q .= ' WHERE ';
        $base_table = array_shift($tables);
        foreach ($tables as $t) {
            $q .= ' {{'.$t.'}}.id = {{'.$base_table.'}}.id AND ';
        }
        $q .= ' {{'.$base_table.'}}.id = "'.fx::db()->escape($cond_val).'"';
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
    protected function  _compile_set_statement($props) {
        $res = array();
        foreach ($props as $p => $v){
            $res []= $p.' = '.$v;
        }
        return join(", ", $res);
    }
    
    public function insert($data) {
        if (!isset($data['type'])){
            throw  new Exception('Can not save essence with no type specified');
        }
        $set = $this->_set_statement($data);
        
        $tables = $this->get_tables();
        
        $base_table = array_shift($tables);
        $root_set = $set[$base_table];
        $q = "INSERT INTO `{{".$base_table."}}` ";
        if (!isset($data['priority'])) {
            $q .= ' ( `priority`, '.join(", ", array_keys($root_set)).') ';
            $q .= ' SELECT MAX(`priority`)+1, ';
            $q .= join(", ", $root_set);
            $q .= ' FROM {{'.$base_table.'}}';
        } else {
            $q .= "SET ".$this->_compile_set_statement($root_set);
        }
        
        $tables_inserted = array();
        
        $q_done = fx::db()->query($q);
        $id = fx::db()->insert_id();
        if ($q_done) {
            // remember, whatever table has inserted
            $tables_inserted []= $base_table;
        } else {
            return false;
        }
        
        foreach ($tables as $table) {
            
            $table_set = isset($set[$table]) ? $set[$table] : array();
            
            $table_set['`id`'] = "'".$id."'";
            $q = "INSERT INTO `{{".$table."}}` SET ".$this->_compile_set_statement($table_set); 
            
            $q_done = fx::db()->query($q);
            if ($q_done) {
                // remember, whatever table has inserted
                $tables_inserted []= $table;
            } else {
                // could not be deleted from all previous tables
                foreach ($tables_inserted as $tbl) {
                    fx::db()->query("DELETE FROM {{".$tbl."}} WHERE id  = '".$id."'");
                }
                // and return false
                return false;
            }
        }
        return $id;
    }
    
    protected function _set_statement($data) {
        $res = array();
        $chain = fx::data('component', $this->component_id)->get_chain();
        foreach ($chain as $level_component) {
            $table_res = array();
            $fields = $level_component->fields();
            $field_keywords = $fields->get_values('keyword');
            // while the underlying field content manually prescription
            if ($level_component['keyword'] == 'content') {
                $field_keywords = array_merge($field_keywords, array(
                    'priority', 
                    'checked',
                    'last_updated',
                    'type',
                    'infoblock_id',
                    'materialized_path',
                    'level'
                ));
            }
            $table_name = $level_component->get_content_table();
            $table_cols = $this->_get_columns($table_name);
            foreach ($field_keywords as $field_keyword) {
                if (!in_array($field_keyword, $table_cols)) {
                    continue;
                }
                
                $field = $fields->find_one('keyword', $field_keyword);
                // put only if the sql type of the field is not false (e.g. multilink)
                if ($field && !$field->get_sql_type()) {
                    continue;
                }
                
                //if (isset($data[$field_keyword]) ) {
                if (array_key_exists($field_keyword, $data)) {
                    $field_val = $data[$field_keyword];
                    $sql_val = is_null($field_val) ? 'NULL' : "'".fx::db()->escape($field_val)."'";
                    $table_res['`'.fx::db()->escape($field_keyword).'`'] = $sql_val;
                }
            }
            if (count($table_res) > 0) {
                $res[$table_name] = $table_res;
            }
        }
        return $res;
    }
    
    public function fake($props = array()) {
        $content = $this->create();
        $content->fake();
        $content->set($props);
        return $content;
    }
    
    public function create_adder_placeholder($collection = null) {
        $params = array();
        foreach ($this->where as $cond) {
            // original field
            $field = $cond[3]; 
            // collection was found by id, adder is impossible
            if ($field === 'id') {
                return;
            }
            if (!preg_match("~\.~", $field) && $cond[2] == '=' && is_scalar($cond[1])) {
                $params[$field] = $cond[1];
            }
        }
        if ($collection) {
            foreach ($collection->get_filters() as $coll_filter) {
                list($filter_field, $filter_value) = $coll_filter;
                if (is_scalar($filter_value)) {
                    $params[$filter_field] = $filter_value;
                }
            }
        }
        $placeholder = $this->create($params);
        $placeholder->dig_set('_meta.placeholder', $params + array('type' => $placeholder['type']));
        $placeholder->dig_set('_meta.placeholder_name', fx::data('component', $placeholder['type'])->get('item_name'));
        $placeholder->is_adder_placeholder(true);
        // guess item's position here
        if ($collection) {
            $collection[]= $placeholder;
        }
        return $placeholder;
    }
    
    protected function _livesearch_apply_terms($terms) {
        $table = $this->get_col_table('name');
        if ($table) {
            parent::_livesearch_apply_terms($terms);
            return;
        }
        
        $c_component = fx::data('component', $this->component_id);
        $components = $c_component->get_all_variants();
        $name_conds = array();
        foreach ($components as $com) {
            $name_field = $com->fields()->find_one('keyword', 'name');
            if (!$name_field) {
                continue;
            }
            $table = '{{'.$com->get_content_table().'}}';
            $this->join($table, $table.'.id = {{content}}.id', 'left');
            $cond = array(
                array(),
                false,
                'OR'
            );
            foreach ($terms as $term) {
                $cond[0][]= array(
                    $table.'.name', '%'.$term.'%', 'like'
                );
            }
            $name_conds []= $cond;
        }
        call_user_func_array(array($this, 'where_or'), $name_conds);
    }

    /**
     * Add filter to get subtree for one ore more parents
     * @param mixed $parent_ids
     * @param boolean $add_parents - include parents to subtree
     * @return fx_data_content
     */
    public function descendants_of($parent_ids, $include_parents = false) {
        if ($parent_ids instanceof System\Collection) {
            $non_content = $parent_ids->find(function($i) {
                return !($i instanceof Essence);
            });
            if (count($non_content) == 0) {
                $parents = $parent_ids;
                $parent_ids = $parents->get_values('id');
            }
        }
        if ($parent_ids instanceof Essence) {
            $parents = array($parent_ids);
            $parent_ids = array($parent_ids['id']);
        } elseif (!isset($parents)) {
            if (is_numeric($parent_ids)) {
                $parent_ids = array($parent_ids);
            }
            $parents = fx::data('content', $parent_ids);
        }
        $conds = array();
        foreach ($parents as $p) {
            $conds []= array('materialized_path', $p['materialized_path'].$p['id'].'.%', 'like');
        }
        if ($include_parents) {
            $conds []= array('id', $parent_ids, 'IN');
        }
        $this->where($conds, null, 'OR');
        return $this;
    }
}