<?php

namespace Floxim\Floxim\Admin;

use Floxim\Floxim\System\Fx as fx;

class Submenu
{

    protected $menu = array();
    protected $error = false;
    protected $type = 'list';
    protected $backlink;
    protected $active = '', $subactive = '';
    protected $active_main_menu = '';
    protected $menu_id = '';
    protected $old_menu_id = '';
    protected $not_update = false;

    public function __construct($old = '')
    {
        $this->old_menu_id = $old;
    }

    public function setMenu($type)
    {

        $this->menu_id = $type;
        if ($this->menu_id == $this->old_menu_id) {
            $this->not_update = true;
        }

        preg_match("/^([a-z]+)(-([a-z0-9]+))?$/i", $type, $match);
        if ($match[1] == 'component' && !$match[3]) {
            $this->initDevelop();
            $this->active = 'component';
            $this->active_main_menu = 'develop';
        }
        if ($match[1] == 'component' && is_numeric($match[3])) {
            $this->initMenuComponent($match[3]);
            $this->active_main_menu = 'develop';
        }

        if ($match[1] == 'layout' && !$match[3]) {
            $this->initDevelop();
            $this->active = 'layout';
            $this->active_main_menu = 'develop';
        }
        if ($match[1] == 'template' && is_numeric($match[3])) {
            $this->initMenuTemplate($match[3]);
            $this->active_main_menu = 'develop';
        }

        if ($match[1] == 'widget' && !$match[3]) {
            $this->initDevelop();
            $this->active = 'widget';
            $this->active_main_menu = 'develop';
        }
        if ($match[1] == 'log' && !$match[3]) {
            $this->initDevelop();
            $this->active = 'log';
            $this->active_main_menu = 'develop';
        }
        if ($match[1] == 'console' && !$match[3]) {
            $this->initDevelop();
            $this->active = 'console';
            $this->active_main_menu = 'develop';
        }
        if ($match[1] == 'widget' && is_numeric($match[3])) {
            $this->initMenuWidget($match[3]);
            $this->active_main_menu = 'develop';
        }


        if ($match[1] == 'site' && !$match[3]) {
            $this->initManage();
            $this->active = 'site';
            $this->active_main_menu = 'manage';
        }


        if ($match[1] == 'administrate') {
            $this->initManage();
            $this->active = 'administrate';
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'tools') {
            $this->initManage();
            $this->active = 'tools';
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'site' && $match[3]) {
            $this->initMenuSite($match[3]);
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'classificator' && $match[3]) {
            $this->initMenuClassificator($match[3]);
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'user' && !$match[3]) {
            $this->initManage();
            $this->active = 'user';
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'user' && $match[3]) {
            $this->initMenuUser($match[3]);
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'settings') {
            $this->initManage();
            $this->active = 'settings';
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'patch') {
            $this->initManage();
            $this->active = 'patch';
            $this->active_main_menu = 'manage';
        }

        if ($match[1] == 'lang') {
            $this->initManage();
            $this->active = 'lang';
            $this->active_main_menu = 'manage';
        }
        return $this;
    }

    public function setSubactive($item)
    {
        $this->subactive = $item;
        return $this;
    }

    protected function initDevelop()
    {
        $this->menu[] = $node_component = $this->addNode('component', fx::alang('Components', 'system'),
            'component.all');
        $this->menu[] = $node_template = $this->addNode('layout', fx::alang('Layouts', 'system'),
            'layout.all'); // template ->layout
        $this->menu[] = $node_widget = $this->addNode('widget', fx::alang('Widgets', 'system'), 'widget.all');
        $this->menu[] = $node_log = $this->addNode('log', fx::alang('Logs', 'system'), 'log.all');
        $this->menu[] = $node_console = $this->addNode('console', fx::alang('Console', 'system'), 'console.show');
    }

    protected function initManage()
    {

        $this->menu[] = $this->addNode('site', fx::alang('All sites', 'system'), 'site.all');
        $this->menu[] = $this->addNode('patch', fx::alang('Patches', 'system'), 'patch.all');
        $this->menu[] = $this->addNode('user', fx::alang('Users', 'system'), 'user.all');
        $this->menu[] = $this->addNode('lang', fx::alang('Languages', 'system'), 'lang.all');
    }

