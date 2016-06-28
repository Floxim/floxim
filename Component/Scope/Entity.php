<?php

namespace Floxim\Floxim\Component\Scope;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\System\Entity {
    
    
    /*
    public function beforeSave() {
        parent::beforeSave();
        if ($this->isModified('conditions')) {
            $ibs = $this['infoblocks'];
            if (count($ibs) > 0) {
                $root_page_id = $this->getRootPage()->get('id');
                foreach ($ibs as $ib) {
                    $ib['page_id'] = $root_page_id;
                }
            }
        }
    }
    
    public function getRootPage()
    {
        $finder = fx::data('floxim.main.page');
        $finder->applyConditions( $this->getConditions() );
        $finder->order('level', 'desc');
        $finder->group('level');
        $finder->select('level');
        $finder->select('count(*) as cnt');
        $finder->select('id');
        $finder->limit(1);
        $data = $finder->getData();
        fx::cdebug($finder->showQuery(), $data, $this['infoblocks']);
    }
    */
    
    public function checkPath($path)
    {
        $conds = $this->getConditions();
        $res = $this->checkCondition($conds, $path);
        //fx::cdebug($res, $conds);
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
    
    public function checkCondition(&$cond, $path) 
    {
        if ($cond['type'] === 'group') {
            $res = $this->checkGroup($cond, $path);
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
            default:
                fx::cdebug('field base', $field_base);
                return false;
        }
        if ($subtype && !$entity_base->isInstanceOf($subtype)) {
            return $cond['inverted'] ? true : false;
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
        
        switch ($cond['type']) {
            default:
                
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
            case 'is_in':
                if ( $tested_value instanceof \Floxim\Floxim\System\Entity ) {
                    $tested_value = $tested_value['id'];
                } 
                $res = in_array($tested_value, $value);
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
        if ($cond['inverted']) {
            $res = !$res;
        }
        $cond['res'] = $res;
        return $res;
    }
    
    protected function checkGroup(&$cond, $path) 
    {
        $logic = $cond['logic'];
        $res = $logic === 'OR' ? false : true;
        foreach ($cond['values'] as &$subcond) {
            $sub_res = $this->checkCondition($subcond, $path);
            if ($logic === 'OR' && $sub_res) {
                $res = true;
                break;
            } elseif ($logic === 'AND' && !$sub_res) {
                $res = false;
                break;
            }
        }
        if ($cond['inverted']) {
            $res = !$res;
        }
        return $res;
    }
    
    protected $conditions = null;
    public function getConditions() 
    {
        if (is_null($this->conditions)) {
            $conds = $this['conditions'];
            $this->conditions = $conds ? json_decode($conds, true) : false;
        }
        return $this->conditions;
    }
}