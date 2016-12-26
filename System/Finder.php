<?php

namespace Floxim\Floxim\System;

use Floxim\Form;

/**
 * Layer between the table and the object
 */
abstract class Finder
{

    protected $table;
    protected $pk = 'id';
    protected $order = array();
    
    protected $json_encode = array();

    protected $limit;
    protected $where = array();

    protected $with = array();
    protected $name_field = null;

    const BELONGS_TO = 0;
    const HAS_MANY = 1;
    const HAS_ONE = 2;
    const MANY_MANY = 3;
    
    public static function getInstance()
    {
        $class = get_called_class();
        $instance = new $class();
        return $instance;
    }
    
    public static function getRegistry() {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])) {
            return $cache[$class];
        }
        $keyword = static::getKeyword();
        $registry = fx::registry()->get($keyword);
        $cache[$class] = $registry;
        return $registry;
    }
    
    public static function getKeyword() {
        return static::getTable();
    }

    protected function livesearchApplyTerms($terms)
    {
        foreach ($terms as $tp) {
            $this->where('name', '%' . $tp . '%', 'LIKE');
        }
    }
    
    public function livesearchApplyConditions($conds) 
    {
        foreach ($conds as $cond_field => $cond_val) {
            if (is_numeric($cond_field) && is_array($cond_val)) {
                $op = isset($cond_val[2]) ? $cond_val[2] : '=';
                if (preg_match("~^[a-z_]+$~", $op)) {
                    $method = 'processCondition'. fx::util()->underscoreToCamel($op);
                    if (method_exists($this, $method)) {
                        $cond = $this->$method($cond_val[0], $cond_val[1]);
                        $this->where( $cond );
                        continue;
                    }
                }
                $this->where($cond_val[0], $cond_val[1], isset($cond_val[2]) ? $cond_val[2] : '=');
            } elseif (is_array($cond_val)) {
                $this->where($cond_field, $cond_val[0], $cond_val[1]);
            } else {
                $this->where($cond_field, $cond_val);
            }
        }
    }

    public function livesearch($term = '', $limit = null, $id_field = 'id')
    {
        if (!isset($term)) {
            return;
        }
        $term = trim($term);
        if (!empty($term)) {
            $terms = explode(" ", $term);
            $this->livesearchApplyTerms($terms);
        }
        if ($limit) {
            $this->limit($limit);
        }
        $this->calcFoundRows(true);
        $items = $this->all();
        if (!$items) {
            return;
        }
        $count = $this->getFoundRows();
        $res = array('meta' => array('total' => $count), 'results' => array());

        $props = array('name', 'id');
        if (isset($this->_livesearch_props) && is_array($this->_livesearch_props)) {
            $props = array_merge($props, $this->_livesearch_props);
        }
        
        foreach ($items as $i) {
            $c_res = array();
            foreach ($props as $prop) {
                $c_res[$prop] = $prop === 'id' ? $i[$id_field] : $i[$prop];
            }
            // @todo: use real prop keyword instead of aliased id
            if ($id_field !== 'id') {
                $c_res[$id_field] = $i[$id_field];
                $c_res['_real_id'] = $i['id'];
            }
            $c_res = $i->prepareForLivesearch($c_res, $term);
            if (!isset($c_res['name'])) {
                continue;
            }
            $res['results'][] = $c_res;
        }
        return $res;
    }

    public function relations()
    {
        return array();
    }

    public function getRelation($name)
    {
        $rels = $this->relations();
        return isset($rels[$name]) ? $rels[$name] : null;
    }

    public function getMultiLangFields()
    {
        return array();
    }
    
    public function getNonScalarFields()
    {
        $fields = $this->json_encode;
        $ml = $this->getMultiLangFields();
        
        $encoded_ml = array_intersect($fields, $ml);
        
        if (count($encoded_ml) > 0) {
            $langs = fx::data('lang')->all()->getValues('lang_code');
            foreach ($encoded_ml as $f) {
                foreach ($langs as $l) {
                    $fields[]= $f.'_'.$l;
                }
            }
        }
        return $fields;
    }
    
    public function getJsonEncodedFields()
    {
        return isset($this->json_encode) ? $this->json_encode : array();
    }
    
    /*
     * @return \Floxim\Floxim\System\Collection
     */
    public function all($limit = null)
    {
        if (!is_null($limit)) {
            $this->limit($limit);
        }
        if (static::isStaticCacheUsed() && static::$fullStaticCache && count($this->where) === 0 && count($this->order) === 0) {
            return static::getStaticCache();
        }
        $data = $this->getEntities();
        return $data;
    }

    public function one($id = null)
    {
        if (func_num_args() === 1) {
            return $this->getById($id);
        }
        $this->limit(1);
        $data = $this->getEntities();
        return isset($data[0]) ? $data[0] : false;
    }

    public function limit()
    {
        $args = func_get_args();
        if (count($args) === 1) {
            $limit = $args[0];
            $this->limit = is_null($limit) ? null : array(
                'offset' => 0,
                'count' => (int) $limit
            );
        } else {
            $this->limit = array(
                'offset' => (int) $args[0],
                'count' => (int) $args[1]
            );
        }
        return $this;
    }
    
    public function createPager($params = array())
    {
        $Pager = new Pager($this, $params);
        return $Pager;
    }
    
    public function page($page_num, $items_per_page = 100){
        $this->limit(
            $items_per_page * ($page_num - 1),
            $items_per_page
        );
        return $this;
    }
    
    public function selectFromRelated($rel, $field, $alias = null) {
        $cf = $this->prepareComplexField($rel.'.'.$field, 'left');
        $aliased = $cf;
        if ($alias) {
            $aliased .= ' as '.$alias;
        }
        $this->select($aliased);
        return $cf;
    }

    /**
     * For relational fields: join related item and prepare real field name
     * @param string $field
     */
    protected function prepareComplexField($field, $join_type = null)
    {
        if (is_null($join_type)) {
            $join_type = 'left';
        }
        list($rel, $field_name) = explode('.', $field, 2);
        if (preg_match("~^\{\{.+\}\}$~", $rel)) {
            return $field;
        }
        $relation = $this->getRelation($rel);
        if (!$relation) {
            return '`' . $rel . '`.`' . $field_name . '`';
        }
        //$with_type = $operator == 'where' ? 'inner' : 'left';
        $this->with($rel, null, $join_type);
        $c_with = $this->with[$rel];
        $with_name = $c_with[0];
        if ($relation[0] === self::MANY_MANY) {
            $with_finder = fx::data($relation[4]);
        } else {
            $with_finder = $c_with[1];
        }

        $table = $with_finder->getColTable($field_name, false);
        $field = $with_name . '__' . $table . '.' . $field_name;
        return $field;
    }

    protected function prepareCondition($field, $value = null, $type = '=', $join_type = null)
    {
        $num_args = func_num_args();
        if ($num_args === 2 && is_string($field) && $value === null) {
            $type = 'IS NULL';
        }
        
        if ( $num_args === 1 ) {
            if (is_array($field)) {
                $is_group = true;
                // if one of array items is scalar, this is not a group
                foreach ($field as $subfield) {
                    if (is_scalar($subfield)) {
                        $is_group = false;
                        break;
                    }
                }
                if (!$is_group) {
                    return call_user_func_array( array($this, 'prepareCondition'), $field);
                }
                $type = 'AND';
            } else {
                $type = 'RAW';
                if ( strtolower($field) === 'false' || $field === false) {
                    $value = '0';
                    $field = null;
                } elseif ( strtolower($field) === 'true' || $field === true ) {
                    $value = '1';
                    $field = null;
                }
            }
        }
        if ($type && is_string($type)) {
            $type = strtoupper($type);
        }
        if (is_array($field) ) {
            if ($type === 'AND' || $type === 'OR') {
                foreach ($field as $n => $c_cond) {
                    $passed_join_type = !is_null($join_type) ? $join_type : ($type === 'OR' ? 'LEFT' : 'INNER');
                    if (count($c_cond) === 3) {
                        $c_cond[3] = $passed_join_type;
                    }
                    $field[$n] = call_user_func_array( array($this, 'prepareCondition'), $c_cond );
                }
                return array($field, $value, $type);
            }
            if ($type === 'NOT') {
                $res = array(
                    call_user_func_array( array($this, 'prepareCondition'), $field ),
                    $value,
                    $type
                );
                return $res;
            }
        }
        $original_field = $field;
        $rels = $this->relations();
        if (isset($rels[$field])) {
            $field = $field.'.id';
        }
        if (strstr($field, '.')) {
            $field = $this->prepareComplexField($field, $join_type);
        } elseif (preg_match("~^[a-z0-9_-]~", $field)) {
            $table = $this->getColTable($field, false);
            if (in_array($field, $this->getMultiLangFields())) {
                $field = $field.'_'.fx::env()->getLang();
            }
            $field = '{{' . $table . '}}.' . $field;
            
        }
        if (is_array($value) && count($value) == 1 && ($type == '=' || $type == 'IN')) {
            $value = current($value);
            $type = '=';
        }
        return array($field, $value, $type, $original_field);
    }

    public function where($field = null, $value = null, $type = '=')
    {
        $num_args = func_num_args();
        if ($num_args === 0) {
            return $this->where;
        }
        
        $this->where []= call_user_func_array( array($this, 'prepareCondition'), func_get_args() );
        
        return $this;
    }
    
    public function whereIsNull($field) 
    {
        $this->where($field, false, 'is null');
        return $this;
    }
    
    public function whereIsNotNull($field) 
    {
        $this->where($field, false, 'is not null');
        return $this;
    }
    
    public function without($rel_name)
    {
        $rel = $this->getRelation($rel_name);
        switch ($rel[0]) {
            case self::BELONGS_TO:
                $rel_finder = fx::data($rel[1]);
                $rel_table = $rel_finder->getTable();
                $rel_alias = 'tbl__without_'.$rel_name;
                $linking_field = $rel[2];
                $our_table = $this->getColTable($linking_field);
                $this->join(
                    array($rel_table, $rel_alias),
                    $rel_alias.'.id = {{'.$our_table.'}}.'.$linking_field,
                    'left'
                );
                $this->where($rel_alias.'.id', null, 'is null');
                break;
            case self::HAS_MANY:
                $rel_finder = fx::data($rel[1]);
                $linking_field = $rel[2];
                $rel_table = $rel_finder->getColTable($linking_field);
                $our_table = $this->getTable();
                $rel_alias = 'tbl_without_'.$rel_name;
                $this->join(
                    array($rel_table, $rel_alias),
                    $rel_alias.'.'.$linking_field .' = {{'.$our_table.'}}.id',
                    'left'
                );
                $this->where($rel_alias.'.id', null, 'is null');
                break;
        }
        return $this;
    }

    public function whereOr()
    {
        $conditions = func_get_args();
        //$this->where [] = array($conditions, null, 'OR');
        $this->where(array($conditions, null, 'OR'));
        return $this;
    }

    public function clearWhere($field, $value = null)
    {
        foreach ($this->where as $where_num => $where_props) {
            if ($where_props[0] == $field) {
                if (func_num_args() == 1 || $value == $where_props[1]) {
                    unset($this->where[$where_num]);
                }
            }
        }
        return $this;
    }

    public function order($field, $direction = 'ASC')
    {
        // clear order by passing null
        if ($field === null) {
            $this->order = array();
            return $this;
        }
        if (is_string($this->order)) {
            $this->order = empty($this->order) ? array() : array($this->order);
        }
        if (!preg_match("~asc|desc~i", $direction)) {
            $direction = 'ASC';
        }
        if (strstr($field, '.')) {
            $this->order [] = $this->prepareComplexField($field, 'left') . ' ' . $direction;
        } else {
            $table = $this->getColTable($field);
            if ($table) {
                $this->order [] = "{{" . $table . "}}.`" . $field . "` " . $direction;
            } else {
                $this->order []= "`".$field."` ".$direction;
            }
        }
        return $this;
    }
    
    /**
     * shortcut for $finder->order($field, 'asc')
     * @param string $field
     * @return \Floxim\Floxim\System\Finder
     */
    public function asc($field)
    {
        $this->order($field, 'asc');
        return $this;
    }
    
    /**
     * shortcut for $finder->order($field, 'desc')
     * @param string $field
     * @return \Floxim\Floxim\System\Finder
     */
    public function desc($field)
    {
        $this->order($field, 'desc');
        return $this;
    }
    
    public function getOrder()
    {
        return $this->order;
    }

    public function with($relation, $finder = null, $only = false)
    {
        if (is_callable($finder) || is_null($finder)) {
            $rel = $this->getRelation($relation);
            $default_finder = $this->getDefaultRelationFinder($rel);
            if (is_callable($finder)) {
                call_user_func($finder, $default_finder);
            }
            $finder = $default_finder;
        }
        $with = array($relation, $finder, $only);
        $this->with [$relation] = $with;
        if ($only !== false) {
            $join_type = is_string($only) ? $only : 'inner';
            $this->joinWith($with, $join_type);
        }
        return $this;
    }

    public function onlyWith($relation, $finder = null)
    {
        $this->with($relation, $finder, true);
        return $this;
    }

    protected $calc_found_rows = false;

    public function calcFoundRows($on = true)
    {
        $this->calc_found_rows = (bool)$on;
    }

    public function getFoundRows()
    {
        return isset($this->found_rows) ? $this->found_rows : null;
    }

    protected $select = null;

    public function select($what)
    {
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
                $tables = $this->getTables();
                $arg = '{{' . $tables[0] . '}}.id';
            }
            $this->select [] = $arg;
        }
        return $this;
    }

    protected $group = array();

    public function group($by)
    {
        if (func_num_args() == 1 && is_null($by)) {
            $this->group = array();
            return $this;
        }
        $this->group [] = $by;
        return $this;
    }

    public function buildQuery()
    {
        // 1. To get tables-parents
        $tables = $this->getTables();
        if (is_null($this->select)) {
            foreach ($tables as $t) {
                $this->select [] = '{{' . $t . '}}.*';
            }
        }
        $base_table = array_shift($tables);
        $q = 'SELECT ';
        if ($this->calc_found_rows && fx::db()->getDriver() !== 'sqlite') {
            $q .= 'SQL_CALC_FOUND_ROWS ';
        }

        $q .= join(", ", $this->select);
        $q .= ' FROM `{{' . $base_table . "}}`\n";
        foreach ($tables as $t) {
            $q .= 'INNER JOIN `{{' . $t . '}}` ON `{{' . $t . '}}`.id = `{{' . $base_table . "}}`.id\n";
        }
        foreach ($this->joins as $join) {
            $q .= $join['type'] . ' ';
            $q .= 'JOIN ';
            $q .= $join['table'] . ' ON ' . $join['on'] . "\n ";
        }
        if (count($this->where) > 0) {
            $conds = array();
            foreach ($this->where as $cond) {
                $conds [] = $this->makeCond($cond, $base_table);
            }
            $q .= "\n WHERE " . join("\n AND ", $conds);
        }
        if (count($this->group) > 0) {
            $q .= "\n GROUP BY " . join(", ", $this->group);
        }
        if (is_string($this->order)) {
            $this->order = array($this->order);
        }
        if (is_array($this->order) && count($this->order) > 0) {
            $q .= "\n ORDER BY " . join(", ", $this->order);
        }
        if ($this->limit) {
            $q .= "\n LIMIT " . $this->limit['offset'].', '.$this->limit['count'];
        }
        return $q;
    }
    
    /**
     * parse complex field name, smth like 'parent[my.app.artist].styles.name[foo.bar]'
     * @param string $f 
     * @return array
     */
    public function parseFieldString($f)
    {
        $f = preg_replace_callback( 
            "~\[.+?\]~",
            function($m) {
                return str_replace(".", ':', $m[0]);
            },
            $f
        );

        $parts = explode(".", $f);

        $path = array();

        foreach ($parts as $p) {
                $subtype = null;
            $field = preg_replace_callback(
              "~\[(.+?)\]~",
              function($m) use (&$subtype) {
                  $subtype = str_replace(":", '.', $m[1]);
                  return '';
              },
              $p
            );
            $path []= array('field' => $field, 'subtype' => $subtype);
        }
        return $path;
    }
    
    public function getFieldTable($field)
    {
        $path = $this->parseFieldString($field);
    }

    protected $joins = array();

    public function join($table, $on, $type = 'inner')
    {
        if (is_array($table)) {
            $table = '{{' . $table[0] . '}} as ' . $table[1];
        }
        $this->joins[$table] = array(
            'table' => $table,
            'on'    => $on,
            'type'  => strtoupper($type)
        );
        return $this;
    }

    // inner join fx_content as user__fx_content on fx_content.user_id = user__fx_content.id
    // todo: psr0 need fix
    public function joinWith($with, $join_type = 'inner')
    {
        $rel_name = $with[0];
        $finder = $with[1];
        $rel = $this->getRelation($rel_name);
        $finder_tables = $finder->getTables();

        // column-link
        $link_col = $rel[2];
        
        $table = static::getTable();

        switch ($rel[0]) {
            case Finder::BELONGS_TO:
                $joined_table = array_shift($finder_tables);
                $joined_alias = $rel_name . '__' . $joined_table;
                // table of current finder containing the page, link
                $our_table = $this->getColTable($link_col, false);
                $this->join(
                    array($joined_table, $joined_alias),
                    $joined_alias . '.id = {{' . $our_table . '}}.' . $link_col,
                    $join_type
                );
                foreach ($finder_tables as $t) {
                    $alias = $rel_name . '__' . $t;
                    $this->join(
                        array($t, $alias),
                        $alias . '.id = ' . $joined_alias . '.id',
                        $join_type
                    );
                }
                break;
            case Finder::HAS_MANY:
                $their_table = $finder->getColTable($link_col, false);
                $joined_alias = $rel_name . '__' . $their_table;
                $their_table_key = array_keys($finder_tables, $their_table);
                unset($finder_tables[$their_table_key[0]]);
                $this->join(
                    array($their_table, $joined_alias),
                    $joined_alias . '.' . $link_col . ' = {{' . $table . '}}.id',
                    $join_type
                );
                $this->group('{{' . $table . '}}.id');
                foreach ($finder_tables as $t) {
                    $alias = $rel_name . '__' . $t;
                    $this->join(
                        array($t, $alias),
                        $alias . '.id = ' . $joined_alias . '.id',
                        $join_type
                    );
                }
                break;
            case Finder::MANY_MANY:
                $linker_table = $finder->getColTable($link_col, false);
                $joined_alias = $rel_name . '_linker__' . $linker_table;
                $linker_table_key = array_keys($finder_tables, $linker_table);
                unset($finder_tables[$linker_table_key[0]]);
                $this->join(
                    array($linker_table, $joined_alias),
                    $joined_alias . '.' . $link_col . ' = {{' . $table . '}}.id',
                    $join_type
                );
                $this->group('{{' . $table . '}}.id');
                foreach ($finder_tables as $t) {
                    $alias = $rel_name . '_linker__' . $t;
                    $this->join(
                        array($t, $alias),
                        $alias . '.id = ' . $joined_alias . '.id',
                        $join_type
                    );
                }
                $link_table_alias = $rel_name . '_linker__' . $finder->getColTable($rel[5], false);

                $end_finder = fx::data($rel[4]);
                $end_tables = $end_finder->getTables();
                $first_end_table = array_shift($end_tables);
                $first_end_alias = $rel_name . '__' . $first_end_table;
                $this->join(
                    array($first_end_table, $first_end_alias),
                    $first_end_alias . '.id = ' . $link_table_alias . '.' . $rel[5],
                    $join_type
                );
                foreach ($end_tables as $et) {
                    $et_alias = $rel_name . '__' . $et;
                    $this->join(
                        array($et, $et_alias),
                        $et_alias . '.id = ' . $first_end_alias . '.id',
                        $join_type
                    );
                }
                break;
        }
    }

    protected function makeCond($cond, $base_table)
    {
        $op = strtoupper($cond[2]);
        if ($op === 'NOT') {
            $cond_str = $this->makeCond($cond[0], $base_table);
            $res_str = ' NOT (' . $cond_str . ') ';
            return $res_str;
        }
        if ($op === 'OR' || $op === 'AND') {
            $parts = array();
            foreach ($cond[0] as $sub_cond) {
                if (!isset($sub_cond[2])) {
                    $sub_cond[2] = '=';
                }
                $parts [] = $this->makeCond($sub_cond, $base_table);
            }
            if (count($parts) == 0) {
                return ' 0';
            }
            return " (" . join(" ".$op." ", $parts) . ") ";
        }
        if (strtoupper($cond[2]) === 'RAW') {
            $field_name = $cond[0];
            if (!$field_name) {
                return $cond[1];
            }
            if (!$cond[1]) {
                return $field_name;
            }
            if (!preg_match("~^\`~", $field_name)) {
                $field_name = '`' . (join("`.`", explode(".", $field_name))) . '`';
            }
            return $field_name . ' ' . $cond[1];
        }
        list($field, $value, $type) = $cond;
        if ($field == 'id') {
            $field = "`{{" . $base_table . "}}`.id";
        }
        if ($value instanceof Collection) {
            $value = $value->column(function ($i) {
                return $i instanceof Entity ? $i['id'] : (int)$i;
            })->unique()->getData();
        }
        
        if ( in_array($type, array('IN', 'NOT IN') )) {
            if (is_scalar($value)) {
                $type = $type === 'IN' ? '=' : '!=';
            }
        }
        
        if (is_array($value)) {
            if (count($value) == 0) {
                return '0';
            }
            if ($type == '=') {
                $type = 'IN';
            }
            $vals = array_unique($value);
            foreach ($vals as &$c_val) {
                $c_val = fx::db()->escape($c_val);
            }
            $value = " ('" . join("', '",  $vals) . "') ";
        } elseif (in_array(strtolower($type), array('is null', 'is not null'))) {
            $value = '';
        } else {
            $value = "'" . fx::db()->escape($value). "'";
        }
        return $field . ' ' . $type . ' ' . $value;
    }

    public function showQuery()
    {
        return fx::db()->prepareQuery($this->buildQuery());
    }

    /*
    * Method collects flat data
    */
    public function getData()
    {
        $query = $this->buildQuery();
        $res = fx::db()->getResults($query);
        
        if ($this->calc_found_rows) {
            if (fx::db()->getDriver() === 'sqlite') {
                $counter = clone $this;
                $counter->select(null)->select('count(*)')->limit(null);
                $this->found_rows = fx::db()->getVar( $counter->buildQuery() );
            } else {
                $this->found_rows = fx::db()->getVar('SELECT FOUND_ROWS()');
            }
        }

        $objs = array();
        $non_scalar_fields = $this->getNonScalarFields();
        foreach ($res as $v) {
            // don't forget json decode
            foreach ($non_scalar_fields as $json_field_name) {
                if (isset($v[$json_field_name])) {
                    $v_decode = @json_decode($v[$json_field_name], true);
                    $v[$json_field_name] = $v_decode ? $v_decode : array();
                }
            }
            $objs[] = $v;
        }
        $collection = new Collection($objs);
        $collection->finder = $this;
        if (is_array($this->order)) {
            foreach ($this->order as $sorting) {
                if (preg_match("~priority~", $sorting)) {
                    $collection->is_sortable = true;
                    break;
                }
            }
        }
        return $collection;
    }

    /*
     * Method call $this->get_data(),
     * from the collection of the flat data collects entity
     */
    protected function getEntities()
    {
        $data = $this->getData();
        foreach ($data as $dk => $dv) {
            $data[$dk] = $this->entity($dv);
        }
        $this->addRelations($data);
        return $data;
    }

    public function getDefaultRelationFinder($rel)
    {
        $finder = fx::data($rel[1]);
        $finder->orderDefault();
        return $finder;
    }
    
    public function orderDefault()
    {
        
    }

    public function addRelated($rel_name, $entities, $rel_finder = null)
    {
        $relations = $this->relations();
        if (!isset($relations[$rel_name])) {
            return;
        }
        $rel = $relations[$rel_name];
        if (!isset($rel[3])) {
            $rel[3] = null;
        }
        list($rel_type, $rel_datatype, $rel_field, $rel_target_field) = $rel;
        if (!$rel_finder) {
            $rel_finder = $this->getDefaultRelationFinder($rel);
        }
        
        // e.g. $rel = array(fx_data::HAS_MANY, 'field', 'component_id');
        switch ($rel_type) {
            case self::BELONGS_TO:
                if (!$rel_target_field) {
                    $rel_target_field = 'id';
                }
                $rel_item_ids = array();
                foreach ($entities as $entity) {
                    $rel_id = $entity[$rel_field];
                    if ($rel_id) {
                        $rel_item_ids []= $rel_id;
                    }
                }
                if (count($rel_item_ids) > 0) {
                    $rel_items = $rel_finder->where($rel_target_field, $rel_item_ids)->all();
                    $entities->attach($rel_items, $rel_field, $rel_name, $rel_target_field);    
                }
                break;
            case self::HAS_MANY:
                $rel_items = $rel_finder->where($rel_field, $entities->getValues('id'))->all();
                $entities->attachMany($rel_items, $rel_field, $rel_name);
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
                    ->where($rel_field, $entities->getValues('id'));
                if ($end_rel_field) {
                    $rel_finder->where($end_rel_field, 0, '!=');
                }
                $rel_items = $rel_finder->all()->find($end_rel, null, '!=');
                $entities->attachMany($rel_items, $rel_field, $rel_name, 'id', $end_rel, $end_rel_field);
                break;
        }
    }

    /*
     * Method adds related-entity to the collection
     * uses $this->with & $this->relations
     */
    protected function addRelations(\Floxim\Floxim\System\Collection $entities)
    {
        if (count($this->with) == 0) {
            return;
        }
        if (count($entities) == 0) {
            return;
        }
        $relations = $this->relations();
        foreach ($this->with as $with) {
            list($rel_name, $rel_finder) = $with;
            if (!isset($relations[$rel_name])) {
                continue;
            }
            $this->addRelated($rel_name, $entities, $rel_finder);
        }
    }
    
    public static function getTable()
    {
        static $cache = array();
        $class = get_called_class();
        if (isset($cache[$class])) {
            return $cache[$class];
        }
        $table = fx::util()->camelToUnderscore(substr($class, 24, -7));
        $cache[$class] = $table;
        return $table;
    }
    /*
    public function getTable() {
        return $this->table;
    }
    */
    public function __construct()
    {
        /*
        if (!$this->table) {
            $this->table = static::getClassTable();
        }
         * 
         */
    }

    public static function getTables()
    {
        return array(static::getTable());
    }

    /**
     * Get name of the table wich contains specified $column
     * @param string $column Column name
     * @param bool $validate Check if the column really exists (for one-table models)
     * @return string Table name
     */
    public function getColTable($column, $validate = true)
    {
        $tables = $this->getTables();

        if (count($tables) == 1 && !$validate) {
            return $tables[0];
        }

        foreach ($tables as $t) {
            $cols = $this->getColumns($t);
            if (in_array($column, $cols)) {
                return $t;
            }
        }
        return null;
    }

    public function getPk()
    {
        return $this->pk;
    }

    /**
     *
     * @param type $id
     * @return \Floxim\Floxim\System\Entity
     */
    public function getById($id)
    {
        $stored = $this->getRegistry()->get($id);
        if ($stored) {
            return $stored;
        }
        return $this->where('id', $id)->one();
    }

    /**
     * Get the objects on the list id
     * @param type $ids
     * @return array
     */
    public function getByIds($ids)
    {
        return $this->where('id', $ids)->all();
    }

    /**
     * To create a new entity instance, to fill in default values
     * @param array $data
     * @return \Floxim\Floxim\System\Entity
     */
    public function create($data = array())
    {
        if ($data instanceof Form\Form) {
            $entity = $this->entity();
            $entity->loadFromForm($data);
        } else {
            $entity = $this->entity($data);
        }
        return $entity;
    }
    
    public function generate($data = array())
    {
        $entity = $this->create($data);
        $entity->is_generated = true;
        return $entity;
    }

    public $useStaticCache = true;

    public function setUseStaticCache($value)
    {
        $this->useStaticCache = (bool)$value;
    }

    /**
     * To initialize entity
     * @param array $data
     * @return \Floxim\Floxim\System\Entity
     */
    public function entity($data = array())
    {
        $registry = $this->getRegistry();
        $id = isset($data['id']) ? $data['id'] : null;
        if ( $id && ($obj = $registry->get($id)) ) {
            return $obj;
        }
        $classname = $this->getEntityClassName($data);
        
        $obj = new $classname($data);
        if ($id) {
            $registry->register($obj, $id);
        }
        return $obj;
    }
    
    public function registerEntity($obj, $id) {
        $this->getRegistry()->register($obj, $id);
    }

    public function insert($data)
    {
        $insert = $this->insertStatement($data);
        $id = null;
        if ($insert) {
            $query = "INSERT INTO `{{" . static::getTable() . "}}` (".join(', ', $insert['into']).") VALUES (".join(', ', $insert['values']).')';
            //fx::db()->query("INSERT INTO `{{" . static::getTable() . "}}` SET " . join(",", $set));
            fx::db()->query($query);
            $id = fx::db()->insertId();
        }
        return $id;
    }

    public function update($data, $where = array())
    {
        $wh = array();
        $update = $this->setStatement($data);

        foreach ($where as $k => $v) {
            $wh[] = "`" . fx::db()->escape($k) . "` = '" . fx::db()->escape($v) . "' ";
        }

        if ($update) {
            fx::db()->query(
                "UPDATE `{{" . static::getTable() . "}}` SET " . join(',', $update) . " " .
                ($wh ? "\n WHERE " . join(' AND ', $wh) : "") . " "
            );
        }
    }

    public function delete()
    {
        $argc = func_num_args();
        if ($argc === 0) {
            $this->all()->apply(function ($i) {
                $i->delete();
            });
            return;
        }

        $argv = func_get_args();

        $where = array();
        for ($i = 0; $i < $argc; $i = $i + 2) {
            $where[] = "`" . $argv[$i] . "` = '" . fx::db()->escape($argv[$i + 1]) . "'";
        }
        if ($where) {
            $where = "\n WHERE " . join(" AND ", $where);
        }

        fx::db()->getResults("DELETE FROM `{{" . static::getTable() . "}}`" . $where);
    }

    public function getParent($item)
    {
        $id = $item;
        if ($item instanceof Entity || is_array($item)) {
            $id = $item['parent_id'];
        }

        return $this->getById($id);
    }

    public function nextPriority()
    {
        return fx::db()->getVar("SELECT MAX(`priority`)+1 FROM `{{" . static::getTable() . "}}`");
    }

    /**
     * Get the name of the class to entity
     * @param array $data data entity'and
     * @return string
     */
    public function getEntityClassName()
    {
        return preg_replace("~[^\\\\]+$~", "Entity", get_class($this));
    }

    protected function getColumns($table = null)
    {
        if (!$table) {
            $table = static::getTable();
        }
        $schema = fx::schema($table);
        return $schema ? array_keys($schema) : null;
    }

    protected function setStatement($data)
    {

        $cols = $this->getColumns();

        $set = array();
        
        $encoded_fields = $this->getNonScalarFields();

        foreach ($data as $k => $v) {
            if (!in_array($k, $cols)) {
                continue;
            }
            if (in_array($k, $encoded_fields)) {
                $v = json_encode($v);
            }
            if ($v === null) {
                $str = 'NULL';
            } else {
                $str = "'" . fx::db()->escape($v) . "' ";
            }

            $set[] = "`" . fx::db()->escape($k) . "` = " . $str;
        }

        return $set;
    }
    
    protected function insertStatement($data)
    {

        $cols = $this->getColumns();

        $insert = array(
            'into' => array(),
            'values' => array()
        );
        
        $encoded_fields = $this->getNonScalarFields();

        foreach ($data as $k => $v) {
            if (!in_array($k, $cols)) {
                continue;
            }
            if (in_array($k, $encoded_fields)) {
                $v = json_encode($v);
            }
            if ($v === null) {
                $str = 'NULL';
            } else {
                $str = "'" . fx::db()->escape($v) . "' ";
            }
            
            $insert['into'] []= "`" . fx::db()->escape($k) . "`";
            $insert['values'] []= $str;
        }

        return $insert;
    }

    public static $isStaticCacheUsed = false;

    public static function isStaticCacheUsed()
    {
        return static::$isStaticCacheUsed;
    }

    public static $fullStaticCache = false;
    public static $storeStaticCache = false;

    protected static function getStaticCacheKey()
    {
        return 'data-meta-' . get_called_class();
    }

    public static function initStaticCache()
    {
        if (static::$fullStaticCache) {
            $class_name = get_called_class();
            return fx::cache('meta')->remember(
                static::getStaticCacheKey(),
                function () use ($class_name) {
                    $class_name::$isStaticCacheUsed = false;
                    $res = $class_name::loadFullDataForCache();
                    $class_name::$isStaticCacheUsed = true;
                    return $res;
                },
                //static::$storeStaticCache ? 60 * 60 : false
                static::$storeStaticCache ? -1 : false
            );
        }
        return new Collection();
    }

    public static function dropStoredStaticCache()
    {
        if (static::isStaticCacheUsed()) {
            fx::cache('meta')->delete(static::getStaticCacheKey());
        }
    }

    public static function loadFullDataForCache()
    {
        $finder = new static();
        static::prepareFullDataForCacheFinder($finder);
        $all = $finder->all();
        $res = array();
        foreach ($all as $item) {
            $res[$item['id']] = $item;
        }
        return fx::collection($res);
    }

    public static function prepareFullDataForCacheFinder($finder)
    {

    }

    public static $cache = array();
    public static function getStaticCache()
    {
        $key = static::getStaticCacheKey();
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = static::initStaticCache();
        }
        return self::$cache[$key];
    }
    
    public static function setStaticCache($data) {
        self::$cache[ static::getStaticCacheKey() ] = $data;
    }

    /**
     * Try to find item by id in static cache
     * @param int|string $id numeric id or string keyword
     */
    public static function getFromStaticCache($id)
    {
        $cache = static::getStaticCache();
        if (!$cache) {
            return false;
        }
        if (is_numeric($id) && ($item = $cache[$id])) {
            return $item;
        }

        if (($kf = static::getKeywordField())) {
            return $cache->findOne($kf, static::prepareSearchKeyword($id), Collection::FILTER_EQ);
        }
        return false;
    }

    public static function getKeywordField()
    {
        return false;
    }

    public static function prepareSearchKeyword($keyword)
    {
        return $keyword;
    }

    public static function getStaticCachedAll($ids)
    {

    }

    public function addToStaticCache($entity)
    {
        if (!static::isStaticCacheUsed()) {
            return;
        }
        if (isset($this) && !$this->useStaticCache) {
            return;
        }
        $cache = static::getStaticCache();
        $entity_id = $entity['id'];
        if (!$entity_id) {
            return;
        }
        if (!static::getFromStaticCache($entity_id)) {
            $cache[$entity_id] = $entity;
        }
    }
    
    public function getNameField()
    {
        return $this->getColTable('name') ? 'name' : false;
    }
    
    public function named($name)
    {
        $name_field = $this->getNameField();
        if ($name_field) {
            $this->where($name_field, '%' . $name . '%', 'like');
        } else {
            $this->where('false');
        }
        return $this;
    }
    
    public function normalizePriority() {
        $this->order(null)->order('priority')->select('id')->select('priority');
        $query = $this->buildQuery();
        $res = fx::db()->getResults($query);
        $n = 0;
        $table = $this->getColTable('priority');
        foreach ($res as $r) {
            $q = 'update {{'.$table.'}} set priority = '.$n.' where id = '.$r['id'];
            $n++;
            fx::db()->query($q);
        }
    }
    
    public function processCondition($cond) {
        $op_parts = explode(".", $cond['type']);
        $op = $op_parts[0];
        $op_type = isset($op_parts[1]) ? $op_parts[1] : 'value';
        
        if ( ($op === 'is_in' || $op === 'is_not_in') && $cond['real_field']) {
            $cond['field'] = $cond['real_field'];
        }
        
        if (isset($cond['field'])) {
            $field_parts = explode(".", $cond['field'], 2);
            $scope = $field_parts[0];
            $field = isset($field_parts[1]) ? $field_parts[1] : null;
            
            if (strstr($scope, ':')) {
                $scope_parts = explode(":", $scope, 2);
                $scope = $scope_parts[0];
                $field = $scope_parts[1].'.'.$field;
            }
            if ($scope === 'context') {
                $is_true = \Floxim\Floxim\Component\Scope\Entity::checkCondition($cond);
                $res = array(
                    null,
                    $is_true ? '1' : '0',
                    'RAW'
                );
                fx::cdebug($res);
                return $res;
            }
        }
        $value = $cond['value'];
        
        
        if ($op_type === 'context') {
            $value = preg_replace("~^context\.~", '', $value);
            $value = fx::env()->getContextProp($value);
        } elseif ($op_type === 'expression') {
            $value = fx::env()->getContextProp($value);
        }
        
        $res = null;

        switch ($op) {
            case 'group':
                $conds = array();
                foreach ($cond['values'] as $val) {
                    $conds []= self::processCondition($val);
                }
                $res = array(
                    $conds,
                    null,
                    $cond['logic']
                );
                break;
            case 'is_in':
                if ($field === null) {
                    $field = 'id';
                }
                if ($value instanceof \Floxim\Floxim\System\Entity) {
                    $value = $value['id'];
                }
                /*
                $res = array(
                    $field,
                    $value,
                    'IN'
                );
                */
                
                $res = array(
                    array(
                        array(
                            $field,
                            $value,
                            'IN'
                        ),
                        array(
                            $field,
                            null,
                            'IS NOT NULL'
                        )
                    ),
                    null,
                    'AND'
                );

                break;
            case 'is_true':
                $res = array(
                    $field,
                    1,
                    $value ? '=' : '!='
                );
                break;
            case 'contains':
                $res = array(
                    $field,
                    '%'.$value.'%',
                    'LIKE'
                );
                break;
            case 'defined':
                $res = array(
                    array(
                        array($field, null, 'IS NOT NULL'),
                        array($field, '', '!=')
                    ),
                    null,
                    'AND'
                );
                break;
            case 'less':
                $res = array(
                    $field,
                    $value,
                    '<'
                );
                break;
            case 'greater':
                $res = array(
                    $field,
                    $value,
                    '>'
                );
                break;
            case 'equals':
                $res = array(
                    $field,
                    $value,
                    '='
                );
                break;
            case 'has_type':
                $res = $this->conditionIs($value);
                break;
            case 'has':
                $res = array(
                    array(
                        array($field, '', $value ? '!=' : '='),
                        array($field, null, $value ? 'IS NOT NULL' : 'IS NULL'),
                    ),
                    null,
                    $value ? 'AND' : 'OR'
                );
                break;
            default:
                $method = 'processCondition'. fx::util()->underscoreToCamel($op);
                if (method_exists($this, $method)) {
                    $res = $this->$method($field, $value);
                }
                break;
        }
        if (isset($cond['inverted']) && $cond['inverted']) {
            $res = array($res, null, 'NOT');
        }
        return $res;
    }
    
    public function applyConditions($conds) {
        if ($conds['type'] === 'group' && $conds['logic'] === 'AND') {
            foreach ($conds['values'] as $cond) {
                $this->where( $this->processCondition($cond) );
            }
            return $this;
        }
        $this->where( $this->processCondition($conds));
        return $this;
    }
    
    public function conditionIs($type)
    {
        return array($type === self::getKeyword());
    }
}