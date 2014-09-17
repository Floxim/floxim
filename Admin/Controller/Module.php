<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Module extends Admin {

    protected $menu_items = array();

    public function basesettings($input) {
        // todo: psr0 need fix
        $module_keyword = str_replace('fx_controller_admin_module_', '', get_class($this));
        $this->response->submenu->set_menu('settings')->set_subactive('settings-'.$module_keyword);
        $this->response->breadcrumb->add_item( fx::alang('Configuring the','system') . ' ' . $module_keyword);
        $this->settings();
    }

    public function settings() {
        $this->response->add_field($this->ui->label( fx::alang('Override the settings in the class','system') ));
    }

    public function basesettings_save($input) {
        $this->settings_save($input);
    }

    public function settings_save($input) {
        ;
    }

    public function add_node($id, $name, $href = '') {
        $this->menu_items[] = array('id' => $id, 'name' => $name, 'href' => $href);
    }

    public function get_menu_items() {
        $this->init_menu();
        return $this->menu_items;
    }

    public function init_menu() {

    }

}