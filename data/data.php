<?php

/**
 * Layer between the table and the object
 */
class fx_data {

    protected $table;
    protected $pk = 'id';
    protected $order = array();
    //protected $classname;
    protected $serialized = array();
    protected $sql_function = array();
    
    protected $limit;
    protected $where = array();
    
    protected $with = array();
    
    const BELONGS_TO = 0;
    const HAS_MANY = 1;
    const HAS_ONE = 2;
    const MANY_MANY = 3;

    public function quicksearch($term = null) {
        if (!isset($term)) {
            return;
        }
        $terms = explode(" ", $term);
        if (count($terms)>0) {
            foreach ($terms as $tp) {
                $this->where('name', '%'.$tp.'%', 'LIKE');
            }
        }
        $items = $this->all();
        $res = array('meta' => array(), 'results' => array());
        foreach ($items as $i) {
            $res['results'][]= array(
                'name' => $i['name'],
                'id' => $i['id']
            );
        }
        return $res;
    }

    public function relations() {
        return array();
    }
    
    public function get_relation($name) {
        $rels = $this->relations();
        return isset($rels[$name]) ? $rels[$name] : null;
    }

    public function get_multi_lang_fields() {
        return array();
    }

    /*
     * @return fx_collection
     */
    public function all() {
        $data = $this->_get_essences();
        return $data;
    }

    public function one() {
        $this->limit = 1;
        $data = $this->_get_essences();
        return isset($data[0]) ? $data[0] : false;
    }
    
    public function limit() {
        $args = func_get_args();
        if (count($args) == 1) {
            $this->limit = $args[0];
        } elseif (count($args) == 2) {
            $this->limit = $args[0].', '.$args[1];
        }
        return $this;
    }
    
    protected function _prepare_condition($field, $value, $type) {
        if (is_array($field)) {
            foreach ($field as $n => $c_cond) {
                $field[$n] = $this->_prepare_condition($c_cond[0], $c_cond[1], $c_cond[2]);
            }
            return array($field, $value, $type);
        }
        if (strstr($field, '.')) {
            list($rel, $field_name) = explode('.', $field, 2);
            if (!isset($this->with[$rel])) {
                $this->only_with($rel);
            } 
            $c_with = $this->with[$rel];
            if (!$c_with[2]) {
                $this->with[$rel][2] = true;
                $this->_join_with($c_with);
            }
            
            $with_name = $c_with[0];
            $with_finder = $c_with[1];
            $relation = $this->get_relation($rel);
            if ($relation[0] != fx_data::MANY_MANY) {
                $with_finder->where($field_name, $value, $type);
            }
            $table = $with_finder->get_col_table($field_name);
            $field = $with_name.'__'.$table.'.'.$field_name;
        } elseif (preg_match("~^[a-z0-9_-]~", $field)) {
            $table = $this->get_col_table($field);
            $field = '{{'.$table.'}}.'.$field;
        }
        return array($field, $value, $type);
    }
    
    public function where($field, $value, $type = '=') {
        $cond = $this->_prepare_condition($field, $value, $type);
        $this->where []= $cond; //array($field, $value, $type);
        return $this;
    }
    
    public function where_or() {
        $conditions = func_get_args();
        $this->where []= array($conditions, null, 'OR');
        return $this;
    }
    
    public function clear_where($field, $value = null) {
        foreach ($this->where as $where_num => $where_props) {
            if ($where_props[0] == $field) {
                if (func_num_args() == 1 || $value == $where_props[1]) {
                    unset($this->where[$where_num]);
                }
            }
        }
        return $this;
    }
    
    public function order($field, $direction = 'ASC') {
        // clear order by passing null
        if ($field === null) {
            $this->order = array();
            return $this;
        }
        if (!preg_match("~asc|desc~i", $direction)) {
            $direction = 'ASC';
        }
        $this->order []= "`".$field."` ".$direction;
        return $this;
    }
        
    public function with($relation, $finder = null, $only = false) {
        if ( is_callable($finder) || is_null($finder) ) {
            $rel = $this->get_relation($relation);
            $default_finder = $this->_get_default_relation_finder($rel);
            if (is_callable($finder)) {
                call_user_func($finder, $default_finder);
            }
            $finder = $default_finder;
        }
        $with = array($relation, $finder, $only);
        $this->with [$relation]= $with;
        if ($only) {
            $this->_join_with($with);
        }
        return $this;
    }
    
