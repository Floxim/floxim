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
                //'value' => fx::env('palette')->dig('params.colors.alt')
                'value' =>json_decode('{"palette-0":"#870043","palette-1":"#ad0056","palette-2":"#ef0087","palette-3":"#ff9dce","palette-4":"#ffc1e0","palette-5":"#ffdeef","palette-hue":330,"palette-saturation":1,"tweaked":{"2":true}}',true)
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
    
    public function livesearch()
    {
        $vals = array(
            array(
                '', 'Empty val'
            ),
            array(
                'test', '1. My Test'
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
                'value' => 'west'
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
        $f = array (
            'type' => 'fx-box-builder',
            'label' => 'Box',
            'value' => 
            array (
              'is_stored' => true,
              'groups' => 
              array (
                0 => 
                array (
                  'floximuiboxgroup_style' => 
                  array (
                    'fields_margin' => '1.5',
                    'margin' => '0 0 1rem 0',
                    'padding' => '0em',
                    'justify_content' => 'none',
                    'align_items' => 'flex-start',
                    'background' => 'none',
                  ),
                  'fields' => 
                  array (
                    0 => 
                    array (
                      'keyword' => 'name',
                      'field_link' => '1',
                      'template' => 'value',
                      'show_label' => '0',
                      'floximuiboxvalue_style' => 'default_variant_47',
                    ),
                  ),
                ),
                1 => 
                array (
                  'floximuiboxgroup_style' => 
                  array (
                    'fields_margin' => '1.5',
                    'margin' => '1rem 0 2rem 0',
                    'padding' => '0em',
                    'justify_content' => 'none',
                    'align_items' => 'flex-start',
                    'background' => 'none',
                  ),
                  'fields' => 
                  array (
                    0 => 
                    array (
                      'keyword' => 'price',
                      'template' => 'value',
                      'show_label' => '0',
                      'floximuiboxvalue_style' => 'default_variant_41',
                      'field_link' => '0',
                    ),
                  ),
                ),
                2 => 
                array (
                  'floximuiboxgroup_style' => 
                  array (
                    'fields_margin' => '1.5',
                    'margin' => '1rem -1.5rem 1rem -1.5rem',
                    'padding' => '0 0 0 0.5rem',
                    'justify_content' => 'none',
                    'align_items' => 'flex-start',
                    'background' => 'none, color, main 0 0.32, none',
                  ),
                  'fields' => 
                  array (
                    0 => 
                    array (
                      'keyword' => 'description',
                      'template' => 'value',
                      'show_label' => '0',
                      'floximuiboxvalue_style' => 'default_variant_69',
                    ),
                  ),
                ),
              ),
              'floximuiboxbox_style' => 
              array (
                'margin' => '0.5rem 0.5rem 0.5rem 0.5rem',
                'padding' => '1.5rem 1.5rem 1.5rem 1.5rem',
                'background' => 'none, linear 180deg, alt 0 0.27 0% alt 5 0 33%, ~"0% 0% / 100%" no-repeat scroll',
                'align' => 'none',
              ),
            ),
            'params' => 
            array (
              'floximuiboxbox_style' => 
              array (
                'label' => '',
                'type' => 'style',
                'block' => 'floxim--ui--box--box',
                'value' => 
                array (
                  'margin' => '0.5rem 0.5rem 0.5rem 0.5rem',
                  'padding' => '1.5rem 1.5rem 1.5rem 1.5rem',
                  'background' => 'none, linear 180deg, alt 0 0.27 0% alt 5 0 33%, ~"0% 0% / 100%" no-repeat scroll',
                  'align' => 'none',
                ),
                'is_inline' => true,
                'style_id' => '3001f2b63bd5d089d8a0fcdf807d21ea',
                'asset_id' => 'default_inline_262_e2f51b52c1deea3875e3ffbcd38195db',
                'mod_value' => 'e2f51b52c1deea3875e3ffbcd38195db',
              ),
              'groups.0.floximuiboxgroup_style' => 
              array (
                'label' => '',
                'type' => 'style',
                'block' => 'floxim--ui--box--group',
                'value' => 
                array (
                  'fields_margin' => '1.5',
                  'margin' => '0 0 1rem 0',
                  'padding' => '0em',
                  'justify_content' => 'none',
                  'align_items' => 'flex-start',
                  'background' => 'none',
                ),
                'is_inline' => true,
                'style_id' => '121b36690e1c50b0bda5d537370adb1d',
                'asset_id' => 'default_inline_262_4861ce3edff2179ba9937e63c749cd07',
                'mod_value' => '4861ce3edff2179ba9937e63c749cd07',
              ),
              'groups.0.fields.0.show_label' => 
              array (
                'is_forced' => true,
                'value' => '0',
                'label' => 'Подпись?',
                'type' => 'checkbox',
                'default' => '0',
              ),
              'groups.0.fields.0.floximuiboxvalue_style' => 
              array (
                'label' => 'Стиль поля',
                'type' => 'style',
                'block' => 'floxim--ui--box--value',
                'value' => 'default_variant_47',
                'is_inline' => false,
                'style_id' => 'dbdd6fccba14d2bbda8edaae5ec4f6ee',
                'asset_id' => 'default_variant_47',
                'mod_value' => 'default--47',
              ),
              'groups.0.fields.0.field_link' => 
              array (
                'is_forced' => true,
                'value' => '1',
                'label' => 'Ссылка?',
                'type' => 'checkbox',
                'default' => '0',
              ),
              'groups.1.floximuiboxgroup_style' => 
              array (
                'label' => '',
                'type' => 'style',
                'block' => 'floxim--ui--box--group',
                'value' => 
                array (
                  'fields_margin' => '1.5',
                  'margin' => '1rem 0 2rem 0',
                  'padding' => '0em',
                  'justify_content' => 'none',
                  'align_items' => 'flex-start',
                  'background' => 'none',
                ),
                'is_inline' => true,
                'style_id' => 'da7cc944903ebe00ec3e609258901e06',
                'asset_id' => 'default_inline_262_b43a5228650e34affdfa8e5c78e26f1f',
                'mod_value' => 'b43a5228650e34affdfa8e5c78e26f1f',
              ),
              'groups.1.fields.0.show_label' => 
              array (
                'is_forced' => true,
                'value' => '0',
                'label' => 'Подпись?',
                'type' => 'checkbox',
                'default' => '0',
              ),
              'groups.1.fields.0.floximuiboxvalue_style' => 
              array (
                'label' => 'Стиль поля',
                'type' => 'style',
                'block' => 'floxim--ui--box--value',
                'value' => 'default_variant_41',
                'is_inline' => false,
                'style_id' => '804a17cbed6bf8193dac67fb0a78788b',
                'asset_id' => 'default_variant_41',
                'mod_value' => 'default--41',
              ),
              'groups.1.fields.0.field_link' => 
              array (
                'is_forced' => true,
                'value' => '0',
                'label' => 'Ссылка?',
                'type' => 'checkbox',
                'default' => '0',
              ),
              'groups.2.floximuiboxgroup_style' => 
              array (
                'label' => '',
                'type' => 'style',
                'block' => 'floxim--ui--box--group',
                'value' => 
                array (
                  'fields_margin' => '1.5',
                  'margin' => '1rem -1.5rem 1rem -1.5rem',
                  'padding' => '0 0 0 0.5rem',
                  'justify_content' => 'none',
                  'align_items' => 'flex-start',
                  'background' => 'none, color, main 0 0.32, none',
                ),
                'is_inline' => true,
                'style_id' => 'd26a78a80ba9a2acd8f11856611d4624',
                'asset_id' => 'default_inline_262_1b830bccc705ec46f953b383abf51e01',
                'mod_value' => '1b830bccc705ec46f953b383abf51e01',
              ),
              'groups.2.fields.0.show_label' => 
              array (
                'is_forced' => true,
                'value' => '0',
                'label' => 'Подпись?',
                'type' => 'checkbox',
                'default' => '0',
              ),
              'groups.2.fields.0.floximuiboxvalue_style' => 
              array (
                'label' => 'Стиль поля',
                'type' => 'style',
                'block' => 'floxim--ui--box--value',
                'value' => 'default_variant_69',
                'is_inline' => false,
                'style_id' => 'a72f32be5480b945c99c8ada91ff8b61',
                'asset_id' => 'default_variant_69',
                'mod_value' => 'default--69',
              ),
            ),
            'avail' => 
            array (
              0 => 
              array (
                'keyword' => 'type',
                'name' => 'Тип',
                'template' => 'value',
              ),
              1 => 
              array (
                'keyword' => 'created',
                'name' => 'Дата создания',
                'template' => 'value',
              ),
              2 => 
              array (
                'keyword' => 'name',
                'name' => 'Название',
                'template' => 'value',
              ),
              3 => 
              array (
                'keyword' => 'description',
                'name' => 'Описание',
                'template' => 'value',
              ),
              4 => 
              array (
                'keyword' => 'url',
                'name' => 'URL',
                'template' => 'value',
              ),
              5 => 
              array (
                'keyword' => 'title',
                'name' => 'Title',
                'template' => 'value',
              ),
              6 => 
              array (
                'keyword' => 'h1',
                'name' => 'H1',
                'template' => 'value',
              ),
              7 => 
              array (
                'keyword' => 'full_text',
                'name' => 'Полный текст',
                'template' => 'value',
              ),
              8 => 
              array (
                'keyword' => 'price',
                'name' => 'Цена',
                'template' => 'value',
              ),
            ),
            'name' => 'visual[template_visual][box_slidebox]',
            'view_context' => 'panel',
        );
        \Floxim\Ui\Box\Box::addAdminAssets();
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
            'value' => 'fa glass'
        );
        return array('fields' => array($f));
    }
}