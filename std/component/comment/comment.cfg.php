<?
return array(
    'actions' => array(
        '*list*' => array(
            'icon' => 'Com',
        ),
        'add' => array(
            'icon' => 'Com',
            'icon_extra' => 'add',
            'settings' => array(
                'target_infoblock_id' => $this->_get_target_infoblock(),
            ),
        ),
    )
);