    public function only_with($relation, $finder = null) {
        $this->with($relation, $finder, true);
        return $this;
    }

    protected $calc_found_rows = false;
    public function calc_found_rows($on = true) {
        $this->calc_found_rows = (bool) $on;
    }
    
    public function get_found_rows() {
        return isset($this->found_rows) ? $this->found_rows : null;
    }
    
    protected $select = null;
    
    public function select($what) {
        // reset: $finder->select(null)
        if (func_num_args() == 1 && is_null($what)) {
            $this->select = null;
            return $this;
        }
        if (is_null($this->select)) {
            $this->select = array();
        }
        foreach (func_get_args() as $arg) {
            if ($arg === 'id') {
                $tables = $this->get_tables();
                $arg = '{{'.$tables[0].'}}.id';
            }
            $this->select []= $arg;
        }
        return $this;
    }
    
    protected $group = array();
    public function group($by) {
        if (func_num_args() == 1 && is_null($by)) {
            $this->group = array();
            return $this;
        }
        $this->group []= $by;
        return $this;
    }
    
    public function build_query() {
        // 1. To get tables-parents
        $tables = $this->get_tables();
        if (is_null($this->select)) {
            foreach ($tables as $t) {
                $this->select []= '{{'.$t.'}}.*';
            }
        }
        $base_table = array_shift($tables);
        $q = 'SELECT ';
        if ($this->calc_found_rows) {
            $q .= 'SQL_CALC_FOUND_ROWS ';
        }
        
        $q .= join(", ", $this->select);
        $q .= ' FROM `{{'.$base_table."}}`\n";
        foreach ($tables as $t) {
            $q .= 'INNER JOIN `{{'.$t.'}}` ON `{{'.$t.'}}`.id = `{{'.$base_table."}}`.id\n";
        }
        foreach ($this->joins as $join) {
            $q .= $join['type'].' ';
            $q .= 'JOIN ';
            $q .= $join['table'].' ON '.$join['on'].' ';
        }
        if (count($this->where) > 0) {
            $conds = array();
            foreach ($this->where as $cond) {
                $conds []= $this->_make_cond($cond, $base_table);
            }
            $q .= "WHERE ".join(" AND ", $conds);
        }
        if (count($this->group) > 0) {
            $q .= " GROUP BY ".join(", ", $this->group);
        }
        if (is_string($this->order)) {
            $this->order = array($this->order);
        }
        if (is_array($this->order) && count($this->order) > 0) {
            $q .= " ORDER BY ".join(", ", $this->order);
        }
        if ($this->limit){
            $q .= ' LIMIT '.$this->limit;
        }
        //fx::debug(fx::db()->prepare_query($q));
        return $q;
    }
    
    protected $joins = array();
    
    public function join($table, $on, $type = 'inner') {
        if (is_array($table)) {
            $table = '{{'.$table[0].'}} as '.$table[1];
        }
        $this->joins[]= array(
            'table' => $table, 
            'on' => $on, 
            'type' => strtoupper($type)
        );
        return $this;
    }
    
