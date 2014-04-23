<?php
class fx_field_multilink extends fx_field_baze {
    public function get_sql_type() {
        return false;
    }
    
    public function get_js_field($content) {
        parent::get_js_field($content);
        $render_type = $this['format']['render_type'];
        if ($render_type == 'livesearch') {
            $this->_js_field['type'] = 'livesearch';
            $this->_js_field['is_multiple'] = true;
            $this->_js_field['params'] = array(
                'content_type' => $this->get_end_data_type()
            );
            $rel = $this->get_relation();
            $related_relation = fx::data($rel[1])->relations();
            $linker_field = $related_relation[$rel[3]][2];
            
            $this->_js_field['name_postfix'] = $linker_field;
            if (isset($content[$this['name']])) {
                $this->_js_field['value'] = array();
                $linkers = $content[$this['name']]->linker_map;
                foreach ($content[$this['name']] as $num => $v) {
                    $this->_js_field['value'] []= array(
                        'id' => $v['id'], 
                        'name' => $v['name'], 
                        'value_id' => $linkers[$num]['id']
                    );
                }
            }
        } elseif ($render_type == 'table') {
            $rel = $this->get_relation();
            $essence = fx::data($rel[1])->create();
            $essence_fields = $essence->get_form_fields();
            $this->_js_field['tpl'] = array();
            $this->_js_field['labels'] = array();
            foreach ($essence_fields as $ef) {
                $this->_js_field['tpl'] []= $ef;
                $this->_js_field['labels'] []= $ef['label'];
            }
            $this->_js_field['values'] = array();
            if (isset($content[$this['name']])) {
                if ($rel[0] === fx_data::HAS_MANY) {
                    $linkers = $content[$this['name']];
                } else {
                    $linkers = $content[$this['name']]->linker_map;
                }
                foreach ($linkers as $linker) {
                    $linker_fields = $linker->get_form_fields();
                    $val_array = array('_index' => $linker['id']);
                    foreach ($linker_fields as $lf) {
                        $val_array [$lf['name']]= $lf['value'];
                    }
                    $this->_js_field['values'] []= $val_array;
                }
            }
            $this->_js_field['type'] = 'set';
        }
        return $this->_js_field;
    }
    
