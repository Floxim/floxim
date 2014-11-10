<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Component;
use Floxim\Floxim\Component\Infoblock as CompInfoblock;
use Floxim\Floxim\System;
use Floxim\Floxim\Template;
use Floxim\Floxim\System\Fx as fx;

class Infoblock extends Admin
{


    /**
     * Select a controller action
     */
    public function selectController($input)
    {
        $fields = array(
            $this->ui->hidden('action', 'select_settings'),
            $this->ui->hidden('entity', 'infoblock'),
            $this->ui->hidden('fx_admin', true),
            $this->ui->hidden('area', serialize($input['area'])),
            $this->ui->hidden('page_id', $input['page_id']),
            $this->ui->hidden('admin_mode', $input['admin_mode']),
            $this->ui->hidden('container_infoblock_id', $input['container_infoblock_id'])
        );

        //fx::env('page', $input['page_id']);
        //$page = fx::data('page', $input['page_id']);
        $page = fx::env('page');

        $area_meta = $input['area'];

        /* The list of controllers */
        $fields['controller'] = array(
            'type'   => 'tree',
            'name'   => 'controller',
            'values' => array()
        );

        $controllers = fx::data('component')->all();
        $controllers->concat(fx::data('widget')->all());

        foreach ($controllers as $c) {
            $controller_type = $c instanceof Component\Entity ? 'component' : 'widget';
            // todo: psr0 need verify
            $controller_name = $c['keyword'];
            $c_item = array(
                'data'     => $c['name'],
                'metadata' => array('id' => $controller_name),
                'children' => array()
            );
            $ctrl = fx::controller($controller_name);
            $actions = $ctrl->getActions();
            foreach ($actions as $action_code => $action_info) {
                // do not show actions starting with "_"
                if (preg_match("~^_~", $action_code)) {
                    continue;
                }
                if (isset($action_info['check_context'])) {
                    $is_avail = call_user_func($action_info['check_context'], $page);
                    if (!$is_avail) {
                        continue;
                    }
                }
                $act_ctr = fx::controller($controller_name . ':' . $action_code);
                $act_templates = $act_ctr->getAvailableTemplates(fx::env('layout'), $area_meta);
                if (count($act_templates) == 0) {
                    continue;
                }
                if (!isset($action_info['name'])) {
                    $action_info['name'] = $c['name'];
                }

                $action_name = $action_info['name'];
                switch ($controller_type) {
                    case 'widget':
                        $action_type = 'widget';
                        break;
                    case 'component':
                        if (preg_match('~^list_(selected|filter)~', $action_code)) {
                            $action_type = 'mirror';
                        } elseif (preg_match('~^list_infoblock~', $action_code)) {
                            $action_type = 'content';
                        } else {
                            $action_type = 'widget';
                        }
                        break;
                }
                $c_item['children'][] = array(
                    'data'     => $action_name,
                    'metadata' => array(
                        // todo: psr0 need verify
                        'id'          => $c['keyword'] . ':' . $action_code,
                        'description' => $action_info['description'],
                        'type'        => $action_type,
                        'icon'        => $action_info['icon'],
                        'icon_extra'  => $action_info['icon_extra'],
                    )
                );
            }
            if (count($c_item['children']) > 0) {
                $fields['controller']['values'][] = $c_item;
            }
        }
        $this->response->addFormButton(array(
            'key'   => 'next',
            'label' => fx::alang('Next', 'system')
        ));
        $this->response->addFormButton(array(
            'key'   => 'finish',
            'label' => fx::alang('Finish', 'system')
        ));
        $result = array(
            'fields'        => $fields,
            'header'        => fx::alang('Adding infoblock', 'system'),
            'dialog_button' => array(
                array('key' => 'save', 'text' => fx::alang('Next', 'system'))
            )
        );
        return $result;
    }

    /**
     * The choice of settings for infoblock
     */

