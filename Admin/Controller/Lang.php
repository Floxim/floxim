<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Lang extends Admin {

    public function all() {
        $langs = fx::data('lang')->all();

        $list = array('type' => 'list', 'filter' => false, 'tpl' => 'imgh', 'essence'=> 'lang');
        $list['labels'] = array();

        $list['values'] = array();
        foreach ($langs as $v) {
            $text = '';
            $sep = ' <span class="fx_list_separator">&middot;</span> ';
            if ($v['native_name']) {
                $text .= $v['native_name'].$sep;
            }
            $text .= $v['lang_code'];
            $text .= $sep;
            $text .= '<a href="#admin.lang.strings('.$v['id'].')">'.fx::alang('Edit strings', 'system').'</a>';
            $r = array(
                    'id' => $v['id'],
                    'header' => array('name' => $v['en_name'], 'url' => 'lang.edit('.$v['id'].')'),
                    'text' => $text
            );
            $list['values'][] = $r;
        }

        $this->response->add_field($list);

        $this->response->add_buttons(
            array(
                array(
                    'key' => 'add', 
                    'title' => fx::alang('Add new language','system'),
                    'url' => '#admin.administrate.lang.add'
                ),
                'delete'
            )
        );
        $this->response->breadcrumb->add_item( fx::alang('Languages','system') );
        $this->response->submenu->set_menu('lang');
    }

    public function add($input) {
        $fields = array();

        $fields[] = $this->ui->hidden('action', 'add_save');
        $fields[] = $this->ui->hidden('essence', 'lang');
        $fields[] = $this->ui->input('en_name', fx::alang('Language name','system'));
        $fields[] = $this->ui->input('native_name', fx::alang('Native language name','system'));
        $fields[] = $this->ui->input('lang_code', fx::alang('Language code','system'));

        $this->response->add_fields($fields);
        $this->response->dialog->set_title( fx::alang('Create a new language','system') );
        $this->response->breadcrumb->add_item( 
            fx::alang('Languages','system'),
            '#admin.administrate.lang.all'
        );
        $this->response->breadcrumb->add_item(
            fx::alang('Add new language','system')
        );
        $this->response->add_form_button('save');
        $this->response->submenu->set_menu('lang');
    }

    public function add_save($input) {
        $result = array('status' => 'ok');

        $lang = fx::data('lang')->create(
            array(
                'en_name' => $input['en_name'],
                'native_name' => $input['native_name'],
                'lang_code' => $input['lang_code']
            )
        );

        if (!$lang->validate()) {
            $result['status'] = 'error';
            $result['errors'] = $lang->get_validate_errors();
            return $result;
        }
        try {
            fx::log('saving', $lang);
            $lang->save();
            fx::log('svd', $lang);
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['text'][] = $e->getMessage();
        }

        return $result;
    }

    public function edit($input) {
        $lang_id = isset($input['id']) ? $input['id'] : isset($input['params'][0]) ? $input['params'][0] : null;
        $lang = fx::data('lang', $lang_id);

        $main_fields = array();
        $main_fields[] = $this->ui->input('en_name', fx::alang('Language name','system'), $lang['en_name']);
        $main_fields[] = $this->ui->input('native_name', fx::alang('Naitive name','system'), $lang['native_name']);
        $main_fields[] = $this->ui->input('lang_code', fx::alang('Language code','system'), $lang['lang_code']);
        $this->response->add_fields($main_fields);

        $fields = array();
        $fields[] = $this->ui->hidden('essence', 'lang');
        $fields[] = $this->ui->hidden('action', 'edit');
        $fields[] = $this->ui->hidden('posting');
        $fields [] = $this->ui->hidden('id', $lang['id']);
        $this->response->add_fields($fields);
        $this->response->add_form_button('save');

        $this->response->breadcrumb->add_item( fx::alang('Languages','system'), '#admin.lang.all');
        $this->response->breadcrumb->add_item($lang['en_name'], '#admin.lang.edit('.$lang['id'].')');
        $this->response->submenu->set_menu('lang');
    }

    public function edit_save($input) {
        
        $lang = fx::data('lang', $input['id']);
        $result = array('status' => 'ok');
        $params = array('en_name', 'native_name', 'lang_code');

        foreach ($params as $v) {
            if (isset($input[$v])) {
                $lang[$v] = $input[$v];
            }
        }

        $lang->save();
        return $result;
    }
    
    public function strings($input) {
        $lang_id = isset($input['id']) ? $input['id'] : isset($input['params'][0]) ? $input['params'][0] : null;
        $lang = fx::data('lang', $lang_id);
        
        $list = array('type' => 'list', 'filter' => false, 'tpl' => 'imgh', 'essence'=> 'lang', 'values' => array());
        $list['labels'] = array(
            'dict' => array(
                'label' => fx::alang('Dictionary', 'system'),
                'filter' => 'select'
            ),
            'string' => array(
                'label' => fx::alang('String','system'),
                'filter' => 'text'
            ),
            'value' => array(
                'label' => fx::alang('Value','system'),
                'filter' => 'text',
                'editable' => array(
                    'essence' => 'lang',
                    'action' => 'string',
                    'lang' => $lang_id
                )
            )
        );
        
        $strings = fx::data('lang_string')->order('dict')->order('string')->all();
        foreach ($strings as $s) {
            $list['values'][]= array(
                'id' => $s['id'],
                'dict' => $s['dict'],
                'string' => $s['string'],
                'value' => $s['lang_'.$lang['lang_code']]
            );
        }
        
        $fields = array('strings' => $list);
        $this->response->add_fields($fields);
        
        $lang_name = fx::config('ADMIN_LANG') == $lang['lang_code'] ? $lang['native_name'] : $lang['en_name'];
        
        $this->response->breadcrumb->add_item( fx::alang('Languages','system'), '#admin.lang.all');
        $this->response->breadcrumb->add_item($lang_name, '#admin.lang.edit('.$lang['id'].')');
        $this->response->breadcrumb->add_item( fx::alang('Language strings'), '#admin.lang.strings('.$lang['id'].')');
        $this->response->submenu->set_menu('lang');
    }
    
    public function string_save($input) {
        if (!isset($input['id'])) {
            return;
        }
        $str = fx::data('lang_string', $input['id']);
        if (!$str) {
            return;
        }
        $lang = fx::data('lang', $input['lang']);
        $str['lang_'.$lang['lang_code']] = $input['value'];
        $str->save();
    }
}