<?php

namespace Floxim\Floxim\Admin;

use Floxim\Floxim\Admin\Controller;
use Floxim\Floxim\System\Fx as fx;

class Configjs
{
    protected $options;

    public function  __construct()
    {
        $this->options['login'] = 'admin';
        $this->options['action_link'] = fx::config()->HTTP_ACTION_LINK;

        $this->addMoreMenu(Controller\Adminpanel::getMoreMenu());
        $this->addButtons(Controller\Adminpanel::getButtons());


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
            ),
            'site'    => array(
                'name' => fx::env('site')->get('domain'),
                'key'  => 'site',
                'href' => '/'
            )
        );

        $other_sites = fx::data('site')->where('id', fx::env('site')->get('id'), '!=')->all();
        if (count($other_sites) > 0) {
            $main_menu['site']['children'] = array();
            foreach ($other_sites as $other_site) {
                $main_menu['site']['children'] [] = array(
                    'name' => $other_site['domain'],
                    'href' => 'http://' . $other_site['domain'] . '/'
                );
            }
        }
        $this->addMainMenu($main_menu);
    }

    public function getConfig()
    {
        return json_encode($this->options);
    }

    public function addMenu($structure)
    {
        $this->options['menu'] = $structure;
    }

    public function addMainMenu($structure)
    {
        $this->options['mainmenu'] = $structure;
    }

    public function addMoreMenu($structure)
    {
        $this->options['more_menu'] = $structure;
    }

    public function addButtons($buttons)
    {
        $this->options['buttons'] = $buttons;
    }

    public function addAdditionalText($text)
    {
        $this->options['additional_text'] = $text;
    }

    // Additional admin panel
    // e.g. layout preview navigation
    public function addAdditionalPanel($data)
    {
        $this->options['additional_panel'] = $data;
    }
}

