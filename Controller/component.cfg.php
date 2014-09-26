<?php

use Floxim\Floxim\System\Fx as fx;

$sort_fields = $this
            ->getComponent()
            ->allFields()
            ->find('type', \Floxim\Floxim\Component\Field\Entity::FIELD_MULTILINK, '!=')
            ->find('type', \Floxim\Floxim\Component\Field\Entity::FIELD_MULTILINK, '!=')
            ->getValues(fx::isAdmin() ? 'name' : 'id', 'keyword');

$component = $this->getComponent();
$content_exists = fx::content($component['keyword'])->contentExists();
$is_new_infoblock = !$this->getParam('infoblock_id');

return array(
    'actions' => array(
        '*.*' => array(
            'icon' => self::getAbbr($component['name'])
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
                    'parent' => array('limit' => '!=')
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
                    'join_with' => 'sorting',
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
                $ctr->bindLostContent($ib, $params);
            },
            'default_scope' => function() {
                return fx::env('page_id').'-this-';
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
                + $this->getTargetConfigFields()
                + $this->getLostContentField(),
            'defaults' => array(
                '!pagination' => true
            )
        ),
        '*list_filtered' => array(
            'name' => $component['name'].' '.fx::alang('by filter', 'controller_component'),
            'icon_extra' => 'fil',
            //'settings' => fx::is_admin() ? $this->_config_conditions() : array()
            'settings' => array(
                'conditions' => function($ctr) {
                    return $ctr->getConditionsField();
                }
            )
        ),
        '*list_selected' => array(
            'name' => $component['name'].' selected',
            'icon_extra' => 'sel',
            'settings' => array(
                'selected' => function($ctr) {
                    return $ctr->getSelectedField();
                },
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
                $ctr->saveSelectedLinkers($params['params']['selected']);
            },
            'delete' => function($ib, $ctr, $params) {
                // drop linkers
                $ctr->dropSelectedLinkers();
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