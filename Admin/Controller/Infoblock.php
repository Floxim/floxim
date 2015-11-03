<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Component;
use Floxim\Floxim\Component\Infoblock as CompInfoblock;
use Floxim\Floxim\System;
use Floxim\Floxim\Template;
use Floxim\Floxim\System\Fx as fx;

class Infoblock extends Admin
{


    public function getAvailableBlocks($page, $area_meta = null)
    {
        $controllers = fx::data('component')->all();
        $controllers->concat(fx::data('widget')->all());
        
        $result = array(
            'controllers' => array(),
            'actions' => array(),
            'groups' => array(
                'content' => array(
                    'name' => 'Данные'
                ),
                'content:infoblock' => array(
                    'name' => 'Новые данные',
                    'description' => 'Добавьте пустой блок и заполните его новыми данными.'
                ),
                'content:filtered' => array(
                    'name' => 'Данные по фильтру',
                    'description' => 'Добавьте блок для отображения существующих данных&nbsp;&mdash; всех, или ограниченных набором условий.'
                ),
                'content:selected' => array(
                    'name' => 'Данные, отобранные вручную',
                    'description' => 'Добавьте блок и внесите в него уже существующие данные, выбрав их из списка.'
                ),
                'widget' => array(
                    'name' => 'Виджеты'
                )
            )
        );
        
        foreach ($controllers as $c) {
            if (fx::config()->isBlockDisabled($c['keyword'])) {
                continue;
            }
            
            $type = $c instanceof Component\Entity ? 'component' : 'widget';
            $keyword = $c['keyword'];
            
            $result['controllers'] [$keyword]= array(
                'name'     => $c['name'],
                'keyword' => $keyword,
                'type' => $type
            );
            $ctrl = fx::controller($keyword);
            $actions = $ctrl->getActions();
            foreach ($actions as $action_code => $action_info) {
                // do not show actions starting with "_"
                if (preg_match("~^_~", $action_code)) {
                    continue;
                }
                
                if (fx::config()->isBlockDisabled($c['keyword'], $action_code)) {
                    continue;
                }
                
                if (isset($action_info['check_context'])) {
                    $is_avail = call_user_func($action_info['check_context'], $page);
                    if (!$is_avail) {
                        continue;
                    }
                }
                
                $act_ctr = fx::controller($keyword . ':' . $action_code);
                $act_templates = $act_ctr->getAvailableTemplates(fx::env('layout'), $area_meta);
                if (count($act_templates) == 0) {
                    continue;
                }
                
                $action = array(
                    'controller' => $keyword,
                    'keyword' => $action_code,
                    'name' => $action_info['name'],
                    'id' => $keyword.':'.$action_code
                );
                
                if (isset($action_info['group'])) {
                    $action['group'] = $action_info['group'];
                } elseif ($type === 'component' && preg_match("~^list_(.+)$~", $action_code, $list_type)) {
                    $action['group'] = 'content';
                    $list_type = $list_type[1];
                    $action['subgroup'] = in_array($list_type, array('infoblock', 'selected')) ? $list_type : 'filtered';
                } else {
                    $action['group'] = 'widget';
                }
                if (empty($action['name'])) {
                    $action['name'] = $action_code;
                }
                
                $result['actions'] []= $action;
            }
        }
        //fx::log($result);
        return $result;
    }
    
    protected function groupAvailableBlocksWithListTypesOnTop($data) 
    {
  	$groups = $data['groups'];
        
        foreach ($data['actions'] as $a) {
            if ($a['subgroup']) {
                $group_keyword = $a['group'].':'.$a['subgroup'];
            } else {
                $group_keyword = $a['group'];
            }


            $c_group = &$groups[$group_keyword];

            if (!isset($c_group['children'])) {
                $c_group['children'] = array();
            }
            if (isset($a['subgroup'])) {
                $controller = $data['controllers'][$a['controller']];
                $a['full_name'] = $a['name'];
                $a['name'] = $controller['name'];
            }
            $c_group['children'][]= $a;
        }
        $res = array();
        foreach ($groups as $gk => $g) {
            if (!isset($g['children'])) {
                continue;
            }
            $g['keyword'] = $gk;
            $res[]= $g;
        }
        return $res;
    }

