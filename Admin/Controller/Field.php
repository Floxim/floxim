<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Field as CompField;
use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Field extends Admin
{

    public function items($input)
    {
        $entity = $input['entity'];

        $items = $entity->getAllFields();
        $ar = array('type' => 'list', 'filter' => true, 'is_sortable' => true);

        // todo: psr0 need verify
        $entity_code = fx::getComponentNameByClass(get_class($entity));

        $ar['entity'] = 'field';
        $ar['values'] = array();
        $ar['labels'] = array(
            'keyword'   => fx::alang('Keyword', 'system'),
            'name'      => fx::alang('Name', 'system'),
            'type'      => fx::alang('Type', 'system'),
            'inherited' => fx::alang('Inherited from', 'system'),
            'editable'  => fx::alang('Editable', 'system')
        );
        foreach ($items as $field) {
            $r = array(
                'id'      => $field->getId(),
                'keyword' => array(
                    'name' => $field['keyword'],
                    'url'  => '#admin.' . $entity_code . '.edit(' . $field['component_id'] . ',edit_field,' . $field['id'] . ')'
                ),
                'name'    => $field['name'],
                'type'    => fx::alang("FX_ADMIN_FIELD_" . strtoupper($field->getTypeKeyword()), 'system')
            );
            if ($entity['id'] != $field['component_id']) {
                $component_name = fx::data('component', $field['component_id'])->get('name');
                $r['inherited'] = $component_name;
            } else {
                $r['inherited'] = ' ';
            }
            switch ($field['type_of_edit']) {
                case CompField\Entity::EDIT_ALL:
                    $r['editable'] = fx::alang('Yes', 'system');
                    break;
                case CompField\Entity::EDIT_NONE:
                    $r['editable'] = fx::alang('No', 'system');
                    break;
                case CompField\Entity::EDIT_ADMIN:
                    $r['editable'] = fx::alang('For admin only', 'system');
                    break;
            }
            $ar['values'][] = $r;
        }

        $result['fields'] = array($ar);
        $this->response->addButtons(array(
            array(
                'key'   => 'add',
                'title' => fx::alang('Add new field', 'system'),
                'url'   => '#admin.' . $entity_code . '.edit(' . $entity['id'] . ',add_field)'
            ),
            "delete"
        ));
        return $result;
    }

    public function add($input)
    {
        $fields = $this->form();

        $fields[] = $this->ui->hidden('action', 'add');
        $fields[] = $this->ui->hidden('to_entity', $input['to_entity']);
        $fields[] = $this->ui->hidden('to_id', $input['to_id']);
        $this->response->addFormButton('save');
        return array('fields' => $fields);
    }


    protected function form($info = array())
    {
        $fields[] = $this->ui->input('keyword', fx::alang('Field keyword', 'system'), $info['keyword']);
        $fields[] = $this->ui->input('name', fx::alang('Field name', 'system'), $info['name']);
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

        foreach (fx::data('datatype')->all() as $v) {
            $values[$v['id']] = fx::alang("FX_ADMIN_FIELD_" . strtoupper($v['name']), 'system');
        }
        $fields[] = array(
            'type'             => 'select',
            'name'             => 'type',
            'label'            => fx::alang('Field type', 'system'),
            'values'           => $values,
            'value'            => $info['type'] ? $info['type'] : 1,
            'post'             => array(
                'entity' => 'field',
                'id'     => $info['id'],
                'action' => 'format_settings'
            ),
            'change_on_render' => true
        );

        $values = array(
            CompField\Entity::EDIT_ALL   => fx::alang('anybody', 'system'),
            CompField\Entity::EDIT_ADMIN => fx::alang('admins only', 'system'),
            CompField\Entity::EDIT_NONE  => fx::alang('nobody', 'system')
        );
        $fields[] = $this->ui->select('type_of_edit', fx::alang('Field is available for', 'system'), $values,
            $info['type_of_edit'] ? $info['type_of_edit'] : CompField\Entity::EDIT_ALL);

        $fields[] = $this->ui->hidden('posting');
        $fields[] = $this->ui->hidden('action', 'add');
        $fields[] = $this->ui->hidden('entity', 'field');
        return $fields;
    }

    public function addSave($input)
    {
        $params = array('format', 'type', 'not_null', 'searchable', 'default', 'type_of_edit', 'form_tab');
        $data['keyword'] = trim($input['keyword']);
        $data['name'] = trim($input['name']);
        foreach ($params as $v) {
            $data[$v] = $input[$v];
        }
        $data['checked'] = 1;
        $data[$input['to_entity'] . '_id'] = $input['to_id'];
        $data['priority'] = fx::data('field')->nextPriority();

        $field = fx::data('field')->create($data);
        
        if (!$field->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $field->getValidateErrors();
        } else {
            try {
                $result = array('status' => 'ok');
                $field->save();
                $result['reload'] = '#admin.' . $input['to_entity'] . '.edit(' . $input['to_id'] . ',fields)';
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['errors'] = $field->getValidateErrors();
            }
        }


        return $result;
    }

    public function edit($input)
    {
        $field = fx::data('field')->getById($input['id']);

        if ($field) {
            $fields = $this->form($field);
            $fields[] = $this->ui->hidden('id', $input['id']);
            $fields[] = $this->ui->hidden('action', 'edit');
        } else {
            $fields[] = $this->ui->error(fx::alang('Field not found', 'system'));
        }

        return array('fields' => $fields);
    }

    public function editSave($input)
    {
        $field = fx::data('field')->getById($input['id']);

        $params = array(
            'keyword',
            'name',
            'format',
            'type',
            'not_null',
            'searchable',
            'default',
            'type_of_edit',
            'form_tab'
        );
        $input['keyword'] = trim($input['keyword']);
        $input['name'] = trim($input['name']);
        foreach ($params as $v) {
            $field->set($v, $input[$v]);
        }

        if (!$field->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $field->getValidateErrors();
        } else {
            $result = array('status' => 'ok');
            $field->save();
        }

        return $result;
    }

    public function formatSettings($input)
    {
        $fields = array();

        $input['id'] = intval($input['id']);
        if ($input['id']) {
            $field = fx::data('field', $input['id']);
        }

        if (!$input['id'] || $field['type'] != $input['type']) {
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
                $to_key = $input['to_entity'] . '_id';
                $to_val = $input['to_id'];
            }
            $field = fx::data('field')->create(array('type' => $input['type']));
            $field[$to_key] = $to_val;
        }
        if ($input['type'] === CompField\Entity::FIELD_DATETIME) {
            $fields[] = array(
                'name'           => 'default',
                'type'           => 'radio',
                'label'          => fx::alang('Default value', 'system'),
                'values'         => array('' => 'No', 'now' => 'NOW'),
                'value'          => $field['default'],
                'selected_first' => true
            );
        } else {
            $fields []= array(
                'name'  => 'default',
                'label' => fx::alang('Default value', 'system'),
                'type'  => $field['type'] == CompField\Entity::FIELD_MULTILINK ? 'hidden' : 'string',
                'value' => $field['default']
            );
        }

        $format_settings = $field->formatSettings();
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