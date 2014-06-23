<?php

class fx_data_field extends fx_data {
    
    public function relations() {
        return array(
            'component' => array(
                self::BELONGS_TO, 
                'component', 
                'component_id'
            )
        );
    }
    
    public function get_multi_lang_fields() {
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

    public function get_by_component($component_id) {
        return $this->where('component_id', $component_id)->all();
    }

    public function get_class_name($data = array()) {
        $class_name  = parent::get_class_name($data);
        if (isset($data['type'])) {
            $class_name .= '_'.fx_field::get_type_by_id($data['type']);
        }
        return $class_name;
    }
}