    // inner join fx_content as user__fx_content on fx_content.user_id = user__fx_content.id
    protected function _join_with($with) {
        $rel_name = $with[0]; 
        $finder = $with[1];
        $rel = $this->get_relation($rel_name);
        $finder_tables = $finder->get_tables();
        
        // column-link
        $link_col = $rel[2];
        
        switch ($rel[0]) {
            case fx_data::BELONGS_TO:
                //fx::debug('bel to', $rel);
                $joined_table = array_shift($finder_tables);
                $joined_alias = $rel_name.'__'.$joined_table;
                // table of current finder containing the page, link
                $our_table = $this->get_col_table($link_col);
                $this->join(
                    array($joined_table, $joined_alias),
                    $joined_alias.'.id = {{'.$our_table.'}}.'.$link_col
                );
                foreach ($finder_tables as $t) {
                    $alias = $rel_name.'__'.$t;
                    $this->join(
                        array($t, $alias),
                        $alias.'.id = '.$joined_alias.'.id'
                    );
                }
                break;
            case fx_data::HAS_MANY:
                $their_table = $finder->get_col_table($link_col);
                $joined_alias = $rel_name.'__'.$their_table;
                $their_table_key = array_keys($finder_tables, $their_table);
                unset($finder_tables[$their_table_key[0]]);
                $this->join(
                    array($their_table, $joined_alias),
                    $joined_alias.'.'.$link_col.' = {{'.$this->table.'}}.id'
                );
                $this->group('{{'.$this->table.'}}.id');
                foreach ($finder_tables as $t) {
                    $alias = $rel_name.'__'.$t;
                    $this->join(
                        array($t, $alias),
                        $alias.'.id = '.$joined_alias.'.id'
                    );
                }
                break;
            case fx_data::MANY_MANY:
                $linker_table = $finder->get_col_table($link_col);
                $joined_alias = $rel_name.'_linker__'.$linker_table;
                $linker_table_key = array_keys($finder_tables, $linker_table);
                unset($finder_tables[$linker_table_key[0]]);
                $this->join(
                    array($linker_table, $joined_alias),
                    $joined_alias.'.'.$link_col.' = {{'.$this->table.'}}.id'
                );
                $this->group('{{'.$this->table.'}}.id');
                foreach ($finder_tables as $t) {
                    $alias = $rel_name.'_linker__'.$t;
                    $this->join(
                        array($t, $alias),
                        $alias.'.id = '.$joined_alias.'.id'
                    );
                }
                $link_table_alias = $rel_name.'_linker__'.$finder->get_col_table($rel[5]);
                
                $end_finder = fx::data($rel[4]);
                $end_tables = $end_finder->get_tables();
                $first_end_table = array_shift($end_tables);
                $first_end_alias = $rel_name.'__'.$first_end_table;
                $this->join(
                    array($first_end_table, $first_end_alias),
                    $first_end_alias.'.id = '.$link_table_alias.'.'.$rel[5]
                );
                foreach ($end_tables as $et) {
                    $et_alias = $rel_name.'__'.$et;
                    $this->join(
                        array($et, $et_alias),
                        $et_alias.'.id = '.$first_end_alias.'.id'
                    );
                }
                //fx::debug($link_table);
                //fx::debug($rel);
                break;
        }
    }
    
    protected function _make_cond($cond, $base_table) {
        if (strtoupper($cond[2]) === 'OR') {
            $parts = array();
            foreach ($cond[0] as $sub_cond) {
                if (!isset($sub_cond[2])) {
                    $sub_cond[2] = '=';
                }
                $parts []= $this->_make_cond($sub_cond, $base_table);
            }
            return " (".join(" OR ", $parts).") ";
        }
        if (strtoupper($cond[2]) === 'RAW') {
            return '`'.$cond[0].'` '.$cond[1];
        }
        list($field, $value, $type) = $cond;
        if ($field == 'id') {
            $field = "`{{".$base_table."}}`.id";
        } else {
            // use conditions like "MD5(`field`)" as is
            if (!preg_match("~[a-z0-9_-]\s*\(.*?\)~i", $field)) {
                //$field = '`'.$field.'`';
            }
        }
        if ($value instanceof fx_collection) {
            $value = $value->column(function($i) {
                return $i instanceof fx_essence ? $i['id'] : (int) $i;
            })->unique()->get_data();
        }
        if (is_array($value)) {
            if (count($value) == 0) {
                return 'FALSE';
            }
            if ($type == '=') {
                $type = 'IN';
            }
            $value = " ('".join("', '", $value)."') ";
        } elseif (in_array(strtolower($type), array('is null', 'is not null'))) {
            $value = '';
        } else {
            $value = "'".$value."'";
        }
        return $field.' '.$type.' '.$value;
    }
    
    public function show_query() {
        return fx::db()->prepare_query($this->build_query());
    }
    
