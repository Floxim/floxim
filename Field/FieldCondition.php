<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class FieldCondition extends \Floxim\Floxim\Component\Field\Entity 
{
    public function getSqlType()
    {
        return "TEXT";
    }
    
    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        
        $res['type'] = 'condition';

        return $res;
    }
    
    public static function getTestedValue($field, $params = array())
    {
        
    }
    
    public static function check($cond, $params = array())
    {
        if (is_string($cond)) {
            $cond = json_decode($cond, true);
        }
        
        $path = isset($params['path']) ? $params['path'] : fx::env()->getPath();
        
        if (!isset($params['getters'])) {
            $params['getters'] = array();
        }
        
        if ($cond['type'] === 'group') {
            $res = self::checkGroup($cond, $params);
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
        
        $entity_base = null;
        
        if (isset($params['getters'][$field_base])) {
            $tested_value = $params['getters'][$field_base]($field_path);
        } else {
        
            switch ($field_base) {
                case 'entity':
                    $entity_base = $path->last();
                    break;
                case 'context':
                    $context_prop = array_shift($field_path);
                    $entity_base = fx::env()->get($context_prop);
                    break;
            }
               
            if ($subtype && $entity_base &&  !$entity_base->isInstanceOf($subtype)) {
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
                if ($tested_entity) {
                    $tested_field = $tested_entity->getField($tested_prop);
                    if ($tested_field['type'] === 'datetime') {
                        $tested_value = fx::timestamp($tested_value);
                        if (is_scalar($value)) {
                            $value = fx::timestamp($value);
                        }
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
            $sub_res = self::check($subcond, $path);
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
}