    public function selectSettings($input)
    {
        // The current, editable) InfoBlock
        $infoblock = null;
        
        /*
        if (isset($input['page_id'])) {
            // set into the environment of the current page
            // it is possible to get the layout
            fx::env('page', $input['page_id']);
        }
         * 
         */

        $area_meta = is_string($input['area']) ? unserialize($input['area']) : $input['area'];

        if (isset($input['id']) && is_numeric($input['id'])) {
            // Edit existing InfoBlock
            $infoblock = fx::data('infoblock', $input['id']);
            $controller = $infoblock->getPropInherited('controller');
            $action = $infoblock->getPropInherited('action');
            $i2l = $infoblock->getVisual();
        } else {
            // Create a new type and ID of the controller received from the previous step
            list($controller, $action) = explode(":", $input['controller']);
            //$site_id = fx::data('page', $input['page_id'])->get('site_id');
            $site_id = fx::env('site_id');
            $infoblock = fx::data("infoblock")->create(array(
                'controller'             => $controller,
                'action'                 => $action,
                'page_id'                => $input['page_id'],
                'site_id'                => $site_id,
                'container_infoblock_id' => $input['container_infoblock_id']
            ));
            $last_visual = fx::data('infoblock_visual')->where('area', $area_meta['id'])->order(null)->order('priority',
                'desc')->one();
            $priority = $last_visual ? $last_visual['priority'] + 1 : 0;
            $i2l = fx::data('infoblock_visual')->create(array(
                'area'      => $area_meta['id'],
                'layout_id' => fx::env('layout'),
                'priority'  => $priority
            ));
            $infoblock->setVisual($i2l);
        }

        if (!isset($infoblock['params']) || !is_array($infoblock['params'])) {
            $infoblock->addParams(array());
        }

        $controller = fx::controller($controller . ':' . $action,
            array('infoblock_id' => $infoblock['id']) + $infoblock['params']);
        $settings = $controller->getActionSettings($action);
        if (!$infoblock['id']) {
            $cfg = $controller->getConfig();
            $infoblock['name'] = $cfg['actions'][$action]['name'];
        }
        foreach ($infoblock['params'] as $ib_param => $ib_param_value) {
            if (isset($settings[$ib_param])) {
                $settings[$ib_param]['value'] = $ib_param_value;
            }
        }
        $this->response->addFields(array(
            array(
                'label' => fx::alang('Block name', 'system'),
                'name'  => 'name',
                'value' => $infoblock['name'],
                'tip'   => $infoblock['controller'] . '.' . $infoblock['action']
            )
        ));

        $this->response->addFields($settings, false, 'params');

        $format_fields = $this->getFormatFields($infoblock, $area_meta);
        $this->response->addFields($format_fields, false, 'visual');

        $c_page = fx::env('page');
        $scope_fields = $this->getScopeFields($infoblock, $c_page);
        $this->response->addFields($scope_fields, false, 'scope');

        if ($input['settings_sent'] == 'true') {
            $infoblock['name'] = $input['name'];
            $action_params = array();
            if ($settings && is_array($settings)) {
                foreach ($settings as $setting_key => $setting) {
                    if (isset($setting['stored']) && !$setting['stored']) {
                        continue;
                    }
                    if (isset($input['params'][$setting_key])) {
                        $action_params[$setting_key] = $input['params'][$setting_key];
                    } else {
                        $action_params[$setting_key] = false;
                    }
                }
            }

            $infoblock['params'] = $action_params;
            if (isset($controller) && $controller instanceof System\Controller) {
                $controller->setInput($action_params);
            }

            $infoblock->setScopeString($input['scope']['complex_scope']);
            $infoblock->digSet('scope.visibility', $input['scope']['visibility']);

            $i2l['wrapper'] = fx::dig($input, 'visual.wrapper');
            $i2l['template'] = fx::dig($input, 'visual.template');
            $is_new_infoblock = !$infoblock['id'];
            $infoblock->save();
            $i2l['infoblock_id'] = $infoblock['id'];
            $i2l->save();
            $controller->setParam('infoblock_id', $infoblock['id']);
            if (isset($controller)) {
                if ($is_new_infoblock) {
                    $controller->handleInfoblock('install', $infoblock, $input);
                }
                $controller->handleInfoblock('save', $infoblock, $input);
            }
            $this->response->setStatusOk();
            $this->response->setProp('infoblock_id', $infoblock['id']);
            return;
        }

        $actions = $controller->getActions();
        $action_name = $actions[$action]['name'];

        if (!$infoblock['id']) {
            $result['header'] = ' <a class="back">' . fx::alang('Adding infoblock', 'system') . '</a>';
            $result['header'] .= ' / ' . $action_name;
        } else {
            $result['header'] = fx::alang('Settings',
                    'system') . ' / <span title="' . $infoblock['id'] . '">' . $action_name . '</span>';
        }

        $fields = array(
            $this->ui->hidden('entity', 'infoblock'),
            $this->ui->hidden('action', 'select_settings'),
            $this->ui->hidden('fx_admin', true),
            $this->ui->hidden('settings_sent', 'true'),
            $this->ui->hidden('controller', $input['controller']),
            $this->ui->hidden('page_id', $input['page_id']),
            $this->ui->hidden('area', serialize($area_meta)),
            $this->ui->hidden('id', $input['id']),
            $this->ui->hidden('mode', $input['mode'])
        );

        $this->response->addFields($fields);
        return $result;
    }

