<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Test extends Admin {
    public function colorSet()
    {
        //$fields = $this->testColors();
        $fields = array(
            'palette' => array(
                'label' => 'Color',
                'type' => 'colorset',
                'value' => fx::env('palette')->dig('params.colors.alt')
                //'value' =>json_decode('{"palette-0":"#870043","palette-1":"#ad0056","palette-2":"#ef0087","palette-3":"#ff9dce","palette-4":"#ffc1e0","palette-5":"#ffdeef","palette-hue":330,"palette-saturation":1,"tweaked":{"2":true}}',true)
            )
        );
        $this->response->addFields($fields);
    }
    
    public function ratio() {
        $fields = array(
            'ratio' => array(
                'label' => 'Пропорции',
                'type' => 'ratio',
                'value' => 'none',
                'min' => 1
            )
        );
        $this->response->addFields($fields);
    }
    
    public function setf()
    {
        $f = [
            'filters' => [
                'type' => 'set',
                'without_delete' => true,
                'tpl' => [
                    [
                        'name' => 'field',
                        'type' => 'text',
                        'disabled' => true
                    ],
                    [
                        'name' => 'title',
                        'type' => 'html'
                    ],
                    [
                        'name' => 'on',
                        'type' => 'checkbox'
                    ]
                ],
                'labels' => [
                    '',
                    'Поле',
                    'Вкл'
                ],
                'values' => [
                    [
                        'field' => 'price',
                        'title' => 'Цена',
                        'on' => false
                    ],
                    [
                        'field' => 'section',
                        'title' => 'Раздел',
                        'on' => true
                    ],
                    [
                        'field' => 'country',
                        'title' => 'Страна',
                        'on' => false
                    ]
                ]
            ]
        ];
        $this->response->addFields($f);
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
            /*
            'test' => array(
                'label' => 'padding',
                'type' => 'measures',
                'prop' => 'padding',
                'lock' => '1-3--2-4'
            ),
             * 
             */
            'cr' => array(
                'label' => 'corners',
                'type' => 'measures',
                'prop' => 'corners',
                'lock' => '1-2--3-4'
            ),
            'br' => array(
                'label' => "borders",
                'type' => 'measures',
                'prop' => 'borders',
                'lock' => '1-3--2-4'/*,
                'value' => 
                    '10 1px solid main 2 0.2, '.
                    '7 3px dotted alt 2 0.2, '.
                    '5 4px solid alt 6 0.1, '.
                    '10 1px solid main 2 0.2'*/
            )
        );
        $this->response->addFields($fields);
    }
    
    public function codemirror()
    {
        $fields = array(
            'test' => array(
                'label' => 'CodeMirror',
                'type' => 'text',
                'code' => 'true'
            )
        );
        $this->response->addFields($fields);
    }
    
    public function background() 
    {
        
        $val = 'light,
        
                    linear 80deg,
                    main 2 0.5 0 main 1 0.1 50% alt 2 1 80%,
                    ~"0% 50% / 100% 100%" no-repeat scroll,

                    linear 45deg,
                    third 5 0.4 0 main 3 0.8 100%,
                    ~"0% 50% / 50% 100%" no-repeat scroll,

                    image,
                    "http://lesstester.com/assets/img/logo.png",
                    ~"50% 100% / 30% 30%" repeat-x fixed,
                    
                    color,
                    alt 2 0.5,
                    none';
        
        $val = '!light,
        
                    linear 80deg,
                    main 0 0.1 0 main 0 0.1 100%,
                    ~"0% 50% / 100% 100%" no-repeat scroll,
                    
                    color,
                    main 5 0.8,
                    none';
        
        $fields = array(
            'test' => array(
                'label' => "Background",
                'type' => 'css-background',
                'value' => $val
            )
        );
        $this->response->addFields($fields);
    }
    
    public function parentCond()
    {
        $fields = array(
            'ratio' => array(
                'label' => 'rat',
                'type' => 'ratio',
                'auto' => true
            ),
            'size' => array(
                'type' => 'livesearch',
                'values' => array(
                    array('small', 'Smal'),
                    array('large', 'BIIIG')
                )
            ),
            'test' => array(
                'label' => "Tst",
                'parent' => 'size != large && ratio != none'
            )
        );
        $this->response->addFields($fields);
    }
    
    public function livesearch()
    {
        $vals = array(
            array(
                'test', '1. My Test'
            ),
            array(
                '', 'Empty val'
            ),
            array(
                'west', 
                '2. Oh West',
                array(
                    'children' => array(
                        array('south.west', '2.1. Southern'),
                        array('nord.west', '2.2. Northen')
                    )
                )
            ),
            array(
                'soo', '3. Doo'
            )
        );
        /*
        $valus []= array(
            'custom', 
            null,
            array(
                'custom' => true,
                'type' => 'number',
                'units' => '%',
                'min' => 10,
                'max' => 100,
                'step' => 5
            )
        );
         * 
         */
        $fields = array(
            'test' => array(
                'label' => 'LS custom',
                'type' => 'livesearch',
                'allow_empty' => false,
                'values' => $vals,
                'value' => ''
            )
        );
        /*
        $page = fx::data('floxim.main.page', 3843);
        $fields []= $page->getFormField('parent_id');
        
        $fields['ajax'] = array(
            'type' => 'livesearch',
            "content_type" => "floxim.blog.news",
            'label' => 'pages',
            'is_multiple' => true,
            'value' => array("3797", "3801")
        );
         * 
         */
        $this->response->addFields($fields);
    }
    
    public function grid() {
        \Floxim\Ui\Grid\Grid::addAdminAssets();
        $f = array (
            'type' => 'fx-grid-builder',
            'code' => true,
            'label' => 'Grid',
            'value' => 
            array (
              'cols' => 
              array (
                0 => 
                array (
                  'id' => 'a',
                  'name' => 'Колонка раз',
                    'width' => 6
                ),
                1 => 
                array (
                  'id' => 'b',
                  'name' => 'Колонка два',
                    'width' => 4
                ),
                2 => 
                array (
                  'id' => 'c',
                  'name' => 'Колонка 3',
                    'width' => 2
                ),
              ),
              'is_stored' => true,
            ),
            'params' => 
            array (
              'cols.0.col1_style' => 
              array (
                'label' => '',
                'type' => 'style',
                'block' => 'floxim--ui--grid--col',
                'value' => 'default',
                'is_inline' => true,
                'style_id' => 'ce8f8f3548308622331bb0b9807880b4',
                'asset_id' => 'default',
                'mod_value' => 'default',
              ),
              'cols.1.col2_style' => 
              array (
                'label' => '',
                'type' => 'style',
                'block' => 'floxim--ui--grid--col',
                'value' => 'default',
                'is_inline' => true,
                'style_id' => 'df7230974af057bdf715ccf9d88c351b',
                'asset_id' => 'default',
                'mod_value' => 'default',
              ),
              'cols.2.col3_style' => 
              array (
                'label' => '',
                'type' => 'style',
                'block' => 'floxim--ui--grid--col',
                'value' => 'default',
                'is_inline' => true,
                'style_id' => 'a098d688c6b481e41453daae3be32e04',
                'asset_id' => 'default',
                'mod_value' => 'default',
              ),
            ),
            'name' => 'visual[template_visual][grid]',
            'view_context' => 'panel',
        );
        return array('fields' => array($f));
    }
    
    public function box() {
        
        fx::env('url','/kontakty');
        $ib = fx::data('infoblock',1006);
        $html = $ib->render();
        preg_match("~data-fx_template_params='([^\']+?)'~", $html, $params);
        $f = json_decode($params[1],1)[1][1];
        
        \Floxim\Ui\Box\Box::addAdminAssets();
        \Floxim\Ui\Grid\Grid::addAdminAssets();
        return array(
            'fields' => [$f]
        );
    }
    
    public function multisearch()
    {
        $f = array(
            'ajax_preload' => true,
            'is_multiple' => true,
            'label' => "Выбраны",
            'name' => "params[selected]",
            'value' => array(3797, 3796),
            'type' => 'livesearch',
            'params' => array( 
                'content_type' => "floxim.blog.news" 
            )
        );
        return array('fields' => array($f));
    }
    
    public function icons()
    {
        $f = array(
            'type' => 'iconpicker',
            'label' => 'Иконка',
            'value' => 'fa glass',
            'recommended' => [
                'fa caret-left',
                'fa angle-double-left',
                'fa arrow-left',
                'fa hand-o-left',
                'fa arrow-circle-left',
                'fa caret-square-o-left',
                'lnr arrow-left',
                'gmdi keyboard_arrow_left'
            ]
        );
        $set = [
            'type' => 'set',
            'labels' => [
                'Somtheing',
                'Icon'
            ],
            'tpl' => [
                [
                    'type' => 'html',
                    'name' => 'smth'
                ],
                [
                    'type' => 'iconpicker',
                    'name' => 'icon',
                    'recommended' => [
                        'fa caret-left',
                        'fa angle-double-left',
                        'fa arrow-left',
                        'fa hand-o-left',
                        'fa arrow-circle-left',
                        'fa caret-square-o-left'
                    ]
                ]
            ],
            'values' => []
        ];
        foreach (range(0, 10) as $n) {
            $set['values'][]= [
                'smth' => str_repeat('oh so long str ', 8).'<br />and<br />more'.
                    '<span class="gmdi gmdi-flight  floxim--ui--box--group__field testicon  fx-block  fx-block_parent-align_center fx-block_lightness_dark fx-block_rw_screen-0-33333333333333 floxim--ui--box--icon-value floxim--ui--box--icon-value_style_default floxim--ui--box--icon-value_style-id_6bb0a1cbd94b2a7a324f9e98577b9326  fx_template_var_in_att fx_hilight fx_hilight_hover fx_selected fx_has_selection fx_edit_in_place"></span>', 
                'icon' => 'fa glass'
            ];
        }
        return array(
            'fields' => array(
                $f, 
                $set
            )
        );
    }
    
    public function angle()
    {
        $f = [
            'type' => 'angle',
            'label' => 'Угол',
            'value' => '90'
        ];
        return ['fields' => [$f]];
    }
    
    public function shadow()
    {
        $f=  array(
            'type' => 'css-shadow',
            'label' => 'Тень',
            'value' => 'outer 0 -20 2 10 alt 1 1, inset 20 0 2 10 third 2 0.48'
        );
        return array('fields' => [$f]);
    }
    
    public function scope($input)
    {
        $pageable = (array) fx::config('pageable');
        
        if (count($pageable)) {
            $pageable = fx::component()->find('keyword', $pageable);
        }
        
        $value = isset($input['conds']) ? json_decode($input['conds'],true) : null;
        
        $cond_field = array(
            'name' => 'conds',
            'type' => 'condition',
            'fields' => array(
                fx::component('floxim.main.page')->getFieldForFilter('entity', $pageable),
            ),
            'types' => fx::data('component')->getTypesHierarchy(),
            'value' => $value,
            'label' => false,
            'pageable' => $pageable->getValues('keyword')
        );
        
        $output = ['type' => 'html'];
        if ($value) {
            ob_start();
            $scope = fx::data('scope')->create(['conditions' => $value]);
            fx::debug($scope->checkScope());
            $output['value'] = ob_get_clean();
        }
        
        $this->response->addFormButton('save');
        return [
            'show_result' => true,
            'fields' => [
                $cond_field,
                $output,
                ['type' => 'hidden', 'name' => 'entity', 'value' => 'test'],
                ['type' => 'hidden', 'name' => 'action', 'value' => 'scope']
            ]
        ];
    }       
}