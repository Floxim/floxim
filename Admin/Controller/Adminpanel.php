<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Adminpanel extends Admin {
    
    public function index() {
        
    }

    static public function panel_html() {
        $res = fx::template('@admin:panel')->render(array('is_front' => true));
        return $res;
    }

    public static function get_more_menu() {
        $more_menu = array();
        $c_page = fx::env('page');
        $more_menu[] = array(
            'name' => fx::alang('Edit current page', 'system'),
            'button' => array(
                'essence' => 'content',
                'action' => 'add_edit',
                'content_type' => $c_page['type'],
                'content_id' => $c_page['id']
            )
        );
        
        $more_menu[] = array(
            'name' => fx::alang('Layout settings','system'),
            'button' => array(
                'essence' => 'infoblock',
                'action' => 'layout_settings',
                'page_id' => fx::env('page_id')
            )
        );
        $more_menu[]= array(
            'name' => fx::alang('Page infoblocks', 'system'),
            'button' => array(
                'essence' => 'infoblock',
                'action' => 'list_for_page',
                'page_id' => fx::env('page_id')
            )
        );
        return $more_menu;
    }

    public static function get_buttons() {
        $result = array(
            'source' => array(
                'add' => array('title' => fx::alang('add', 'system'), 'type' => 'text'),
                'edit' => array('title' => fx::alang('edit', 'system')),
                'on' => array('title' => fx::alang('on', 'system')),
                'off' => array('title' => fx::alang('off', 'system')),
                'settings' => array('title' => fx::alang('settings', 'system')),
                'delete' => array('title' => fx::alang('delete', 'system')),
                'select_block' => array('title' => fx::alang('Select parent block','system')),
                'upload' => array('title' => fx::alang('Upload file','system')),
                'download' => array('title' => fx::alang('Download file','system')),
                'map' => array('title' => fx::alang('Site map','system')),
                'export' => array('title' => fx::alang('Export','system')),
                'store' => array('title' => fx::alang('Download from FloximStore','system')),
                'import' => array('title' => fx::alang('Import','system')),
                'change_password' => array('title' => fx::alang('Change password','system')),
                'undo' => array('title' => fx::alang('Cancel', 'system')),
                'redo' => array('title' => fx::alang('Redo', 'system')),
                'more' => array('title' => fx::alang('More', 'system'))
            ),
            'map' => array(
                'page' => explode(
                    ",",
                    'add,divider,edit,on,off,delete,divider,select_block,settings'
                )
            )
        );
        return $result;
    }
}