    public function format_settings() {
        $fields = array();
        
        if (!$this['component_id']) {
            return $fields;
        }
        
        $com = fx::data('component', $this['component_id']);
        $chain = new fx_collection($com->get_chain());
        $chain_ids = $chain->get_values('id');
        $link_fields = fx::data('field')
                        ->where('type', fx_field::FIELD_LINK)
                        ->where('component_id', 0, '!=')
                        ->all();
        
        // select from the available fields-links
        $linking_field_values = array();
        
        // array of InputB with specification of the data type
        $res_datatypes = array();
        
        // array of InputB with specification of the field for many-many
        $res_many_many_fields = array();
        
        // array of InputB with specification of the type for many-many
        $res_many_many_types = array();
        
        foreach ($link_fields as $lf) {
            if (in_array($lf['format']['target'], $chain_ids)) {
                // the component that owns the current box-link
                $linking_field_owner_component = fx::data('component', $lf['component_id']);
                
                $linking_field_values[]= array(
                    $lf['id'], 
                    $linking_field_owner_component['keyword'].'.'.$lf['name']
                );
                
                // get the list of references component and all of its descendants
                $component_tree = fx::data('component')->get_select_values($lf['component_id']);
                
                $res_datatypes[$lf['id']] = array();
                foreach ($component_tree as $com_variant) {
                    $linking_component = fx::data('component', $com_variant[0]);
                    $res_datatypes[$lf['id']] []= array(
                        $com_variant[0],
                        $com_variant[1]
                    );
                    
                    // For links many_many relations
                    // get the field-component links that point to other components
                    $linking_component_links = $linking_component->
                            all_fields()->
                            find('type', fx_field::FIELD_LINK)->
                            find('id', $lf['id'], '!=');
                    
                    // exclude fields, connected to the parent
                    if ($lf['format']['is_parent']){
                        $linking_component_links = $linking_component_links->find('name', 'parent_id', '!=');
                    }
                    if (count($linking_component_links) === 0) {
                        continue;
                    }
                    // key for many-many
                    $mmf_key = $lf['id'].'_'.$com_variant[0];
                    
                    $res_many_many_fields[$mmf_key] = array( array('', '--') );
                    foreach ($linking_component_links as $linking_component_link) {
                        $res_many_many_fields[$mmf_key] []= array(
                            $linking_component_link['id'],
                            $linking_component_link['name'],
                        );
                        
                        $target_component = fx::data(
                            'component', 
                            $linking_component_link['format']['target']
                        );
                        $end_tree = fx::data('component')->get_select_values($target_component['id']);
                        $mmt_key = $mmf_key.'|'.$linking_component_link['id'];
                        $res_many_many_types[$mmt_key] = array();
                        foreach ($end_tree as $end_com) {
                           $end_component = fx::data('component', $end_com[0]);
                           $res_many_many_types[$mmt_key] []= array(
                               $end_com[0],
                               $end_component['keyword']
                           );
                        }
                    }
                }
            }
        }
        
        $fields[] = array(
            'id' => 'format[linking_field]',
            'name' => 'format[linking_field]',
            'label' => 'Linking field',
            'type' => 'select',
            'values' => $linking_field_values,
            'value' => $this['format']['linking_field']
        );
        foreach ($res_datatypes as $rel_field_id => $linking_datatype) {
            $field_id = 'format[linking_field_'.$rel_field_id.'_datatype]';
            $fields[]= array(
                'id' => $field_id,
                'name' => $field_id,
                'type' => 'select',
                'label' => 'Linking datatype',
                'parent' => array('format[linking_field]' => $rel_field_id),
                'values' => $linking_datatype,
                'value' => $this['format']['linking_datatype']
            );
        }
        
        foreach ($res_many_many_fields as $res_mmf_key => $mm_fields) {
            list($check_field, $check_type) = explode("_", $res_mmf_key);
            $field_id = 'format[linking_mm_field_'.$res_mmf_key.']';
            $fields[]= array(
                'id' => $field_id,
                'name' => $field_id,
                'type' => 'select',
                'label' => 'Many-many field',
                'parent' => array(
                    'format[linking_field_'.$check_field.'_datatype]' => $check_type
                ),
                'values' => $mm_fields,
                'value' => $this['format']['mm_field']
            );
        }
        foreach ($res_many_many_types as $res_mmt_key => $mmt_fields) {
            list($check_mmf, $check_field) = explode("|", $res_mmt_key);
            $field_id = 'format[linking_mm_type_'.str_replace("|", "_", $res_mmt_key).']';
            $fields[]= array(
                'id' => $field_id,
                'name' => $field_id,
                'type' => 'select',
                'label' => 'Many-many datatype',
                'parent' => array(
                    'format[linking_mm_field_'.$check_mmf.']' => $check_field
                ),
                'values' => $mmt_fields,
                'value' => $this['format']['mm_datatype']
            );
        }
        $fields[]= array(
            'id' => 'format[render_type]',
            'name' => 'format[render_type]',
            'label' => fx::alang('Render type', 'system'),
            'type' => 'select',
            'values' => array(
                'livesearch' => fx::alang('Live search','system'),
                'table' => fx::alang('Fields table','system')
            ),
            'value' => $this['format']['render_type']
        );
        return $fields;
    }
    
    public function set_value($value) {
        parent::set_value($value);
    }
    
    protected function _before_save() {
        $c_lf = $this['format']['linking_field'];
        $format = array(
            'render_type' => $this['format']['render_type'],
            'linking_field' => $c_lf
        );
        $c_ldt = $this['format']['linking_field_'.$c_lf.'_datatype'];
        $format['linking_datatype'] = $c_ldt;
        $mm_field = $this['format']['linking_mm_field_'.$c_lf.'_'.$c_ldt];
        if ($mm_field) {
            $format['mm_field'] = $mm_field;
            $format['mm_datatype'] = $this['format']['linking_mm_type_'.$c_lf.'_'.$c_ldt.'_'.$mm_field];
        }
        $this['format'] = $format;
        parent::_before_save();
    }
    
    
    
    /*
     * Converts a value from a form to the collection
     * Seems, is confined only under many_many relations
     */
    public function get_savestring($content) {
        $rel = $this->get_relation();
        $is_mm = $rel[0] == fx_data::MANY_MANY;
        if ($is_mm) {
            return $this->_append_many_many($content);
        }
        return $this->_append_has_many($content);
    }
    
