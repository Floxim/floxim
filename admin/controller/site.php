<?php

class fx_controller_admin_site extends fx_controller_admin {

    public function all() {
        $sites = fx::data('site')->all();

        $list = array(
            'type' => 'list', 
            'filter' => true, 
            'sortable' => true
        );
        $list['labels'] = array(
            'name' => fx::alang('Site name','system'),
            'domain' => fx::alang('Domain','system'),
            'language' => fx::alang('Language', 'system')
        );

        $list['values'] = array();
        $list['essence'] = 'site';
        foreach ($sites as $v) {
            $r = array(
                'id' => $v['id'],
                'domain' => $v['domain'],
                'name' => array(
                    'url' => 'site.settings('.$v['id'].')',
                    'name' => $v['name']
                ),
                'language' => $v['language']
            );
            $list['values'][]= $r;
        }

        $this->response->add_field($list);

        $this->response->add_buttons(
            array(
                array(
                    'key' => 'add', 
                    'title' => fx::alang('Add new site','system'),
                    'url' => '#admin.administrate.site.add'
                ),
                'delete'
            )
        );
        $this->response->breadcrumb->add_item( fx::alang('Sites','system') );
        $this->response->submenu->set_menu('site');
    }

    public function add() {
        $fields = $this->_get_fields(fx::data('site')->create());
        $fields[] = $this->ui->hidden('action', 'add_save');
        $fields[] = $this->ui->hidden('essence', 'site');
        
        $this->response->add_fields($fields);
        $this->response->dialog->set_title( fx::alang('Create a new site','system') );
        $this->response->breadcrumb->add_item( 
            fx::alang('Sites','system'),
            '#admin.administrate.site.all'
        );
        $this->response->breadcrumb->add_item(
            fx::alang('Add new site','system')
        );
        $this->response->add_form_button('save');
        $this->response->submenu->set_menu('site');
    }

    public function add_save($input) {
        
        $result = array();
        $site = fx::data('site')->create(array(
            'name' => $input['name'], 
            'domain' => $input['domain'],
            'layout_id' => $input['layout_id'],
            'mirrors' => $input['mirrors'], 
            'language' => $input['language'],
            'checked' => 1
        ));

        if (!$site->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $site->get_validate_errors();
            return $result;
        }

        $site->save();

        $index_page = fx::data('content_page')->create(array(
            'name' => fx::alang('Cover Page','system'),
            'url' => '/',
            'site_id' => $site['id']
        ))->save();
        
        $error_page = fx::data('content_page')->create(array(
            'name' => fx::alang('Page not found','system'),
            'url' => '/404', 
            'site_id' => $site['id'],
            'parent_id' => $index_page['id']
        ))->save();
        
        $site['error_page_id'] = $error_page['id'];
        $site['index_page_id'] = $index_page['id'];
        
        fx::data('infoblock')->create(
            array(
                'controller' => 'layout',
                'action' => 'show',
                'name' => 'Layout',
                'site_id' => $site['id']
            )
        )->save();
        $site->save();
        fx::input()->set_cookie('fx_target_location', '/floxim/#admin.site.all');
        $result = array(
            'status' => 'ok',
            'reload' => '/~ajax/user._crossite_auth_form'
        );
        return $result;
    }
    
    protected function _set_layout($section, $site) {
    	$titles = array(
    		'map' => fx::alang('Site map','system'),
    		'settings' => fx::alang('Settings','system'),
    		'design' => fx::alang('Design','system')
		);
    	$this->response->breadcrumb->add_item( fx::alang('Sites','system'), '#admin.site.all');
        $this->response->breadcrumb->add_item($site['name'], '#admin.site.settings('.$site['id'].')');
        $this->response->breadcrumb->add_item($titles[$section]);
        $this->response->submenu->set_menu('site-'.$site['id'])->set_subactive('site'.$section.'-'.$site['id']);
    }
    
