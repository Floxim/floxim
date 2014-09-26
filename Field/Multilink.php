<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\Component\Field;
use Floxim\Floxim\System\Fx as fx;

class Multilink extends Baze {
    public function getSqlType() {
        return false;
    }
    
    public function getJsField($content) {
        parent::getJsField($content);
        $render_type = $this['format']['render_type'];
        if ($render_type == 'livesearch') {
            $this->_js_field['type'] = 'livesearch';
            $this->_js_field['is_multiple'] = true;
            $this->_js_field['params'] = array(
                'content_type' => $this->getEndDataType()
            );
            $rel = $this->getRelation();
            $related_relation = fx::data($rel[1])->relations();
            $linker_field = $related_relation[$rel[3]][2];
            
            $this->_js_field['name_postfix'] = $linker_field;
            if (isset($content[$this['keyword']])) {
                $this->_js_field['value'] = array();
                $linkers = $content[$this['keyword']]->linker_map;
                foreach ($content[$this['keyword']] as $num => $v) {
                    $this->_js_field['value'] []= array(
                        'id' => $v['id'], 
                        'name' => $v['name'], 
                        'value_id' => $linkers[$num]['id']
                    );
                }
            }
        } elseif ($render_type == 'table') {
            $rel = $this->getRelation();
            $entity = fx::data($rel[1])->create();
            $entity_fields = $entity->getFormFields();
            $this->_js_field['tpl'] = array();
            $this->_js_field['labels'] = array();
            
            foreach ($entity_fields as $ef) {
                if ($ef['name'] == $rel[2]) {
                    continue;
                }
                $this->_js_field['tpl'] []= $ef;
                $this->_js_field['labels'] []= $ef['label'];
            }
            $this->_js_field['values'] = array();
            if (isset($content[$this['keyword']])) {
                if ($rel[0] === System\Data::HAS_MANY) {
                    $linkers = $content[$this['keyword']];
                } else {
                    $linkers = $content[$this['keyword']]->linker_map;
                }
                foreach ($linkers as $linker) {
                    $linker_fields = $linker->getFormFields();
                    $val_array = array('_index' => $linker['id']);
                    foreach ($linker_fields as $lf) {
                        // skip the relation field
                        if ($lf['name'] == $rel[2]) {
                            continue;
                        }
                        // form field has "name" prop instead of "keyword"
                        $val_array [$lf['name']]= $lf['value'];
                    }
                    $this->_js_field['values'] []= $val_array;
                }
            }
            $this->_js_field['type'] = 'set';
        }
        return $this->_js_field;
    }
    
    public function formatSettings() {
        $fields = array();
        
        if (!$this['component_id']) {
            return $fields;
        }
        
        $com = fx::data('component', $this['component_id']);
        $chain = new System\Collection($com->getChain());
        $chain_ids = $chain->getValues('id');
        $link_fields = fx::data('field')
                        ->where('type', Field\Entity::FIELD_LINK)
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
                    $linking_field_owner_component['keyword'].'.'.$lf['keyword']
                );
                
                // get the list of references component and all of its descendants
                $component_tree = fx::data('component')->getSelectValues($lf['component_id']);
                
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
                            allFields()->
                            find('type', Field\Entity::FIELD_LINK)->
                            find('id', $lf['id'], '!=');
                    
                    // exclude fields, connected to the parent
                    if ($lf['format']['is_parent']){
                        $linking_component_links = $linking_component_links->find('keyword', 'parent_id', '!=');
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
                            $linking_component_link['keyword'],
                        );
                        
                        $target_component = fx::data(
                            'component', 
                            $linking_component_link['format']['target']
                        );
                        $end_tree = fx::data('component')->getSelectValues($target_component['id']);
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
            'label' => fx::alang('Linking field'),
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
                'label' => fx::alang('Linked datatype'),
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
    
    public function setValue($value) {
        parent::setValue($value);
    }
    
    protected function beforeSave() {
        if ($this->isModified('format') || !$this['id']) {
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
        }
        parent::beforeSave();
    }
    
    
    
    /*
     * Converts a value from a form to the collection
     * Seems, is confined only under many_many relations
     */
    public function getSavestring($content) {
        $rel = $this->getRelation();
        $is_mm = $rel[0] == System\Data::MANY_MANY;
        if ($is_mm) {
            $res = $this->appendManyMany($content);
        } else {
            $res = $this->appendHasMany($content);
        }
        return $res;
    }
    
