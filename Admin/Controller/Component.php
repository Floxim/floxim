<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Component extends Admin
{

    /**
     * A list of all components
     */
    public function all()
    {
        $entity = $this->entity_type;
        $finder = fx::data($entity);

        $tree = $finder->getTree();

        $field = array('type' => 'list', 'filter' => true);
        $field['labels'] = array(
            'name'    => fx::alang('Name', 'system'),
            'keyword' => fx::alang('Keyword'),
            'count'   => fx::alang('Count', 'system'),
            'buttons' => array('type' => 'buttons')
        );
        $field['values'] = array();
        $field['entity'] = $entity;

        $append_coms = function ($coll, $level) use (&$field, &$append_coms) {
            foreach ($coll as $v) {
                $submenu = Component::getComponentSubmenu($v);
                $submenu_first = current($submenu);
                try {
                    $items_count = fx::db()->getCol("SELECT count(*) from  {{" . $v->getContentTable() . "}}");
                } catch (\Exception $e) {
                    $items_count = 0;
                }
                $r = array(
                    'id'      => $v['id'],
                    'keyword' => $v['keyword'],
                    'count'   => $items_count,
                    'name'    => array(
                        'name'  => $v['name'],
                        'url'   => $submenu_first['url'],
                        'level' => $level
                    )
                );

                $r['buttons'] = array();
                foreach ($submenu as $submenu_item_key => $submenu_item) {
                    if (!$submenu_item['parent']) { // && $submenu_item_key != 'fields') {
                        $r['buttons'] [] = array(
                            'type'  => 'button',
                            'label' => $submenu_item['title'],
                            'url'   => $submenu_item['url']
                        );
                    }
                }
                $field['values'][] = $r;
                if (isset($v['children']) && $v['children']) {
                    $append_coms($v['children'], $level + 1);
                }
            }
        };

        $append_coms($tree, 0);

        $fields[] = $field;

        $this->response->addButtons(array(
            array(
                'key'   => "add",
                'title' => fx::alang('Add new ' . $entity, 'system'),
                'url'   => '#admin.' . $entity . '.add'
            ),
            "delete"
        ));

        $result = array('fields' => $fields);

        $this->response->breadcrumb->addItem(self::entityTypes($entity), '#admin.' . $entity . '.all');
        $this->response->submenu->setMenu($entity);
        return $result;
    }

    public function getComponentSubmenu($component)
    {
        $entity_code = $component instanceof \Floxim\Floxim\Component\Component\Entity ? 'component' : 'widget';

        $titles = array(
            'component' => array(
                'fields'    => fx::alang('Fields', 'system'),
                'settings'  => fx::alang('Settings', 'system'),
                'items'     => fx::alang('Items', 'system'),
                'templates' => fx::alang('Templates', 'system')
            ),
            'widget'    => array(
                'settings'  => fx::alang('Settings', 'system')//,
                //'templates' => fx::alang('Templates', 'system')
            )
        );

        $res = array();
        foreach ($titles[$entity_code] as $code => $title) {
            $res[$code] = array(
                'title'  => $title,
                'code'   => $code,
                'url'    => $entity_code . '.edit(' . $component['id'] . ',' . $code . ')',
                'parent' => null
            );
        }
        return $res;
    }

    protected function getComponentTemplates($ctr_entity)
    {
        $controller_name = $ctr_entity['keyword'];
        $controller = fx::controller($controller_name);
        $actions = $controller->getActions();
        $templates = array();
        foreach (array_keys($actions) as $action_code) {
            $action_controller = fx::controller($controller_name . ':' . $action_code);
            $action_templates = $action_controller->getAvailableTemplates();
            foreach ($action_templates as $atpl) {
                $templates[$atpl['full_id']] = $atpl;
            }
        }
        return fx::collection($templates);
    }

    public function getModuleFields()
    {
        $fields = array();
        $vf = $this->getVendorField();
        $fields [] = $vf;
        $vendors = $vf['values'];
        $module_field = array(
            'label' => 'Module',
            'type' => 'livesearch',
            'name' => 'module',
            'join_with' => 'vendor',
            'join_type' => 'line',
            'hidden_on_one_value' => true
        );
        $filter = array();
        $modules = array();
        foreach ($vendors as $vendor_val) {
            $v =  $vendor_val[0];
            $path = fx::path()->abs('/module/' . $v) . '/*';
            $module_dirs = glob($path);
            
            if (!$module_dirs) {
                continue;
            }
            $vendor_modules = array();
            if ($v === 'Floxim') {
                $vendor_modules []= array('System', '[System]');
            }
            foreach ($module_dirs as $md) {
                if (!is_dir($md)) {
                    continue;
                }
                $md = fx::path()->fileName($md);
                $module_keyword = fx::util()->camelToUnderscore($md);
                $vendor_modules[]= array($module_keyword, $md);
            }
            
            foreach ($vendor_modules as $vm) {
                $module_keyword = $vm[0];
                if (!isset($filter[$module_keyword])) {
                    $filter[$module_keyword] = array(
                        array(
                            'vendor',
                            array()
                        )
                    );
                }
                $filter[$module_keyword][0][1][]= $v;
                $modules[]= $vm;
            }
        }
        $modules [] = array('new', '-- New --');
        $module_field['values'] = $modules;
        $module_field['values_filter'] = $filter;
        $fields []= $module_field;
        $fields [] = array(
            'type'   => 'string',
            'label'  => 'New module name',
            'name'   => 'new_module',
            'parent' => array('module' => 'new')
        );
        return $fields;
    }

    protected function getFullKeyword($input)
    {
        $keyword = trim($input['keyword']);

        if (!$keyword && $input['name']) {
            $keyword = fx::util()->strToKeyword($input['name']);
        }
        
        $vendor = $input['vendor'];
        $module = $input['module'];
        
        if ($module === 'new') {
            $module = fx::util()->strToKeyword($input['new_module']);
        }

        $parts = array(
            'vendor' => $vendor,
            'module' => $module,
            'keyword' => $keyword
        );
        
        foreach ($parts as &$p) {
            $p = fx::util()->camelToUnderscore($p);
        }
        
        // special system components have "vendor.module" no prefix
        if ($parts['vendor'] === 'floxim' && $parts['module'] === 'system') {
            return $parts['keyword'];
        }
        
        return join(".", $parts);
    }

    public function add($input)
    {

        $fields = array(
            $this->ui->hidden('action', 'add'),
            array(
                'label' => fx::alang('Component name', 'system'),
                'name'  => 'name'
            ),
            array(
                'label' => fx::alang('Keyword', 'system'),
                'name'  => 'keyword'
            ),
            array(
                'label' => fx::alang('Is abstract?', 'system'),
                'name' => 'is_abstract',
                'type' => 'checkbox'
            )
        );

        foreach ($this->getModuleFields() as $mf) {
            $fields [] = $mf;
        }

        $fields[] = $this->ui->hidden('source', $input['source']);
        $fields[] = $this->ui->hidden('posting');
        $fields[] = $this->getParentComponentField();

        $entity = $this->entity_type;
        $fields[] = $this->ui->hidden('entity', $entity);

        $this->response->breadcrumb->addItem(self::entityTypes($entity), '#admin.' . $entity . '.all');
        $this->response->breadcrumb->addItem(fx::alang('Add new ' . $entity, 'system'));

        $this->response->submenu->setMenu($entity);
        $this->response->addFormButton('save');

        return array('fields' => $fields);
    }

    public function edit($input)
    {

        $entity_code = $this->entity_type;

        $component = fx::data($entity_code)->getById($input['params'][0]);

        $action = isset($input['params'][1]) ? $input['params'][1] : 'settings';

        self::makeBreadcrumb($component, $action, $this->response->breadcrumb);
        $action = fx::util()->underscoreToCamel($action, false);
        if (method_exists($this, $action)) {
            $result = call_user_func(array($this, $action), $component, $input);
        }
        $result['tree']['mode'] = $entity_code . '-' . $component['id'];
        $this->response->submenu->setMenu($entity_code . '-' . $component['id']);
        
        return $result;
    }

    protected static function entityTypes($key = null)
    {
        $arr = array(
            'widget'    => fx::alang('Widgets', 'system'),
            'component' => fx::alang('Components', 'system')
        );
        return (empty($key) ? $arr : $arr[$key]);
    }
    
    protected static function getEntityType() 
    {
        $c = get_called_class();
        return preg_match("~Component~", $c) ? 'component' : 'widget';
    }

    public static function makeBreadcrumb($component, $action, $breadcrumb)
    {
        // todo: psr0 need verify
        $entity_code = static::getEntityType(); 
        //fx::getComponentNameByClass(get_class($component));
        $submenu = self::getComponentSubmenu($component);
        $submenu_first = current($submenu);
        $breadcrumb->addItem(self::entityTypes($entity_code), '#admin.' . $entity_code . '.all');
        $breadcrumb->addItem($component['name'], $submenu_first['url']);
        if (isset($submenu[$action])) {
            $breadcrumb->addItem($submenu[$action]['title'], $submenu[$action]['url']);
        }
    }

    public function addSave($input)
    {
        $result = array('status' => 'ok');

        $data['name'] = trim($input['name']);


        $data['keyword'] = $this->getFullKeyword($input);
        
        $data['parent_id'] = $input['parent_id'];

        $res_create = fx::data('component')->createFull($data);
        if (!$res_create['validate_result']) {
            $result['status'] = 'error';
            $result['errors'] = $res_create['validate_errors'];
            return $result;
        }
        if ($res_create['status'] == 'successful') {
            $component = $res_create['component'];
            $result['reload'] = '#admin.component.edit(' . $component['id'] . ',fields)';
        } else {
            $result['status'] = 'error';
            $result['text'][] = $res_create['error'];
        }

        return $result;
    }

    public function editSave($input)
    {
        if (!($component = fx::data('component', $input['id']))) {
            return;
        }
        if (!empty($input['name'])) {
            $component['name'] = $input['name'];
        }
        $component['declension'] = $input['declension'];
        $component['is_abstract'] = $input['is_abstract'];
        $component->save();
        return array('status' => 'ok');
    }

    public function importSave($input)
    {
        $file = $input['importfile'];
        if (!$file) {
            $result = array('status' => 'error');
            $result['text'][] = fx::alang('Error creating a temporary file', 'system');
        }

        $result = array('status' => 'ok');
        try {
            // todo: psr0 need fix - class fx_import not found
            $imex = new fx_import();
            $imex->import_by_file($file['tmp_name']);
        } catch (Exception $e) {
            $result = array('status' => 'ok');
            $result['text'][] = $e->getMessage();
        }

        return $result;
    }

    public function fields($component)
    {
        $controller = new Field(array(
            'entity'    => $component,
            'do_return' => true
        ), 'items');
        $this->response->submenu->setSubactive('fields');
        return $controller->process();
    }

    public function addField($component)
    {
        $controller = new Field(array(
            'component_id'     => $component['id'],
            //'to_entity' => 'component',
            'do_return' => true
        ), 'add');
        $this->response->breadcrumb->addItem(fx::alang('Fields', 'system'),
            '#admin.component.edit(' . $component['id'] . ',fields)');
        $this->response->breadcrumb->addItem(fx::alang('Add new field', 'system'));
        return $controller->process();
    }

    public function templates($component, $input)
    {
        // todo: psr0 need verify
        $ctr_type = fx::getComponentNameByClass(get_class($component));
        $this->response->submenu->setSubactive('templates');
        if (isset($input['params'][2])) {
            $res = $this->template(array('template_full_id' => $input['params'][2]));
            if ($res === false) {
                return array('reload' => '#admin.'.$ctr_type.'.edit('. $input['params'][0].',templates)');
            } 
            return $res;
        }
        $templates = $this->getComponentTemplates($component);
        $visuals = fx::data('infoblock_visual')->where('template', $templates->getValues('full_id'))->all();
        $field = array('type' => 'list', 'filter' => true);
        $field['labels'] = array(
            'name'      => fx::alang('Name', 'system'),
            'action'    => fx::alang('Action', 'system'),
            'inherited' => fx::alang('Inherited', 'system'),
            'source'    => fx::alang('Source', 'system'),
            'file'      => fx::alang('File', 'system'),
            'used'      => fx::alang('Used', 'system')
        );
        $field['values'] = array();
        foreach ($templates as $tpl) {
            $r = array(
                'id'     => $tpl['full_id'],
                'name'   => array(
                    'name' => $tpl['name'],
                    'url'  => $ctr_type . '.edit(' . $component['id'] . ',templates,' . $tpl['full_id'] . ')',
                ),
                'action' => preg_replace("~^.+\:~", '', $tpl['of']), // todo: psr0 verify with multiple fx:of
                'used'   => count($visuals->find('template', $tpl['full_id']))
            );
            /*
            $owner_ctr_match = null;
            preg_match("~^(component_|widget_)?(.+?)\..+$~", $tpl['of'], $owner_ctr_match);
            $owner_ctr = $owner_ctr_match ? $owner_ctr_match[2] : null;

            if ($owner_ctr == $component['keyword']) {
                $r['inherited'] = ' ';
            } else {
                $r['inherited'] = $owner_ctr;
            }
            // example: theme.floxim.phototeam:featured_news_list
            if (preg_match("#^theme\.(\w+)\.(\w+)\:.+$#i", $tpl['full_id'], $match)) {
                // todo: psr0 need use $match[1] for define vendor template
                $r['source'] = fx::data('layout')->where('keyword',
                        $match[1] . '.' . $match[2])->one()->get('name') . ' (layout)';
            } else {
                // todo: psr0 need verify
                $c_parts = fx::getComponentParts($tpl['full_id']);
                $r['source'] = fx::data($ctr_type, $c_parts['vendor'].'.'.$c_parts['module'].'.'.$c_parts['component'])->get('name');
            }
            */
            $r['file'] = fx::path()->http($tpl['file']);
            $field['values'][] = $r;
        }
        return array('fields' => array('templates' => $field));
    }

    public function getTemplateInfo($full_id)
    {
        $tpl = fx::template($full_id);
        if (!$tpl) {
            return;
        }
        $info = $tpl->getInfo();
        if (!isset($info['file']) || !isset($info['offset'])) {
            return;
        }
        $res = array();
        $source = file_get_contents(fx::path()->abs($info['file']));
        $res['file'] = $info['file'];
        $res['hash'] = md5($source);
        $res['full'] = $source;
        $offset = explode(',', $info['offset']);
        $length = $offset[1] - $offset[0];
        
        
        $res['start'] = $offset[0];
        $res['length'] = $length;
        
        $source_part = $res['source'] = mb_substr($source, $offset[0], $length);
        
        $first_part = mb_substr($source, 0, $res['start']);
        
        $res['first_line'] = count(explode("\n", $first_part));
        
        $space_tale = '';
        if (preg_match("~[ \t]+$~", $first_part, $space_tale)) {
            $space_tale = $space_tale[0];
            $tale_length = strlen($space_tale);
            $lines = explode("\n", $source_part);
            foreach ($lines as &$l) {
                if (substr($l, 0, $tale_length) === $space_tale) {
                    $l = substr($l, $tale_length);
                }
            }
            $source_part = join("\n", $lines);
        }
        $res['common_spaces'] = $space_tale;
        $res['source'] = $source_part;
        
        return $res;
    }

    public function template($input)
    {
        $template_full_id = $input['template_full_id'];
        $this->response->breadcrumb->addItem($template_full_id);
        $info = $this->getTemplateInfo($template_full_id);
        if (!$info) {
            return false;
        }
        $fields = array(
            $this->ui->hidden('entity', 'component'),
            $this->ui->hidden('action', 'template'),
            $this->ui->hidden('data_sent', '1'),
            $this->ui->hidden('template_full_id', $template_full_id),
            $this->ui->hidden('hash', $info['hash']),
            $this->ui->hidden('common_spaces', $info['common_spaces']),
            'file_path' => array(
                'type' => 'html',
                'html' => fx::alang('File').' <b>'.$info['file'].'</b>'.
                          '<br />'.fx::alang('Starting from line').' <b>'.$info['first_line'].'</b>'
            ),
            'source' => array(
                'type'  => 'text',
                'value' => $info['source'],
                'name'  => 'source',
                'code'  => 'htmlmixed'
            )
        );
        $this->response->addFields($fields);
        $this->response->addFormButton('save');
        if ($input['data_sent']) {
            return $this->templateSave($input);
        }
    }

    public function templateSave($input)
    {
        $info = $this->getTemplateInfo($input['template_full_id']);
        if (!$info) {
            return;
        }
        if ($info['hash'] !== $input['hash']) {
            die("Hash error");
        }
        $res = mb_substr($info['full'], 0, $info['start']);
        $input_source = $input['source'];
        $spaces = $input['common_spaces'];
        if (!empty($spaces)) {
            $lines = explode("\n", $input_source);
            foreach ($lines as $num => &$line) {
                if ($num === 0) {
                    continue;
                }
                $line = $spaces.$line;
            }
            $input_source = join("\n", $lines);
        }
        $res .= $input_source;
        $res .= mb_substr($info['full'], $info['start'] + $info['length']);
        fx::files()->writefile($info['file'], $res);
        return array('status' => 'ok');
    }

    protected function getParentComponentField($component = null)
    {
        $field = array(
            'label'  => fx::alang('Parent component', 'system'),
            'name'   => 'parent_id',
            'type'   => 'livesearch',
            'values' => array() //array('' => fx::alang('--no--','system'))
        );
        $c_finder = fx::data('component');
        if ($component) {
            $c_finder->where('id', $component['id'], '!=');
            $field['value'] = $component['parent_id'];
        }
        $field['values'] = $c_finder->getSelectValues();
        return $field;
    }

    public function settings($component)
    {
        $fields[] = array(
            'label'    => fx::alang('Keyword:', 'system'),
            'disabled' => 'disabled',
            'value'    => $component['keyword']
        );
        $fields[] = array(
            'label' => fx::alang('Component name', 'system'),
            'name'  => 'name',
            'value' => $component['name']
        );
        
        $fields []= array(
            'label' => fx::alang('Is abstract?', 'system'),
            'name' => 'is_abstract',
            'type' => 'checkbox',
            'value' => $component['is_abstract']
        );
        
        $lang = fx::data('lang')->where('lang_code', fx::alang()->getLang())->one();
        $decl = $lang->getDeclensionField($component['declension']);
        $decl['name'] = 'declension';
        $decl['label'] = fx::alang('Declension');
        $fields[]= $decl;

        $fields[] = array('type' => 'hidden', 'name' => 'phase', 'value' => 'settings');
        $fields[] = array('type' => 'hidden', 'name' => 'id', 'value' => $component['id']);

        $this->response->submenu->setSubactive('settings');
        $fields[] = $this->ui->hidden('entity', 'component');
        $fields[] = $this->ui->hidden('action', 'edit_save');

        return array('fields' => $fields, 'form_button' => array('save'));
    }

    public function editField($component)
    {
        
        $component_id = $this->input['params'][0];
        $field_id = $this->input['params'][2];
        
        $ctx = isset($this->input['field_context']) ? $this->input['field_context'] : array();
        
        $field = fx::data('field', $field_id);
        
        $controller = new Field();
        
        $ctr_params = array_merge(
            array('infoblock_id' => null, 'entity_id' => null, 'entity_type' => null),
            array(
                'component_id' => $component_id,
                'field_id' => $field_id
            ),
            $ctx
        );
        
        $result = $controller->edit($ctr_params);
        
        $submenu = self::getComponentSubmenu($component);
        $this->response->breadcrumb->addItem($submenu['fields']['title'], $submenu['fields']['url']);

        $this->response->breadcrumb->addItem($field['name']);

        $this->response->submenu->setSubactive('field-' . $field_id);

        return $result;
    }

    public function items($component, $input)
    {
        $this->response->submenu->setSubactive('items');
        $ctr = new Content(array(
            'content_type' => $component['keyword'],
            'do_return'    => true,
            'page' => isset($input['params'][2]) ? $input['params'][2] : 1,
            'url_template' => '#admin.component.edit('.$component['id'].',items[[,#page_number#]])'
        ), 'all');
        $res = $ctr->process();
        $this->response->addButtons(array(
            array(
                'key'   => "add",
                'title' => 'Add new ' . $component['keyword'],
                'url'   => '#admin.component.edit(' . $component['id'] . ',add_item)'
            ),
            array(
                'key' => "delete",
                'params' => array('content_type' => $component['keyword'])
            )
        ));
        foreach ($res['fields'][0]['values'] as &$item) {
            $url = '#admin.component.edit(' . $component['id'] . ',edit_item,' . $item['id'] . ')';
            $item['id'] = array('url' => $url, 'name' => $item['id']);
            //$item['id'] = '<a href="">'.$item['id'].'</a>';
        }
        return $res;
    }

    public function addItem($component, $input)
    {
        $items_url = '#admin.component.edit(' . $component['id'] . ',items)';
        $this->response->submenu->setSubactive('items');
        $this->response->breadcrumb->addItem(fx::alang('Items'), $items_url);
        $this->response->breadcrumb->addItem(fx::alang('Add'));
        $ctr = new Content(array(
            'content_type' => $component['keyword'],
            'mode'         => 'backoffice',
            'reload_url'   => $items_url,
            'do_return'    => true
        ), 'add_edit');
        $res = $ctr->process();
        return $res;
    }

    public function editItem($component, $input)
    {
        $this->response->submenu->setSubactive('items');
        $items_url = '#admin.component.edit(' . $component['id'] . ',items)';
        $this->response->breadcrumb->addItem(fx::alang('Items'), $items_url);
        $this->response->breadcrumb->addItem(fx::alang('Edit'));
        $ctr = new Content(array(
            'content_type' => $component['keyword'],
            'content_id'   => $input['params'][2],
            'reload_url'   => $items_url,
            'mode'         => 'backoffice',
            'do_return'    => true
        ), 'add_edit');
        $res = $ctr->process();
        return $res;
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
                $component = fx::data($es, $id);
                $component->delete();
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }
}