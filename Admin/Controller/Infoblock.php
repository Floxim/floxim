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
        $controllers = fx::collection();
        $coms = fx::data('component')->all();
        foreach ($coms as $c) {
            $controllers[$c['keyword']] = array(
                'type' => 'component',
                'keyword' => $c['keyword'],
                'name' => $c['name']
            );
        }
        
        fx::modules()->apply(function($m) use ($controllers) {
            $m_controllers = $m->getControllerKeywords();
            foreach ($m_controllers as $m_ctr) {
                if (isset($controllers[$m_ctr])) {
                    continue;
                }
                $controllers[$m_ctr] = array(
                    'type' => 'widget',
                    'keyword' => $m_ctr,
                    'name' => $m_ctr
                );
            }
        });
        
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
            
            $type = $c['type'];
            $keyword = $c['keyword'];
            
            $result['controllers'] [$keyword]= array(
                'name'     => $c['name'],
                'keyword' => $keyword,
                'type' => $type
            );
            try {
                $ctrl = fx::controller($keyword);
            } catch (\Exception $e) {
                fx::log('faild ctr loading', $e);
                continue;
            }
            $actions = $ctrl->getActions();
            
            foreach ($actions as $action_code => $action_info) {
                // do not show actions starting with "_"
                if (preg_match("~^_~", $action_code)) {
                    continue;
                }
                
                $com = fx::component($c['keyword']);
                
                if ( ($com && !$com->isBlockAllowed($action_code)) ) {
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
                $action_kw = $keyword . ':' . $action_code;
                $act_ctr = fx::controller($action_kw);
                $act_templates = $act_ctr->getAvailableTemplates(fx::env('theme_id'), $area_meta);
                
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
                $a['action_name'] = $a['name'];
                $a['controller_name'] = $controller['name'];
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
                ->where('visuals.theme_id', fx::env('theme_id'))
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
            $this->ui->hidden('area', json_encode($input['area'])),
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
        
        //$presets = $this->getAvailablePresets($blocks['actions']);
        $blocks = $this->groupAvailableBlocksWithListTypesOnTop($blocks);
        /*
        if (count($presets) > 0) {
            $fields[] = array(
                'type' => 'tree',
                'name' => 'preset_id',
                'values' => $presets->getValues(function($preset) {
                    return array($preset['id'], $preset['name']);
                })
            );
        }
         * 
         */
        
        /* The list of controllers */
        $fields['controller'] = array(
            'type'   => 'tree',
            'name'   => 'controller',
            'values' => $blocks
        );
        
        $this->response->addFormButton('cancel');
        
        
        $header = fx::alang('Adding infoblock', 'system');
        
        if ( ($area_name = fx::dig($input, 'area.name')) ) {
            $header .= ' в область &laquo;'.$area_name.'&raquo;';
        }
        
        $result = array(
            'fields'        => $fields,
            'header'        => $header
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
        
        $area_meta = is_string($input['area']) ? json_decode($input['area'],true) : $input['area'];
        
        $site_id = fx::env('site_id');
        
        if (isset($input['id']) && is_numeric($input['id'])) {
            // Edit existing InfoBlock
            $infoblock = fx::data('infoblock', $input['id']);
            $controller = $infoblock->getPropInherited('controller');
            $action = $infoblock->getPropInherited('action');
            $controller_val = $controller.':'.$action;
            $i2l = $infoblock->getVisual();
        } else {
            // Create a new type and ID of the controller received from the previous step
            $controller_val = $input['controller'];
            list($controller, $action) = explode(":", $controller_val);
            
            $infoblock = fx::data("infoblock")->create(array(
                'controller'             => $controller,
                'action'                 => $action,
                'page_id'                => $input['page_id'],
                'site_id'                => $site_id,
                'container_infoblock_id' => $input['container_infoblock_id']
            ));
            $i2l = fx::data('infoblock_visual')->create(array(
                'area'      => $area_meta['id'],
                'theme_id' => fx::env('theme_id')
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
            $cfg = $controller->getConfig($action);
            //$infoblock['name'] = $cfg['actions'][$action]['name'];
            $infoblock['name'] = $cfg['name'];
        }
        
        foreach ($infoblock['params'] as $ib_param => $ib_param_value) {
            if (isset($settings[$ib_param])) {
                $settings[$ib_param]['value'] = $ib_param_value;
            }
        }
        
        $this->response->addTabs(array(
            'settings' => array(
                'label' => fx::alang('Settings'),
                'icon' => 'settings'
            ),
            'design' => array(
                'label' => fx::alang('Template'),
                'icon' => 'design'
            ),
            'wrapper' => array(
                'label' => fx::alang('Block'),
                'icon' => 'container'
            ),
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
        
        $container_is_one_page = isset($area_meta['container_scope_type']) && $area_meta['container_scope_type'] === 'one_page';
        
        if (!$infoblock['id']) {
            
            if (isset($cfg['scope_type'])) {
                $infoblock['scope_type'] = $cfg['scope_type'];
            } else {
                if ($container_is_one_page) {
                    $infoblock['scope_type'] = 'one_page';
                } elseif (isset($area_meta['scope'])) {
                    $infoblock['scope_type'] = $area_meta['scope'] === 'nav' ? 'all_pages' : 'one_page';
                }
            }
        }
        $scope_fields = $this->getScopeFields($infoblock);
        if ($container_is_one_page) {
            $scope_fields[0]['type'] = 'hidden';
        }
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
        
        $wrapper_fields = $this->getWrapperFields($infoblock, $area_meta);
        $this->response->addFields(
            $wrapper_fields, 
            'wrapper', // tab
            'visual'
        );

        if (isset($input['settings_sent']) && $input['settings_sent'] == 'true') {
            
            $is_preset = isset($input['pressed_button']) && $input['pressed_button'] === 'favorite';
            
            if (!$is_preset) {
                $scope_data = $input['scope'];
                
                if (isset($scope_data['user_scope'])) {
                    $infoblock['user_scope'] = $scope_data['user_scope'];
                } else {
                    $infoblock['user_scope'] = null;
                }
                
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
                    case 'infoblock_pages':
                        $infoblock['scope_infoblock_id'] = fx::env('page')->get('infoblock_id');
                        $infoblock['page_id'] = null;
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
            
            foreach (array('template','wrapper') as $template_type) {
                $template_val = $input['visual'][$template_type];
                $vis_props = (array) $input['visual'][$template_type.'_visual'];
                if (is_numeric($template_val)) {
                    $c_variant = fx::data('template_variant', $template_val);
                    if ($c_variant) {
                        $vis_props = array_merge(
                            (array) $c_variant['params'],
                            $vis_props
                        );
                        $i2l[$template_type] = null;
                        $i2l[$template_type.'_variant_id'] = $template_val;
                        $c_variant->set('params', $vis_props)->save();
                    }
                } else {
                    $i2l[$template_type] = $template_val;
                    $i2l[$template_type.'_variant_id'] = null;
                    $vis_props = array_merge(
                        (array) $i2l[$template_type.'_visual'],
                        $vis_props
                    );
                    $i2l[$template_type.'_visual'] = $vis_props;
                }
            }
            
            /*
            $i2l['wrapper'] = fx::dig($input, 'visual.wrapper');
            $i2l['template'] = fx::dig($input, 'visual.template');
            
            foreach (array('template_visual', 'wrapper_visual') as $vis_prop) {
                if (isset($input['visual'][$vis_prop])) {
                    if (!is_array($i2l[$vis_prop])) {
                        $i2l[$vis_prop] = array();
                    }
                    $c_prop_data = $input['visual'][$vis_prop];
                    $i2l[$vis_prop] = array_merge($i2l[$vis_prop], $c_prop_data);
                }
            }
            */
            
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
            $this->ui->hidden('controller', $controller_val),
            $this->ui->hidden('page_id', isset($input['page_id']) ? $input['page_id'] : ''),
            $this->ui->hidden('area', json_encode($area_meta)),
            $this->ui->hidden('id', isset($input['id']) ? $input['id'] : ''),
            $this->ui->hidden('mode', isset($input['mode']) ? $input['mode'] : '')
        );

        $this->response->addFields($fields);
        $this->response->addFormButton('cancel');
        /*
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
        */
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

        $infoblocks = fx::page()->getInfoblocks();

        if ($input['data_sent']) {
            foreach ($infoblocks as $ib) {
                $ib_data = fx::dig($input, ['infoblocks', $ib['id']]);
                if (isset($ib_data['area'])) {
                    $vis = $ib->getVisual();
                    $vis['area'] = $ib_data['area'];
                    $vis->save();
                }
                /*
                if (isset($input['visibility'][$ib['id']])) {
                    $ib->digSet('scope.visibility', $input['visibility'][$ib['id']]);
                    $ib->save();
                }
                 * 
                 */
            }
            return;
        }

        $list = array(
            'type'   => 'set',
            'name' => 'infoblocks',
            'values' => array(),
            'labels' => array(
                'Блок',
                'Область'
            ),
            'without_delete' => true,
            'tpl' => [
                ['name' => 'name', 'type' => 'html'],
                ['name' => 'area', 'type' => 'livesearch']
            ]
        );

        foreach ($infoblocks as $ib) {
            if ($ib->isLayout()) {
                continue;
            }
            $vis = $ib->getVisual();
            $action = $ib['controller'] . ':' . $ib['action'];
            $list['values'] [] = array(
                'id'         => $ib['id'],
                'name'       => '<div class="fx-infoblock-list-item" title="'.$ib['id'].'">'.
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
            'panel' => [
                'is_fluid' => true,
                'keep_hilight_on' => true
            ],
            'header' => fx::alang('Page infoblocks')
        );
        return $res;
    }

    public function layoutSettings($input)
    {
        $c_page = fx::env('page');
        $infoblock = fx::router('front')->getLayoutInfoblock($c_page);

        $scope_fields = $this->getScopeFields($infoblock);
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
    
    protected function getScopeTypeField($infoblock)
    {
        $page = fx::env('page');
        $page_type_name = $page->getComponent()->getItemName();
        $page_ib_name = $page['infoblock']['name'];
        return array(
            'type'   => 'livesearch',
            'label'  => fx::alang('Scope'),
            'name'   => 'type',
            'values' => array(
                array('one_page', 'Только эта страница'),
                array('all_pages', 'Все страницы'),
                array('infoblock_pages', 'Страницы такого типа', array(
                    'title' => 'Показывать на страницах, имеющих тип «'.$page_type_name.'»'.
                                ' и принадлежащих инфоблоку «'.$page_ib_name.'»'
                )),
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
        if (fx::config('has_users')) {
            $fields []= array(
                'type' => 'livesearch',
                'is_multiple' => true,
                'label' => 'Кому показывать',
                'name' => 'user_scope',
                'values' => array(
                    array(
                        'guest',
                        'Гостям'
                    ),
                    array(
                        'admin',
                        'Админам'
                    ),
                    array(
                        'user',
                        'Пользователям'
                    )
                ),
                'value' => $infoblock['user_scope']
            );
        }
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
            $type_field['values'][3][1] = 'Специальные условия';
            $fields []= $type_field;
        }
        
        // re-edit
        if (isset($input['conditions'])) {
            $scope['conditions'] = $input['conditions'];
        }
        
        /*
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
            
        //$fields []= $q_field;
         * 
         */
        
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
            'types' => fx::data('component')->getTypesHierarchy(),
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
    
    public function checkScope($input)
    {
        $scope = fx::data('scope')->create(
            array(
                'conditions' => $input['conditions']
            )
        );
        $finder = $scope->getPageFinder();
        $finder->where('site_id', fx::env('site_id'));
        
        $finder->limit(10);
        $finder->calcFoundRows();
        
        $pages = $finder->all();
        if (count($pages) === 0) {
            $count = 0;
            $has_current_page = false;
        } else {
            $count = $finder->getFoundRows();
            
            
            $c_page_id = fx::env('page_id');
            
            $c_page_in_found = $pages->findOne('id', $c_page_id);
            
            if ($c_page_in_found) {
                $has_current_page = true;
            } else {
                if (count($pages) === $count) {
                    $has_current_page = false;
                } else {
                    $has_current_page = (bool) $finder->where('id', $c_page_id)->one();
                }
            }
        }
        
        $pages = $pages->getValues(function($p) {
            return array(
                'name' => $p['name'],
                'type' => $p['type'],
                'type_name' => $p->getComponent()->getItemName(),
                'url' => $p['url']
            );
        });
        
        
        if ($count === 0) {
            $total_readable = 'Не нашлось <b>ни одной</b> подходящей страницы';
        } else {
            $total_readable = fx::util()->getDeclensionByNumber(
                array(
                    'Нашлась %s подходящая страница', 
                    'Нашлось %s подходящих страницы',
                    'Нашлось %s подходящих страниц'
                ), 
                $count
            );
            $total_readable = sprintf($total_readable, '<b>'.$count.'</b>');
        }
        
        $res = array(
            'total_readable' => $total_readable,
            'total' => $count,
            'pages' => $pages,
            'has_current_page' => $has_current_page
        );
        return $res;
    }

    protected function getFormatFields(CompInfoblock\Entity $infoblock, $area_meta = null)
    {
        $ib_visual = $infoblock->getVisual();
        $fields = array(
            array(
                'label' => "Area",
                'name'  => 'area',
                'value' => $ib_visual['area'],
                'type'  => 'hidden'
            )
        );
        
        $controller = $infoblock->initController();
        $tmps = $controller->getAvailableTemplates(fx::env('theme_id'), $area_meta);
        
        $c_value = $ib_visual['template_variant_id'] 
                                ? $ib_visual['template_variant_id'] 
                                : $ib_visual['template'];
        
        $tpl_field = $this->getTemplatesField(
            $tmps,
            $controller,
            $area_meta,
            'template',
            $c_value,
            $infoblock
        );
        
        if ($tpl_field['value'] && !$c_value) {
            $ib_visual['template_variant_id'] = $tpl_field['value'];
        }
        
        $fields []= $tpl_field;
        
        return $fields;
    }
    
    protected function getTemplateVariantCounts($variants, $skip_infoblock_id = null)
    {
        if (count($variants) === 0) {
            return [];
        }
        $q = fx::data('template_variant');

        $q->join(
                '{{infoblock_visual}} as vis', 
            '(vis.template_variant_id = {{template_variant}}.id '.
                ' or vis.wrapper_variant_id = {{template_variant}}.id)'.
                ($skip_infoblock_id ? ' and vis.infoblock_id != '. ( (int) $skip_infoblock_id) : '')
        );

        $q->where('id', $variants->getValues('id'));

        $q->group('{{template_variant}}.id');

        $q->select('id', 'count(*) as cnt');
        
        
        $data = $q->getData();
        $res = $data->getValues('cnt', 'id');
        return $res;
    }
    
    public function getTemplatesField(
        $templates, 
        $controller = null, 
        $area_meta = null, 
        $role = 'template',
        $c_value = null,
        $infoblock = null
    )
    {
        
        if (empty($templates)) {
            return array(
                'type' => 'hidden',
                'name' => 'template',
                'value' => $c_value
            );
        }
        
        // Collect the available templates
        $theme_variants = fx::env('theme')->get('template_variants');
        
        $area_size = isset($area_meta['size']) ? $area_meta['size'] : '';
        $area_size = \Floxim\Floxim\Template\Suitable::getSize($area_size);
        
        $template_codes = fx::collection($templates)->getValues('full_id');
        
        $mismatched = fx::collection();
        
        
        $template_variants = $theme_variants->find(
            function($variant) use ($area_size, $template_codes, &$c_value, $controller, $mismatched) {
                if (!in_array($variant['template'], $template_codes)) {
                    return false;
                }
                if (
                    $area_size['width'] !== 'any' && 
                    ($variant['size'] && $variant['size'] !== 'any' && $variant['size'] !== $area_size['width'])
                ) {
                    $mismatched []= $variant;
                    return false;
                }
                if ($controller) {
                    $avail_for_type = $controller->checkTemplateAvailForType($variant);
                    if (!$avail_for_type) {
                        $mismatched []= $variant;
                        return false;
                    }
                }
                if (is_null($c_value)) {
                    $c_value = $variant['id'];
                }
                return true;
            }
        );
        
        
        $template_variant_counts = $this->getTemplateVariantCounts(
            $template_variants,
            $infoblock ? $infoblock['id'] : null
        );
        
        $values = [];
        
        $special_values = [];
        
        
        $variant_to_value = function($variant) use ($template_variant_counts) {
            return array(
                (string) $variant['id'],
                $variant['name'],
                array(
                    'basic_template' => $variant['template'],
                    'real_name' => $variant->getReal('name'),
                    'is_locked' => $variant['is_locked'],
                    'size' => $variant['size'] ? $variant['size'] : 'any',
                    'avail_for_type' => $variant['avail_for_type'],
                    'wrapper_variant_id' => $variant['wrapper_variant_id'],
                    'count_using_blocks' => isset($template_variant_counts[$variant['id']]) ?
                                                $template_variant_counts[$variant['id']] :
                                                0
                )
            );
        };
        
        foreach ($templates as $template) {
            
            $c_template_variants = $template_variants->find('template', $template['full_id']);
                
            foreach ($c_template_variants as $variant) {
                $values []= $variant_to_value($variant);
            }
            
            $special_values []= [
                'id' => $template['full_id'],
                'name' => $template['name']
            ];
            
        }
        
        if (count($special_values) > 1) {
            $values []= [
                'name' => 'Специальные настройки',
                'children' => $special_values,
                'expanded' => 'always',
                'disabled' => true
            ];
        } else {
            $special_values[0]['name'] = 'Специальные настройки';
            $values []= $special_values[0];
        }
        if (is_null($c_value)) {
            $c_value = $special_values[0]['id'];
        }
        
        if (count($mismatched) > 0) {
            $mismatched_values = [
                'name' => '<span style="color:#F00;">Не подходят</span>',
                'children' => [],
                //'expanded' => 'always',
                'expanded' => false,
                'disabled' => true
            ];
            foreach ($mismatched as $variant) {
                $mismatched_value = $variant_to_value($variant);
                if (isset($mismatched_value[2]['avail_for_type'])) {
                    $target_com = fx::getComponentByKeyword($mismatched_value[2]['avail_for_type']);
                    if ($target_com) {
                        $mismatched_value[2]['target_type_name'] = $target_com['name'];
                    }
                }
                $mismatched_values['children'] []= $mismatched_value;
            }
            $values []= $mismatched_values;
        }
        
        $res = array(
            'label'  => fx::alang('Template', 'system'),
            'name'   => 'template',
            'type'   => 'livesearch',
            'values' => $values,
            'value' => $c_value
        );
        if ($controller && $role !== 'wrapper') {
            $avail_for_type_field = $controller->getTemplateAvailForTypeField();
            if ($avail_for_type_field) {
                $res['template_variant_params'] = [$avail_for_type_field];
            }
        }
        return $res;
    }
    
    protected function getWrapperContainer($visual)
    {
        $parent_props = isset($_POST['content_parent_props']) ? json_decode($_POST['content_parent_props'], true) : array();
        
        $container = new \Floxim\Floxim\Template\Container(
            null, 
            'wrapper_'.($visual['infoblock_id'] ? $visual['infoblock_id'] : 'new'),
            'wrapper_visual',
            array(
                \Floxim\Floxim\Template\Container::create($parent_props)
            )
        );
        
        $container->bindVisual($visual);
        
        return $container;
    }
    
    protected static function collectWrapperTemplates($area_meta)
    {
        $wrapper_tpl = fx::template('floxim.layout.wrapper');
        return \Floxim\Floxim\Template\Suitable::getAvailableWrappers($wrapper_tpl, $area_meta);
    }
    
    protected function getWrapperFields($infoblock, $area_meta)
    {
        
        $wrappers = self::collectWrapperTemplates($area_meta);
        $controller = $infoblock->initController();
        
        $visual = $infoblock->getVisual();
        
        $c_value = $visual['wrapper_variant_id'] 
                                ? $visual['wrapper_variant_id'] 
                                : $visual['wrapper'];
        
        if (is_null($c_value)) {
            $template_variant = $visual['template_variant'];
            $c_value = $template_variant['wrapper_variant_id'];
        }
        
        $field = $this->getTemplatesField(
            $wrappers, 
            $controller, 
            $area_meta, 
            'wrapper', 
            $c_value,
            $infoblock
        );
        $field['name'] = 'wrapper';
        
        $field['values'][]= ['', '-нет-'];
        
        return array($field);
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
            foreach ($vars['content'] as $vn => $v) {
                if (!isset($v['var']['content_type_id']) && isset($v['var']['content_type'])) {
                    $var_com = fx::getComponentByKeyword($v['var']['content_type']);
                    $v['var']['content_type_id'] = $var_com['id'];
                    $vars['content'][$vn] = $v;
                }
            }
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
                    fx::log('Content not found in group', $contents, $content_id, $vals, $content_id_and_type);
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
                if (isset($var['scope_path']) && $var['scope_path'] !== '') {
                    fx::digSet($c_visual, $var['scope_path'].'.'.$var['id'], $value);
                } else {
                    if ($value == 'null') {
                        unset($c_visual[$var['id']]);
                    } else {
                        $c_visual[$var['id']] = $value;
                    }
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
        if (isset($input['next_visual_id'])) {
            $vis->moveBefore($input['next_visual_id']);
        } else {
            $vis->moveLast();
        }
        $vis->save();
    }
    
    public function containerSettings($input)
    {
        $fields = array(
            $this->ui->hidden('action', $input['action']),
            $this->ui->hidden('entity', $input['entity']),
            $this->ui->hidden('visual_id', $input['visual_id']),
            $this->ui->hidden('container_meta', $input['container_meta']),
            $this->ui->hidden('data_sent', 1),
        );
        
        $meta = json_decode($input['container_meta'],true);
        
        $vis = fx::data('infoblock_visual', $input['visual_id']);
        
        $parent_props = isset($_POST['content_parent_props']) ? json_decode($_POST['content_parent_props'], true) : array();
        
        $container = new \Floxim\Floxim\Template\Container(
            null, 
            $meta['name'], 
            $meta['set'],
            array(
                \Floxim\Floxim\Template\Container::create($parent_props)
            )
        );
        
        $container->bindVisual($vis);
        
        $container_fields = $container->getForm();
        
        foreach ($container_fields as $field) {
            $fields []= $field;
        }

        if (isset($input['data_sent'])){
            $container->save($input);
        }
        
        return array(
            'fields' => $fields
        );
    }
    
    public function saveTemplateVariant($input)
    {
        
        $tv = isset($input['target_id']) && !$input['save_as_new'] 
                    ? fx::data('template_variant', $input['target_id'])
                    : fx::data('template_variant')->create(array(
                        'theme_id' => fx::env('theme_id'),
                        'template' => $input['basic_template']
                    ));
        
        if ($tv->isSaved() && $input['pressed_button'] === 'delete') {
            $template_value = $tv['template'];
            $tv->delete();
        } else {
            $variant_props = ['name', 'is_locked', 'size', 'avail_for_type', 'wrapper_variant_id'];
            foreach ($variant_props as $prop) {
                if (isset($input[$prop])) {
                    $tv[$prop] = $input[$prop];
                }
            }
            $prev_params = $tv['params'] ? $tv['params'] : array();
            $new_params = isset($input['params']) && is_array($input['params']) ? $input['params'] : [];
            $tv['params'] = fx::util()->fullMerge($prev_params, $new_params);
            $tv->save();
            $template_value = $tv['id'];
        }
        
        $controller = fx::controller($input['controller']);
        $area = $input['area'];
        
        if ($input['template_type'] === 'wrapper') {
            $templates = self::collectWrapperTemplates($area);
        } else {
            $templates = $controller->getAvailableTemplates(null, $area);
        }
        
        $ib_id = isset($input['infoblock_id']) && $input['infoblock_id'] ? $input['infoblock_id'] : null;
        
        $template_field = $this->getTemplatesField(
            $templates, 
            $controller,
            $area,
            $input['template_type'],
            null,
            $ib_id ? fx::data('infoblock', $ib_id) : null
        );
        
        return array(
            'template_value' => $template_value,
            'template_field' => $template_field
        );
    }
    
    public function getTemplateVariantUsingBlocks($input)
    {
        $id = (int) $input['template_variant_id'];
        if (!$id) {
            return;
        }
        $q = fx::data('infoblock_visual')
                ->whereOr(
                    ['template_variant_id', $id],
                    ['wrapper_variant_id', $id]
                )
                ->with('infoblock');
        if (isset($input['infoblock_id']) && $input['infoblock_id']) {
            $q->where('infoblock_id', $input['infoblock_id'], '!=');
        }
        $blocks = $q
                ->all()
                ->getValues(function($vis) {
                    return $vis['infoblock']->getSummary();
                });
        return [
            'fields' => [
                [
                    'type' => 'infoblock_list',
                    'value' => $blocks
                ]
            ]
        ];
    }
}