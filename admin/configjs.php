<?php

class fx_admin_configjs {
  protected $options;

  public function  __construct() {
    $this->options['login'] = 'admin';
    $this->options['action_link'] = fx::config()->HTTP_ACTION_LINK;

    $this->add_more_menu(fx_controller_admin_adminpanel::get_more_menu());
    $this->add_buttons(fx_controller_admin_adminpanel::get_buttons());
    
    
    $main_menu = array(
        'manage' => array(
            'name' => fx::alang('Management', 'system'),
            'key' => 'manage',
            'href' => '/floxim/#admin.administrate.site.all'
        ), 
        'develop' => array(
            'name' => fx::alang('Development', 'system'),
            'key' => 'develop',
            'href' => '/floxim/#admin.component.all'
        ), 
        'site' => array(
            'name' => fx::env('site')->get('domain'),
            'key' => 'site',
            'href' => '/'
        )
    );
    
    $other_sites = fx::data('site')->where('id', fx::env('site')->get('id'), '!=')->all();
    if (count($other_sites) > 0) {
        $main_menu['site']['children'] = array();
        foreach ($other_sites as $other_site) {
            $main_menu['site']['children'] []= array(
                'name' => $other_site['domain'],
                'href' => 'http://'.$other_site['domain'].'/'
            );
        }
    }
    $this->add_main_menu($main_menu);
  }

  public function get_config() {
    return json_encode($this->options);
  }

  public function add_menu ( $structure ) {
    $this->options['menu'] = $structure;
  }

  public function add_main_menu ( $structure ) {
    $this->options['mainmenu'] = $structure;
  }

  public function add_more_menu ( $structure ) {
    $this->options['more_menu'] = $structure;
  }

  public function add_buttons ( $buttons ) {
    $this->options['buttons'] = $buttons;
  }

  public function add_additional_text ( $text ) {
      $this->options['additional_text'] = $text;
  }

  // Additional admin panel
  // e.g. layout preview navigation
  public function add_additional_panel ( $data ) {
      $this->options['additional_panel'] = $data;
  }
}

