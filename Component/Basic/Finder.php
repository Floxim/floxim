<?php

namespace Floxim\Floxim\Component\Basic;

use Floxim\Floxim\System\Collection;
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
            try {
                $type_tables = array_reverse(fx::data($type)->getTables());
            } catch (\Exception $e) {
                $data = $data->findRemove('type', $type);
                continue;
            }
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
    
    public function getType()
    {
        return $this->getComponent()->get('keyword');
    }

    public static function getTables() 
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])){
            return $cache[$class];
        }
        $tables = array();
        $com = static::getComponent();
        if (!$com) {
            fx::log('no com', $this);
            return [];
        }
        $chain = $com->getChain();
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
        $com = $this->getComponent();
        if (!$com) {
            fx::log('no com', $this);
            return [];
        }
        $fields = $com
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
        $com = static::getComponent();
        if (!$com) {
            $class = get_called_class();
            return fx::getComponentNameByClass($class);
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
        if (!isset($data['type'])) {
            $obj['type'] = $component['keyword'];
        }
        if (!isset($data['site_id'])) {
            $site = fx::env('site');
            if ($site) {
                $obj['site_id'] = $site['id'];
            }
        }
        $fields = $component->getAllFields()->find('default', '', Collection::FILTER_NEQ);
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
        
        if (
            isset($data['type']) && 
            ($component = fx::getComponentByKeyword($data['type'])) 
        ) {
            $component_id = $component->offsetGet('id');
        } else {
            $component = $this->getComponent();
            $component_id = $component->offsetGet('id');
            $data['type'] = $component['keyword'];
        }
        
        $classname = $this->getEntityClassName($data);
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
            throw new \Exception("Content can be killed only by id!");
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
        
        $priority_field = $com->getFieldByKeyword('priority', true);
        
        $base_table = array_shift($tables);
        $root_set = $set[$base_table];
        
        $q = "INSERT INTO `{{" . $base_table . "}}` ";
        
        $auto_priority = $priority_field && !isset($data['priority']);
        
        if ($auto_priority) {
            $root_set = array_reverse($root_set, true);
            $root_set['`priority`'] = 'SELECT MAX(`priority`) + 1';
            $root_set = array_reverse($root_set, true);
        }
        
        $q .= '( '.join(', ', array_keys($root_set)).' ) ';
        
        if (!$auto_priority) {
            $q .= 'VALUES (';
        }
        $q .= join(', ', $root_set);
        
        if ($auto_priority) {
            $q .= ' FROM {{'.$base_table.'}}';
        } else {
            $q .= ')';
        }
        
        $tables_inserted = array();

        $q_done = fx::db()->query($q);
        $id = fx::db()->insertId();
        
        if (!$q_done) {
            return false;
        }
        
        $tables_inserted [] = $base_table;

        foreach ($tables as $table) {

            $table_set = isset($set[$table]) ? $set[$table] : array();

            $table_set['`id`'] = "'" . $id . "'";
            
            $q = "INSERT INTO `{{".$table."}}` (".join(', ', array_keys($table_set)).') ';
            $q .= 'VALUES ('.join(', ', $table_set).')';
            
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
        $json_encoded = $this->getJsonEncodedFields();
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
                    if (is_array($field_val) && in_array($field_keyword, $json_encoded)) {
                        $field_val = json_encode($field_val);
                    }
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
    
    public function hasType($type)
    {
        if (!$this->getColTable('type')) {
            return $this;
        }
        $com = fx::component($type);
        $types = $com->getAllVariants()->getValues('keyword');
        $this->where('type', $types, 'in');
        return $this;
    }
    
    public function contentExists()
    {
        $base_table = current($this->getTables());
        $cnt = fx::db()->getVar('select count(*) from {{'.$base_table.'}}');
        return $cnt > 0;
    }
    
    protected function isCollectionInverted($collection)
    {
        $f = $collection->finder;
        if (!$f) {
            return false;
        }
        $order = $f->getOrder();
        if (!$order || !isset($order[0])) {
            return false;
        }
        $order = $order[0];
        if (!preg_match("~desc$~i", $order)) {
            return false;
        }
        $keywords = 'date|created';
        if (preg_match("~`".$keywords."~i", $order) || preg_match("~".$keywords."`~i", $order)) {
            return true;
        }
        return false;
    }
    
    public function removeAdderPlaceholder($collection)
    {
        $collection->findRemove(function($e) {
            if (!$e instanceof Entity) {
                return false;
            }
            return $e->isAdderPlaceholder();
        });
    }

    /**
     * 
     * @param \Floxim\Floxim\System\Collection $collection
     * @return null
     */
    public function createAdderPlaceholder($collection)
    {
        $params = array();
        if ($this->limit && $this->limit['count'] == 1 && count($collection) > 0) {
            return;
        }
        $replace_last = $this->limit && $this->limit['count'] === count($collection);
        
        $collection->findRemove(function($e)  {
            if (!$e instanceof Entity) {
                return false;
            }
            return $e->isAdderPlaceholder();
        });
        
        // collection has linker map, so it contains final many-many related data, 
        // and current finder can generate only linkers
        // @todo invent something to add-in-place many-many items
        if ($collection->linkers) {
            return $this->createLinkerAdderPlaceholder($collection);
        }
        
        // OH! My! God!
        $add_to_top = $this->isCollectionInverted($collection);
        
        $params = self::extractCollectionParams($collection);
        
        if (!$params) {
            return;
        }
        
        $param_variants = array();
        if (isset($params['parent_id']) && !isset($params['infoblock_id'])) {
            $avail_infoblocks = fx::data('infoblock')->whereContent($params['_component']);
            if (isset($params['parent_id'])) {
                $avail_infoblocks = $avail_infoblocks->getForPage($params['parent_id'][0]);
            } else {
                $avail_infoblocks = $avail_infoblocks->all();
            }
            if (count($avail_infoblocks)) {
                foreach ($avail_infoblocks as $c_ib) {
                    $param_variants []= array_merge(
                        $params,
                        array(
                            'infoblock_id' => array($c_ib['id'], Collection::FILTER_EQ),
                            '_component' => $c_ib['controller']
                        )
                    );
                }
            } else {
                $param_variants []= $params;
            }
        } else {
            $param_variants[]= $params;
        }
        
        foreach ($collection->getConcated() as $concated_coll) {
            if (!$concated_coll->finder) {
                continue;
            }
            $concated_params = self::extractCollectionParams($concated_coll);
            if (!$concated_params || count($concated_params) === 0) {
                continue;
            }
            if (!isset($concated_params['parent_id']) && isset($params['parent_id'])) {
                $concated_params['parent_id'] = $params['parent_id'];
            }
            $param_variants []= $concated_params;
        }
        $placeholder_variants = array();
        
        foreach ($param_variants as $c_params) {
            $com = fx::component($c_params['_component']);
            if (isset($c_params['infoblock_id']) && isset($c_params['parent_id'])) {
                $c_ib = fx::data('infoblock', $c_params['infoblock_id'][0]);
                if (!$c_ib || !$c_ib['is_preset']) {
                    $c_parent = fx::data('floxim.main.content', $c_params['parent_id'][0]);
                    $c_ib_avail = 
                            $c_ib && 
                            $c_parent && 
                            $c_ib->isAvailableOnPage($c_parent);

                    if (!$c_ib_avail) {
                        continue;
                    }
                }
            }
            $com_types = $com->getAllVariants();
            $avail_types = null;
            if (isset($c_params['type'])) {
                $avail_types = $com_types->find('keyword', $c_params['type'][0], $c_params['type'][1])->getValues('keyword');
            }
            
            foreach ($com_types as $com_type) {
                // skip abstract components like "publication", "contact" etc.
                if (
                    $com_type['is_abstract'] && 
                    (!isset($c_params['type']) || ($com_type['keyword'] !== $c_params['type'][0]) )
                ) {
                    continue;
                }
                $com_key = $com_type['keyword'];
                if ($avail_types && !in_array($com_key, $avail_types)) {
                    continue;
                }
                if (!isset($placeholder_variants[$com_key])) {
                    $placeholder_params = array();
                    foreach ($c_params as $c_prop => $c_vals) {
                        if ($c_vals[1] === Collection::FILTER_EQ) {
                            $placeholder_params[$c_prop] = $c_vals[0];
                        }
                    }
                    $placeholder = fx::data($com_key)->create($placeholder_params);
                    $placeholder_meta = array(
                        'placeholder' => $placeholder_params + array('type' => $com_key),
                        'placeholder_name' => $com_type->getItemName('add')
                    );
                    if ($add_to_top) {
                        $placeholder_meta['add_to_top'] = true;
                    }
                    
                    if ($replace_last) {
                        $placeholder_meta['replace_last'] = true;
                    }
                    
                    $placeholder['_meta'] = $placeholder_meta;
                    
                    $placeholder->isAdderPlaceholder(true);
                    $collection[] = $placeholder;
                    $placeholder_variants[$com_key] = $placeholder;
                }
            }
        }
    }
    
    public function createLinkerAdderPlaceholder($collection)
    {
        if (!isset($collection->linkers)) {
            return;
        }
        
        $linkers = $collection->linkers;
        
        $variants = $this->getComponent()->getAllVariants();
        
        $common_params = self::extractCollectionParams($linkers);
        
        
        $content_params = self::extractCollectionParams($collection, false);
        $strict_type = isset($content_params['type']) ? $content_params['type'] : null;
        
        foreach ($variants as $var_com) {
            if ($var_com['is_abstract']) {
                continue;
            }
            if ($strict_type && $var_com['keyword'] !== $strict_type) {
                continue;
            }
            
            $com_finder = fx::data($var_com['keyword']);
            $placeholder = $com_finder->create();
            
            // skip components like floxim.nav.external_link
            if (!$placeholder->isAvailableInSelectedBlock()) {
                continue;
            }
            
            $linker_params = array();
            foreach ($common_params as $params_prop => $params_val) {
                if (is_array($params_val)) {
                    if (
                        count($params_val) === 2 && $params_val[1] === \Floxim\Floxim\System\Collection::FILTER_EQ
                    ) {
                        $linker_params[$params_prop] = $params_val[0];
                    }
                } else {
                    $linker_params[$params_prop] = $params_val;
                }
            }
            
            $linker_params['type'] = $linker_params['_component'];
            unset($linker_params['_component']);
            $linker_params['_link_field'] = $linkers->linkedBy;
            $placeholder['_meta'] = array(
                'placeholder' => array('type' => $var_com['keyword']),
                'placeholder_name' => $var_com->getItemName('add'),
                'placeholder_linker' => $linker_params
            );
            $placeholder->isAdderPlaceholder(true);
            $collection[]= $placeholder;
            $linkers[]= fx::data('floxim.main.linker')->create();
        }
    }
    
    protected static $fake_variants = [];
    
    public function fake($props = array(), $level = 0)
    {
        $com_kw = $this->getComponent()['keyword'];
        if (is_null(self::$fake_variants[$com_kw])) {
            self::$fake_variants[$com_kw] = $this->getComponent()->getAllVariants()->find('is_abstract', 0)->getValues();
        }
        $com = fx::util()->circle(self::$fake_variants[$com_kw]);
        $finder = $com->getEntityFinder();
        $content = $finder->create();
        $content->fake($level);
        $content->set($props);
        return $content;
    }
    
    protected static function extractCollectionParams($collection, $skip_linkers = true)
    {
        $params = array();
        if ($collection->finder && $collection->finder instanceof Finder) {
            foreach ($collection->finder->where() as $cond) {
                
                if ($cond[2] === 'AND') {
                    $conds = $cond[0];
                    if (
                        count($conds) === 2 && 
                        $conds[0][0] === $conds[1][0] &&
                        $conds[1][2] === 'IS NOT NULL'
                    ) {
                        $cond = $conds[0];
                    }
                }
                
                
                // original field
                $field = isset($cond[3]) ? $cond[3] : null;
                // collection was found by id, adder is impossible
                if ($field === 'id') {
                    if ($skip_linkers) {
                        return false;
                    }
                    continue;
                }
                //fx::cdebug($cond, $field);
                if (!preg_match("~\.~", $field) && $cond[2] == '=' && is_scalar($cond[1])) {
                    $params[$field] = array($cond[1], Collection::FILTER_EQ);
                }
            }
            $params['_component'] = $collection->finder->getComponent()->get('keyword');
        }
        if ($collection->linkers && $skip_linkers) {
            return false;
        }
        foreach ($collection->getFilters() as $coll_filter) {
            list($filter_field, $filter_value, $operator) = $coll_filter;
            if (is_scalar($filter_value) || is_array($filter_value)) {
                $params[$filter_field] = array($filter_value, $operator);
            }
        }
        return  $params;
    }
    
    public function whereSamePriorityGroup($entity)
    {
        $fields = array('parent_id', 'infoblock_id');
        foreach ($fields as $f) {
            if (!$entity->hasField($f)) {
                continue;
            }
            $val = $entity[$f];
            if (!is_null($val)) {
                $this->where($f, $val);
            }
        }
        return $this;
    }
}