    public function listForPage($input)
    {
        $fields = array();
        if (!$input['page_id']) {
            return;
        }
        $c_page = fx::content('page', $input['page_id']);
        fx::env('page', $c_page);

        $infoblocks = $c_page->getPageInfoblocks();

        if ($input['data_sent']) {
            foreach ($infoblocks as $ib) {
                if (isset($input['area'][$ib['id']])) {
                    $vis = $ib->getVisual();
                    $vis['area'] = $input['area'][$ib['id']];
                    $vis->save();
                }
                if (isset($input['visibility'][$ib['id']])) {
                    $ib->digSet('scope.visibility', $input['visibility'][$ib['id']]);
                    $ib->save();
                }
            }
            return;
        }

        $list = array(
            'type'   => 'list',
            'entity' => 'infoblock',
            'values' => array(),
            'labels' => array(
                'name'       => fx::alang('Name', 'system'),
                'type'       => fx::alang('Type', 'system'),
                'visibility' => fx::alang('Visibility', 'system'),
                'area'       => fx::alang('Area', 'system'),
            )
        );

        foreach ($infoblocks as $ib) {
            if ($ib->isLayout()) {
                continue;
            }
            $vis = $ib->getVisual();
            $list['values'] [] = array(
                'id'         => $ib['id'],
                'name'       => $ib['name'],
                'type'       => preg_replace("~^component_~", '', $ib['controller']) . '.' . $ib['action'],
                'visibility' => array(
                    'field' => array(
                        'name'   => 'visibility[' . $ib['id'] . ']',
                        'type'   => 'select',
                        'values' => $this->getScopeVisibilityOptions(),
                        'value'  => $ib['scope']['visibility']
                    )
                ),
                'area'       => $vis['area']
            );
        }
        $fields['list'] = $list;
        $fields[] = $this->ui->hidden('entity', 'infoblock');
        $fields[] = $this->ui->hidden('action', 'list_for_page');
        $fields[] = $this->ui->hidden('page_id', $c_page['id']);
        $fields[] = $this->ui->hidden('data_sent', 1);
        $res = array(
            'fields' => $fields,
            'id'     => 'page_infoblocks'
        );
        return $res;
    }

    public function layoutSettings($input)
    {
        //$c_page = fx::data('page', $input['page_id']);
        //$infoblock = $c_page->getLayoutInfoblock();
        $c_page = fx::env('page');
        $infoblock = fx::router('front')->getLayoutInfoblock($c_page);

        $scope_fields = $this->getScopeFields($infoblock, $c_page);
        unset($scope_fields['visibility']);
        $this->response->addFields($scope_fields, false, 'scope');

        $format_fields = $this->getFormatFields($infoblock);
        $this->response->addFields($format_fields, false, 'visual');

        if ($input['settings_sent']) {
            $this->saveLayoutSettings($infoblock, $input);
            return;
        }

        $fields = array(
            $this->ui->hidden('entity', 'infoblock'),
            $this->ui->hidden('action', 'layout_settings'),
            $this->ui->hidden('fx_admin', true),
            $this->ui->hidden('settings_sent', 'true'),
            $this->ui->hidden('page_id', $input['page_id'])
        );

        $existing = fx::data('infoblock')->isLayout()->getForPage($c_page['id'], false);
        if (count($existing) > 1) {
            $existing = fx::data('infoblock')->sortInfoblocks($existing);
            $next = $existing->eq(1);
            $fields [] = array(
                'type'  => 'button',
                'role'  => 'preset',
                'label' => fx::alang('Drop current rule and use the wider one', 'system'),
                'data'  => array(
                    'scope[complex_scope]' => $next->getScopeString(),
                    'visual[template]'     => $next->getPropInherited('visual.template')
                )
            );
        }

        $this->response->addFields($fields);
        $res = array(
            'header' => fx::alang('Layout settings', 'system'),
            'view'   => 'horizontal'
        );
        return $res;
    }

