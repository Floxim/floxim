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
                    'id' => $keyword.':'.$action_code,
                    'templates' => array()
                );
                
                foreach ($act_templates as $tplv) {
                    $action['templates'][$tplv['full_id']] = $tplv['name'];
                }
                
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
    
    protected function getAvailablePresets($actions)
    {
        $presets = 
            fx::data('infoblock')
                ->where('visuals.layout_id', fx::env('layout_id'))
                ->where('is_preset',1)
                ->all();
        $actions = fx::collection($actions);
        $presets = $presets->find( function($preset) use ($actions) {
            $vis = $preset->getVisual();
            $action = $actions->find(function($action) use ($preset, $vis) {
                return $action['controller'] === $preset['controller']
                    && $action['keyword'] === $preset['action']
                    && isset($action['templates'][ $vis['template'] ]);
            });
            if ($action) {
                return true;
            }
        });
        return $presets;
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
        
        if (isset($input['next_visual_id'])) {
            $fields []= $this->ui->hidden('next_visual_id', $input['next_visual_id']);
        }

        $page = fx::env('page');

        $area_meta = $input['area'];
        
        $blocks = $this->getAvailableBlocks($page, $area_meta);
        $presets = $this->getAvailablePresets($blocks['actions']);
        $blocks = $this->groupAvailableBlocksWithListTypesOnTop($blocks);
        
        if (count($presets) > 0) {
            $fields[] = array(
                'type' => 'tree',
                'name' => 'preset_id',
                'values' => $presets->getValues(function($preset) {
                    return array($preset['id'], $preset['name']);
                })
            );
        }

        /* The list of controllers */
        $fields['controller'] = array(
            'type'   => 'tree',
            'name'   => 'controller',
            'values' => $blocks
        );
        
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
        if (isset($input['preset_id']) && $input['preset_id'] > 0) {
            return $this->renderPreset($input);
        }
        // The current, editable InfoBlock
        $infoblock = null;
        
        if (isset($input['next_visual_id'])) {
            $this->response->addFields(array(
                $this->ui->hidden('next_visual_id', $input['next_visual_id'])
            ));
        }
        
        $area_meta = is_string($input['area']) ? unserialize($input['area']) : $input['area'];
        
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
            $i2l = fx::data('infoblock_visual')->create(array(
                'area'      => $area_meta['id'],
                'layout_id' => fx::env('layout')
            ));
            if (isset($input['next_visual_id']) && $input['next_visual_id']) {
                $i2l->moveBefore($input['next_visual_id']);
            } else {
                $i2l->moveLast();
            }
            $infoblock->setVisual($i2l);
        }

        if (!isset($infoblock['params']) || !is_array($infoblock['params'])) {
            $infoblock->addParams(array());
        }

        $controller = fx::controller(
            $controller . ':' . $action,
            array('infoblock_id' => $infoblock['id']) + $infoblock['params']
        );
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
                'label' => fx::alang('Settings')
            ),
            'design' => array(
                'label' => fx::alang('Design settings')
            )
        ));
        
        $this->response->addFields(array(
            array(
                'label' => fx::alang('Block name', 'system'),
                'name'  => 'name',
                'value' => $infoblock['name'],
                'tip'   => $infoblock['controller'] . '.' . $infoblock['action'] . ' | '. ($infoblock['id'] ? $infoblock['id'] : 'new'),
                'tab' => 'settings'
            )
        ));
        
        $c_page = fx::env('page');
        $scope_fields = $this->getScopeFields($infoblock, $c_page);
        $this->response->addFields(
            $scope_fields, 
            'settings',
            'scope'
        );

        $this->response->addFields(
            $settings, 
            'settings',
            'params'
        );

        $format_fields = $this->getFormatFields($infoblock, $area_meta);
        $this->response->addFields(
            $format_fields, 
            'design', // tab
            'visual'
        );

        if (isset($input['settings_sent']) && $input['settings_sent'] == 'true') {
            
            $is_preset = isset($input['pressed_button']) && $input['pressed_button'] === 'favorite';
            
            if (!$is_preset) {
                $scope_data = $input['scope'];
                $infoblock['scope_type'] = $scope_data['type'];
                switch ($scope_data['type']) {
                    case 'custom':
                        $scope_params = json_decode($scope_data['params'], true);
                        if (isset($scope_params['id']) && is_numeric($scope_params['id']) ) {
                            $scope = fx::data('scope', (int) $scope_params['id']);
                        } else {
                            $scope = fx::data('scope')->create();
                        }
                        $scope['conditions'] = $scope_params['conditions'];
                        $infoblock['scope_entity'] = $scope;
                        $infoblock['page_id'] = null;
                        break;
                    case 'one_page':
                        $infoblock['page_id'] = fx::env('page_id');
                        break;
                    case 'all_pages':
                        $infoblock['page_id'] = null;
                        break;
                }
            } else {
                $infoblock['is_preset'] = 1;
            }
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
            
            if (isset($controller) && $controller instanceof System\Controller) {
                $controller->setInput($action_params);
                $controller->setParam('infoblock_id', $infoblock['id']);
                if ($is_new_infoblock) {
                    $controller->handleInfoblock('install', $infoblock, $input);
                }
                $controller->handleInfoblock('save', $infoblock, $input);
            }
            $this->response->setStatusOk();
            $this->response->setProp('infoblock_id', $infoblock['id']);
            return;
        }
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
        $this->response->addFormButton('cancel');
        if (!$infoblock['id']) {
            $this->response->addFormButton(
                array(
                    'key' => 'favorite', 
                    'label' => fx::alang('Favorite'),
                    'class' => 'cancel',
                    'is_active' => false,
                    'is_submit' => true
                )
            );
        }
        $this->response->addFormButton('save');
    }
    
    public function renderPreset($input) {
        $preset = fx::data('infoblock', $input['preset_id']);
        $html = $preset->render();
        return array(
            'preset_id' => $input['preset_id'],
            'html'  => $html
        );
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

        $c_page = fx::data('floxim.main.page', $input['page_id']);

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
    
    protected function getScopeTypeField($infoblock)
    {
        return array(
            'type'   => 'livesearch',
            'label'  => fx::alang('Scope'),
            'name'   => 'type',
            'values' => array(
                array('one_page', 'Только эта страница'),
                array('all_pages', 'Все страницы'),
                array('custom', 'Настроить...')
            ),
            'value'  => $infoblock['scope_type'] ? $infoblock['scope_type'] : 'one_page'
        );
    }
    
    protected function getScopeFields(CompInfoblock\Entity $infoblock)
    {
        $scope = $infoblock['scope_entity'];
        $fields = array(
            $this->getScopeTypeField($infoblock),
            array(
                'type' => 'hidden',
                'name' => 'params',
                'value' => $scope ? json_encode(array('id' => $scope['id'], 'conditions' => $scope['conditions'])) : null
            )
        );
        return $fields;
        
    }
    public function scope($input) 
    {
        if (isset($input['params']) && isset($input['params'][0])) {
            $input['infoblock_id'] = $input['params'][0];
        }
        if (isset($input['infoblock_id'])) {
            $ib = fx::data('infoblock', $input['infoblock_id']);
            $scope = $ib['scope_entity'];
        }
        if (!$scope) {
            $scope = fx::data('scope')->create();
        }
        $fields = array();
        
        $has_type_field = isset($input['force_scope_type']);
        if ($has_type_field) {
            $type_field = $this->getScopeTypeField($ib);
            $type_field['tab'] = 'header';
            $type_field['values'][2][1] = 'Специальные условия';
            $fields []= $type_field;
        }
        
        // re-edit
        if (isset($input['conditions'])) {
            $scope['conditions'] = $input['conditions'];
        }
        
        $path = fx::env()->getPath();
        $scope_page_id = $scope->getScopePageId($path);
        
        $q_field = array(
            'type' => 'radio_facet',
            'label' => 'Быстрый выбор',
            'name' => 'scope[page_id]',
            'values' => $path->getValues(function($v) {
                return array($v['id'], $v->getName());
            }),
            'value' => $scope_page_id
        );
            
        if ($has_type_field) {
            $q_field['parent'] = array(
                array('type', 'custom')
            );
        }
            
        $fields []= $q_field;
        
        $fields []= array(
            'name' => 'scope[id]',
            'type' => 'hidden',
            'value' => $scope['id']
        );
        
        $cond_field = array(
            'name' => 'scope[conditions]',
            'type' => 'condition',
            'fields' => array(
                fx::component('floxim.main.page')->getFieldForFilter('entity')
            ),
            'value' => $scope['conditions'],
            'label' => false
        );
        
        if ($has_type_field) {
            $cond_field['parent'] = array(
                array('type', 'custom')
            );
        }
        $fields[]= $cond_field;
        
        return array(
            'fields' => $fields,
            'header' => 'Где показывать блок?'
        );
    }

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
            $wrappers[]= array('', '-', array('title' => fx::alang('With no wrapper', 'system')));
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
            $cnt = 0;
            foreach ($avail_wrappers as $avail_wrapper) {
                $cnt++;
                $wrappers[]= array($avail_wrapper['full_id'], $cnt,  array('title' => $avail_wrapper['name']));
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
                'type'      => 'radio_facet',
                //'join_with' => 'template',
                'hidden_on_one_value' => true,
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
        
        // check if we are saving first content for an infoblock created from preset
        $ib_is_preset = $ib['is_preset'];
        $preset_id = null;
        
        if ($ib_is_preset) {
            $preset_id = (int) $ib['id'];
            $ib = $ib->createFromPreset();
            if (isset($input['preset_params'])) {
                $ib_visual = $ib->getVisual();
                $ib_visual['area'] = $input['preset_params']['area'];
                if (isset($input['preset_params']['next_visual_id'])) {
                    $ib_visual->moveBefore($input['preset_params']['next_visual_id']);
                } else {
                    $ib_visual->moveFirst();
                }
            }
            $ib->save();
            fx::log('created from preset', $ib, $preset_id);
        }
        
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
                if ($ib_is_preset && $c['infoblock_id'] === $preset_id) {
                    $c['infoblock_id'] = $ib['id'];
                }
                fx::log('saving', $c, $input);
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
        if ($ib_is_preset) {
            $result['real_infoblock_id'] = $ib['id'];
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
                $nested_query = fx::data('floxim.main.content')
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
        $vis['area'] = $input['area'];
        fx::log('moving vis', $vis, $input);
        if (isset($input['next_visual_id'])) {
            $vis->moveBefore($input['next_visual_id']);
            fx::log('place before', $vis, $input['next_visual_id']);
        } else {
            $vis->moveLast();
            fx::log('place last', $vis);
        }
        $vis->save();
    }
}
