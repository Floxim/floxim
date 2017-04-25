<?php

namespace Floxim\Floxim\Component\Scope;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\System\Entity {
    
    public function checkPath($path)
    {
        $conds = $this->getConditions();
        $res = self::checkCondition($conds, $path);
        return $res;
    }
    
    public function getScopePageId($path)
    {
        $ids = $path->getValues('id');
        $conds = $this->getConditions();
        if (!$conds) {
            return null;
        }
        if ($conds['type'] === 'group') {
            $conds = $conds['values'];
        } else {
            $conds = array($conds);
        }
        foreach ($conds as $c) {
            if ($c['field'] === 'entity' && (!isset($c['inverted']) || !$c['inverted']) ) {
                foreach ($ids as $id) {
                    if (in_array($id, $c['value'])) {
                        return $id;
                    }
                }
            }
        }
        return null;
    }
    
    public function getPageFinder()
    {
        $finder = fx::data('floxim.main.page');
        $finder->applyConditions($this->getConditions());
        return $finder;
    }
    
    public function getPageFinders()
    {
        $conds = $this->getConditions();
        $res = $this->groupCondsByType($conds);
        $finders = [];
        foreach ($res as $type => $conds) {
            
            $finder = fx::data($type);
            
            $conds = self::fixCondsForType($conds, $type);
            if (count($conds)) {
                $finder->applyConditions($conds);
            }
            $finders []= $finder;
        }
        return $finders;
    }
    
    public function checkScope($params = []) 
    {
        $finders = $this->getPageFinders();
        
        $pages = fx::collection();
        $has_current_page = false;
        $current_page = fx::env('page');
        
        $total = 0;
        
        $params = array_merge(
            [
                'limit' => 10
            ],
            $params
        );
        
        foreach ($finders as $finder) {
            if ($finder instanceof \Floxim\Main\Page\Finder) {
                $finder->where('site_id', fx::env('site_id'));
            }
            $limit = $params['limit'];
            if ($limit) {
                $finder->calcFoundRows();
                $finder->limit($limit);
            }
            $finder_res = $finder->all();
            $finder_total = $limit ? $finder->getFoundRows() : count($finder_res);
            $total += $finder_total;
            
            $pages = $pages->concat($finder_res);
            
            if ($current_page->isInstanceOf($finder->getType())) {
                $has_current_page = $finder_res->findOne('id', $current_page['id']);
                if (!$has_current_page && $limit && $finder_total > $limit) {
                    $has_current_page = (bool) $finder->where('id', $current_page['id'])->one();
                }
            }
        }
        
        $pages = $pages->getValues(function($p) {
            return array(
                'name' => $p['name'],
                'type' => $p['type'],
                'type_name' => $p->getComponent()->getItemName(),
                'url' => $p['url']
            );
        });
        
        return [
            'pages' => $pages,
            'total' => $total,
            'has_current_page' => $has_current_page
        ];
    }
    
    protected static function fixCondsForType($conds, $type)
    {
        if (count($conds) > 1) {
            $conds = [
                'type' => 'group',
                'logic' => 'OR',
                'values' => $conds
            ];
        } elseif (count($conds) === 1) {
            $conds = $conds[0];
        }
        $walk = function ($cond) use ($type, &$walk) {
            if ($cond['type'] === 'group') {
                $sub_res = [];
                foreach ($cond['values'] as $sub_cond) {
                    $sub_cond = $walk($sub_cond, $type);
                    if (count($sub_cond)) {
                        $sub_res []= $sub_cond;
                    }
                }
                if (count($sub_res) === 0) {
                    return [];
                }
                if (count($sub_res) === 1) {
                    return $sub_res[0];
                }
                $cond['values'] = $sub_res;
                return $cond;
            }
            if ($cond['type'] === 'has_type' && $cond['inverted'] === false) {
                $fixed_val = [];
                foreach ($cond['value'] as $cond_com) {
                    if ($cond_com === $type) {
                        continue;
                    }
                    $cond_com_chain = fx::component($cond_com)->getChain()->getValues('keyword');
                    if (!in_array($type, $cond_com_chain)) {
                        continue;
                    }
                    $fixed_val []= $cond_com;
                }
                if (count($fixed_val) === 0) {
                    return  [];
                }
                $cond['value'] = $fixed_val;
                return $cond;
            }
            $field = self::parseFieldFromCond($cond);
            if ($field['base'] === 'entity' && $field['type'] === $type) {
                $cond['field'] = 'entity.'.$field['field'];
            }
            return $cond;
        };
        $res = $walk($conds);
        return $res;
    }
    