    protected function saveLayoutSettings($infoblock, $input)
    {
        $visual = $infoblock->getVisual();
        $old_scope = $infoblock->getScopeString();
        $new_scope = $input['scope']['complex_scope'];

        $old_layout = $visual['template'];
        $new_layout = $input['visual']['template'];

        $c_page = fx::data('page', $input['page_id']);

        if ($old_layout == $new_layout && $old_scope == $new_scope) {
            return;
        }
        $create = false;
        $update = false;
        $delete = false;
        // this is the root infoblock - default rule for all pages
        if (!$infoblock['parent_infoblock_id']) {
            // they changed the scope, we must create new iblock
            // but only if template also changed
            // because if it didn't, default rule will mean the same
            if ($old_scope != $new_scope && $old_layout != $new_layout) {
                $create = true;
            } else {
                // they changed default layout
                $update = true;
            }
        } else {
            // if everything changed, let's create new ib
            if ($old_scope != $new_scope && $old_layout != $new_layout) {
                $create = true;
            } // if there's only one modified param, update existing rule
            else {
                $update = true;
            }
            $existing = fx::data('infoblock')->isLayout()->getForPage($c_page['id'], false);
            if (count($existing) > 1) {
                $existing = fx::data('infoblock')->sortInfoblocks($existing);
                $next = $existing->eq(1);
                if ($next->getScopeString() == $new_scope && $next->getPropInherited('visual.template') == $new_layout) {
                    $delete = true;
                }
            }
        }
        if ($delete) {
            $infoblock->delete();
        } elseif ($create) {
            $params = $infoblock->get();
            unset($params['id']);
            $new_ib = fx::data('infoblock')->create($params);
            $c_parent = $infoblock['parent_infoblock_id'];
            $new_ib['parent_infoblock_id'] = $c_parent ? $c_parent : $infoblock['id'];
            $new_ib->setScopeString($new_scope);
            $new_vis = fx::data('infoblock_visual')->create(array(
                'layout_id' => $visual['layout_id']
            ));
            $new_vis['template'] = $new_layout;
            $new_ib->save();
            $new_vis['infoblock_id'] = $new_ib['id'];
            $new_vis->save();
        } elseif ($update) {
            $infoblock->setScopeString($new_scope);
            $visual->set('template', $new_layout);
            $infoblock->save();
            $visual->save();
        }
    }

    /*
     * Receipt of the form fields tab "Where show"
     * @param fx_infoblock $infoblock - information block whose looking for a suitable place
     * @param fx_content_page $c_page - page, where he opened the window settings
     */

    protected function getScopeFields(CompInfoblock\Entity $infoblock, \Floxim\Main\Content\Entity $c_page)
    {

        $fields = array();
        // format: [page_id]-[descendants|children|this]-[|type_id]


        $path_ids = $c_page->getParentIds();
        $path = fx::data('page', $path_ids);
        $path [] = $c_page;
        $path_count = count($path);
        $c_type = $c_page['type'];
        $page_com = fx::data('component', $c_page['type']);
        $c_type_name = $page_com['item_name'];

        $container_infoblock = null;
        if ($infoblock['container_infoblock_id']) {
            $container_infoblock = fx::data('infoblock', $infoblock['container_infoblock_id']);
        }

        $c_scope_code = $infoblock->getScopeString();

        $vals = array();

        foreach ($path as $i => $pi) {
            $sep = str_repeat(" -- ", $i);
            $pn = '"' . $pi['name'] . '"';
            $is_last = $i === $path_count - 1;
            $c_page_id = $pi['id'];
            if ($i === 0) {
                $c_page_id = fx::env('site')->get('index_page_id');
                $vals [] = array($c_page_id . '-descendants-', fx::alang('All pages'));
                if ($path_count > 1) {
                    $vals [] = array(
                        $c_page_id . '-children-' . $c_type,
                        sprintf(fx::alang('All pages of type %s'), $c_type_name)
                    );
                }
            }
            if ($is_last) {
                $vals [] = array(
                    $c_page_id . '-this-',
                    $sep . sprintf(fx::alang('%s only'), $pn)
                );
            } else {
                $vals [] = array(
                    $c_page_id . '-children-',
                    $sep . sprintf(fx::alang('%s children only'), $pn)
                );
            }
            if ($i !== 0) {
                $vals [] = array(
                    $c_page_id . '-descendants-',
                    $sep . sprintf(fx::alang('%s and children'), $pn)
                );
            }
            if (!$is_last) {
                $vals [] = array(
                    $c_page_id . '-children-' . $c_type,
                    $sep . sprintf(fx::alang('%s children of type %s'), $pn, $c_type_name)
                );
            }
        }

        // can be set to "hidden" later
        $scope_field_type = 'select';

        if (!$infoblock['id']) {
            if ($container_infoblock) {
                $c_scope_code = $container_infoblock->getScopeString();
                if ($container_infoblock['scope']['pages'] === 'this') {
                    $scope_field_type = 'hidden';
                }
            } else {
                $ctr = $infoblock->initController();
                $cfg = $ctr->getConfig(true);
                if (isset($cfg['default_scope']) && is_callable($cfg['default_scope'])) {
                    $c_scope_code = call_user_func($cfg['default_scope']);
                }
            }
        }

        $fields [] = array(
            'type'   => $scope_field_type,
            'label'  => fx::alang('Scope'),
            'name'   => 'complex_scope',
            'values' => $vals,
            'value'  => $c_scope_code
        );
        $fields ['visibility'] = array(
            'type'      => 'select',
            'label'     => 'Visibility',
            'name'      => 'visibility',
            'join_with' => 'complex_scope',
            'values'    => $this->getScopeVisibilityOptions(),
            'value'     => $infoblock['scope']['visibility']
        );
        return $fields;
    }

