<?php

use Floxim\Floxim\System\Fx as fx;

$component = $this->getComponent();

$sort_fields = $component
                    ->getAllFields()
                    ->find(function($f) {
                        if (
                            $f instanceof \Floxim\Floxim\Field\Link 
                            || $f instanceof \Floxim\Floxim\Field\MultiLink
                            || $f instanceof \Floxim\Floxim\Field\Text
                            || $f instanceof \Floxim\Floxim\Field\Image
                            || in_array(
                                    $f['keyword'], 
                                array(
                                    'priority',
                                    'is_published',
                                    'is_branch_published', 
                                    'type',
                                    'url',
                                    'h1',
                                    'title'
                                )
                            )
                        ) {
                            return false;
                        }
                        return true;
                    })
                    ->getValues(fx::isAdmin() ? 'name' : 'id', 'keyword');
                    
if ($component->getFieldByKeyword('priority', true)) {
    $sort_fields_with_manual = array( array('manual', fx::alang('Manual', 'controller_component') ) ) + $sort_fields;
} else {
    $sort_fields_with_manual = $sort_fields;
}

$content_exists = fx::content($component['keyword'])->contentExists();
$is_new_infoblock = !$this->getParam('infoblock_id');

$component_infoblocks = fx::data('infoblock')->getContentInfoblocks($component['keyword']);

return array(
    'actions' => array(
        '*list*' => array(
            'settings' => array(
                'limit' => array(
                    'label' => fx::alang('Count entries','controller_component'),
                    'class_name' => 'fx_field_limit'
                ),
                'pagination' => array(
                    'label' => fx::alang('Paginate?','controller_component'),
                    'type' => 'checkbox',
                    //'type' => 'hidden',
                    'parent' => array('limit' =>  '!=')
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
                    //'join_with' => 'sorting',
                    'parent' => array('sorting' => '!=manual')
                )
            )
        ),
        '*list' => array(
            'disabled' => true
        ),
        '*list_infoblock' => array(
            //'name' => fx::alang('New block with %s', 'controller_component', $component->getItemName('with')),
            'name' => fx::util()->ucfirst($component->getItemName('list')),
            // ! APC fatal error occured here sometimes
            'install' => function($ib, $ctr, $params) {
                $ctr->bindLostContent($ib, $params);
            },
            'default_scope' => function() {
                $ds = fx::env('page_id').'-this-';
                return $ds;
            },
            'settings' => array(
                'sorting' => array(
                    //'values' => array( array('manual', fx::alang('Manual', 'controller_component') ) ) + $sort_fields
                    'values' => $sort_fields_with_manual
                )
            ) 
            + $this->getParentConfigFields(),
                //+ $this->getTargetConfigFields(),
                //+ $this->getLostContentField(),
            'defaults' => array(
                '!pagination' => true
            )
        ),
        '*list_filtered' => array(
            'name' => fx::util()->ucfirst(fx::alang('%s by filter', 'controller_component', $component->getItemName('list'))),
            'settings' => array(
                'conditions' => function($ctr) {
                    return $ctr->getConditionsField();
                },
                'sorting' => array(
                    //'values' => array( array('manual', fx::alang('Manual', 'controller_component') ) ) + $sort_fields
                    'values' => $sort_fields_with_manual
                ),
            ),
            'defaults' => array(
                'limit' => 10
            )
        ),
        '*list_selected' => array(
            'name' => fx::util()->ucfirst(
                fx::alang('%s selected', 'controller_component', $component->getItemName('list'))
            ),
            'settings' => array(
                'selected' => function($ctr) {
                    return $ctr->getSelectedField();
                },
                'allow_select_doubles' => array(
                    'type' => 'checkbox',
                    'label' => fx::alang('Allow doubles', 'controller_component')
                ),
                'is_pass_through' => array(
                    'label' => fx::alang('Pass-through data','controller_component'),
                    'type' => 'checkbox',
                    'parent' => array('scope[complex_scope]' => '!~this')
                ),
                'sorting' => array(
                    'values' => array( array('manual', fx::alang('Manual', 'controller_component') ) ) + $sort_fields
                ),
            ),
            'defaults' => array(
                '!pagination' => false,
                '!limit' => 0,
                '!allow_select_doubles' => true,
                'is_pass_through' => 'false'
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
        ),
        '*form*' => [
            'disabled' => true
        ],
        '*form_create' => array(
            /*
            'check_context' => function() use ($component_infoblocks) {
                return count($component_infoblocks) > 0;
            },
             * 
             */
            'settings' => array(
                'target_infoblock' => array(
                    'type' => 'select',
                    'label' => fx::alang('Target infoblock', 'controller_component'),
                    'values' => $component_infoblocks->getSelectValues('id', 'name'),
                    'hidden_on_one_value' => true
                ),
                'redirect_to' => array(
                    'type' => 'select',
                    'label' => fx::alang('After submission...', 'controller_component'),
                    'values' => array(
                        array('refresh', fx::alang('Refresh page')),
                        array('new_page', fx::alang('Go to the created page')),
                        array('parent_page', fx::alang('Go to the parent page'))
                    )
                )
            )
        )
    )
);