    /*
     * Process value of many-many relation field
     * such as post - tag_linker - tag
     */
    protected function appendManyMany($content) {
        // pull the previous value
        // to fill it
        $existing_items = $content->get($this['keyword']);
        $rel = $this->getRelation();
        // end type (for fields lot)
        $linked_data_type = $this->getEndDataType();
        // binding type, which directly references
        $linker_data_type = $rel[1];
        // the name of the property, the linker where to target
        $linker_prop_name = $rel[3];
        // value to be returned
        $new_value = new System\Collection();
        $new_value->linker_map = new System\Collection();
        // Find the name for the field, for example "most part"
        // something strashnenko...
        //$linker_com_name = preg_replace('~^content_~', '', $linker_data_type);
        $linker_com_name = $linker_data_type;
        $end_link_field_name = 
            fx::data('component', $linker_com_name)
            ->allFields()
            ->findOne(function($i) use ($linker_prop_name) {
                //!!! some tin
                return isset($i['format']['prop_name']) && $i['format']['prop_name'] == $linker_prop_name;
            })
            ->get('keyword');
        $linked_infoblock_id = null;
        $linked_parent_id = null;
        foreach ($this->value as $item_props) {
            $linked_props = $item_props[$end_link_field_name];
            // if the linked entity doesn't yet exist
            // we get it as an array of values including 'title'
            $linker_item = null;
            
            if (is_array($linked_props)) {
                if (!$linked_infoblock_id) {
                    $linked_infoblock_id = $content->getLinkFieldInfoblock($this['id']);
                }
                $linked_props['type'] = $linked_data_type;
                if ($linked_infoblock_id) {
                    $linked_props['infoblock_id'] = $linked_infoblock_id;
                } else {
                    if (is_null($linked_parent_id) && $content['infoblock_id']) {
                        $our_infoblock = fx::data('infoblock', $content['infoblock_id']);
                        $linked_parent_id = $our_infoblock['page_id'];
                    }
                    if ($linked_parent_id) {
                        $linked_props['parent_id'] = $linked_parent_id;
                    }
                }
            } elseif (isset($existing_items->linker_map)) {
                $linker_item = $existing_items->linker_map->findOne($end_link_field_name, $linked_props);
            }
            if (!$linker_item) {
                $linker_item = fx::data($linker_data_type)->create();
            }
            $linker_item->setFieldValues(
                array($end_link_field_name => $linked_props), 
                array($end_link_field_name)
            );
            $new_value[]= $linker_item[$linker_prop_name];
            $new_value->linker_map []= $linker_item;
        }
        return $new_value;
    }
    
    /*
     * Process value of has-many relation field
     * such as news - comment
     */
    protected function appendHasMany($content) {
        // end type (for fields lot)
        $linked_type = $this->getRelatedComponent()->get('keyword');
        $new_value = fx::collection();
        foreach ($this->value as $item_id => $item_props) {
            $linked_finder = fx::data($linked_type);
            $linked_item = null;
            if (is_numeric($item_id)) {
                $linked_item = $linked_finder->where('id', $item_id)->one();
            } else {
                $is_empty = true;
                foreach ($item_props as $item_prop_val) {
                    if (!empty($item_prop_val)) {
                        $is_empty = false;
                        break;
                    }
                }
                // if all props are empty, skip this row and do nothing
                if ($is_empty) {
                    continue;
                }
                $linked_item = $linked_finder->create();
            }
            $linked_item->setFieldValues($item_props);
            $new_value[]= $linked_item;
        }
        return $new_value;
    }
    
    public function getEndDataType() {
        // the connection generated by the field
        $relation = $this->getRelation();
        if (isset($relation[4])) {
            return $relation[4];
        }
    }
    
    /*
     * Get the referenced component field
     */
    public function getRelatedComponent() {
        $rel = $this->getRelation();
        switch ($rel[0]) {
            case System\Data::HAS_MANY:
                $content_type = $rel[1];
                break;
            case System\Data::MANY_MANY:
                $content_type = $rel[4];
                break;
        }
        return fx::data('component', $content_type);
    }
    
    public function getRelation() {
        if (!$this['format']['linking_field']) {
            return false;
        }
        $direct_target_field = fx::data('field', $this['format']['linking_field']);
        $direct_target_component = fx::data('component', $this['format']['linking_datatype']);

        $first_type = $direct_target_component['keyword'];
        
        if (!$this['format']['mm_field']) {
            $res_rel = array(
                System\Data::HAS_MANY,
                $first_type,
                $direct_target_field['keyword']
            );
            return $res_rel;
        }
        
        $end_target_field = fx::data('field', $this['format']['mm_field']);
        $end_datatype = fx::data('component', $this['format']['mm_datatype']);
        
        $end_type = $end_datatype['keyword'];
        
        return array(
            System\Data::MANY_MANY,
            $first_type,
            $direct_target_field['keyword'],
            $end_target_field->getPropName(),
            $end_type,
            $end_target_field['keyword']
        );
    }
}