    protected function getScopeVisibilityOptions()
    {
        return array(
            'all'    => 'Everybody',
            'admin'  => 'Admins',
            'guests' => 'Guests',
            'nobody' => 'Nobody'
        );
    }

    /*
     * Receipt of the form fields for the tab "How to show"
     */
    protected function getFormatFields(CompInfoblock\Entity $infoblock, $area_meta = null)
    {
        $i2l = $infoblock->getVisual();
        $fields = array(
            array(
                'label' => "Area",
                'name'  => 'area',
                'value' => $i2l['area'],
                'type'  => 'hidden'
            )
        );
        $area_suit = Template\Suitable::parseAreaSuitProp($area_meta['suit']);

        $force_wrapper = $area_suit['force_wrapper'];
        $default_wrapper = $area_suit['default_wrapper'];

        $wrappers = array();
        $c_wrapper = '';
        if (!$force_wrapper) {
            $wrappers[''] = fx::alang('With no wrapper', 'system');
            if ($i2l['id'] || !$default_wrapper) {
                $c_wrapper = $i2l['wrapper'];
            } else {
                $c_wrapper = $default_wrapper[0];
            }
        }
        $layout_name = fx::data('layout', $i2l['layout_id'])->get('keyword');

        $controller_name = $infoblock->getPropInherited('controller');

        $action_name = $infoblock->getPropInherited('action');

        // Collect available wrappers
        $layout_tpl = fx::template('theme.' . $layout_name);
        if ($layout_tpl) {
            $template_variants = $layout_tpl->getTemplateVariants();
            foreach ($template_variants as $tplv) {
                $full_id = $tplv['full_id'];
                if ($tplv['suit'] == 'local' && $area_meta['id'] != $tplv['area']) {
                    continue;
                }
                if ($force_wrapper && !in_array($tplv['full_id'], $force_wrapper)) {
                    continue;
                }
                if (is_string($tplv['suit']) && $tplv['suit']) {
                    $tplv_suit = preg_split("~\,\s*~", $tplv['suit']);
                    if (in_array('local', $tplv_suit)) {
                        $tplv_suit []= $tplv['area'];
                    }
                    if (!in_array($area_meta['id'], $tplv_suit)) {
                        continue;
                    }
                }

                if ($tplv['of'] == 'floxim.layout.wrapper:show') {
                    $wrappers[$full_id] = $tplv['name'];
                    if ($force_wrapper && empty($c_wrapper)) {
                        $c_wrapper = $full_id;
                    }
                }
            }
        }

        // Collect the available templates
        $controller = fx::controller($controller_name . ':' . $action_name);
        $tmps = $controller->getAvailableTemplates($layout_name, $area_meta);
        if (!empty($tmps)) {
            foreach ($tmps as $template) {
                $templates[] = array($template['full_id'], $template['name']);
            }
        }

        if (count($templates) == 1) {
            $fields [] = array(
                'name'  => 'template',
                'type'  => 'hidden',
                'value' => $templates[0][0]
            );
        } else {
            $fields [] = array(
                'label'  => fx::alang('Template', 'system'),
                'name'   => 'template',
                'type'   => 'select',
                'values' => $templates,
                'value'  => $i2l['template']
            );
        }
        if ($controller_name != 'layout' && (count($wrappers) > 1 || !isset($wrappers['']))) {
            $fields [] = array(
                'label'     => fx::alang('Wrapper', 'system'),
                'name'      => 'wrapper',
                'type'      => 'select',
                'join_with' => 'template',
                'values'    => $wrappers,
                'value'     => $c_wrapper
            );
        }
        return $fields;
    }