    /*
     * Process value of many-many relation field
     * such as post - tag_linker - tag
     */
    protected function _append_many_many($content) {
        // pull the previous value
        // to fill it
        $existing_items = $content->get($this['name']);
        $rel = $this->get_relation();
        // end type (for fields lot)
        $linked_data_type = $this->get_end_data_type();
        // binding type, which directly references
        $linker_data_type = $rel[1];
        // the name of the property, the linker where to target
        $linker_prop_name = $rel[3];
        // value to be returned
        $new_value = new fx_collection();
        $new_value->linker_map = new fx_collection();
        // Find the name for the field, for example "most part"
        // something strashnenko...
        $linker_com_name = preg_replace('~^content_~', '', $linker_data_type);
        $end_link_field_name = 
            fx::data('component', $linker_com_name)
            ->all_fields()
            ->find_one(function($i) use ($linker_prop_name) {
                //!!! some tin
                return isset($i['format']['prop_name']) && $i['format']['prop_name'] == $linker_prop_name;
            })
            ->get('name');
        $linker_infoblock_id = null;
        foreach ($this->value as $item_props) {
            $linked_props = $item_props[$end_link_field_name];
            // if the linked entity doesn't yet exist
            // we get it as an array of values including 'title'
            $linker_item = null;
            
            if (is_array($linked_props)) {
                if (!$linked_infoblock_id) {
                    $linked_infoblock_id = $content->get_link_field_infoblock($this['id']);
                }
                $linked_props['type'] = $linked_data_type;
                $linked_props['infoblock_id'] = $linked_infoblock_id;
            } elseif (isset($existing_items->linker_map)) {
                $linker_item = $existing_items->linker_map->find_one($end_link_field_name, $linked_props);
            }
            if (!$linker_item) {
                $linker_item = fx::data($linker_data_type)->create();
            }
            $linker_item->set_field_values(
                array($end_link_field_name => $linked_props), 
                array($end_link_field_name)
            );
            $new_value[]= $linker_item[$linker_prop_name];
            $new_value->linker_map []= $linker_item;
        }
        //fx::log($new_value);
        return $new_value;
    }
    
    /*
     * Process value of has-many relation field
     * such as news - comment
     */
    protected function _append_has_many($content) {
        // end type (for fields lot)
        $linked_type = 'content_'.$this->get_related_component()->get('keyword');
        $new_value = fx::collection();
        foreach ($this->value as $item_id => $item_props) {
            $linked_finder = fx::data($linked_type);
            $linked_item = null;
            if (is_numeric($item_id)) {
                $linked_item = $linked_finder->where('id', $item_id)->one();
            }
            if (!is_numeric($item_id)) {
                $linked_item = $linked_finder->create();
            }
            $linked_item->set_field_values($item_props);
            $new_value[]= $linked_item;
        }
        return $new_value;
    }
    
    public function get_end_data_type() {
        // the connection generated by the field
        $relation = $this->get_relation();
        if (isset($relation[4])) {
            return $relation[4];
        }
    }
    
    /*
     * Get the referenced component field
     */
    public function get_related_component() {
        $rel = $this->get_relation();
        switch ($rel[0]) {
            case fx_data::HAS_MANY:
                $content_type = $rel[1];
                break;
            case fx_data::MANY_MANY:
                $content_type = $rel[4];
                break;
        }
        return fx::data(
                'component', 
                preg_replace("~^content_~", '', $content_type)
        );
    }
    
    public function get_relation() {
        if (!$this['format']['linking_field']) {
            return false;
        }
        $direct_target_field = fx::data('field', $this['format']['linking_field']);
        $direct_target_component = fx::data('component', $this['format']['linking_datatype']);

        $first_type = $direct_target_component['keyword'];
        if ($first_type !== 'content') {
            $first_type = 'content_'.$first_type;
        }
        
        if (!$this['format']['mm_field']) {
            return array(
                fx_data::HAS_MANY,
                $first_type,
                $direct_target_field['name']
            );
        }
        
        $end_target_field = fx::data('field', $this['format']['mm_field']);
        $end_datatype = fx::data('component', $this['format']['mm_datatype']);
        
        $end_type = $end_datatype['keyword'];
        if ($end_type !== 'content') {
            $end_type = 'content_'.$end_type;
        }
        
        return array(
            fx_data::MANY_MANY,
            $first_type,
            $direct_target_field['name'],
            $end_target_field->get_prop_name(),
            $end_type,
            $end_target_field['name']
        );
    }
}