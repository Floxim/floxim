<?php

class fx_controller_admin_widget extends fx_controller_admin_component {
    
    public function all() {
        $field = array('type' => 'list', 'filter' => true);
        $field['labels'] = array(
            'name' => fx::alang('Name', 'system'),
            'buttons' => array('type' => 'buttons')
        );
        $field['values'] = array();
        $field['essence'] = 'widget';
        $widgets = fx::data('widget')->all();
        foreach ($widgets as $widget) {
            $submenu = fx_controller_admin_component::get_component_submenu($widget);
            $submenu_first = current($submenu);
            $r = array(
                'id' => $widget['id'],
                'name' => array(
                    'name' => $widget['name'],
                    'url' => $submenu_first['url']
                )
            );

            $r['buttons'] = array();
            foreach ($submenu as $submenu_item) {
                //if (!$submenu_item['parent']) {
                    $r['buttons'] []= array(
                        'type' => 'button', 
                        'label' => $submenu_item['title'], 
                        'url' => $submenu_item['url']
                    );
                //}
            }

            $field['values'][] = $r;
        }
        $this->response->add_buttons(array(
            array(
                'key' => "add", 
                'title' => fx::alang('Add new widget', 'system'),
                'url' => '#admin.widget.add'
            ),
            "delete"
        ));
        
        $result = array('fields' => array($field));

        $this->response->breadcrumb->add_item(self::_essence_types('widget'), '#admin.widget.all');
        $this->response->submenu->set_menu('widget');
        return $result;
    }

    public function add($input) {
        $fields = array();

        switch ($input['source']) {
            default:
                $input['source'] = 'new';
                $fields[] = $this->ui->hidden('action', 'add');
                $fields[] = array('label' => fx::alang('Name','system'), 'name' => 'name');
                $fields[] = array('label' => fx::alang('Keyword','system'), 'name' => 'keyword');
        }

        $fields[] = $this->ui->hidden('source', $input['source']);
        $fields[] = $this->ui->hidden('posting');
        $fields[] = $this->ui->hidden('essence', 'widget');
        
        $this->response->breadcrumb->add_item(
            self::_essence_types('widget'), 
            '#admin.widget.all'
        );
        $this->response->breadcrumb->add_item(
            fx::alang('Add new widget', 'system')
        );
        
        $this->response->submenu->set_menu('widget');
        $this->response->add_form_button('save');
        return array('fields' => $fields);
    }

    public function add_save($input) {
        $result = array('status' => 'ok');

        $data['name'] = trim($input['name']);
        $data['keyword'] = trim($input['keyword']);

        
        $widget = fx::data('widget')->create($data);
        
        if (!$widget->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $widget->get_validate_errors();
            return $result;
        }
        $widget->save();
        $result['reload'] = '#admin.widget.all';
        return $result;
    }

    public function edit_save($input) {
        $widget = fx::data('widget')->get_by_id($input['id']);
        $result['status'] = 'ok';
        // save settings
        if ($input['phase'] == 'settings') {
            $params = array('name', 'description', 'embed');
            if (!trim($input['name'])) {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('Enter the widget name','system');
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

    public function settings($widget) {

        
        $fields[] = array('label' => fx::alang('Keyword:','system') . ' '.$widget['keyword'], 'type' => 'label');

        $fields[] = array('label' => fx::alang('Name','system'), 'name' => 'name', 'value' => $widget['name']);
        
        $fields[] = array('label' => fx::alang('Description','system'), 'name' => 'description', 'value' => $widget['description'], 'type' => 'text');

        $fields[] = array('type' => 'hidden', 'name' => 'phase', 'value' => 'settings');
        $fields[] = array('type' => 'hidden', 'name' => 'id', 'value' => $widget['id']);
        
        $this->response->submenu->set_subactive('settings');
        $fields[] = $this->ui->hidden('essence', 'widget');
        $fields[] = $this->ui->hidden('action', 'edit_save');
        
        return array('fields' => $fields, 'form_button' => array('save'));
    }
}