    /*
     * Save multiple fields from the front-end
     */
    public function saveVar($input)
    {
        /* @var $ib fx_infoblock */

        if (isset($input['page_id'])) {
            fx::env('page_id', $input['page_id']);
        }

        $ib = fx::data('infoblock', $input['infoblock']['id']);
        // for InfoBlock-layouts always save the parameters in the root InfoBlock
        if ($ib->isLayout()) {
            $root_ib = $ib->getRootInfoblock();
            $ib_visual = $root_ib->getVisual();
        } elseif (($visual_id = fx::dig($input, 'infoblock.visual_id'))) {
            $ib_visual = fx::data('infoblock_visual', $visual_id);
        } else {
            $ib_visual = $ib->getVisual();
        }

        // group vars by type to process content vars first
        // because we need content id for 'content-visual' vars on adding a new entity
        $vars = fx::collection($input['vars'])->apply(function ($v) {
            if ($v['var']['type'] == 'livesearch' && !$v['value']) {
                $v['value'] = array();
            }
        })->group(function ($v) {
            return $v['var']['var_type'];
        });


        $contents = fx::collection();

        if (isset($input['new_entity_props'])) {
            $new_props = $input['new_entity_props'];
            $contents['new'] = fx::content($new_props['type'])->create($new_props);
        }

        if (isset($vars['content'])) {
            $content_groups = $vars['content']->group(function ($v) {
                $vid = $v['var']['content_id'];
                if (!$vid) {
                    $vid = 'new';
                }
                return $vid;
            });
            foreach ($content_groups as $content_id => $content_vars) {
                if ($content_id !== 'new') {
                    $fv = $content_vars->first();
                    // todo: verify $fv['content_type_id'] -> $fv['var']['content_type_id']
                    $c_content = fx::content($fv['var']['content_type_id'], $content_id);
                    if (!$c_content) {
                        continue;
                    }
                    $contents[$content_id] = $c_content;
                }
                $vals = array();
                foreach ($content_vars as $var) {
                    $vals[$var['var']['name']] = $var['value'];
                }
                $contents[$content_id]->setFieldValues($vals, array_keys($vals));
            }
        }

        $new_id = false;
        foreach ($contents as $cid => $c) {
            $c->save();
            if ($cid == 'new') {
                $new_id = $c['id'];
            }
        }

        if (isset($vars['visual'])) {
            foreach ($vars['visual'] as $c_var) {
                $var = $c_var['var'];
                $value = $c_var['value'];
                $var['id'] = preg_replace("~\#new_id\#$~", $new_id, $var['id']);
                $visual_set = $var['template_is_wrapper'] ? 'wrapper_visual' : 'template_visual';
                if ($value == 'null') {
                    $value = null;
                }
                $c_visual = $ib_visual[$visual_set];
                if (!is_array($c_visual)) {
                    $c_visual = array();
                }
                if ($value == 'null') {
                    unset($c_visual[$var['id']]);
                } else {
                    $c_visual[$var['id']] = $value;
                }
                $ib_visual[$visual_set] = $c_visual;
            }
            $ib_visual->save();
        }
        if (isset($vars['ib_param'])) {
            $modified_params = array();
            foreach ($vars['ib_param'] as $c_var) {
                $var = $c_var['var'];
                $value = $c_var['value'];
                if (!isset($var['stored']) || ($var['stored'] && $var['stored'] != 'false')) {
                    $ib->digSet('params.' . $var['name'], $value);
                }
                $modified_params[$var['name']] = $value;
            }
            if (count($modified_params) > 0) {
                $controller = $ib->initController();
                $ib->save();
                $controller->handleInfoblock('save', $ib, array('params' => $modified_params));
            }
        }
    }


