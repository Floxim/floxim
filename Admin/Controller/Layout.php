<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Layout extends Admin
{

    /**
     * List all of layout design in development
     */
    public function all()
    {
        $items = array();

        $layouts = fx::data('layout')->all();
        foreach ($layouts as $layout) {
            $layout_id = $layout['id'];
            $items[$layout_id] = $layout;
        }

        $layout_use = array(); // [room layout][number of website] => 'website Name'
        foreach (fx::data('site')->all() as $site) {
            $layout_use[$site['layout_id']][$site['id']] = '<a href="#admin.site.settings(' . $site['id'] . ')">' . $site['name'] . '</a>';
        }

        $ar = array('type' => 'list', 'filter' => true, 'entity' => 'layout');
        $ar['labels'] = array('name'    => fx::alang('Name', 'system'),
                              'use'     => fx::alang('Used on', 'system'),
                              'buttons' => array('type' => 'buttons')
        );

        foreach ($items as $item) {
            $submenu = self::getTemplateSubmenu($item);
            $submenu_first = current($submenu);
            $name = array(
                'name' => $item['name'],
                'url'  => $submenu_first['url']
            );
            $el = array('id' => $item['id'], 'name' => $name);
            if (isset($layout_use[$item['id']])) {
                $el['use'] = join(', ', $layout_use[$item['id']]);
                $el['fx_not_available_buttons'] = array('delete');
            } else {
                $el['use'] = ' - ';
            }
            $el['buttons'] = array();

            foreach ($submenu as $submenu_item) {
                $el['buttons'][] = array(
                    'label' => $submenu_item['title'],
                    'url'   => $submenu_item['url']
                );
            }

            $ar['values'][] = $el;
        }

        $fields[] = $ar;

        $this->response->addButtons(array(
            array(
                'key'   => 'add',
                'title' => 'Add new layout',
                'url'   => '#admin.layout.add'
            ),
            'delete'
        ));

        $result = array('fields' => $fields);
        $this->response->submenu->setMenu('layout');
        return $result;
    }

    public function add($input)
    {
        $input['source'] = 'new';
        $fields = array(
            $this->ui->hidden('action', 'add'),
            $this->ui->hidden('entity', 'layout'),
            array('name' => 'name', 'label' => fx::alang('Layout name', 'system')),
            $this->getVendorField(),
            array('name' => 'keyword', 'label' => fx::alang('Layout keyword', 'system')),
            $this->ui->hidden('source', $input['source']),
            $this->ui->hidden('posting')
        );
        $this->response->submenu->setMenu('layout');
        $this->response->breadcrumb->addItem(fx::alang('Layouts', 'system'), '#admin.layout.all');
        $this->response->breadcrumb->addItem(fx::alang('Add new layout', 'system'));
        $this->response->addFormButton('save');
        $result['fields'] = $fields;
        return $result;
    }


    public function addSave($input)
    {
        $result = array('status' => 'ok');
        $keyword = trim($input['keyword']);
        $name = trim($input['name']);
        $vendor = trim($input['vendor']);

        if (empty($keyword)) {
            $keyword = fx::util()->strToKeyword($name);
        }

        //$keyword = $vendor.'.'.fx::util()->underscoreToCamel($keyword,true);
        $keyword = fx::util()->camelToUnderscore($vendor) . '.' . $keyword;

        $existing = fx::data('layout')->where('keyword', $keyword)->one();
        if ($existing) {
            return array(
                'status' => 'error',
                'text'   => sprintf(fx::alang('Layout %s already exists'), $keyword)
            );
        }

        $data = array('name' => $name, 'keyword' => $keyword);
        $layout = fx::data('layout')->create($data);
        try {
            $layout->save();
            $layout->scaffold();
            fx::trigger('layout_created', array('layout' => $layout));
            $result['reload'] = '#admin.layout.all';
        } catch (Exception $e) {
            $result['status'] = 'error';
        }
        return $result;
    }

    public function deleteSave($input)
    {
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


    public function operating($input)
    {
        $layout = fx::data('layout', $input['params'][0]); //->get_by_id($input['params'][0]);
        $action = isset($input['params'][1]) ? $input['params'][1] : 'layouts';

        if (!$layout) {
            $fields[] = $this->ui->error(fx::alang('Layout not found', 'system'));
            return array('fields' => $fields);
        }

        self::makeBreadcrumb($layout, $action, $this->response->breadcrumb);

        if (method_exists($this, $action)) {
            $result = call_user_func(array($this, $action), $layout);
        }

        $this->response->submenu->setMenu('layout-' . $layout['id'])->setSubactive($action);
        return $result;
    }

    public static function getThemeFields($site = null)
    {
        if ($site && $site['id'] === fx::env('site_id')) {
            $current_preview = fx::env()->getLayoutPreview();
            $current_variant = fx::env()->getLayoutStyleVariantId();
            $current_layout_id = fx::env('layout_id');
        } else {
            $current_preview = null;
            $current_variant = $site['style_variant_id'];
            $current_layout_id = $site['layout_id'];
        }

        $fields = array();

        $layouts = fx::data('layout')->all();
        $layouts_select = array();

        $variants = array();
        $variants_filter = array();

        $theme_styles = fx::data('style_variant')->where('style', 'theme.%', 'like')->all();

        foreach ($layouts as $layout) {

            $layouts_select[] = array(
                $layout['id'],
                $layout['name'] . ($current_preview == $layout['id'] ? ' ('.fx::alang('Preview').')' : '')
            );

            $style_name = 'theme.'.$layout['keyword'];
            $style_variants = $theme_styles->find('style', $style_name);
            foreach ($style_variants as $style_variant) {
                if (!isset($variants_filter[$style_variant['id']])) {
                    $variants []= array($style_variant['id'], $style_variant['name']);
                    $variants_filter[$style_variant['id']] = array();
                    if (!$current_variant) {
                        $current_variant = $style_variant['id'];
                    }
                }
                $variants_filter[$style_variant['id']] []= array('layout_id', $layout['id']);
            }
        }

        $variants []= array('__new', fx::alang('New').'...');

        $fields [] = array(
            'name'   => 'layout_id',
            'type'   => 'livesearch',
            'allow_empty' => false,
            'values' => $layouts_select,
            'value'  => $current_layout_id,
            'label'  => fx::alang('Theme', 'system')
        );



        $fields []= array(
            'name' => 'style_variant',
            'label' => 'Вариант стилей',
            'values' => $variants,
            'allow_empty' => false,
            'values_filter' => $variants_filter,
            'type' => 'livesearch',
            'value' => $current_variant ? $current_variant : 'default'
        );

        $fields []= array(
            'name' => 'new_variant_name',
            'type' => 'string',
            'label' => 'Название для нового стиля',
            'parent' => array(
                'style_variant' => '__new'
            )
        );
        return $fields;
    }
    
    public function changeTheme($input) {
        $fields = array();

        $current_preview = fx::env()->getLayoutPreview();

        $fields = array_merge($fields, self::getThemeFields(fx::env('site')));
        
        $fields[]= $this->ui->hidden('settings_sent', 'true');
        $fields[]= $this->ui->hidden('entity', 'layout');
        $fields[]= $this->ui->hidden('action', 'change_theme');
        
        $real_layout_id = fx::env('site')->get('layout_id');
        
        if ($current_preview) {    
            $fields [] = array(
                'type'  => 'button',
                'role'  => 'preset',
                'label' => fx::alang('Cancel preview', 'system'),
                'data'  => array(
                    'layout_id' => $real_layout_id
                ),
                'parent' => array('layout_id' => '!='.$real_layout_id),
                'submit' => true
            );
        }
        
        if (isset($input['settings_sent'])) {
            $style_variant_id = $input['style_variant'];
            if ($style_variant_id === '__new') {
                $style_variant_name = $input['new_variant_name'];
                $style_name = 'theme.'.fx::env('layout')->get('keyword');
                $new_variant = fx::data('style_variant')->create(
                    array(
                        'name' => $style_variant_name,
                        'style' => $style_name,
                        'less_vars' => fx::env()->getLayoutStyleVariant()->getLessVars()
                    )
                );
                $new_variant->save();
                $style_variant_id = $new_variant['id'];
            }
            if ($input['pressed_button'] == 'preview') {
                fx::env()->setLayoutPreview($input['layout_id'], $style_variant_id);
            } else {
                $site = fx::env('site');
                $site['layout_id'] = $input['layout_id'];
                
                $site['style_variant_id'] = $style_variant_id;
                $site->save();
                if ($current_preview) {
                    fx::env()->setLayoutPreview(false);
                }
            }
            return array(
                'status' => 'ok',
                'reload' => true
            );
        }
        
        $this->response->addFormButton(array(
            'key'   => 'preview',
            'label' => fx::alang('Preview', 'system')
        ));
        
        $this->response->addFormButton(array(
            'key'   => 'save',
            'label' => fx::alang('Save', 'system')
        ));
        
        return array(
            'fields' => $fields,
            'header' => fx::alang('Change theme', 'system'),
            'view'   => 'horizontal'
        );
    }
    
    public function themeSettings($input) 
    {
        $fields = array(
            $this->ui->hidden('entity', 'layout'),
            $this->ui->hidden('action', 'theme_settings'),
            $this->ui->hidden('sent', 1)
        );
        $palette = fx::env('theme')->get('palette');
        
        if (isset($input['sent']) && $input['sent']) {
            $params = $palette->paramsFromInput($input);
            $palette['params'] = $params;
            $palette->save();
            
            fx::util()->dropCache('assets');
            
            return array(
                'status' => 'ok',
                'reload' => true
            );
        }
        
        $theme_form = $palette->getForm();
        $res = array(
            'fields' => array_merge($fields, $theme_form['fields']),
            'header' => fx::alang('Theme settings', 'system'),
            'view'   => 'horizontal',
            'tabs' => $theme_form['tabs']
        );
        return $res;
    }
    
    protected function prepareStyleVar($props)
    {
        if ($props['units'] && $props['value']) {
            $props['value'] = preg_replace("~[^\d\.]+~", '', $props['value']);
        }
        return $props;
    }
    
    public function styleSettings($input)
    {
        $fields = $this->getHiddenFields(array('style', 'block', 'style_variant_id'));
        
        $style_variant_id = isset($input['style_variant_id']) ? $input['style_variant_id'] : null;
        $save_as_new = isset($input['save_as_new']) ? (bool) $input['save_as_new'] : false;

        $style_variant = 
             $style_variant_id && !$save_as_new
                ? fx::data('style_variant', $style_variant_id)
                : fx::data('style_variant')->create(
                    array(
                        'block' => $input['block'],
                        'style' => $input['style'],
                        'theme_id' => fx::env('theme_id')
                    )
                );

        fx::log($input);
        $is_new = !$style_variant->isSaved();
        
        $input['style'] = preg_replace("~_variant_[^_]+$~", '', $input['style']);
        
        $bundle = fx::assets('style', $input['block'].'_'.$input['style']);
        
        $style = $bundle->getStyleMeta();
        
        $is_sent = $this->isSent();
        $less_vars = $style_variant['less_vars'];
                
        if (!isset($style['vars'])) {
            $style['vars'] = array();
        }
        
        foreach ($style['vars'] as $var => $props) {
            $props['name'] = $var;
            if (isset($less_vars[$var])) {
                $props['value'] = $less_vars[$var];
            }
            $props = $this->prepareStyleVar($props);
            $fields []= $props;
            if ($is_sent && isset($input[$var])) {
                $less_vars[$var] = $input[$var];
                if (isset($props['units'])) {
                    $less_vars[$var]  .= $props['units'];
                }
            }
        }
        
        if ($is_sent) {
            if ($style_variant['id'] && isset($input['pressed_button']) && $input['pressed_button'] === 'delete') {
                $style_variant->delete();
                $id = '';
            } else {
                $style_variant['less_vars'] = $less_vars;
                $style_variant['name'] = $input['style_name'];
                if (isset($input['screenshot'])) {
                    $style_variant->setPayload('screenshot', $input['screenshot']);
                }
                $style_variant->save();
                $id = $style_variant['id'];
            }
            
            fx::page()->getBundleManager()->getBundle('css', 'default')->delete();
            
            return array(
                'id' => $id,
                'name' => $style_variant['name'],
                'saved_as_new' => $save_as_new,
                'variants' => \Floxim\Floxim\Asset\Less\StyleBundle::collectStyleVariants($input['block'])
            );
        }
        
        $fields[]= array(
            'name' => 'style_name',
            'tab' => 'header',
            'type' => 'string',
            'label' => false,
            'placeholder' => 'название стиля',
            'autocomplete' => false,
            'value' => $style_variant->getReal('name')
        );
        
        if (!$is_new) {
            $fields[]= array(
                'name' => 'save_as_new',
                'tab' => 'header',
                'label' => 'Сохранить как новый',
                'type' => 'checkbox',
                'view_context' => ''
            );
        }
        
        $mixin_name = substr($bundle->getMixinName(), 1);
        
        $is_saved = $style_variant->isSaved();
        
        $res = array(
            'tweaker' => array(
                'tweaker_file' => $bundle->getTweakerLessFile(),
                'rootpath' => fx::path()->http( dirname($style['file']) ) . '/',
                'tweaked_vars' => array_keys($style['vars']),
                'mixin_name' => $mixin_name,
                'style_class' => $style_variant['block'].'_style_'.$style_variant['style']. ($is_saved ? '--'.$style_variant['id'] : ''),
                'is_new' => !$is_saved,
                'container' => isset($style['container']) ? $style['container'] : array()
            ),
            'fields' => $fields,
            'header' => $is_new ? 'Создаем новый стиль' : 'Настраиваем стиль'
        );
        if ($style['tabs']) {
            $res['tabs'] = $style['tabs'];
        } else {
            $res['tabs'] = array('_default' => 'Стиль');
        }
        
        if (!$is_new) {
            $using_blocks = $style_variant->findUsingBlocks();
            
            //$using_blocks = fx::data('infoblock')->all();
            
            $res['tabs']['usage_scope'] = 'Где используется ('.count($using_blocks).')';
            
            $using_list = $using_blocks->getValues(
                function($ib) {
                    return $ib->getSummary();
                }
            );
            
            $res['fields'][]= array(
                'name' => 'using_block_list',
                'type' => 'infoblock_list',
                'value' => $using_list,
                'tab' => 'usage_scope'
            );
        }
        
        if ($style_variant['id']) {
            $this->response->addFormButton(array('class' => 'delete', 'key' => 'delete', 'label' => fx::alang('Delete')));
            $this->response->addFormButton(array('key' => 'save'));
        }
        return $res;
    }

    public static function makeBreadcrumb($template, $action, $breadcrumb)
    {
        $tpl_submenu = self::getTemplateSubmenu($template);
        $tpl_submenu_first = current($tpl_submenu);

        $breadcrumb->addItem(fx::alang('Layouts', 'system'), '#admin.layout.all');
        $breadcrumb->addItem($template['name'], $tpl_submenu_first['url']);
        $breadcrumb->addItem($tpl_submenu[$action]['title'], $tpl_submenu[$action]['url']);
    }

    public function layouts($template)
    {
        $items = $template->get_layouts();

        $ar = array('type' => 'list', 'filter' => true);
        $ar['labels'] = array('name' => fx::alang('Name', 'system'));

        foreach ($items as $item) {
            $name = array('name' => $item['name'], 'url' => 'layout.edit(' . $item['id'] . ')');
            $el = array('id' => $item['id'], 'name' => $name);
            $ar['values'][] = $el;
        }

        $fields[] = $ar;
        $buttons = array("add", "delete");
        $buttons_action['add']['options']['parent_id'] = $template['id'];
        $result = array('fields'         => $fields,
                        'buttons'        => $buttons,
                        'buttons_action' => $buttons_action,
                        'entity'         => 'layout'
        );
        return $result;
    }

    public static function getTemplateSubmenu($layout)
    {
        $titles = array(
            'settings' => fx::alang('Settings', 'system'),
            'source'   => "Source"
        );

        $layout_id = $layout['id'];
        $items = array();
        foreach ($titles as $key => $title) {
            $items [$key] = array(
                'title' => $title,
                'code'  => $key,
                'url'   => 'layout.operating(' . $layout_id . ',' . $key . ')'
            );
        }
        return $items;
    }

    // todo: not used method?
    public function files($template)
    {
        $params = isset($this->input['params']) ? $this->input['params'] : array();

        $fm_action = isset($params[2]) ? $params[2] : 'ls';
        $fm_path = isset($params[3]) ? $params[3] : '';
        // todo: psr0 need verify - class fx_controller_admin_module_filemanager not found
        $filemanager = new fx_controller_admin_module_filemanager($fm_input, $fm_action, true);
        $path = $template->getPath();
        $fm_input = array(
            'base_path'         => $path,
            'path'              => $fm_path,
            'base_url_template' => '#admin.template.operating(' . $template['id'] . ',files,#action#,#params#)',
            'root_name'         => $template['name'],
            'file_filters'      => array('!~^\.~', '!~\.php$~'),
            'breadcrumb_target' => $this->response->breadcrumb
        );
        $result = $filemanager->process();
        $result['buttons_entity'] = 'module_filemanager';
        return $result;
    }

    public function settings($template)
    {
        $fields[] = $this->ui->input('name', fx::alang('Layout name', 'system'), $template['name']);
        $fields[] = array(
            'name'     => 'keyword',
            'label'    => fx::alang('Layout keyword', 'system'),
            'value'    => $template['keyword'],
            'disabled' => true
        );
        $fields[] = $this->ui->hidden('action', 'settings');
        $fields[] = $this->ui->hidden('id', $template['id']);

        $this->response->submenu->setMenu('layout');
        $result = array('fields' => $fields, 'form_button' => array('save'));
        return $result;
    }

    public function settingsSave($input)
    {
        $name = trim($input['name']);
        if (!$name) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Enter the layout name', 'system');
            $result['fields'][] = 'name';
        } else {
            $template = fx::data('template')->getById($input['id']);
            if ($template) {
                $result['status'] = 'ok';
                $template->set('name', $name)->save();
            } else {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('Layout not found', 'system');
            }
        }

        return $result;
    }
    
    public function getStyleVariants($input)
    {
        $res = array();
        foreach ($input['blocks'] as $block) {
            if ($block['is_inline'] === 'true') {
                
                $bundle_id = $block['block'].'_default';
                
                $bundle = fx::assets('style', $bundle_id);
                $style = $bundle->getStyleMeta();
                $fields = $bundle->getFormFields($block['value']);
                $res []= array(
                    'type' => 'group',
                    'style' => 'transparent',
                    'fields' =>  $fields,
                    'tweaker' => array(
                        'tweaker_file' => $bundle->getTweakerLessFile(),
                        'rootpath' => $bundle->getRootPath(),
                        //'is_new' => true,
                        'vars' => array_keys($style['vars']),
                        'mixin_name' => substr($bundle->getMixinName(), 1),
                        //'style_class' => $block['block'].'_style_'.$block['mod_value'],
                        //'style_id' => $block['style_id'],
                        'container' => isset($style['container']) ? $style['container'] : array()
                    )
                );
            } else {
                $res []= \Floxim\Floxim\Asset\Less\StyleBundle::collectStyleVariants($block['block']);
            }
        }
        return array(
            'variants' => $res
        );
    }
    
    public function saveInlineStyle($input)
    {
        $vis = fx::data('infoblock_visual', $input['visual_id']);
        $vis->digSet('template_visual.'.$input['prop'], $input['value']);
        $vis->save();
    }


    public function source($layout)
    {
        $template = fx::template('theme.' . $layout['keyword']);
        $vars = $template->getTemplateVariants();
        $files = array();
        foreach ($vars as $var) {
            $files[preg_replace("~^.+/~", '', $var['file'])] = $var['file'];
        }
        foreach ($files as $file => $path) {
            $tab_code = md5($file);//preg_replace("~\.~", '_', $file);
            $tab_name = fx::path()->fileName($file);
            $source = file_get_contents($path);
            $this->response->addTab($tab_code, $tab_name);
            $this->response->addFields(array(
                array(
                    'type'  => 'text',
                    'code'  => 'htmlmixed',
                    'name'  => 'source_' . $tab_code,
                    'value' => $source
                )
            ), $tab_code);
        }
        $fields = array(
            $this->ui->hidden('entity', 'layout'),
            $this->ui->hidden('action', 'source')
        );
        $this->response->submenu->setMenu('layout');
        $this->response->addFormButton('save');
        return array('fields' => $fields, 'form_button' => array('save'));
    }
}