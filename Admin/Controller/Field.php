<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Field as CompField;
use Floxim\Floxim\System;

class Field extends Admin {

    public function items( $input ) {
        $essence = $input['essence'];
        
        $items = $essence->all_fields();
        $ar = array('type' => 'list', 'filter' => true, 'is_sortable' => true);
        // todo: psr0 need fix
        $essence_code = str_replace('fx_','',get_class($essence));
        
        $ar['essence'] = 'field';
        $ar['values'] = array();
        $ar['labels'] = array(
            'keyword' => fx::alang('Keyword','system'),
            'name' => fx::alang('Name','system'),
            'type' => fx::alang('Type','system'),
            'inherited' => fx::alang('Inherited from','system'),
            'editable' => fx::alang('Editable', 'system')
        );
        foreach ( $items as $field ) {
            $r = array(
                'id' => $field->get_id(), 
                'keyword' => array(
                    'name' => $field['keyword'], 
                    'url' =>  '#admin.'.$essence_code.'.edit('.$field['component_id'].',edit_field,'.$field['id'].')'
                ),
                'name' => $field['name'], 
                'type' => fx::alang("FX_ADMIN_FIELD_".strtoupper($field->get_type_keyword()), 'system')
            );
            if ($essence['id'] != $field['component_id']) {
                $component_name = fx::data('component', $field['component_id'])->get('name');
                $r['inherited'] = $component_name;
            } else {
                $r['inherited'] = ' ';
            }
            switch ($field['type_of_edit']) {
                case CompField\Essence::EDIT_ALL:
                    $r['editable'] = fx::alang('Yes','system');
                    break;
                case CompField\Essence::EDIT_NONE:
                    $r['editable'] = fx::alang('No','system');
                    break;
                case CompField\Essence::EDIT_ADMIN:
                    $r['editable'] = fx::alang('For admin only', 'system');
                    break;
            }
            $ar['values'][] = $r;
        }
        
        $result['fields'] = array($ar);
        $this->response->add_buttons(
            array(
                array(
                    'key' => 'add', 
                    'title' => fx::alang('Add new field', 'system'),
                    'url' => '#admin.'.$essence_code.'.edit('.$essence['id'].',add_field)'
                ),
                "delete"
            )
        );
        return $result;
    }
    
    public function add ( $input ) {
        $fields = $this->_form();

        $fields[] = $this->ui->hidden('action', 'add');
        $fields[] = $this->ui->hidden('to_essence', $input['to_essence']);
        $fields[] = $this->ui->hidden('to_id', $input['to_id']);
        $this->response->add_form_button('save');
        return array('fields' => $fields);
    }
    
    
    protected function _form ( $info = array() ) {
        $fields[] = $this->ui->input('keyword', fx::alang('Field keyword','system'), $info['keyword']);
        $fields[] = $this->ui->input('name', fx::alang('Field name','system'), $info['name']);
        /*
        $fields []= array(
            'type' => 'select',
            'name' => 'form_tab',
            'label' => 'Form col',
            'values' => array(
                array('', '-auto-'),
                array(1, '1'),
                array(2, '2'),
                array(3, '3'),
                array(4, '4')
            ),
            'value' => $info['form_tab']
        );*/
        
        foreach (fx::data('datatype')->all() as $v ) {
            $values[$v['id']] = fx::alang("FX_ADMIN_FIELD_".strtoupper($v['name']), 'system');
        }
        $fields[] = array(
        	'type' => 'select', 
        	'name' => 'type', 
        	'label' => fx::alang('Field type','system'),
        	'values' => $values, 
        	'value' => $info['type'] ?  $info['type']  : 1, 
        	'post' => array(
        		'essence' => 'field', 
        		'id' => $info['id'], 
        		'action' => 'format_settings'
        	),
        	'change_on_render' => true
        );
        
        $values = array(
            CompField\Essence::EDIT_ALL => fx::alang('anybody','system'),
            CompField\Essence::EDIT_ADMIN => fx::alang('admins only','system'),
            CompField\Essence::EDIT_NONE => fx::alang('nobody','system')
        );
        $fields[] = $this->ui->select(
                'type_of_edit', 
                fx::alang('Field is available for','system'),
                $values, 
                $info['type_of_edit'] ? $info['type_of_edit'] : CompField\Essence::EDIT_ALL
        );
        
        $fields[] = $this->ui->hidden('posting');
        $fields[] = $this->ui->hidden('action', 'add');
        $fields[] = $this->ui->hidden('essence', 'field');
        return $fields;
    }
    
