<?php

class fx_controller_admin_site extends fx_controller_admin {

    public function all() {
        $sites = fx::data('site')->all();

        $list = array('type' => 'list', 'filter' => true, 'tpl' => 'imgh', 'sortable' => true);
        $list['labels'] = array();

        $list['values'] = array();
        $list['essence'] = 'site';
        foreach ($sites as $v) {
            $text = fx::alang('Language:','system') . ' ' . $v['language'];
            if ($v['domain']) {
                $text .= "<br />".$v['domain'];
            }
            $text = '<a href="http://'.$v['domain'].'" style="color:#666;" target="_blank"> '.$v['domain'].'</a>';
            $text .=" <span style='font-size:10px; color:#777;'>&middot;</span> ".$v['language'];
            if ($v['type'] == 'mobile') $text .= "<br/>" . fx::alang('for mobile devices','system');
            $r = array(
                    'id' => $v['id'],
                    'header' => array('name' => $v['name'], 'url' => 'site.settings('.$v['id'].')'),
                    'text' => $text
            );
            $list['values'][] = $r;
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

    public function add($input) {
        $fields = array();

        $fields[] = $this->ui->hidden('action', 'add_save');
        $fields[] = $this->ui->hidden('essence', 'site');
        $fields[] = $this->ui->input('name', fx::alang('Site name','system'));
        $fields[] = $this->ui->input('domain', fx::alang('Domain','system'));
        
        //$fields[] = $this->ui->hidden('posting');
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

    public function import_save($input) {
        $file = $input['importfile'];
        if (!$file) {
            $result = array('status' => 'error');
            $result['text'][] = fx::alang('Error creating a temporary file','system');
        }

        $result = array('status' => 'ok');
        try {
            $imex = new fx_import(array('template_id' => intval($input['template_id'])));
            $imex->import_by_file($file['tmp_name']);
        } catch (Exception $e) {
            $result = array('status' => 'error');
            $result['text'][] = $e->getMessage();
        }

        return $result;
    }

    public function add_save($input) {
        $result = array(
            'status' => 'ok',
            'reload' => '#admin.site.all'
        );

        $site = fx::data('site')->create(array('name' => $input['name'], 'domain' => $input['domain']));

        if (!$site->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $site->get_validate_error();
            return $result;
        }

        $current_site = fx::data('site')->get_by_host_name();
        $layout_id = $current_site['layout_id'];
        if (!$layout_id) {
            $layout_id = fx::data('layout')->one()->get('id');
        }
        
        $site['layout_id'] = $layout_id;
        $site['checked'] = 1;
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
        return $result;
    }

    public function map($input) {
        $site = fx::data('site')->get_by_id($input['params'][0]);
        if (!$site) {
            $this->response->set_status_error( fx::alang('Site not found','system') );
            return;
        }
        $fields = array();
        $fields[] = $this->ui->tree($this->_get_site_tree($site));

        $this->response->add_fields($fields);
        $this->response->add_buttons("add,edit,settings,delete");
        $this->response->add_button_options('add', 'site_id='.$site['id']);
        $this->response->set_essence('content');
        $this->_set_layout('map', $site);
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

    protected function _get_site_tree($site) {
        $content = fx::data('content')->where('site_id', $site['id'])->all();
        $tree = fx::data('content_page')->make_tree($content);
        $res = $this->_get_tree_branch($tree);
        return $res[0]['children'];
    }
    
    protected function _get_tree_branch($level_collection) {
        $result = array();
        $content_blocks = $level_collection->group('infoblock_id');
        $infoblocks = fx::data('infoblock', $content_blocks->keys());
        foreach ($content_blocks as $ib_id => $items) {
            $infoblock = $infoblocks->find_one('id', $ib_id);
            if (!$infoblock) {
                fx::log('no ib', $ib_id, $infoblocks);
                $ib_name = '<span style="color:#F00;">ib #'.$ib_id.'</span>';
            } else {
                $ib_name = $infoblock['name'] ? $infoblock['name'] : 'ib #'.$ib_id;
            }
            $type_result = array();
            foreach ($items as $item) {
                $name = isset($item['name']) ? $item['name'] : $item['type'].' #'.$item['id'];
                $item_res = array(
                    'data' => $name,
                    'metadata' => array(
                        'id' => $item['id'],
                        'essence' => 'content'
                    )
                );
                if ($item['children']) {
                    $item_res['children'] = $this->_get_tree_branch($item['children']);
                }
                $type_result []= $item_res;
            }
            $result []= array(
                'data' => $ib_name,
                'metadata' => array(
                    'id' => $ib_id,
                    'is_groupper' => 1,
                    'essence' => 'infoblock'
                ),
                'children' => $type_result
            );
        }
        if (count($result) == 1) {
            $result = $result[0]['children'];
        }
        return $result;
    }

    public function settings($input) {
        $site_id = isset($input['id']) ? $input['id'] : isset($input['params'][0]) ? $input['params'][0] : null;
        $site = fx::data('site', $site_id);
        $main_fields = array();
        $main_fields[] = $this->ui->input('name', fx::alang('Site name','system'), $site['name']);
        $main_fields[] = $this->ui->input('domain', fx::alang('Domain','system'), $site['domain']);
        $main_fields[] = $this->ui->input('mirrors', fx::alang('Aliases','system'), $site['mirrors']);
        
        $languages = fx::data('lang')->all()->get_values('lang_code', 'lang_code');
        $main_fields[] =
            array(
                'name' => 'language',
                'type' => 'select',
                'values' => $languages,
                'value' => $site['language'],
                'label' => fx::alang('Language','system')
            );
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
        $params = array('name', 'domain', 'mirrors', 'language', 'robots', 'language', 'robots', 'index_page_id', 'error_page_id', 'offline_text');

        foreach ($params as $v) {
            if (isset($input[$v])) {
                $site[$v] = $input[$v];
            }
        }
        
        $site->save();
        return $result;
    }
    
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
}