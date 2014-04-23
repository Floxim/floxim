<?php

class fx_admin_response {

    /** @var fx_admin_submenu  */
    public $submenu;
    /** @var fx_admin_breadcrumb  */
    public $breadcrumb;
    /** @var fx_admin_dialog  */
    public $dialog;
    
    protected $buttons = array(), $buttons_pulldown = array(), $fields = array(), $tabs = array(), $form_buttons = array();
    protected $essence;
    protected $props = array();
    
    protected $status, $status_text, $error_fields, $reload;
    
    public function __construct($input) {
        $this->submenu = new fx_admin_submenu($input['menu_id']);
        $this->breadcrumb = new fx_admin_breadcrumb();
        $this->dialog = new fx_admin_dialog();
    }

    public function to_array() {
        $result = array();

        $submenu = $this->submenu->to_array();
        
        
        if ($this->reload) {
            $result['reload'] = $this->reload;
        }
        
        if ($submenu) {
            $result['submenu'] = $submenu;
        }

        $result['main_menu']['active'] = $this->submenu->get_active_main_menu();

        $breadcrumb = $this->breadcrumb->to_array();
        if ($breadcrumb) {
            $result['breadcrumb'] = $breadcrumb;
        }
        
        if ( $this->buttons ) {
            $result['buttons'] = $this->buttons;
        }
        if ( $this->buttons_pulldown ) {
            $result['buttons_pulldown'] = $this->buttons_pulldown;
        }
        
        if ( $this->buttons_action ) {
            $result['buttons_action'] = $this->buttons_action;
        }
        
        if ( $this->form_buttons ) {
            $result['form_button'] = $this->form_buttons;
        }
         
        if ( $this->fields ) {
            $result['fields'] = $this->fields;
        }
        
        if ( $this->tabs ) {
            $result['tabs'] = $this->tabs;
        }
        
        $dialog = $this->dialog->to_array();
        if ( $dialog ) {
            $result['dialog'] = $dialog;
        }
        
        if ( $this->essence ) {
            $result['essence'] = $this->essence;
        }
        
        if ( $this->status ) {
            $result['status'] = $this->status;
            if ( $this->status_text ) {
                $result['text'] = $this->status_text;
            }
            if ( $this->error_fields ) {
                $result['fields'] = $this->error_fields;
            }
        }
        
        if ($this->props) {
            $result['props'] = $this->props;
        }

        return $result;
    }

    public function add_buttons($buttons) {
        if (!is_array($buttons)) {
            $buttons = explode(",", $buttons);
        }
        foreach ($buttons as &$b) {
            if (is_string($b)) {
                $b = trim($b);
                continue;
            }
            if (is_array($b)) {
                if (isset($b['url'])) {
                    $this->buttons_action[$b['key']]['url'] = $b['url'];
                }
            }
        }
        $this->buttons = array_merge($this->buttons, $buttons);
    }
    
    public function add_pulldown_item($button, $name, $options) {
        if ( is_string($options) ) {
            parse_str($options, $options);
        }
        
        $this->buttons_pulldown[$button][] = array('name' => $name, 'options' => $options);
        
    }
    
    public function add_button_options ( $button, $options ) {
        if ( is_string($options) ) {
            parse_str($options, $options);
        }
        $this->buttons_action[$button]['options'] = $options;
    }
    
    public function add_form_button ( $button ) {
        if (!is_array($button)) {
            $button = array('key' => trim($button));
        }
        $this->form_buttons[]= $button;
    }
    public function add_field ( $field, $tab = null ) {
        if ( $tab ) {
            $field['tab'] = $tab;
        }
        $this->fields[] = $field;
    }
    
    public function add_fields ( $fields, $tab = null, $prefix = null ) {
        if ($fields instanceof fx_collection) {
            $fields = $fields->get_data();
        }
        if (!is_array($fields) ) {
            return;
        }
        if ( $tab ) {
            foreach ( $fields as &$field ) {
                $field['tab'] = $tab;
            }
        }
        if ($prefix) {
            foreach ($fields as &$field) {
                $field['name'] = $prefix.'['.$field['name'].']';
                if ($field['parent'] && is_array($field['parent'])) {
                    $np = array();
                    foreach ($field['parent'] as $pkey => $pval) {
                        if (preg_match("~\[~", $pkey)) {
                            $np[$pkey] = $pval;
                        } else {
                            $np[ $prefix.'['.$pkey.']'] = $pval;
                        }
                    }
                    $field['parent'] = $np;
                }
            }
        }
        $this->fields = array_merge($this->fields, $fields);
    }
    
    
    public function add_tab($tab, $name, $active = false) {
        $item = array('name' => $name);
        if ($active) $item['active'] = 1;
        $this->tabs[$tab] = $item;
    }
    
    public function set_essence ( $essence ) {
        $this->essence = $essence;
    }
    
    public function set_status_error ( $text = '' , $fields = array() ) {
        $this->status = 'error';
        $this->status_text = $text;
        if ( !is_array($fields) ) {
            $fields = array($fields);
        }
        if ( $fields ) {
            $this->error_fields = $fields;
        }
    }
    
    public function set_status_ok ( $text = '' ) {
        $this->status = 'ok';
        $this->status_text = $text;
    }
    
    public function set_reload($reload = true) {
        $this->reload = $reload;
    }
    
    public function set_prop($prop, $value) {
        $this->props[$prop] = $value;
    }
    

}