    function groupAvailableBlocksWithListTypesOnBottom($data) 
    {
        $groups = array();
        foreach ($data['groups'] as $gk => $g) {
          if (!preg_match("~\:~", $gk)) {
            $groups[$gk]= $g;
          }
        }
        foreach ($data['actions'] as $a) {
            $c_group = &$groups[$a['group']];
            if (!isset($c_group['children'])) {
                    $c_group['children'] = array();
            }
            if (!isset($a['subgroup'])) {
                    $c_group['children'] []= $a;
                continue;
            }
            $com_keyword = $a['controller'];
            if (!isset($c_group['children'][$com_keyword])) {
                $com = $data['controllers'][$com_keyword];
                $c_group['children'][$com_keyword] = $com;
                $c_group['children'][$com_keyword]['children'] = array();
            }
            $c_group['children'][$com_keyword]['children'][]= $a;
        }
        foreach ($groups as &$g) {
            $g['children'] = array_values($g['children']);
        }
        return $groups;
    }
    
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
            $this->ui->hidden('admin_mode', isset($input['admin_mode']) ? $input['admin_mode'] : ''),
            $this->ui->hidden('container_infoblock_id', $input['container_infoblock_id']),
            $this->ui->hidden('container_infoblock_id', $input['container_infoblock_id']),
        );
        
        if (isset($input['rel_infoblock_id'])) {
            $fields []= $this->ui->hidden('rel_infoblock_id', $input['rel_infoblock_id']);
            $fields []= $this->ui->hidden(
                'rel_position', 
                isset($input['rel_position']) ? $input['rel_position'] : 'after'
            );
        }

        $page = fx::env('page');

        $area_meta = $input['area'];
        
        $blocks = $this->getAvailableBlocks($page, $area_meta);
        $blocks = $this->groupAvailableBlocksWithListTypesOnTop($blocks);

        /* The list of controllers */
        $fields['controller'] = array(
            'type'   => 'tree',
            'name'   => 'controller',
            'values' => $blocks
        );
        

        /*
        $controllers = fx::data('component')->all();
        
        $controllers->concat(fx::data('widget')->all());
        
        
        
        foreach ($controllers as $c) {
            
            if (fx::config()->isBlockDisabled($c['keyword'])) {
                continue;
            }
            
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
                $action_info = array_merge(array(
                        'icon'=>'',
                        'icon_extra' => '',
                        'description' => ''
                    ), $action_info
                );
                // do not show actions starting with "_"
                if (preg_match("~^_~", $action_code)) {
                    continue;
                }
                
                if (fx::config()->isBlockDisabled($c['keyword'], $action_code)) {
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
        */
        
        $this->response->addFormButton('cancel');
        
        $result = array(
            'fields'        => $fields,
            'header'        => fx::alang('Adding infoblock', 'system')
        );
        return $result;
    }

    /**
     * The choice of settings for infoblock
     */

    public function selectSettings($input)
    {
        // The current, editable InfoBlock
        $infoblock = null;
        
        if (isset($input['rel_infoblock_id'])) {
            $this->response->addFields(array(
                $this->ui->hidden('rel_infoblock_id', $input['rel_infoblock_id']),
                $this->ui->hidden(
                    'rel_position', 
                    isset($input['rel_position']) ? $input['rel_position'] : 'after'
                )
            ));
        }
        
        $area_meta = is_string($input['area']) ? unserialize($input['area']) : $input['area'];
        
        $update_priority = false;
        $site_id = fx::env('site_id');
        
        if (isset($input['id']) && is_numeric($input['id'])) {
            // Edit existing InfoBlock
            $infoblock = fx::data('infoblock', $input['id']);
            $controller = $infoblock->getPropInherited('controller');
            $action = $infoblock->getPropInherited('action');
            $i2l = $infoblock->getVisual();
        } else {
            // Create a new type and ID of the controller received from the previous step
            list($controller, $action) = explode(":", $input['controller']);
            
            $infoblock = fx::data("infoblock")->create(array(
                'controller'             => $controller,
                'action'                 => $action,
                'page_id'                => $input['page_id'],
                'site_id'                => $site_id,
                'container_infoblock_id' => $input['container_infoblock_id']
            ));
            if (!$input['rel_infoblock_id']) {
                $last_visual = 
                        fx::data('infoblock_visual')
                            ->where('area', $area_meta['id'])
                            ->where('infoblock.site_id', $site_id)
                            ->order(null)
                            ->order('priority', 'desc')
                            ->one();
                $priority = $last_visual ? $last_visual['priority'] + 1 : 0;
            } else {
                $rel_visual = fx::data('infoblock', $input['rel_infoblock_id'])->getVisual();
                $priority = $input['rel_position'] === 'after' ? $rel_visual['priority'] + 1 : $rel_visual['priority'];
                $update_priority = true;
            }
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
        $this->response->addTabs(array(
            'settings' => array(
                'label' => fx::alang('Settings'),
                //'icon' => 'settings'
            ),
            'design' => array(
                'label' => fx::alang('Design settings'),
                //'icon' => 'design'
            )
        ));
        $this->response->addFields(array(
            array(
                'label' => fx::alang('Block name', 'system'),
                'name'  => 'name',
                'value' => $infoblock['name'],
                'tip'   => $infoblock['controller'] . '.' . $infoblock['action'],
                'tab' => 'settings'
            )
        ));
        
        $c_page = fx::env('page');
        $scope_fields = $this->getScopeFields($infoblock, $c_page);
        $this->response->addFields(
            $scope_fields, 
            'settings', //'header', 
            'scope'
        );

        $this->response->addFields(
            $settings, 
            'settings', // 'header', // tab
            'params'
        );

        $format_fields = $this->getFormatFields($infoblock, $area_meta);
        $this->response->addFields(
            $format_fields, 
            'design', // tab
            'visual'
        );

        if (isset($input['settings_sent']) && $input['settings_sent'] == 'true') {
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
            
            if ($update_priority) {
                $next_vis = fx::data('infoblock_visual')
                    ->where('area', $area_meta['id'])
                    ->where('infoblock.site_id', $site_id)
                    ->where('priority', $i2l['priority'], '>=')
                    ->all();
                $next_vis->apply(function($vis) {
                    $vis->set('priority', $vis['priority'] + 1)->save();
                });
            }

            $i2l['wrapper'] = fx::dig($input, 'visual.wrapper');
            $i2l['template'] = fx::dig($input, 'visual.template');
            
            foreach (array('template_visual', 'wrapper_visual') as $vis_prop) {
                if (isset($input['visual'][$vis_prop])) {
                    if (!is_array($i2l[$vis_prop])) {
                        $i2l[$vis_prop] = array();
                    }
                    $i2l[$vis_prop] = array_merge($i2l[$vis_prop], $input['visual'][$vis_prop]);
                }
            }
            
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

        //$actions = $controller->getActions();

        $fields = array(
            $this->ui->hidden('entity', 'infoblock'),
            $this->ui->hidden('action', 'select_settings'),
            $this->ui->hidden('fx_admin', true),
            $this->ui->hidden('settings_sent', 'true'),
            $this->ui->hidden('controller', $input['controller']),
            $this->ui->hidden('page_id', $input['page_id']),
            $this->ui->hidden('area', serialize($area_meta)),
            $this->ui->hidden('id', isset($input['id']) ? $input['id'] : ''),
            $this->ui->hidden('mode', isset($input['mode']) ? $input['mode'] : '')
        );

        $this->response->addFields($fields);
    }

    public function listForPage($input)
    {
        $fields = array();
        if (!$input['page_id']) {
            return;
        }
        
        $c_page = fx::env('page');

        $infoblocks = fx::data('infoblock')->getForPage($c_page);

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
                //'type'       => fx::alang('Type', 'system'),
                //'visibility' => fx::alang('Visibility', 'system'),
                'area'       => fx::alang('Area', 'system'),
            )
        );

        foreach ($infoblocks as $ib) {
            if ($ib->isLayout()) {
                continue;
            }
            $vis = $ib->getVisual();
            $action = $ib['controller'] . ':' . $ib['action'];
            $list['values'] [] = array(
                'id'         => $ib['id'],
                'name'       => '<div class="fx-infoblock-list-item">'.
                                    '<div class="fx-infoblock-list-item__name">'.$ib['name'].'</div>'.
                                    '<div class="fx-infoblock-list-item__action">'.$action.'</span>'.
                                '</div>',
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
            'id'     => 'page_infoblocks',
            'header' => fx::alang('Page infoblocks')
        );
        return $res;
    }

    public function layoutSettings($input)
    {
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


        //$path_ids = $c_page->getParentIds();
        $path_ids = $c_page->getPath()->getValues('id');
        $path = fx::data('page', $path_ids);
        if (!$path->findOne('id', $c_page['id'])) {
            $path [] = $c_page;
        }
        $path_count = count($path);
        $c_type = $c_page['type'];
        $page_com = fx::data('component', $c_page['type']);
        $c_type_name = $page_com->getItemName('one');

        $container_infoblock = null;
        if ($infoblock['container_infoblock_id']) {
            $container_infoblock = fx::data('infoblock', $infoblock['container_infoblock_id']);
        }

        $c_scope_code = $infoblock->getScopeString();

        $vals = array();

        foreach ($path as $i => $pi) {
            //$sep = str_repeat(" -- ", $i);
            $sep  = str_repeat("&nbsp;", $i*6);
            $pn = $pi['name'];
            $pt = $pi->getComponent()->getItemName('one');
            $pt_of = $pi->getComponent()->getItemName('of');
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
            if ($i !== 0) {
                $vals [] = array(
                    $c_page_id . '-descendants-',
                    $sep . fx::util()->ucfirst(sprintf(fx::alang('%s %s and children'), $pt, $pn))
                );
            }
            if ($is_last) {
                $vals [] = array(
                    $c_page_id . '-this-',
                    $sep . fx::util()->ucfirst(sprintf(fx::alang('%s %s only'), $pt, $pn))
                );
            } else {
                $vals [] = array(
                    $c_page_id . '-children-',
                    $sep . fx::util()->ucfirst(sprintf(fx::alang('%s %s children only'), $pt_of, $pn))
                );
            }
            
            if (!$is_last && $i !== 0) {
                $vals [] = array(
                    $c_page_id . '-children-' . $c_type,
                    $sep . fx::util()->ucfirst(sprintf(fx::alang('%s %s children of type %s'), $pt_of, $pn, $c_type_name))
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
                if (isset($cfg['default_scope'])) {
                    $c_scope_code = is_callable($cfg['default_scope']) 
                                        ? call_user_func($cfg['default_scope'])
                                        : $cfg['default_scope'];
                    
                    if ($c_scope_code === 'this') {
                        $c_scope_code = fx::env('page_id').'-this-';
                    } elseif ($c_scope_code === 'all') {
                        $c_scope_code = fx::env('site')->get('index_page_id').'-descendants-';
                    }
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
            'type'      => 'hidden',
            'label'     => 'Visibility',
            'name'      => 'visibility',
            //'join_with' => 'complex_scope',
            //'values'    => $this->getScopeVisibilityOptions(),
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
        
        
        $layout_name = fx::data('layout', $i2l['layout_id'])->get('keyword');

        $controller_name = $infoblock->getPropInherited('controller');

        $action_name = $infoblock->getPropInherited('action');
        
        $area_suit = Template\Suitable::parseAreaSuitProp(isset($area_meta['suit']) ? $area_meta['suit'] : '');
        
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

        // Collect available wrappers
        $layout_tpl = fx::template('theme.' . $layout_name);
        if ($layout_tpl) {
            $avail_wrappers = \Floxim\Floxim\Template\Suitable::getAvailableWrappers($layout_tpl, $area_meta);
            foreach ($avail_wrappers as $avail_wrapper) {
                $wrappers[$avail_wrapper['full_id']] = $avail_wrapper['name'];
            }
            //$wrappers = array_merge($wrappers, $avail_wrappers);
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
                //'join_with' => 'template',
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
        $result = array();
        if (isset($input['page_id'])) {
            fx::env('page_id', $input['page_id']);
        }

        $ib = fx::data('infoblock', $input['infoblock']['id']);
        
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
        
        $new_entity = null;

        if (isset($input['new_entity_props'])) {
            $new_props = $input['new_entity_props'];
            $new_com = fx::component($new_props['type']);
            $new_entity = fx::content($new_props['type'])->create($new_props);
            $contents['new@'.$new_com['id']] = $new_entity;
            
            // we are working with linker and user pressed "add new" button to create linked entity
            if (isset($input['create_linked_entity'])) {
                $linked_entity_com = fx::component($input['create_linked_entity']);
                $linked_entity = fx::content($linked_entity_com['keyword'])->create();
                $contents['new@'.$linked_entity_com['id']] = $linked_entity;
                // bind the new entity to the linker prop
                if (isset($new_props['_link_field'])) {
                    $link_field = $new_com->getFieldByKeyword($new_props['_link_field'], true);
                    $target_prop = $link_field['format']['prop_name'];
                    $new_entity[$target_prop] = $linked_entity;
                }
            }
        }

        if (isset($vars['content'])) {
            $content_groups = $vars['content']->group(function ($v) {
                $vid = $v['var']['content_id'];
                if (!$vid) {
                    $vid = 'new';
                }
                return $vid.'@'.$v['var']['content_type_id'];
            });
            foreach ($content_groups as $content_id_and_type => $content_vars) {
                list($content_id, $content_type_id) = explode("@", $content_id_and_type);
                if ($content_id !== 'new') {
                    $c_content = fx::content($content_type_id, $content_id);
                    if (!$c_content) {
                        continue;
                    }
                    $contents[$content_id_and_type] = $c_content;
                }
                $vals = array();
                foreach ($content_vars as $var) {
                    $vals[$var['var']['name']] = $var['value'];
                }
                if (isset($contents[$content_id_and_type])) {
                    $contents[$content_id_and_type]->setFieldValues($vals, array_keys($vals));
                } else {
                    fx::log('Content not found in group', $contents, $content_id, $vals);
                }
            }
        }
        
        $new_id = false;
        
        $result['saved_entities'] = array();
        
        foreach ($contents as $cid => $c) {
            try {
                $c->save();
                $result['saved_entities'][]= $c->get();
                if ($cid == 'new') {
                    $new_id = $c['id'];
                }
            } catch (\Exception $e) {
                $result['status'] = 'error';
                if ($e instanceof \Floxim\Floxim\System\Exception\EntityValidation) {
                    $result['errors'] = $e->toResponse();
                }
                break;
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
        return $result;
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
                'name'  => 'delete_confirm',
                'type'  => 'hidden'
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
                'type'   => 'hidden',
                'value' => 'delete'
                /*
                ,
                'label'  => fx::alang('The infoblock contains some content',
                        'system') . ', <b>' . $ib_content->length . '</b> ' . fx::alang('items. What should we do with them?',
                        'system'),
                'values' => array(
                    'unbind' => fx::alang('Unbind/Hide', 'system'),
                    'delete' => fx::alang('Delete', 'system')
                )
                 * 
                 */
            );
        }
        
        $alert = '';
        if (count($ib_content)) {
            $ib_content_count = count($ib_content);
            $ib_content_types = $ib_content->getValues('type');
            $ib_content_type_count = array();
            foreach ($ib_content_types as $ib_content_type) {
                if (!isset($ib_content_type_count[$ib_content_type])) {
                    $ib_content_type_count[$ib_content_type] = 0;
                }
                $ib_content_type_count[$ib_content_type]++;
            }
            
            // block contains linkers only
            if (count($ib_content_type_count) === 1 && $ib_content_types[0] === 'floxim.main.linker') {
                $link_word = fx::util()->getDeclensionByNumber(
                    array(
                        'ссылку', 
                        'ссылки', 
                        'ссылок'
                    ), 
                    $ib_content_count
                );
                $alert = '<p>Блок содержит '.$ib_content_count.' '.$link_word.' на другие данные. ';
                $alert .= $ib_content_count == 1 ? 'Эта ссылка будет удалена' : 'Эти ссылки будут удалены';
                $alert .= ', а сами данные останутся.</p>';
            } else {
                
                // $ib_content_ids = $ib_content->getValues('id');
                $alert = '<p>Блок содержит ';
                if (count($ib_content_type_count) === 1) {
                    $com = fx::component($ib_content_types[0]);
                    $decl = $com['declension'];
                    $alert .= $ib_content_count . ' ';
                    $alert .= fx::util()->getDeclensionByNumber(
                        array(
                            $decl['acc']['singular'], 
                            $decl['gen']['singular'],
                            $decl['gen']['plural'],
                        ), 
                        $ib_content_count
                    );
                    $alert .= '</p>';
                } else {
                    $alert .= ' данные:</p>';
                    $type_parts = array();
                    foreach ($ib_content_type_count as $ib_content_type => $c_type_count) {
                        $com = fx::component($ib_content_type);
                        $type_parts []= $c_type_count.' '.fx::util()->getDeclensionByNumber(
                            $com['declension'],
                            $c_type_count
                        );
                    }
                    $alert .= '<ul><li>'.join('</li><li>', $type_parts).'</li></ul>';
                }
                $alert .= '<p>Эти данные будут удалены.</p>';
                
                
                $ids = $ib_content->getValues('id');
                $nested_query = fx::data('content')
                    ->descendantsOf($ids, false)
                    ->group('type')
                    ->select('type')
                    ->select('count(*) as cnt')
                    ->showQuery();
                $nested_types = fx::db()->getResults($nested_query);
                if (count($nested_types) > 0) {
                    $type_parts = array();
                    foreach ($nested_types as $c_nested_type) {
                        if ($c_nested_type['type'] === 'floxim.main.linker') {
                            continue;
                        }
                        $com = fx::component($c_nested_type['type']);
                        $type_parts []= $c_nested_type['cnt'].' '.fx::util()->getDeclensionByNumber(
                            $com['declension'], 
                            $c_nested_type['cnt']
                        );
                    }
                    if (count($type_parts) > 0) {
                        $alert .= '<p>Также будут удалены все вложенные данные:</p>';
                        $alert .= '<ul><li>'.join('</li><li>', $type_parts).'</li></ul>';
                    }
                }
            }
        }
        $fields []= array(
            'name' => 'content_alert',
            'type' => 'html',
            'value' => '<div class="fx_delete_alert">'.$alert.'</div>'
        );
        
        
        if ($infoblock['controller'] == 'layout' && !$infoblock['parent_infoblock_id']) {
            unset($fields[0]);
            $fields [] = array('type' => 'html', 'html' => fx::alang('Layouts can not be deleted', 'system'));
        }
        $this->response->addFields($fields);
        $this->response->addFormButton(
            array(
                'key' => 'save', 
                'label' => fx::alang('Delete'),
                'class' => 'delete'
            )
        );
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
        if ($infoblock['name']) {
            $header = fx::alang('Delete infoblock', 'system');
            $header .= ' &laquo;'.$infoblock['name'].'&raquo';
        } else {
            $header = fx::alang('Delete this infoblock', 'system');
        }
        $header .= '?';
        $header = '<span title="'.$infoblock['controller'].':'.$infoblock['action'].'">'.$header."</span>";
        return array(
            'header' => $header
        );
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
