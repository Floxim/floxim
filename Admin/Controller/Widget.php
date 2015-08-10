<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Widget extends Component
{

    public function all()
    {
        $field = array('type' => 'list', 'filter' => true);
        $field['labels'] = array(
            'name'    => fx::alang('Name', 'system'),
            'keyword' => fx::alang('Keyword', 'system'),
            'buttons' => array('type' => 'buttons')
        );
        $field['values'] = array();
        $field['entity'] = 'widget';
        $widgets = fx::data('widget')->all();
        foreach ($widgets as $widget) {
            $submenu = Component::getComponentSubmenu($widget);
            $submenu_first = current($submenu);
            $r = array(
                'id'      => $widget['id'],
                'keyword' => $widget['keyword'],
                'name'    => array(
                    'name' => $widget['name'],
                    'url'  => $submenu_first['url']
                )
            );

            $r['buttons'] = array();
            foreach ($submenu as $submenu_item) {
                //if (!$submenu_item['parent']) {
                $r['buttons'] [] = array(
                    'type'  => 'button',
                    'label' => $submenu_item['title'],
                    'url'   => $submenu_item['url']
                );
                //}
            }

            $field['values'][] = $r;
        }
        $this->response->addButtons(array(
            array(
                'key'   => "add",
                'title' => fx::alang('Add new widget', 'system'),
                'url'   => '#admin.widget.add'
            ),
            "delete"
        ));

        $result = array('fields' => array($field));

        $this->response->breadcrumb->addItem(self::entityTypes('widget'), '#admin.widget.all');
        $this->response->submenu->setMenu('widget');
        return $result;
    }

    public function add($input)
    {
        $fields = array();

        
        $input['source'] = 'new';
        $fields[] = $this->ui->hidden('action', 'add');
        $fields[] = array('label' => fx::alang('Name', 'system'), 'name' => 'name');
        $fields[] = array('label' => fx::alang('Keyword', 'system'), 'name' => 'keyword');
        //$fields[] = $this->getVendorField();
        foreach ($this->getModuleFields() as $mf) {
            $fields [] = $mf;
        }
        
        $fields[] = $this->ui->hidden('source', $input['source']);
        $fields[] = $this->ui->hidden('posting');
        $fields[] = $this->ui->hidden('entity', 'widget');

        $this->response->breadcrumb->addItem(self::entityTypes('widget'), '#admin.widget.all');
        $this->response->breadcrumb->addItem(fx::alang('Add new widget', 'system'));

        $this->response->submenu->setMenu('widget');
        $this->response->addFormButton('save');
        return array('fields' => $fields);
    }

    public function addSave($input)
    {
        $result = array('status' => 'ok');

        $data['name'] = trim($input['name']);
        $data['keyword'] = $this->getFullKeyword($input);

        $widget = fx::data('widget')->create($data);

        if (!$widget->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $widget->getValidateErrors();
            $result['text'] = 'Error';
            fx::log('invald', $result);
            return $result;
        }
        $widget->save();
        fx::console('widget scaffold --id=' . $widget['id']);
        $result['reload'] = '#admin.widget.all';
        return $result;
    }

    public function editSave($input)
    {
        $widget = fx::data('widget')->getById($input['id']);
        $result['status'] = 'ok';
        // save settings
        if ($input['phase'] == 'settings') {
            $params = array('name', 'description', 'embed');
            if (!trim($input['name'])) {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('Enter the widget name', 'system');
                $result['fields'][] = 'name';
            }

            if ($result['status'] == 'ok') {
                foreach ($params as $v) {
                    $widget->set($v, trim($input[$v]));
                }

                $widget->save();
            }
        }

        return $result;
    }

    public function settings($widget)
    {


        //$fields[] = array('label' => fx::alang('Keyword:','system') . ' '.$widget['keyword'], 'type' => 'label');
        $fields[] = array(
            'label'    => fx::alang('Keyword', 'system'),
            'name'     => 'keyword',
            'disabled' => true,
            'value'    => $widget['keyword']
        );

        $fields[] = array('label' => fx::alang('Name', 'system'), 'name' => 'name', 'value' => $widget['name']);

        $fields[] = array(
            'label' => fx::alang('Description', 'system'),
            'name'  => 'description',
            'value' => $widget['description'],
            'type'  => 'text'
        );

        $fields[] = array('type' => 'hidden', 'name' => 'phase', 'value' => 'settings');
        $fields[] = array('type' => 'hidden', 'name' => 'id', 'value' => $widget['id']);

        $this->response->submenu->setSubactive('settings');
        $fields[] = $this->ui->hidden('entity', 'widget');
        $fields[] = $this->ui->hidden('action', 'edit_save');

        return array('fields' => $fields, 'form_button' => array('save'));
    }
}