    protected function initMenuComponent($id)
    {
        $this->type = 'full';
        $component = fx::data('component')->getById($id);
        if (!$component) {
            $this->error = fx::alang('Component not found', 'system');
        } else {
            $this->title = $component['name'];
            $this->backlink = 'component.all';
            $cid = $component['id'];
            // print the main sections
            $submenu_items = Controller\Component::getComponentSubmenu($component);
            foreach ($submenu_items as $item) {
                $this->menu[] = $this->addNode($item['code'], $item['title'], $item['url'], $item['parent']);
            }
        }
    }

    protected function initMenuTemplate($id)
    {
        $this->type = 'full';
        // $template = fx::data('template')->get_by_id($id);
        $layout = fx::data('layout', $id);
        if (!$layout) {
            $this->error = fx::alang('Layout not found', 'system');
        } else {
            $this->title = $layout['name'];
            $this->backlink = 'template.all';
            /*
            foreach ($layout->get_layouts() as $l) {
                $this->menu[] = $this->add_node('layout-'.$l['id'], $l['name'], 'layout.edit('.$l['id'].')', 'layouts');
            }
            */
            // todo: psr0 need fix - not found class fx_controller_admin_template
            $items = Controller\Template::getTemplateSubmenu($layout);
            foreach ($items as $item) {
                $this->menu [] = $this->addNode($item['code'], $item['title'], $item['url']);
            }
        }
    }

    protected function initMenuWidget($id)
    {
        $this->type = 'full';
        $widget = fx::data('widget')->getById($id);
        if (!$widget) {
            $this->error = fx::alang('Widget not found', 'system');
        } else {
            $this->title = $widget['name'];
            $this->backlink = 'widget.all';

            $items = Controller\Component::getComponentSubmenu($widget);
            foreach ($items as $item) {
                $this->menu [] = $this->addNode($item['code'], $item['title'], $item['url']);
            }
        }
    }

    protected function initMenuSite($id)
    {
        $this->type = 'full';
        $site = fx::data('site')->getById($id);
        if (!$site) {
            $this->error = fx::alang('Site not found', 'system');
        } else {
            $this->title = $site['name'];
            $this->backlink = 'site.all';

            $this->menu[] = $this->addNode('sitesettings-' . $site['id'], fx::alang('Settings', 'system'),
                'site.settings(' . $site['id'] . ')');
            //$this->menu[] = $this->add_node('sitedesign-'.$site['id'], fx::alang('Design','system'), 'site.design('.$site['id'].')');
            //$this->menu[] = $this->add_node('sitemap-'.$site['id'], fx::alang('Site map','system'), 'site.map('.$site['id'].')');
        }
    }

    protected function initMenuClassificator($id)
    {
        $this->type = 'full';
        $classificator = fx::data('classificator')->getById($id);
        if (!$classificator) {
            $this->error = fx::alang('List not found', 'system');
        } else {
            $this->title = $classificator['name'];
            $this->backlink = 'classificator.all';
        }
    }

    protected function initMenuUser($id)
    {
        $this->type = 'full';
        $user = fx::data('user')->getById($id);
        if (!$user) {
            $this->error = fx::alang('User not found', 'system');
        } else {
            $this->title = $user['name'];
            $this->backlink = 'user.all';
            $this->menu[] = $this->addNode('profile', fx::alang('Profile', 'system'), 'user.full(' . $user['id'] . ')');
        }
    }

    public function toArray()
    {
        $res = array();

        if ($this->not_update) {
            $res['not_update'] = true;
        }

        if ($this->menu) {
            $res['items'] = $this->menu;
        }
        if ($this->error) {
            $res['error'] = $this->error;
        }
        if ($this->title) {
            $res['title'] = $this->title;
        }
        if ($this->backlink) {
            $res['backlink'] = $this->backlink;
        }
        $res['active'] = $this->active;
        $res['subactive'] = $this->subactive;
        $res['type'] = $this->type;
        $res['menu_id'] = $this->menu_id;
        return $res;
    }

    public function addNode($id, $name, $href = '', $parent = null)
    {
        $node = array('id' => $id, 'name' => $name, 'href' => $href);
        if ($parent) {
            if (is_array($parent)) {
                $parent = $parent['id'];
            }
            $node['parent'] = $parent;
        }
        return $node;
    }

    public function getActiveMainMenu()
    {
        return $this->active_main_menu;
    }
}