    protected static function groupCondsByType($cond)
    {
        $res = [];
        if ($cond['type'] === 'group') {
            $sub_results = [];
            $sub_types = [];
            foreach ($cond['values'] as $sub) {
                $sub_res = self::groupCondsByType($sub);
                $sub_results []= $sub_res;
                if (count($sub_res) > 0) {
                    $sub_types []= array_keys($sub_res);
                }
            }
            
            $all_types = call_user_func_array('array_merge', $sub_types);
            $common_types = self::getCommonTypeRoots($all_types);
            
            if ($cond['logic'] === 'AND') {
                
                if (count($common_types) > 1 && count($sub_types) > 1) {
                    $valid_commons = [];
                    foreach ($common_types as $common_type) {
                        $chain = fx::component($common_type)->getChain()->getValues('keyword');
                        $is_valid = true;
                        foreach ($sub_types as $sub_type) {
                            if ( count(array_intersect($sub_type, $chain)) === 0) {
                                $is_valid = false;
                                break;
                            }
                        }
                        if ($is_valid) {
                            $valid_commons []= $common_type;
                        }
                    }
                    $common_types = $valid_commons;
                }
            }
            
            foreach ($common_types as $common_type) {
                $type_conds = [];
                $chain = fx::component($common_type)->getChain()->getValues("keyword");
                foreach ($sub_results as $sub_group) {
                    foreach ($sub_group as $sub_type_key => $sub_conds) {
                        if (in_array($sub_type_key, $chain)) {
                            $type_conds = array_merge($type_conds, $sub_conds);
                        }
                    }
                }
                if (count($type_conds) === 1 || $cond['logic'] === 'OR') {
                    $res[$common_type] = $type_conds;
                } else {
                    $res[$common_type] = [
                        [
                            'type' => 'group',
                            'logic' => 'AND',
                            'values' => $type_conds
                        ]
                    ];
                }
            }
        } else {
            $cond_types = self::getCondContentTypes($cond);
            $cond_roots = self::getCommonTypeRoots($cond_types);
            foreach ($cond_roots as $rtype) {
                $res[$rtype] = [$cond];
            }
        }
        return $res;
    }
    
    public static function getCommonTypeRoots($types)
    {
        if (count($types) < 2) {
            return $types;
        }
        $coms = fx::component()->find('keyword', $types);
        $res = [];
        $tree = [];
        foreach ($coms as $com) {
            $chain = $com->getChain()->getValues('keyword');
            $ctree =& $tree;
            foreach ($chain as $level) {
                if (!isset($ctree[$level])) {
                    $ctree[$level] = [];
                }
                $ctree =& $ctree[$level];
            }
        }
        $walk = function($arr) use (&$res, &$walk) {
            foreach ($arr as $k => $items) {
                if (count($items) === 1) {
                    $walk($items);
                } else {
                    $res []= $k;
                }
            }
        };
        $walk($tree);
        return $res;
    }
    
    public static function getGroupContentTypes($group)
    {
        
        if ($group['type'] !== 'group') {
            return self::getCondContentTypes($group);
        }
        $res = [];
        foreach ($group['values'] as $sub) {
            $types = self::getGroupContentTypes($sub);
            $res = array_merge($res, $types);
        }
        return $res;
    }
    
    public static function getCondContentTypes($cond)
    {
        if ($cond['type'] === 'has_type') {
            return $cond['value'];
        }
        $field = self::parseFieldFromCond($cond);
        return [empty($field['type']) ? 'floxim.main.page' : $field['type']];
        /*
        $field = $cond['field'];
        $parts = explode(".", $field);
        $base = explode(":", array_shift($parts));
        $base_type = array_shift($base);
        if (count($base) > 0) {
            return [join('.', $base)];
        }
        return $base_type === 'entity' ? ['floxim.main.page'] : [];
         * 
         */
    }
    
    protected static function parseFieldFromCond($cond)
    {
        $field = $cond['field'];
        $parts = explode(".", $field);
        $base = explode(":", array_shift($parts));
        $base_type = array_shift($base);
        return [
            'base' => $base_type,
            'type' => join('.', $base),
            'field' => $parts[0]
        ];
    }
    
