<?php

namespace Floxim\Floxim\Component\Field;

use Floxim\Floxim\System;

class Finder extends System\Data {
    
    public function relations() {
        return array(
            'component' => array(
                self::BELONGS_TO, 
                'component', 
                'component_id'
            )
        );
    }
    
    public function getMultiLangFields() {
        return array(
            'name',
            'description'
        );
    }

    public function __construct() {
        parent::__construct();
        $this->serialized = array('format', 'parent');
        $this->order = 'priority';
    }

    public function getByComponent($component_id) {
        return $this->where('component_id', $component_id)->all();
    }

    public function getClassName($data = array()) {
        //$class_name  = parent::get_class_name($data);
        if (isset($data['type'])) {
            // todo: psr0 need verify
            $type = Entity::getTypeById($data['type']);
            $type = ucfirst($type);
            $class_name = '\\Floxim\\Floxim\\Field\\'.$type;
            //$class_name .= '_'.Entity::get_type_by_id($data['type']);
        }
        return $class_name;
    }
}