     /*
     * Method collects flat data
     */
    public function get_data() {
        $query = $this->build_query();
        $res = fx::db()->get_results($query);

        if (fx::db()->is_error()) {
            throw new Exception("SQL ERROR");
        }
        
        if ($this->calc_found_rows) {
            $this->found_rows = fx::db()->get_var('SELECT FOUND_ROWS()');
        }

        $objs = array();
        foreach ($res as $v) {
            // don't forget serialized
            foreach ($this->serialized as $serialized_field_name) {
                if (isset($v[$serialized_field_name])) {
                    $v[$serialized_field_name] = unserialize($v[$serialized_field_name]);
                }
            }
            $objs[] = $v;
        }
        $collection = new fx_collection($objs);
        if (is_array($this->order)) {
            $sorting = strtolower(trim(join("", $this->order)));
            $sorting = str_replace("asc", '', $sorting);
            $sorting = str_replace("`", '', $sorting);
            $sorting = trim($sorting);
            $collection->is_sortable = $sorting == 'priority';
        }
        return $collection;
    }
    
    /*
     * Method call $this->get_data(),
     * from the collection of the flat data collects essence
     */
    protected function _get_essences() {
        $data = $this->get_data();
        foreach ($data as $dk => $dv) {
            $data[$dk] = $this->essence($dv);
        }
        $this->_add_relations($data);
        return $data;
    }
    
    protected function _get_default_relation_finder($rel) {
        return fx::data($rel[1]);
    }
    
    public function add_related($rel_name, $essences, $rel_finder = null) {
        //echo fx_debug('adding rel', 'bt5', $rel_name, $essences, $rel_finder);
        $relations = $this->relations();
        if (!isset($relations[$rel_name])) {
            return;
        }
        $rel = $relations[$rel_name];
        list($rel_type, $rel_datatype, $rel_field) = $rel;

        if (!$rel_finder){
            $rel_finder = $this->_get_default_relation_finder($rel);
        }

        // e.g. $rel = array(fx_data::HAS_MANY, 'field', 'component_id');
        switch ($rel_type) {
            case self::BELONGS_TO:
                $rel_items = $rel_finder->where('id', $essences->get_values($rel_field))->all();
                $essences->attache($rel_items, $rel_field, $rel_name);
                break;
            case self::HAS_MANY:
                //echo fx_debug('has manu', $rel_finder);
                $rel_items = $rel_finder->where($rel_field, $essences->get_values('id'))->all();
                $essences->attache_many($rel_items, $rel_field, $rel_name);
                break;
            case self::HAS_ONE:
                break;
            case self::MANY_MANY:
                $end_rel = $rel[3];
                // removed to related entities
                // only with a non-empty field in which relate
                $end_rel_data = $rel_finder->relations();
                $end_rel_field = $end_rel_data[$end_rel][2];
                
                // $rel[4] is the data type for many-many
                $end_finder = null;
                if (isset($rel[4])) {
                    $end_rel_datatype = $rel[4];
                    $end_finder = fx::data($end_rel_datatype);
                }
                
                
                $rel_finder
                        ->with($end_rel, $end_finder)
                        ->where($rel_field, $essences->get_values('id'));
                if ($end_rel_field) {
                    $rel_finder->where($end_rel_field, 0, '!=');
                }
                $rel_items = $rel_finder->all()->find($end_rel, null, '!=');
                $essences->attache_many($rel_items, $rel_field, $rel_name, 'id', $end_rel);
                break;
        }
    }
    
    /*
     * Method adds related-entity to the collection
     * uses $this->with & $this->relations
     */
    protected function _add_relations(fx_collection $essences) {
        if (count($this->with) == 0) {
            return;
        }
        if (count($essences) == 0) {
            return;
        }
        $relations = $this->relations();
        foreach ($this->with as $with) {
            list($rel_name, $rel_finder) = $with;
            if (!isset($relations[$rel_name])) {
                continue;
            }
            $this->add_related($rel_name, $essences, $rel_finder);
        }
    }
    

    /**
     * @todo NEXT to understand that you can kill
     */
///////////////////////////
    
    static public function optional($table) {
        return new self($table);
    }

    public function __construct($table = null) {
        if (!$table) {
            $table = str_replace('fx_data_', '', get_class($this));
        }
        $this->table = $table;
    }
    
    public function get_tables() {
        return array($this->table);
    }
    
