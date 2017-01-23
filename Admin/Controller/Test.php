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
            'value' => 'fa glass'
        );
        return array('fields' => array($f));
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
            
}