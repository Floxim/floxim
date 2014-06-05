<?php
class fx_data_content extends fx_data {
    
    public function relations() {
        $relations = array();
        $fields = fx::data('component', $this->component_id)->
                    all_fields()->
                    find('type', array(fx_field::FIELD_LINK, fx_field::FIELD_MULTILINK));
        foreach ($fields as $f) {
            if ( !($relation = $f->get_relation()) ) {
                continue;
            }
            switch ($f['type']) {
                case fx_field::FIELD_LINK:
                    $relations[$f->get_prop_name()] = $relation;
                    break;
                case fx_field::FIELD_MULTILINK:
                    $relations[$f['keyword']] = $relation;
                    break;
            }
        }
        return $relations;
    }
    
    protected function _get_default_relation_finder($rel) {
        $finder = parent::_get_default_relation_finder($rel);
        if ( ! $finder instanceof fx_data_lang ) {
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
            $type_tables = array_reverse(fx::data('content_'.$type)->get_tables());
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
            $extensions = fx::db()->get_results($q);
            foreach ($extensions as $ext) {
                foreach ($data as $data_index => $data_item) {
                    if ($data_item['id'] == $ext['id']) {
                        foreach ($ext as $ext_k => $ext_v) {
                            $data_item[$ext_k] = $ext_v;
                        }
                        $data[$data_index] = $data_item;
                    }
                }
            }
        }
        return $data;
    }
    
    public function get_tables() {
        $chain = fx::data('component', $this->component_id)->get_chain();
        $tables = array();
        foreach ($chain as $comp) {
            $tables []= $comp->get_content_table();
        }
        return $tables;
    }
    
    protected $component_id = null;
    
    public function __construct($table = null) {
        parent::__construct($table);
        $content_type = null;
        if (preg_match("~^fx_data_content_(.+)$~", get_class($this), $content_type)) {
            $this->set_component($content_type[1]);
        }
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
        $fields = $component->all_fields()->find('default', '', fx_collection::FILTER_NEQ);
        foreach ($fields as $f) {
            if (!isset($obj[$f['keyword']])) {
                if ($f['type'] == fx_field::FIELD_DATETIME) {
                    $obj[$f['keyword']] = date('Y-m-d H:i:s');
                } else {
                    $obj[$f['keyword']] = $f['default'];
                }
            }
        }
        if (!isset($obj['priority'])) {
            $obj['priority'] = $this->next_priority();
        }
        return $obj;
    }

    public function next_priority () {
        return fx::db()->get_var(
                "SELECT MAX(`priority`)+1 FROM `{{content}}`"
        );
    }
    
    protected static $content_classes = array();
    
    protected function get_class_name($data = null) {
        if ($data && isset($data['type'])) {
            if (isset(fx_data_content::$content_classes[$data['type']])) {
                return fx_data_content::$content_classes[$data['type']];
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
            $class_name = $c_level['keyword'] == 'content' ? 'fx_content' : 'fx_content_'.$c_level['keyword'];
            try {
                $exists = class_exists($class_name);
            } catch (Exception $e) {}
        }
        fx_data_content::$content_classes[$data['type']] = $class_name;
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
            $q = 'UPDATE `{{'.$table.'}}` SET '.join(', ', $props);
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
        $where = " WHERE `id` = '".fx::db()->escape($cond_val)."'";
        foreach ($this->get_tables() as $table) {
            $q = "DELETE FROM `{{".$table."}}` ".$where;
            fx::db()->query($q);
        }
    }
    
    public function insert($data) {
        if (!isset($data['type'])){
            throw  new Exception('Can not save essence with no type specified');
        }
        $set = $this->_set_statement($data);
        
        $tables = $this->get_tables();
        $root_set = $set['content'];
        $q = "INSERT INTO `{{content}}` SET ".join(", ", $root_set);
        $tables_inserted = array();
        
        $q_done = fx::db()->query($q);
        $id = fx::db()->insert_id();
        if ($q_done) {
            // remember, whatever table has inserted
            $tables_inserted []= 'content';
        } else {
            return false;
        }
        
        foreach ($tables as $table) {
            if ($table == 'content') {
                continue;
            }
            $table_set = isset($set[$table]) ? $set[$table] : array();
            $table_set[]= "`id` = '".$id."'";
            $q = "INSERT INTO `{{".$table."}}` SET ".join(", ", $table_set);
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
                if ($field && !$field->get_sql_type()) {
                    continue;
                }
                // put only if the sql type of the field is not false (e.g. multilink)
                if (isset($data[$field_keyword]) ) {
                    $table_res[]= "`".fx::db()->escape($field_keyword)."` = '".fx::db()->escape($data[$field_keyword])."' ";
                }
            }
            if (count($table_res) > 0) {
                $res[$table_name] = $table_res;
            }
        }
        return $res;
    }
    
    
    public function _ss($data, $table = null) {
        $ss = $this->_set_statement($data);
        return $ss;//[$table];
    }
    
    public function fake($props = array()) {
        $content = $this->create();
        $content->fake();
        $content->set($props);
        return $content;
    }
    
    protected function _quicksearch_apply_terms($terms) {
        $table = $this->get_col_table('name');
        if ($table) {
            parent::_quicksearch_apply_terms($terms);
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
        if (is_numeric($parent_ids)) {
            $parent_ids = array($parent_ids);
        }
        $parents = fx::data('content', $parent_ids);
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