<?php
$sort_fields = $this
            ->get_component()
            ->all_fields()
            ->find('type', fx_field::FIELD_MULTILINK, '!=')
            ->find('type', fx_field::FIELD_LINK, '!=')
            ->get_values(fx::is_admin() ? 'name' : 'id', 'keyword');

$component = $this->get_component();

$content_exists = fx::data('content_'.$component['keyword'])
                            ->where('site_id', fx::env('site')->get('id'))
                            ->one();

$is_new_infoblock = !$this->get_param('infoblock_id');

return array(
    'actions' => array(
        '*.*' => array(
            'icon' => self::_get_abbr($component['name'])
        ),
        '*list*' => array(
            'settings' => array(
                'limit' => array(
                    'label' => fx::alang('Count entries','controller_component'),
                    'class_name' => 'fx_field_limit'
                ),
                'pagination' => array(
                    'label' => fx::alang('Paginate?','controller_component'),
                    'type' => 'checkbox',
                    'parent' => array('limit' => '!=0')
                ),
                'sorting' => array(
                    'name' => 'sorting',
                    'label' => fx::alang('Sorting','controller_component'),
                    'type' => 'select',
                    'values' => $sort_fields
                ),
                'sorting_dir' => array(
                    'name' => 'sorting_dir',
                    'label' => fx::alang('Order','controller_component'),
                    'type' => 'select',
                    'values' => array(
                        'asc' => fx::alang('Ascending','controller_component'), 
                        'desc' => fx::alang('Descending','controller_component')
                    ),
                    'parent' => array('sorting' => '!=manual')
                )
            )
        ),
        '*list' => array(
            'disabled' => true
        ),
        '*list_infoblock' => array(
            'name' => $component['name'],
            // ! APC fatal error occured here sometimes
            'install' => function($ib, $ctr, $params) {
                $ctr->bind_lost_content($ib, $params);
            },
            'settings' => array(
                'sorting' => array(
                    'values' => array( array('manual', fx::alang('Manual', 'controller_component') ) ) + $sort_fields
                ),
                'parent_type' => array(
                    'label' => fx::alang('Add items to','controller_component'),
                    'type' => 'select',
                    'values' => array(
                        'current_page_id' => fx::alang('Current page','controller_component'),
                        'mount_page_id' => fx::alang('Infoblock page','controller_component')
                    ),
                    'parent' => array('scope[complex_scope]' => '!~this')
                )
            ) 
                + $this->get_target_config_fields()
                + $this->get_lost_content_field(),
            'defaults' => array(
                '!pagination' => true
            )
        ),
        '*list_filtered' => array(
            'name' => $component['name'].' '.fx::alang('by filter', 'controller_component'),
            'icon_extra' => 'fil',
            'settings' => fx::is_admin() ? $this->_config_conditions() : array()
        ),
        '*list_selected' => array(
            'name' => $component['name'].' selected',
            'icon_extra' => 'sel',
            'settings' => array(
                'selected' => $this->_get_selected_field(),
                'parent_type' => array(
                    'label' => fx::alang('Bind items to','controller_component'),
                    'type' => 'select',
                    'values' => array(
                        'current_page_id' => fx::alang('Current page','controller_component'),
                        'mount_page_id' => fx::alang('Infoblock page','controller_component')
                    ),
                    'parent' => array('scope[complex_scope]' => '!~this')
                ),
                'sorting' => array(
                    'values' => array( array('manual', fx::alang('Manual', 'controller_component') ) ) + $sort_fields
                ),
            ),
            'defaults' => array(
                '!pagination' => false,
                '!limit' => 0
            ),
            'save' => function($ib, $ctr, $params) {
                // update linkers
                $ctr->save_selected_linkers($params['params']['selected']);
            },
            'delete' => function($ib, $ctr, $params) {
                // drop linkers
                $ctr->drop_selected_linkers();
            }
        ),
        '*list_filtered*, *list_selected*, *listing_by*' => array(
            'check_context' => function() use ($content_exists) {
                return $content_exists;
            }
        ),
        '*listing_by' => array(
            'disabled' => 1
        )
    )
);