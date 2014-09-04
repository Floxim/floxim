<?php

namespace Floxim\Floxim\Admin\Controller;

class Layout extends Admin {

    /**
     * List all of layout design in development
     */
    public function all() {
        $items = array();
        
        $layouts = fx::data('layout')->all();
        foreach ($layouts as $layout) {
            $layout_id = $layout['id'];
            $items[$layout_id] = $layout;
        }

        $layout_use = array(); // [room layout][number of website] => 'website Name'
        foreach (fx::data('site')->all() as $site) {
            $layout_use[$site['layout_id']][$site['id']] = 
                    '<a href="#admin.site.settings('.$site['id'].')">'.$site['name'].'</a>';
        }

        $ar = array('type' => 'list', 'filter' => true, 'essence' => 'layout');
        $ar['labels'] = array('name' => fx::alang('Name', 'system'), 'use' => fx::alang('Used on','system'), 'buttons' => array('type' => 'buttons'));

        foreach ($items as $item) {
            $submenu = self::get_template_submenu($item);
            $submenu_first = current($submenu);
            $name = array(
            	'name' => $item['name'],
            	'url' => $submenu_first['url']
            );
            $el = array('id' => $item['id'], 'name' => $name);
            if ($layout_use[$item['id']]) {
                $el['use'] = join(', ', $layout_use[$item['id']]);
                $el['fx_not_available_buttons'] = array('delete');
            } else {
                $el['use'] = ' - ';
            }
            $el['buttons'] = array();
            
            foreach ($submenu as $submenu_item) {
            	$el['buttons'][]= array(
            		'label' => $submenu_item['title'],
            		'url' => $submenu_item['url']
                );
            }

            $ar['values'][] = $el;
        }

        $fields[] = $ar;

        $this->response->add_buttons(array(
            array(
                'key'=> 'add',
                'title' => 'Add new layout',
                'url' => '#admin.layout.add'
            ),
            'delete'
        ));

        $result = array('fields' => $fields);
        $this->response->submenu->set_menu('layout');
        return $result;
    }

    public function add($input) {
        $input['source'] = 'new';
        $fields = array(
            $this->ui->hidden('action', 'add'),
            $this->ui->hidden('essence', 'layout'),
            array('name' => 'name', 'label' => fx::alang('Layout name','system')),
            array('name' => 'keyword', 'label' => fx::alang('Layout keyword','system')),
            $this->ui->hidden('source', $input['source']),
            $this->ui->hidden('posting')
        );
        $this->response->submenu->set_menu('layout');
        $this->response->breadcrumb->add_item(
            fx::alang('Layouts','system'),
            '#admin.layout.all'
        );
        $this->response->breadcrumb->add_item(
            fx::alang('Add new layout','system')
        );
        $this->response->add_form_button('save');
        $result['fields'] = $fields;
        return $result;
    }
    

    public function add_save($input) {
        $result = array('status' => 'ok');
        $keyword = trim($input['keyword']);
        $name = trim($input['name']);
        
        if (empty($keyword)) {
            $keyword = fx::util()->str_to_keyword($name);
        }
        
        $existing = fx::data('layout')->where('keyword', $keyword)->one();
        if ($existing) {
            return array(
                'status' => 'error', 
                'text' => sprintf(fx::alang('Layout %s already exists'), $keyword)
            );
        }

        $data = array('name' => $name, 'keyword' => $keyword);
        $layout = fx::data('layout')->create($data);
        try {
            $layout->save();
            $result['reload'] = '#admin.layout.all';
        } catch (Exception $e) {
            $result['status'] = 'error';
        }
        return $result;
    }
    
    public function delete_save($input) {
        $result = array('status' => 'ok');
        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                $layout = fx::data('layout', $id);
                $layout->delete();
            } catch (Exception $e) {
                $result['status'] = 'ok';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }
    
    
    public function operating($input) {
        $layout = fx::data('layout', $input['params'][0]); //->get_by_id($input['params'][0]);
        $action = isset($input['params'][1]) ? $input['params'][1] : 'layouts';

        if (!$layout) {
            $fields[] = $this->ui->error(fx::alang('Layout not found','system'));
            return array('fields' => $fields);
        }
        
        self::make_breadcrumb($layout, $action, $this->response->breadcrumb);
        
        if (method_exists($this, $action)) {
        	$result = call_user_func(array($this, $action), $layout);
        }

        $this->response->submenu->set_menu('layout-'.$layout['id'])->set_subactive($action);
        return $result;
    }
    