    public static function checkCondition(&$cond, $path = null) 
    {
        if (is_null($path)) {
            $path = fx::env()->getPath();
        }
            
        if ($cond['type'] === 'group') {
            $res = self::checkGroup($cond, $path);
            $cond['res'] = $res;
            return $res;
        }
        
        $res = false;
        
        // @todo: handle expression / context values
        $value = isset($cond['value']) ? $cond['value'] : null;
        
        $field_path = explode(".", $cond['field']);
        $field_base = array_shift($field_path);
        $subtype = null;
        if (strstr($field_base, ':')) {
            $base_parts = explode(":", $field_base, 2);
            $field_base = $base_parts[0];
            $subtype = str_replace(":", '.', $base_parts[1]);
        }
        switch ($field_base) {
            case 'entity':
                $entity_base = $path->last();
                break;
            case 'context':
                $context_prop = array_shift($field_path);
                $entity_base = fx::env()->get($context_prop);
                
                break;
        }
        if ($subtype && !$entity_base->isInstanceOf($subtype)) {
            return isset($cond['inverted']) && $cond['inverted'] ? true : false;
        }
        $tested_value = $entity_base;
        $tested_entity = $entity_base;
        while (count($field_path) > 0) {
            $c_prop = array_shift($field_path);
            if (!isset($tested_value[$c_prop])) {
                break;
            }
            $tested_prop = $c_prop;
            $tested_entity = $tested_value;
            $tested_value = $tested_value[$c_prop];
        }
        
        if (preg_match("~\.context$~", $cond['type'])) {
            $tested_parts = explode(".", $value);
            if ($tested_parts[0] === 'context' && isset($tested_parts[1])) {
                $value = fx::env()->get($tested_parts[1]);
            }
        }
        
        switch ($cond['type']) {
            default:
                
                break;
            case 'is_true':
                // cast to number
                $res = $tested_value * 1 === $value * 1;
                break;
            case 'defined':
                $res = !empty($tested_value);
                break;
            case 'less': case 'greater':
                $tested_field = $tested_entity->getField($tested_prop);
                if ($tested_field['type'] === 'datetime') {
                    $tested_value = fx::timestamp($tested_value);
                    if (is_scalar($value)) {
                        $value = fx::timestamp($value);
                    }
                }
                $res = $cond['type'] === 'less' ? $tested_value < $value  : $tested_value > $value;
                break;
            case 'contains':
                if (!is_string($tested_value)) {
                    $res = false;
                } else {
                    $res = mb_stristr($tested_value, $value) !== false;
                }
                break;
            case 'equals':
                $res = $tested_value == $value;
                break;
            case 'is_under_or_equals':
                $ids = $path->getValues('id');
                $res = false;
                if (!is_array($value)) {
                    break;
                }
                foreach ($value as $c_id) {
                    if (in_array($c_id, $ids)) {
                        $res = true;
                        break;
                    }
                }
                break;
            case 'is_under':
                $ids = $path->getValues('id');
                $ids = array_slice($ids, null, -1);
                $res = false;
                foreach ($value as $c_id) {
                    if (in_array($c_id, $ids)) {
                        $res = true;
                        break;
                    }
                }
                break;
            case 'is_in.context':
            case 'is_in':
                if ( $tested_value instanceof \Floxim\Floxim\System\Entity ) {
                    $tested_value = $tested_value['id'];
                } 
                $res = in_array($tested_value, (array) $value);
                break;
            case 'has_type':
                $res = false;
                if ( ! ($tested_value instanceof \Floxim\Floxim\System\Entity) ) {
                    break;
                }
                foreach ($value as $c_com_keyword) {
                    $res = $tested_value->isInstanceOf( $c_com_keyword );
                    if ($res) {
                        break;
                    }
                }
                break;
        }
        if (isset($cond['inverted']) && $cond['inverted']) {
            $res = !$res;
        }
        $cond['res'] = $res;
        return $res;
    }
    
    protected static function checkGroup(&$cond, $path) 
    {
        $logic = $cond['logic'];
        $res = $logic === 'OR' ? false : true;
        foreach ($cond['values'] as &$subcond) {
            $sub_res = self::checkCondition($subcond, $path);
            if ($logic === 'OR' && $sub_res) {
                $res = true;
                break;
            } elseif ($logic === 'AND' && !$sub_res) {
                $res = false;
                break;
            }
        }
        if ( isset($cond['inverted']) && $cond['inverted']) {
            $res = !$res;
        }
        return $res;
    }
    
    protected $conditions = null;
    public function getConditions() 
    {
        if (is_null($this->conditions)) {
            $conds = $this['conditions'];
            if (!is_array($conds)) {
                $conds = $conds ? json_decode($conds, true) : false;
            }
            $this->conditions = $conds;
        }
        return $this->conditions;
    }
}