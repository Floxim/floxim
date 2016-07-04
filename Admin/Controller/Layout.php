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
        fx::env('css_bundle', 'default');
        // run current page rendering and extract css files
        $ib = fx::router('front')->getLayoutInfoblock(fx::env('page'));
        fx::router('infoblock')->route('/~ib/'.$ib['id'].'@'.fx::env('page')->get('id'));
        fx::page()->getCssFilesFinal();
        
        fx::env('css_bundle', 'admin');
        
        $style = fx::env()->getLayoutStyleVariant();
        
        $params = $style->getLessVars();
        
        $bundler = fx::page()->getBundleManager();
        $bundle = $bundler->getBundle('css', 'default');
        $bundle->save();
        
        $meta = $bundle->getMeta();
        
        
        $tweak_file = $bundle->getTweakerLessFile();
        
        $fields = array(
            $this->ui->hidden('entity', 'layout'),
            $this->ui->hidden('action', 'theme_settings'),
            $this->ui->hidden('sent', 1),
            $this->ui->hidden('less_tweak_file', $tweak_file)
        );
        
        if (!isset($meta['vars']) || !is_array($meta['vars'])) {
            $meta['vars'] = array();
        }
        
        
        $tabs = array(
            'colors' => 'Цвета',
            'fonts' => 'Шрифты',
            'sizes' => 'Размеры и отступы'
        );
        
        //$var_list = array();
        
        foreach ($meta['vars'] as $var) {
            $var_name = $var['name'];
            if ($var['type'] === 'colorset') {
                $color_code = $var['name'];
                $color_val = array();
                foreach ($params as $k => $v) {
                    $color_prop = null;
                    preg_match("~^".$color_code."-(.+)~", $k, $color_prop);
                    if ($color_prop) {
                        $color_val[ $color_prop[1]] = $v;
                    }
                }
                $var['value'] = $color_val;
            } else {
                if (isset($params[$var_name])) {
                    $c_val = $params[$var_name];
                    if ($var['type'] === 'number') {
                        $c_val = preg_replace("~[^0-9\.]~", '', $c_val);
                    }
                    $var['value'] = $c_val;
                }
            }
            if ($var['type'] === 'font') {
                $var['type'] = 'livesearch';
                $font_type = preg_replace("~^font_?~", '', $var_name);
                if (empty($font_type)) {
                    $font_type = 'text';
                }
                $var['fontpicker'] = $font_type;
                //$var['values'] = \Floxim\Floxim\Asset\Fonts::getAvailableFontValues();
            }
            $fields []= $var;
        }
        
        if (isset($input['sent'])) {
            $res_params = array();
            foreach ($meta['vars'] as $var) {
                $var_name = $var['name'];
                if (isset($input[$var_name]) && !empty($input[$var_name])) {    
                    if ($var['type'] === 'colorset') {
                        $res_params = array_merge($res_params, json_decode($input[$var_name], true));
                    } else {
                        $res_params[$var_name] = $input[$var_name];
                        if (isset($var['units'])) {
                            $res_params[$var_name] .= $var['units'];
                        }
                    }
                }
            }
            $style['less_vars'] = $res_params;
            $style->save();
            $bundle->delete();
            return array(
                'status' => 'ok',
                'reload' => true
            );
        }
        
        return array(
            'fields' => $fields,
            'header' => fx::alang('Theme settings', 'system'),
            'view'   => 'horizontal',
            'tabs' => $tabs
        );
    }

    protected function prepareStyleVar($props)
    {
        if ($props['type'] === 'palette') {
            $props['colors'] = fx::env()->getLayoutStyleVariant()->getPalette();
            $props['empty'] = false;
        }
        if ($props['type'] === 'css-text-transform') {
            $props['type'] = 'livesearch';
            $props['values'] = array(
                array('none', 'Abc'),
                array('uppercase', 'ABC'),
                array('lowercase', 'abc')
            );
            if (!$props['label']) {
                $props['label'] = 'Регистр';
            }
            $props['allow_empty'] = false;
        }
        if ($props['type'] === 'css-font-style') {
            $props['type'] = 'livesearch';
            $props['values'] = array(
                array('normal', 'Нормальный'),
                array('bold', 'Жирный'),
                array('normal italic', 'Курсив'),
                array('bold italic', 'Жирный курсив')
            );
            if (!$props['label']) {
                $props['label'] = 'Начертание';
            }
            $props['allow_empty'] = false;
        }
        if ($props['units'] && $props['value']) {
            $props['value'] = preg_replace("~[^\d]+~", '', $props['value']);
        }
        return $props;
    }
    
    protected function getStyleMeta($style, $block, $source_template)
    {
        $bundle = fx::page()->getDefaultCssBundle();

        $meta = $bundle->getMeta();
        
        $find_style = function($meta) use ($style, $block) {
            if (!isset($meta['styles']) || !is_array($meta['styles'])) {
                return;
            }
            foreach ($meta['styles'] as $c_style) {
                if ($c_style['keyword'] === $block.'_style_'.$style) {
                    return $c_style;
                }
            }
        };
        $found_style = $find_style($meta);
        if ($found_style) {
            return $found_style;
        }
        $tpl = fx::template($source_template);
        $styles = $tpl->collectStyles($block.'_style_*');
        if (!isset($styles[$style])) {
            return;
        }
        $bundle->push($styles[$style]['files']);
        $bundle->getBundleContent();
        $found_style = $find_style($bundle->getMeta());
        if ($found_style) {
            return $found_style;
        }
    }

    public function styleSettings($input)
    {
        $bundle = fx::page()->getDefaultCssBundle();
        
        $fields = $this->getHiddenFields(array('style', 'block', 'style_variant_id', 'source_template'));

        $style_variant = 
            isset($input['style_variant_id'])  && $input['style_variant_id']
                ? fx::data('style_variant', $input['style_variant_id'])
                : fx::data('style_variant')->create();

        $input['style'] = preg_replace("~\-\-\d+$~", '', $input['style']);

        $style = $this->getStyleMeta($input['style'], $input['block'], $input['source_template']);
        
        $is_sent = $this->isSent();
        $less_vars = $style_variant['less_vars'];
        
        
        
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
                $style_variant['style'] = $input['block'].'_'.$input['style'];
                $style_variant->save();
                $id = $style_variant['id'];
            }
            fx::page()->getBundleManager()->getBundle('css', 'default')->delete();
            
            return array(
                'id' => $id,
                'name' => $style_variant['name']
            );
        }
        
        $fields[]= array(
            'name' => 'style_name',
            'tab' => 'header',
            'type' => 'string',
            'label' => false,
            'placeholder' => 'название стиля',
            'value' => $style_variant['name']
        );
        
        $mixin_name = $input['block'].'_style_'.$input['style'];
        

        $res = array(
            'tweaker_file' => 
                $bundle->getStyleTweakerLessFile(
                    array(
                        $input['block'],
                        $input['style']
                    )
                ),
            'rootpath' => fx::path()->http( dirname($style['file']) ) . '/',
            'tweaked_vars' => array_keys($style['vars']),
            'mixin_name' => $mixin_name,
            'existing_class' => $style_variant['id'] ? $mixin_name.'--'.$style_variant['id'] : null,
            'fields' => $fields,
            'header' => 'Настраиваем стиль'
        );
        if ($style['tabs']) {
            $res['tabs'] = $style['tabs'];
        }
        
        if ($style_variant['id']) {
            $this->response->addFormButton(array('class' => 'delete', 'key' => 'delete', 'label' => fx::alang('Delete')));
            $this->response->addFormButton(array('key' => 'save'));
        }
        return $res;
    }
    
    protected function testColors() {
        $fields = array(
            'main' => array(
                'type' => 'colorset',
                'label' => 'Основной',
                'saturation' => array(0, 0.15),
                'luminance_map' => array(
                    0.01,
                    0.04,
                    0.15,
                    0.45,
                    0.7,
                    0.9
                ),
                'value' => array(
                    'hue' => 0,
                    'saturation' => 0
                )
            ),
            'alt' => array(
                'type' => 'colorset',
                'label' => 'Акценты',
                'value' => array(
                    'hue' => 0,
                    'saturation' => 0.6
                )
            ),
            'third' => array(
                'type' => 'colorset',
                'label' => 'Дополнительный',
                'value' => array(
                    'hue' => 100,
                    'saturation' => 0.4
                )
            )
        );
        return $fields;
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
        $templates = array();
        $res = array();
        foreach ($input['blocks'] as $block) {
            $tpl = $block['source_template'];
            if (!isset($templates[$tpl])) {
                $templates[$tpl] = fx::template($tpl);
            }
            $res []= $templates[$tpl]->collectStyleValues($block['block'].'_style_*');
        }
        return array(
            'variants' => $res
        );
        
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
    
    public function colorSet()
    {
        //$fields = $this->testColors();
        $fields = array(
            'palette' => array(
                'type' => 'palette',
                'transparent' => true,
                'colors' => fx::env()->getLayoutStyleVariant()->getPalette(),
                'value' => 'alt 2'
            )
        );
        $this->response->addFields($fields);
    }

    public function font()
    {
        $fields = array(
            'font' => array(
                'label' => 'Тестовый шрифт',
                'type' => 'css-font',
                'value' => 'nav 16px bold italic uppercase underline'
            )
        );
        $this->response->addFields($fields);
    }
    
    public function measures()
    {
        $fields = array(
            'test' => array(
                'label' => 'padding',
                'type' => 'measures',
                'prop' => 'padding',
                'lock' => '1-3--2-4'
            ),
            'cr' => array(
                'label' => 'corners',
                'type' => 'measures',
                'prop' => 'corners',
                'lock' => '1-2--3-4'
            )
        );
        $this->response->addFields($fields);
    }
}