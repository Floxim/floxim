<?php

namespace Floxim\Floxim\Component\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Finder
{

    public function relations()
    {
        return array(
            'component' => array(
                self::BELONGS_TO,
                'component',
                'component_id'
            ),
            'parent_field' => array(
                self::BELONGS_TO,
                'field',
                'parent_field_id'
            ),
            'child_fields' => array(
                self::HAS_MANY,
                'field',
                'parent_field_id'
            ),
            'select_values' => array(
                self::HAS_MANY,
                'select_value',
                'field_id'
            )
        );
    }

    public function getMultiLangFields()
    {
        return array(
            'name',
            'description'
        );
    }

    public static $isStaticCacheUsed = true;

    public function __construct()
    {
        parent::__construct();
        $this->json_encode = array('format');
        $this->order = 'priority';
    }

    public function getByComponent($component_id)
    {
        return $this->where('component_id', $component_id)->all();
    }

    public function getEntityClassName($data = array())
    {
        $field_type = null;
        if (isset($data['parent_field_id']) && $data['parent_field_id']) {
            $parent_field = self::getInstance()->where('id', $data['parent_field_id'])->one();
            $field_type = $parent_field['type'];
        }
        if (!$field_type) {
            $field_type = isset($data['type']) ? $data['type'] : 'text';
        }
        
        $type = ucfirst($field_type);
        $class_name = '\\Floxim\\Floxim\\Field\\' . $type;
        return $class_name;
    }
    
    public static function dropStoredStaticCache() {
        fx::data('component')->dropStoredStaticCache();
        parent::dropStoredStaticCache();
    }
    
    public function getFieldImplementation($field_id, $params)
    {
        $params = array_merge(
            array(
                'component_id' => null,
                'infoblock_id' => null
            ),
            $params
        );
        $field = null;
        if ($field_id) {
            $field = $this->getInstance()->getById($field_id);
            if ($field) {
                $field = $field->getRootField()->getForContext($params['infoblock_id'], $params['component_id']);
            }
        }
        if (
            !$field || (
                $field['component_id'] != $params['component_id'] 
                || $field['infoblock_id'] != $params['infoblock_id']
            )
        ) {
            if (isset($params['group_id']) && empty($params['group_id'])) {
                $params['group_id'] = null;
            }
            $params['parent_field_id'] = $field['id'];
            $real_field = $this->create($params);
            return $real_field;
        }
        return $field;
    }
    
    public function moveAfter($what_id, $after_what_id, $params)
    {
        $prev_priority = 0;
        if ($after_what_id) {
            $prev_entity = $this->getInstance()->getById($after_what_id);
            if ($prev_entity) {
                $prev_priority = $prev_entity['priority'];
                fx::log('prev', $prev_priority, $prev_entity);
            }
        }
        $field_to_move = $this->getFieldImplementation($what_id, $params);
        $next_priority = $prev_priority + 1; // default for last item
        $next_entity = $this->where('priority', $prev_priority, '>')->one();
        if ($next_entity) {
            $next_priority = $next_entity['priority'];
            fx::log('next', $next_priority, $next_entity);
        }
        $field_to_move['priority'] = ($prev_priority + $next_priority) / 2;
        $field_to_move->save();
        fx::log($field_to_move, $what_id, $after_what_id, $params);
        return $field_to_move;
    }
}