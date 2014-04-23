<?php

class fx_data_component extends fx_data {

    public function relations() {
        return array(
            'fields' => array(
                self::HAS_MANY,
                'field',
                'component_id'
            ),
            'children' => array(
                self::HAS_MANY,
                'component',
                'parent_id'
            )
        );
    }

    public function get_multi_lang_fields() {
        return array(
            'name',
            'description',
            'item_name',
        );
    }

    public function __construct() {
        $this->order = '`group`, `id`';
        parent::__construct();
    }

    public function get_all_groups() {
        $result = array();
        $groups = fx::db()->get_col("SELECT DISTINCT `group` FROM `{{component}}` ORDER BY `group`");
        if ($groups)
                foreach ($groups as $v) {
                $result[$v] = $v;
            }

        return $result;
    }

    public function get_all_store_ids() {
        $result = fx::db()->get_col("SELECT `store_id` FROM `{{component}}` WHERE `store_id` IS NOT NULL");
        if (!$result) $result = array();

        return $result;
    }
    
    public function get_by_id($id) {
        if (!is_numeric($id)) {
            $this->where('keyword', $id);
        } else {
            $this->where('id', $id);
        }
        return $this->one();
    }
    
    public function get_by_keyword($keyword) {
    	return $this->get('keyword', $keyword);
    }
    
    public function get_select_values($com_id = null) {
        $items = $this->all();
        $recursive_get = function($comp_coll, $result = array(), $level = 0) 
                            use (&$recursive_get, $items) {
            if (count($comp_coll) == 0) {
                return $result;
            }
            foreach ($comp_coll as $comp) {
                $result[] = array($comp['id'], str_repeat(" - ", $level).$comp['name']);
                $result = $recursive_get($items->find('parent_id', $comp['id']), $result, $level+1);
            }
            return $result;
        };
        if ($com_id ) {
            if (!is_numeric($com_id)) {
                $root = $items->find('keyword', $com_id);
            } else {
                $root = $items->find('id', $com_id); 
            }
        } else {
            $root = $items->find('parent_id', 0);
        }
        $res = $recursive_get($root);
        return $res;
    }
    
    public function get_tree() {
        $items = $this->all();
        return $items->make_tree('parent_id', 'children');
    }
}