    public static function make_breadcrumb($template, $action, $breadcrumb) {
    	$tpl_submenu = self::get_template_submenu($template);
        $tpl_submenu_first = current($tpl_submenu);
        
    	$breadcrumb->add_item(fx::alang('Layouts','system'), '#admin.layout.all');
        $breadcrumb->add_item($template['name'], $tpl_submenu_first['url']);
        $breadcrumb->add_item($tpl_submenu[$action]['title'], $tpl_submenu[$action]['url']);
    }

    public function layouts($template) {
        $items = $template->get_layouts();

        $ar = array('type' => 'list', 'filter' => true);
        $ar['labels'] = array('name' => fx::alang('Name', 'system'));

        foreach ($items as $item) {
            $name = array('name' => $item['name'], 'url' => 'layout.edit('.$item['id'].')');
            $el = array('id' => $item['id'], 'name' => $name);
            $ar['values'][] = $el;
        }

        $fields[] = $ar;
        $buttons = array("add", "delete");
        $buttons_action['add']['options']['parent_id'] = $template['id'];
        $result = array('fields' => $fields, 'buttons' => $buttons, 'buttons_action' => $buttons_action, 'essence' => 'layout');
        return $result;
    }
    
    public static function get_template_submenu($layout) {
    	$titles = array(
            'settings' => fx::alang('Settings','system'),
            'source' => "Source"
        );

        $layout_id = $layout['id'];
        $items = array();
        foreach ($titles as $key => $title) {
            $items [$key]= array(
                'title' => $title,
                'code' => $key,
                'url' => 'layout.operating('.$layout_id.','.$key.')'
            );
        }
        return $items;
    }

    public function files($template) {
    	$params = isset($this->input['params']) ? $this->input['params'] : array();
    	
    	$fm_action = isset($params[2]) ? $params[2] : 'ls';
    	$fm_path = isset($params[3]) ? $params[3] : '';
    	// todo: psr0 need verify - class fx_controller_admin_module_filemanager not found
    	$filemanager = new fx_controller_admin_module_filemanager($fm_input, $fm_action, true);
    	$path = $template->get_path();
    	$fm_input = array(
    		'base_path' => $path,
    		'path' => $fm_path,
    		'base_url_template' => '#admin.template.operating('.$template['id'].',files,#action#,#params#)',
    		'root_name' => $template['name'],
    		'file_filters' => array('!~^\.~', '!~\.php$~'),
    		'breadcrumb_target' => $this->response->breadcrumb
		);
    	$result = $filemanager->process();
    	$result['buttons_essence'] = 'module_filemanager';
    	return $result;
    }
    
    public function settings($template) {
    	$fields[] = $this->ui->input('name', fx::alang('Layout name','system'), $template['name']);
        $fields[] = array(
            'name' => 'keyword', 
            'label' => fx::alang('Layout keyword','system'), 
            'value' => $template['keyword'],
            'disabled' => true
        );
        $fields[] = $this->ui->hidden('action', 'settings');
        $fields[] = $this->ui->hidden('id', $template['id']);
        
        $this->response->submenu->set_menu('layout');
        $result = array('fields' => $fields, 'form_button' => array('save'));
        return $result;
    }
    
    public function settings_save ( $input ) {
        $name = trim($input['name']);
        if ( !$name ) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Enter the layout name','system');
            $result['fields'][] = 'name';
        }
        else {
            $template = fx::data('template')->get_by_id($input['id']);
            if ( $template ) {
                $result['status'] = 'ok';
                $template->set('name', $name)->save();
            }
            else {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('Layout not found','system');
            }
        }
        
        return $result;
    }
    
    
    public function source($layout) {
        $template = fx::template('layout_'.$layout['keyword']);
        $vars = $template->get_template_variants();
        $files = array();
        foreach ($vars as $var) {
            $files[preg_replace("~^.+/~", '', $var['file'])]= $var['file'];
        }
        foreach ($files as $file => $path) {
            $tab_code = md5($file);//preg_replace("~\.~", '_', $file);
            $tab_name = fx::path()->file_name($file);
            $source = file_get_contents($path);
            $this->response->add_tab($tab_code, $tab_name);
            $this->response->add_fields(
                array(array(
                    'type' => 'text',
                    'code' => 'htmlmixed',
                    'name' => 'source_'.$tab_code, 
                    'value' => $source
                )),
                $tab_code
            );
        }
        $fields = array(
            $this->ui->hidden('essence', 'layout'),
            $this->ui->hidden('action', 'source')
        );
        $this->response->submenu->set_menu('layout');
        $this->response->add_form_button('save');
        return array('fields' => $fields, 'form_button' => array('save'));
    }
}