    /**
     * Get fields for website create/edit form
     * @param type fx_site $site
     * @return array
     */
    protected function _get_fields($site) {
        $main_fields = array();
        $main_fields[] = $this->ui->input('name', fx::alang('Site name','system'), $site['name']);
        $main_fields[] = $this->ui->input('domain', fx::alang('Domain','system'), $site['domain']);
        $main_fields[] = array(
            'name' => 'mirrors', 
            'label' => fx::alang('Aliases','system'), 
            'value' => $site['mirrors'],
            'type' => 'text'
        );
        
        $languages = fx::data('lang')->all()->get_values('lang_code', 'lang_code');
        $main_fields[] = array(
            'name' => 'language',
            'type' => 'select',
            'values' => $languages,
            'value' => $site['language'],
            'label' => fx::alang('Language','system')
        );
        
        $layouts = fx::data('layout')->all();
        $layouts_select = array();
        foreach ( $layouts  as $layout ) {
            $layouts_select[] = array($layout['id'], $layout['name']);
        }

        $main_fields []= array(
            'name' => 'layout_id',
            'type' => 'select',
            'values' => $layouts_select,
            'value' => $site['layout_id'],
            'label' => fx::alang('Layout','system')
        );
        return $main_fields;
    }

    public function settings($input) {
        $site_id = isset($input['id']) ? $input['id'] : isset($input['params'][0]) ? $input['params'][0] : null;
        $site = fx::data('site', $site_id);
        
        $main_fields = $this->_get_fields($site);
            
        $this->response->add_fields($main_fields);

        $fields = array();
        $fields[] = $this->ui->hidden('essence', 'site');
        $fields[] = $this->ui->hidden('action', 'settings');
        $fields[] = $this->ui->hidden('posting');
        $fields [] = $this->ui->hidden('id', $site['id']);
        $this->response->add_fields($fields);
        $this->response->add_form_button('save');
        $this->_set_layout('settings', $site);
    }

    public function settings_save($input) {
        
        $site = fx::data('site')->get_by_id($input['id']);
        $result = array(
            'status' => 'ok',
            'reload' => '#admin.site.all'
        );
        $params = array(
            'name', 
            'domain', 
            'mirrors', 
            'language', 
            'robots', 
            'layout_id',
            'index_page_id', 
            'error_page_id', 
            'offline_text'
        );

        foreach ($params as $v) {
            if (isset($input[$v])) {
                $site[$v] = $input[$v];
            }
        }
        
        $site->save();
        return $result;
    }
    
    /*
    public function design($input) {
      	$site_id = $input['params'][0];
        $site = fx::data('site')->get_by_id($site_id);
        $layouts = fx::data('layout')->all();
        $layouts_select = array();
        foreach ( $layouts  as $layout ) {
            $layouts_select[] = array($layout['id'], $layout['name']);
        }

        $fields = array(
            array(
                'name' => 'layout_id',
                'type' => 'select',
                'values' => $layouts_select,
                'value' => $site['layout_id'],
                'label' => fx::alang('Layout','system')
            ),
            array(
                'type' => 'hidden',
                'name' => 'site_id',
                'value' => $site_id
            )
        );
        $fields[] = $this->ui->hidden('essence', 'site');
        $fields[] = $this->ui->hidden('action', 'design_save');
        $this->response->add_fields($fields);
        
        $this->response->add_form_button('save');
        $this->_set_layout('design', $site);
    }
    
    public function design_save($input) {
        $site = fx::data('site', $input['site_id']);
    	$site['layout_id'] = $this->input['layout_id'];
        $site->save();
    }
    public function download($input) {
        $items = $input['params'];
        if ($items) {
            $store = new fx_admin_store();
            $fields[] = $this->ui->label( fx::alang('You are about to install:','system') );
            foreach ($items as $store_id) {
                $info = $store->get_info($store_id);
                $fields[] = $this->ui->hidden('download['.$info['type'].']', $store_id);
                $fields[] = $this->ui->label($info['name']);
            }
        }

        $fields[] = $this->ui->hidden('action', 'download');
        $fields[] = $this->ui->hidden('essence', 'site');
        $fields[] = $this->ui->hidden('posting');

        $result['fields'] = $fields;
        $result['tree']['mode'] = 'administrate';
        $result['form_button'] = array('save');
        return $result;
    }

    public function download_save($input) {
        $store = new fx_admin_store();

        $download = $input['download'];
        if ($download['design']) {
            $content = $store->get_file($download['design']);
            $imex = new fx_import();
            $result = $imex->import_by_content($content);
            $template = $result[0];
        }

        if ($download['site']) {
            $content = $store->get_file($download['site']);
            $imex = new fx_import('template_id='.$template['id']);
            $result = $imex->import_by_content($content);
        }
    } 
    */
}