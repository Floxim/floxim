<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Field as CompField;
use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Field extends Admin
{
    
    public function cond() {
        
        $entity_type = 'floxim.blog.news';
        $com = fx::component($entity_type);
        $context = fx::env()->getFieldsForFilter();
        $fields = array(
            array(
                'keyword' => 'entity',
                'name' => fx::util()->ucfirst($com->getItemName()),
                'type' => 'entity',
                'entity_type' => $com['keyword'],
                'children' => $com->getFieldsForFilter()
            )
        );
        foreach ($context as $ctx) {
            $fields []= $ctx;
        }
        $field = array(
            'label' => 'Conds',
            'type' => 'condition',
            'context' => $context,
            'fields' => $fields
        );
        fx::log('test', $field);
        return array('fields' => array(
            $field,
            array(
                'label' => 'ls',
                'type' => 'livesearch',
                'values' => array(
                    array(1, 'foo tob sd'),
                    array(2, 'baa taz sd'),
                    array(3, 'olo trolo popaka')
                )
            )
        ));
    }

    public function items($input)
    {
        $component = $input['entity'];

        $items = $component->getAllFields()->sort('priority');
        $ar = array(
            'type' => 'list', 
            'filter' => true, 
            'is_sortable' => true,
            'sort_params' => array(
                'mode' => 'relative',
                'params' => array(
                    'component_id' => $component['id']
                )
            )
        );

        $ar['entity'] = 'field';
        $ar['values'] = array();
        $ar['labels'] = array(
            'keyword'   => fx::alang('Keyword', 'system'),
            'name'      => fx::alang('Name', 'system'),
            'type'      => fx::alang('Type', 'system'),
            'inherited' => fx::alang('Inherited from', 'system'),
            'editable'  => fx::alang('Editable', 'system')
        );
        $field_types = fx::data('datatype')->all()->getValues('name', 'keyword');
        foreach ($items as $field) {
            $r = array(
                'id'      => $field->getId(),
                'keyword' => array(
                    'name' => $field['keyword'],
                    'url'  => '#admin.component.edit(' . $component['id'] . ',edit_field,' . $field['id'] . ')'
                ),
                'name'    => $field['name'],
                'type'    => $field_types[$field['type']] // fx::alang("FX_ADMIN_FIELD_" . strtoupper($field['type']), 'system')
            );
            if ($component['id'] != $field['component_id']) {
                $component_name = fx::getComponentById($field['component_id'])->get('name');
                $r['inherited'] = $component_name;
            } else {
                $root = $field->getRootField();
                if ($root['id'] === $field['id']) {
                    $r['inherited'] = ' ';
                } else {
                    $r['inherited'] = fx::getComponentById($root['component_id'])->get('name').', extended';
                }
            }
            $r['editable'] = $field['is_editable'] ? fx::alang('Yes', 'system') : fx::alang('No', 'system');
            $ar['values'][] = $r;
        }

        $result['fields'] = array($ar);
        $this->response->addButtons(array(
            array(
                'key'   => 'add',
                'title' => fx::alang('Add new field', 'system'),
                'url'   => '#admin.component.edit(' . $component['id'] . ',add_field)'
            ),
            "delete"
        ));
        return $result;
    }

    public function add($input)
    {
        $field = $this->getField($input);
        $fields = $this->form($field);

        $fields[] = $this->ui->hidden('action', 'add');
        $fields[] = $this->ui->hidden('component_id', $input['component_id']);
        $this->response->addFormButton('save');
        return array('fields' => $fields);
    }


    protected function form($field)
    {
        $parent_field = $field['parent_field'];
        
        $f_keyword = array(
            'name' => 'keyword',
            'label' => fx::alang('Keyword', 'system'),
            'value' => $field['keyword']
        );
        if ($parent_field) {
            $f_keyword['disabled'] = true;
        }
        $fields[]= $f_keyword;
        
        $fields[] = array(
            'name' => 'name',
            'label' => fx::alang('Field name', 'system'),
            'locked' => $field->getReal('name') === null,
            'value' => $field['name']
        );
        
        /*
        $fields[]= array(
            'name' => 'priority',
            'label'=> 'Priority',
            'locked' => $field->getReal('priority') === null,
            'value' => $field['priority']
        );
        */
        $groups = $field['component']->getAllFields()->find('type', 'group');
        
        if (count($groups) > 0) {
            $group_vals = array_values(
                $groups->getValues(
                    function($f) {
                        return array($f['id'], $f['name']);
                    }
                )
            );
            array_unshift($group_vals, array('', ' - None - '));
            $fields[]= array(
                'type' => 'select',
                'name' => 'group_id',
                'label' => fx::alang('Field group', 'system'),
                'values' => $group_vals,
                'locked' => $field->getReal('group_id') === null,
                'value' => $field['group_id']
            );
        }
        
        if (!$parent_field && !$field['id']) {
            foreach (fx::data('datatype')->order('priority')->all() as $v) {
                $values[$v['keyword']] = $v['name']; //fx::alang("FX_ADMIN_FIELD_" . strtoupper($v['keyword']), 'system');
            }
            $f_type = array(
                'type'             => 'select',
                'name'             => 'type',
                'label'            => fx::alang('Field type', 'system'),
                'values'           => $values,
                'value'            => $field['type'] ? $field['type'] : 1,
                'post'             => array(
                    'entity' => 'field',
                    'id'     => $field['id'],
                    'action' => 'format_settings'
                ),
                'change_on_render' => true
            );
            $fields[]= $f_type;
        } else {
            $format_fields = $field->getFormatFields();
            $fields = array_merge($fields, $format_fields);
        }
        
        $fields[] = array(
            'name' => 'is_required', 
            'label' => fx::alang('Is required'),
            'type' => 'checkbox',
            'locked' => $field->getReal('is_required') === null,
            'value' => $field['is_required']
        );
        
        $fields []= array(
            'name' => 'is_editable',
            'type' => 'checkbox',
            'label' => fx::alang('Is editable', 'system'),
            'locked' => $field->getReal('is_editable') === null,
            'value' => $field['is_editable']
        );

        $fields[] = $this->ui->hidden('posting');
        $fields[] = $this->ui->hidden('action', 'add');
        $fields[] = $this->ui->hidden('entity', 'field');
        return $fields;
    }

    public function addSave($input)
    {
        
        $params = array('format', 'type', 'default', 'is_editable', 'is_required');
        $data['keyword'] = trim($input['keyword']);
        $data['name'] = trim($input['name']);
        foreach ($params as $v) {
            $data[$v] = $input[$v];
        }
        $data['checked'] = 1;
        $data['component_id'] = $input['component_id'];
        $data['priority'] = fx::data('field')->nextPriority();

        $field = fx::data('field')->create($data);
        
        if (!$field->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $field->getValidateErrors();
        } else {
            try {
                $result = array('status' => 'ok');
                $field->save();
                $result['reload'] = '#admin.component.edit(' . $input['component_id'] . ',fields)';
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['errors'] = $field->getValidateErrors();
            }
        }


        return $result;
    }
    
    protected function normalizeInput($input)
    {
        $input = array_merge(
            array(
                'component_id' => null,
                'infoblock_id' => null
            ),
            $input
        );
        return $input;
    }
    
    /**
     * Get existing field or create new overriding for the certain component
     * @param type $field_id
     * @param type $component_id
     * @return \Floxim\Floxim\Component\Field\Entity
     */
    protected function getField($input)
    {
        
        $input = $this->normalizeInput($input);
        $params = array(
            'component_id' => $input['component_id'],
            'infoblock_id' => $input['infoblock_id']
        );
        if (isset($input['field_id']) && $input['field_id']) {
            $field_id = (int) $input['field_id'];
        } else {
            $field_id = null;
            $params['type'] = $input['type'];
        }
        $res = fx::data('field')->getFieldImplementation(
            $field_id, 
            $params
        );
        return $res;
    }

    public function edit($input)
    {
        $result = array();
        
        $input = $this->normalizeInput($input);
        
        $field = $this->getField($input);
        $result['form_button'] = array();
        if ($field['parent_field']) {
            $result['lockable'] = true;
            if ($field['id']) {
                $result['form_button'][]= array(
                    'key' => 'use_defaults',
                    'label' => 'Use defaults',
                    'class' => 'delete',
                    'is_active' => false
                );
            }
        }
        $result['form_button'][]= 'save';
        if ($field) {
            $fields = $this->form($field);
            $passed_props = array('field_id', 'component_id', 'infoblock_id', 'entity_id', 'entity_type');
            foreach ($passed_props as $prop) {
                if ($input[$prop]) {
                    $fields[] = $this->ui->hidden($prop, $input[$prop]);
                }
            }
            $fields[] = $this->ui->hidden('action', 'edit');
        } else {
            $fields[] = $this->ui->error(fx::alang('Field not found', 'system'));
        }
        $result['fields'] = $fields;
        $title = $field['id'] ? 'id: '.$field['id'].', ' : '';
        $title .= $field['parent_field_id'] ? 'pfid: '.$field['parent_field_id'].', ' : '';
        $title .= 'com:'.$field['component']['keyword'].'/'.$field['component_id'].', ib:'.$field['infoblock_id'];
        $result['header'] = '<span title="'.$title.'">Настраиваем поле &laquo;'.$field['name'].'&raquo;</span>';
        return $result;
    }

    public function editSave($input)
    {
        $input = $this->normalizeInput($input);
        $field = $this->getField($input);
        
        $result = array(
            'reload' => '#admin.component.edit(' . $field['component_id']. ',fields)'
        );
        
        if (isset($input['group_id']) && $input['group_id']) {
            $field['group_id'] = (int) $input['group_id'];
        } else {
            $field['group_id'] = null;
        }
        
        // reset field to defaults
        if ($field['parent_field_id'] && isset($input['pressed_button']) && $input['pressed_button'] == 'use_defaults') {
            if ($field['id']) {
                $field->delete();
            }
            if ($input['entity_id'] && $input['entity_id'] !== 'null') {
                $entity = fx::data($input['entity_type'], $input['entity_id']);
                $result['new_json'] = $entity->getFormField($field['keyword']);
                $result['new_json']['name'] = 'content['.$result['new_json']['name'].']';
            }
            return $result;
        }
        
        $params = array(
            'name',
            //'format',
            'default',
            'is_editable',
            'is_required'
        );
        
        // these props can be specified only for top-level fields
        if (!$field['parent_field_id']) {
            $params []= 'keyword';
            $params []= 'type';
        }
        
        $input['keyword'] = trim($input['keyword']);
        $input['name'] = trim($input['name']);
        
        
        $lock_postfix = '__is_locked';
        foreach ($params as $v) {
            if (!array_key_exists($v, $input)) {
                continue;
            }
            if ($input[$v.$lock_postfix]) {
                $field->set($v, null);
            } else {
                $field->set($v, $input[$v]);
            }
        }
        if (isset($input['format']) && is_array($input['format'])) {
            $format = array();
            foreach ($input['format'] as $fprop => $fval) {
                if (preg_match("~".$lock_postfix."$~", $fprop)) {
                    continue;
                }
                if (!isset($input['format'][$fprop.$lock_postfix]) || !$input['format'][$fprop.$lock_postfix]) {
                    $format[$fprop] = $fval;
                }
            }
            if (count($format) === 0) {
                $format = null;
            }
            $field->set('format', $format);
        }
        if (!$field->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $field->getValidateErrors();
            unset($result['reload']);
        } else {
            $result = array('status' => 'ok');
            $field->save();
            if ($input['entity_id'] && $input['entity_id'] !== 'null') {
                $entity = fx::data($input['entity_type'], $input['entity_id']);
                $result['new_json'] = $entity->getFormField($field['keyword']);
                $result['new_json']['name'] = 'content['.$result['new_json']['name'].']';
            }
        }
        return $result;
    }

    public function formatSettings($input)
    {
        $fields = array();
        $input = $this->normalizeInput($input);
        $field = $this->getField($input);
        
        if ($input['type'] === 'datetime') {
            $fields[] = array(
                'name'           => 'default',
                'type'           => 'radio',
                'label'          => fx::alang('Default value', 'system'),
                'values'         => array('' => 'No', 'now' => 'NOW'),
                'value'          => $field['default'],
                'selected_first' => true
            );
        }
        $format_settings = $field->getFormatFields();
        if ($format_settings) {
            foreach ($format_settings as $v) {
                $fields[] = $v;
            }
        }
        return (array('fields' => $fields));
    }

    public function deleteSave($input)
    {

        $es = $this->entity_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        
        foreach ($ids as $id) {
            try {
                $field = fx::data($es, $id);
                if (!$field) {
                    continue;
                }
                $field->delete();
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }
}