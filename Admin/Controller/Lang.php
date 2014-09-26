<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Lang extends Admin {

    public function all() {
        $langs = fx::data('lang')->all();

        $list = array('type' => 'list', 'filter' => false, 'tpl' => 'imgh', 'entity'=> 'lang');
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

        $this->response->addField($list);

        $this->response->addButtons(
            array(
                array(
                    'key' => 'add', 
                    'title' => fx::alang('Add new language','system'),
                    'url' => '#admin.administrate.lang.add'
                ),
                'delete'
            )
        );
        $this->response->breadcrumb->addItem( fx::alang('Languages','system') );
        $this->response->submenu->setMenu('lang');
    }

    public function add($input) {
        $fields = array();

        $fields[] = $this->ui->hidden('action', 'add_save');
        $fields[] = $this->ui->hidden('entity', 'lang');
        $fields[] = $this->ui->input('en_name', fx::alang('Language name','system'));
        $fields[] = $this->ui->input('native_name', fx::alang('Native language name','system'));
        $fields[] = $this->ui->input('lang_code', fx::alang('Language code','system'));

        $this->response->addFields($fields);
        $this->response->dialog->setTitle( fx::alang('Create a new language','system') );
        $this->response->breadcrumb->addItem( 
            fx::alang('Languages','system'),
            '#admin.administrate.lang.all'
        );
        $this->response->breadcrumb->addItem(
            fx::alang('Add new language','system')
        );
        $this->response->addFormButton('save');
        $this->response->submenu->setMenu('lang');
    }

    public function addSave($input) {
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
            $result['errors'] = $lang->getValidateErrors();
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
        $this->response->addFields($main_fields);

        $fields = array();
        $fields[] = $this->ui->hidden('entity', 'lang');
        $fields[] = $this->ui->hidden('action', 'edit');
        $fields[] = $this->ui->hidden('posting');
        $fields [] = $this->ui->hidden('id', $lang['id']);
        $this->response->addFields($fields);
        $this->response->addFormButton('save');

        $this->response->breadcrumb->addItem( fx::alang('Languages','system'), '#admin.lang.all');
        $this->response->breadcrumb->addItem($lang['en_name'], '#admin.lang.edit('.$lang['id'].')');
        $this->response->submenu->setMenu('lang');
    }

    public function editSave($input) {
        
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
        
        $list = array('type' => 'list', 'filter' => false, 'tpl' => 'imgh', 'entity'=> 'lang', 'values' => array());
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
                    'entity' => 'lang',
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
        $this->response->addFields($fields);
        
        $lang_name = fx::config('ADMIN_LANG') == $lang['lang_code'] ? $lang['native_name'] : $lang['en_name'];
        
        $this->response->breadcrumb->addItem( fx::alang('Languages','system'), '#admin.lang.all');
        $this->response->breadcrumb->addItem($lang_name, '#admin.lang.edit('.$lang['id'].')');
        $this->response->breadcrumb->addItem( fx::alang('Language strings'), '#admin.lang.strings('.$lang['id'].')');
        $this->response->submenu->setMenu('lang');
    }
    
    public function stringSave($input) {
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