    public function get_col_table($col) {
        $tables = $this->get_tables();
        foreach ($tables as $t) {
            $cols = $this->_get_columns($t);
            if (in_array($col, $cols)) {
                return $t;
            }
        }
        return null;
    }

    public function get_pk() {
        return $this->pk;
    }

    /**
     *
     * @param type $id
     * @return fx_essence
     */
    public function get_by_id($id) {
        return $this->where('id', $id)->one();
    }
    
    /**
     * Get the objects on the list id
     * @param type $ids
     * @return array
     */
    public function get_by_ids($ids) {
        return $this->where('id', $ids)->all();
    }
    
    /**
     * To create a new essence instance, to fill in default values
     * @param array $data
     * @return fx_essence
     */
    public function create($data = array()) {
        return $this->essence($data);
    }
    
    /**
     * To initialize essence
     * @param type $data
     * @return fx_essence
     */
    public function essence($data = array()) {
        $classname = $this->get_class_name($data);
        $obj = new $classname(array('data' => $data));
        if ($classname == 'fx_simplerow') {
            $obj->table = $this->table;
        }
        return $obj;
    }

    public function insert($data) {
        $set = $this->_set_statement($data);
        if ($set) {
            fx::db()->query("INSERT INTO `{{".$this->table."}}` SET ".join(",", $set));
            $id = fx::db()->insert_id();
        }

        return $id;
    }

    public function update($data, $where = array()) {
        $wh = array();
        $update = $this->_set_statement($data);
        
        foreach ($where as $k => $v) {
            $wh[] = "`".fx::db()->escape($k)."` = '".fx::db()->escape($v)."' ";
        }
        
        if ($update) {
            fx::db()->query(
                "UPDATE `{{".$this->table."}}` SET ".join(',', $update)." ".
                        ( $wh ? " WHERE ".join(' AND ', $wh) : "")." "
            );
        }
    }

    public function delete() {
        $argc = func_num_args();
        if ($argc === 0) {
            $this->all()->apply(function($i) {
                $i->delete();
            });
            return;
        }
        
        $argv = func_get_args();

        $where = array();
        for ($i = 0; $i < $argc; $i = $i + 2) {
            $where[] = "`".$argv[$i]."` = '".fx::db()->escape($argv[$i + 1])."'";
        }
        if ($where) {
            $where = " WHERE ".join(" AND ", $where);
        }

        fx::db()->get_results("DELETE FROM `{{".$this->table."}}`".$where);
    }

    public function get_parent($item) {
        $id = $item;
        if ($item instanceof fx_essence || is_array($item)) {
            $id = $item['parent_id'];
        }

        return $this->get_by_id($id);
    }

    public function next_priority() {
        return fx::db()->get_var("SELECT MAX(`priority`)+1 FROM `{{".$this->table."}}`");
    }

    /**
     * Get the name of the class to essence
     * @param array $data data essence'and
     * @return string
     */
    protected function get_class_name($data = array()) {
        $classname = 'fx_'.str_replace('fx_data_', '', get_class($this));
        try {
            if (class_exists($classname)) {
                return $classname;
            }
        } catch (Exception $e) {}
        return 'fx_simplerow';
    }
    
    protected function _get_columns($table = null) {
        if (!$table) {
            $table = $this->table;
        }
        $cache_key = 'table_columns_'.$table;
        if ( ($columns = fx::cache($cache_key)) ) {
            return $columns;
        }
        $columns = fx::db()->get_col('SHOW COLUMNS FROM {{'.$table.'}}', 0);
        fx::cache($cache_key, $columns);
        return $columns;
    }

    protected function _set_statement($data) {
        
        $cols = $this->_get_columns();
        
        $set = array();

        foreach ($data as $k => $v) {
            if (!in_array($k, $cols)) {
                continue;
            }
            if (in_array($k, $this->serialized) && is_array($v)) {
                $v = serialize($v);
            }
            $str = "'".fx::db()->escape($v)."' ";
            if (isset($this->sql_function[$k])) {
                $str = $this->sql_function[$k]."(".$str.")";
            }

            $set[] = "`".fx::db()->escape($k)."` = ".$str;
        }

        return $set;
    }
}
