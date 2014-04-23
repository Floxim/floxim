<?php
$page_config = array(
    'actions' => array(
        '*record' => array(
            'name' => $component['name'].' record',
            'check_context' => function($page) use ($component) {
                $res = $page['type'] === $component['keyword'];
                return $res;
            }
        ),/*
        '.*' => array(
            'disabled' => true
        ),*/
        'list_infoblock' => array(
            'disabled' => false
        ),
        '*list_infoblock' => array(
            'settings' => (
                $is_new_infoblock ? array(
                    'create_record_ib' => array(
                        'type' => 'checkbox',
                        'label' => 'Create record infoblock'
                    )
                ) : array()
            ),
            'install' => function($list_ib, $ctr) {
                if (!$list_ib['params']['create_record_ib']) {
                    return;
                }
                $rec_ib = fx::data('infoblock')->create();
                $content_type = $ctr->get_content_type();
                $rec_ib->set(
                    array(
                        'site_id' => $list_ib['site_id'],
                        'controller' => $ctr->get_controller_name(true),
                        'action' => 'record',
                        'name' => $content_type.' record',
                        'page_id' => $list_ib['page_id'],
                        'scope' => array(
                            'pages' => 'children',
                            'page_type' => $content_type
                        )
                    )
                );
                $rec_ib->save();
            },
            'delete' => function($list_ib, $ctr) {
                $rec_ib = fx::data('infoblock')
                            ->where('page_id', $list_ib['page_id'])
                            ->where('controller', $ctr->get_controller_name(true))
                            ->where('action', 'record')
                            ->one();
                if ($rec_ib) {
                    $rec_ib->delete();
                }
            }
        )
    )
);
return $page_config;