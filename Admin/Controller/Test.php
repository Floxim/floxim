<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Test extends Admin {
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
    
    public function ratio() {
        $fields = array(
            'ratio' => array(
                'label' => 'Пропорции',
                'type' => 'ratio',
                'value' => 5,
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
        
        $val = 'light,
        
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
        $fields = array(
            'test' => array(
                'label' => 'LS custom',
                'type' => 'livesearch',
                'allow_empty' => false,
                'values' => array(
                    array(
                        'test', 'My Test'
                    ),
                    array(
                        'west', 'Oh West'
                    ),
                    array(
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
                    )
                ),
                'value' => 25
            )
        );
        $this->response->addFields($fields);
    }
}