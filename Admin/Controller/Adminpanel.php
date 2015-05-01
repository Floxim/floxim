<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Adminpanel extends Admin
{

    public function index()
    {

    }
    
    public static function getMainMenu()
    {
        $main_menu = array(
            'manage'  => array(
                'name' => fx::alang('Management', 'system'),
                'key'  => 'manage',
                'href' => '/floxim/#admin.administrate.site.all'
            ),
            'develop' => array(
                'name' => fx::alang('Development', 'system'),
                'key'  => 'develop',
                'href' => '/floxim/#admin.component.all'
            )
        );
        
        $site = fx::env('site');
        if ($site) {
            $main_menu['site'] = array(
                'name' => fx::env('site')->getLocalDomain(),
                'key'  => 'site',
                'href' => '/'
            );
            $other_sites = fx::data('site')->where('id', $site['id'], '!=')->all();
            if (count($other_sites) > 0) {
                $main_menu['site']['children'] = array();
                foreach ($other_sites as $other_site) {
                    $domain = $other_site->getLocalDomain();
                    $main_menu['site']['children'] [] = array(
                        'name' => $domain,
                        'href' => 'http://' . $domain . '/'
                    );
                }
            }
        }
        return $main_menu;
    }

    static public function panelHtml()
    {
        $data = array(
            'main_menu' => self::getMainMenu(),
            'more_menu' => self::getMoreMenu(),
            'modes' => array(
                "view" => array(
                    "name" => fx::alang("View"),
                    "href" => "view"
                ),
                "edit" => array(
                    "name" => fx::alang("Edit"),
                    "href" => "edit"
                ), 
                "design" => array(
                    "name" => fx::alang("Design"),
                    "href" => "design"
                )
            ),
            'profile' => array(
                'logout' => array(
                    'name' => fx::alang('Sign out','system'),
                    'url' => fx::user()->getLogoutUrl()
                )
            ),
            'is_front' => true
        );
        $res = fx::template('@admin:panel')->render($data);
        return $res;
    }

    public static function getMoreMenu()
    {
        $more_menu = array();
        $c_page = fx::env('page');
        $more_menu[] = array(
            'name'   => fx::alang('Edit current page', 'system'),
            'button' => array(
                'entity'       => 'content',
                'action'       => 'add_edit',
                'content_type' => $c_page['type'],
                'content_id'   => $c_page['id']
            )
        );

        $more_menu[] = array(
            'name'   => fx::alang('Layout settings', 'system'),
            'button' => array(
                'entity'  => 'infoblock',
                'action'  => 'layout_settings',
                'page_id' => fx::env('page_id')
            )
        );
        $more_menu[]= array(
            'name' => fx::alang('Change theme', 'system'),
            'button' => array(
                'entity' => 'layout',
                'action' => 'change_theme',
                'page_id' => fx::env('page_id')
            )
        );
        $more_menu[] = array(
            'name'   => fx::alang('Page infoblocks', 'system'),
            'button' => array(
                'entity'  => 'infoblock',
                'action'  => 'list_for_page',
                'page_id' => fx::env('page_id')
            )
        );
        return $more_menu;
    }

    public static function getButtons()
    {
        $result = array(
            'source' => array(
                'add'             => array('title' => fx::alang('add', 'system'), 'type' => 'text'),
                'edit'            => array('title' => fx::alang('edit', 'system')),
                'on'              => array('title' => fx::alang('on', 'system')),
                'off'             => array('title' => fx::alang('off', 'system')),
                'settings'        => array('title' => fx::alang('settings', 'system')),
                'delete'          => array('title' => fx::alang('delete', 'system')),
                'select_block'    => array('title' => fx::alang('Select parent block', 'system')),
                'upload'          => array('title' => fx::alang('Upload file', 'system')),
                'download'        => array('title' => fx::alang('Download file', 'system')),
                'map'             => array('title' => fx::alang('Site map', 'system')),
                'export'          => array('title' => fx::alang('Export', 'system')),
                'store'           => array('title' => fx::alang('Download from FloximStore', 'system')),
                'import'          => array('title' => fx::alang('Import', 'system')),
                'change_password' => array('title' => fx::alang('Change password', 'system')),
                'undo'            => array('title' => fx::alang('Cancel', 'system')),
                'redo'            => array('title' => fx::alang('Redo', 'system')),
                'more'            => array('title' => fx::alang('More', 'system'))
            ),
            'map'    => array(
                'page' => explode(",", 'add,divider,edit,on,off,delete,divider,select_block,settings')
            )
        );
        return $result;
    }
}