    public function add_save( $input ) {
        $params = array('format', 'type', 'not_null', 'searchable', 'default', 'type_of_edit', 'form_tab');
        $data['keyword'] = trim($input['keyword']);
        $data['name'] = trim($input['name']);
        foreach ( $params as $v ) {
            $data[$v] = $input[$v];
        }
        $data['checked'] = 1;
        $data[ $input['to_essence'].'_id'] = $input['to_id'];
        $data['priority'] = fx::data('field')->next_priority();

        $field = fx::data('field')->create($data);
        if (!$field->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $field->get_validate_errors();
        }
        else {
            $result = array('status' => 'ok');
            $field->save();
            // run creating hook
            if ($input['to_essence']=='component') {
                $com = fx::data('component', $input['to_id']);
                if ($com['vendor'] == 'std') {
                    fx::hooks()->create(null,'field_create',array('data'=>$data));
                }
            }
            $result['reload'] = '#admin.'.$input['to_essence'].'.edit('.$input['to_id'].',fields)';
        }
        
        
        return $result;
    }
    
    public function edit ( $input ) {
        $field = fx::data('field')->get_by_id ( $input['id']);
        
        if ( $field ) {
            $fields = $this->_form($field);
            $fields[] = $this->ui->hidden('id',$input['id'] );
            $fields[] = $this->ui->hidden('action','edit');
        }
        else {
            $fields[] = $this->ui->error( fx::alang('Field not found','system') );
        }

        return array('fields' => $fields);
    }
    
    public function edit_save ( $input ) {
        $field = fx::data('field')->get_by_id( $input['id']);

        $params = array('keyword', 'name', 'format', 'type', 'not_null', 'searchable', 'default', 'type_of_edit', 'form_tab');
        $input['keyword'] = trim($input['keyword']);
        $input['name'] = trim($input['name']);
        fx::log('saving f', $input);
        foreach ( $params as $v ) {
            $field->set( $v, $input[$v]);
        }

        if (!$field->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $field->get_validate_errors();
        }
        else {
            $result = array('status' => 'ok');
            $field->save();
            if ($field['component_id']) {
                $com = fx::data('component', $field['component_id']);
                if ($com['vendor'] == 'std') { 
                    fx::hooks()->create(null,'field_update',array('field'=>$field));
                }
            }
        }
        
        return $result;
    }
    
    public function format_settings ( $input ) {
        $fields = array();
        
        $input['id'] = intval($input['id']);
        if ( $input['id'] ) {
            $field = fx::data('field', $input['id']);
        }
        
        if ( !$input['id'] || $field['type'] != $input['type'] ) {
            if ($field && $field['type'] != $input['type']) {
                $to_key = null;
                $to_val = null;
                foreach ($field->get() as $ffk => $ffv) {
                    if ($ffv && in_array($ffk, array('component_id', 'widget_id', 'system_table_id'))) {
                        $to_key = $ffk;
                        $to_val = $ffv;
                        break;
                    }
                }
            } else {
                $to_key = $input['to_essence'].'_id';
                $to_val = $input['to_id'];
            }
            $field = fx::data('field')->create( array('type' => $input['type']));
            $field[$to_key] = $to_val;
        }
       
        if ($input['type'] === CompField\Essence::FIELD_DATETIME) {
            $fields[] = array(
                'name' => 'default',
                'type' => 'radio',
                'label' => fx::alang('Default value','system'),
                'values' => array(''=>'No', 'now'=>'NOW'),
                'value' => $field['default'],
                'selected_first' => true
            );
        } else {
            $fields[] = $this->ui->input('default', fx::alang('Default value','system'), $field['default']);
        }

        $format_settings =  $field->format_settings();  
        if ( $format_settings ) {
            foreach ( $format_settings as $v) {
                $fields[] = $v;
            }
        }
        return (array('fields' => $fields)) ;
    }

    public function delete_save($input) {

        $es = $this->essence_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                $field=fx::data($es, $id);
                $field->delete();
                if ($field['component_id']) {
                    $com = fx::data('component', $field['component_id']);
                    if ($com['vendor'] == 'std') { 
                        fx::hooks()->create(null,'field_delete',array('field'=>$field));
                    }
                }
            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }
}