    public function deleteInfoblock($input)
    {
        $infoblock = fx::data('infoblock', $input['id']);
        if (!$infoblock) {
            return;
        }
        $controller = $infoblock->initController();
        $fields = array(
            array(
                'label' => fx::alang('I am REALLY sure', 'system'),
                'name'  => 'delete_confirm',
                'type'  => 'checkbox'
            ),
            $this->ui->hidden('id', $input['id']),
            $this->ui->hidden('entity', 'infoblock'),
            $this->ui->hidden('action', 'delete_infoblock'),
            $this->ui->hidden('fx_admin', true)
        );
        $ib_content = $infoblock->getOwnedContent();
        if ($ib_content->length > 0) {
            $fields[] = array(
                'name'   => 'content_handle',
                'label'  => fx::alang('The infoblock contains some content',
                        'system') . ', <b>' . $ib_content->length . '</b> ' . fx::alang('items. What should we do with them?',
                        'system'),
                'type'   => 'select',
                'values' => array(
                    'unbind' => fx::alang('Unbind/Hide', 'system'),
                    'delete' => fx::alang('Delete', 'system')
                ),
                //'parent' => array('delete_confirm' => true)
            );
        }

        if ($infoblock['controller'] == 'layout' && !$infoblock['parent_infoblock_id']) {
            unset($fields[0]);
            $fields [] = array('type' => 'html', 'html' => fx::alang('Layouts can not be deleted', 'system'));
        }
        $this->response->addFields($fields);
        if ($input['delete_confirm']) {
            $this->response->setStatusOk();
            if ($ib_content) {
                if ($input['content_handle'] == 'delete') {
                    foreach ($ib_content as $ci) {
                        $ci->delete();
                    }
                } else {
                    foreach ($ib_content as $ci) {
                        $ci->set('infoblock_id', 0)->save();
                    }
                }
            }
            $controller->handleInfoblock('delete', $infoblock, $input);
            $infoblock->delete();
        }
    }

    protected function getAreaVisual($area, $layout_id, $site_id)
    {
        return fx::db()->getResults("SELECT V.*
                    FROM {{infoblock}} as I 
                    INNER JOIN {{infoblock_visual}} as V ON V.infoblock_id = I.id
                    WHERE
                        I.site_id = '" . $site_id . "' AND
                        V.layout_id = '" . $layout_id . "' AND
                        V.area = '" . $area . "'
                    ORDER BY V.priority");
    }

    public function move($input)
    {
        if (!isset($input['visual_id']) || !isset($input['area'])) {
            return;
        }

        $vis = fx::data('infoblock_visual', $input['visual_id']);
        if (!$vis) {
            return;
        }

        $infoblock = fx::data('infoblock', $input['infoblock_id']);
        if (!$infoblock) {
            return;
        }

        // move from region to region
        // need to rearrange the blocks from the old area
        // until very stupidly, in order
        if ($vis['area'] != $input['area']) {
            $source_vis = $this->getAreaVisual($vis['area'], $vis['layout_id'], $infoblock['site_id']);
            $cpos = 1;
            foreach ($source_vis as $csv) {
                if ($csv['id'] == $vis['id']) {
                    continue;
                }
                fx::db()->query("UPDATE {{infoblock_visual}}
                    SET priority = '" . $cpos . "'
                    WHERE id = '" . $csv['id'] . "'");
                $cpos++;
            }
        }

        $target_vis = $this->getAreaVisual($input['area'], $vis['layout_id'], $infoblock['site_id']);

        $next_visual_id = isset($input['next_visual_id']) ? $input['next_visual_id'] : null;

        $cpos = 1;
        $new_priority = null;
        foreach ($target_vis as $ctv) {
            if ($ctv['id'] == $vis['id']) {
                continue;
            }
            if ($ctv['id'] == $next_visual_id) {
                $new_priority = $cpos;
                $cpos++;
            }
            if ($ctv['priority'] != $cpos) {
                fx::db()->query("UPDATE {{infoblock_visual}}
                    SET priority = '" . $cpos . "'
                    WHERE id = '" . $ctv['id'] . "'");
            }
            $cpos++;
        }
        if (!$new_priority) {
            $new_priority = $cpos;
        }

        fx::db()->query("UPDATE {{infoblock_visual}}
            SET priority = '" . $new_priority . "', area = '" . $input['area'] . "'
            WHERE id = '" . $vis['id'] . "'");

        return array('status' => 'ok');
    }
}
