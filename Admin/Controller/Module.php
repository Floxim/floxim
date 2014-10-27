<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Module extends Admin
{

    protected $menu_items = array();

    public function basesettings($input)
    {
        // todo: psr0 need fix
        $module_keyword = str_replace('fx_controller_admin_module_', '', get_class($this));
        $this->response->submenu->setMenu('settings')->setSubactive('settings-' . $module_keyword);
        $this->response->breadcrumb->addItem(fx::alang('Configuring the', 'system') . ' ' . $module_keyword);
        $this->settings();
    }

    public function settings()
    {
        $this->response->addField($this->ui->label(fx::alang('Override the settings in the class', 'system')));
    }

    public function basesettingsSave($input)
    {
        $this->settingsSave($input);
    }

    public function settingsSave($input)
    {
        ;
    }

    public function addNode($id, $name, $href = '')
    {
        $this->menu_items[] = array('id' => $id, 'name' => $name, 'href' => $href);
    }

    public function getMenuItems()
    {
        $this->initMenu();
        return $this->menu_items;
    }

    public function initMenu()
    {

    }

}