<?php

namespace Floxim\Floxim\Admin;

use Floxim\Floxim\System;

class Response
{

    /** @var \Floxim\Floxim\Admin\Submenu */
    public $submenu;
    /** @var \Floxim\Floxim\Admin\Breadcrumb */
    public $breadcrumb;
    /** @var \Floxim\Floxim\Admin\Dialog */
    public $dialog;

    protected $buttons = array(), $buttons_pulldown = array(), $fields = array(), $tabs = array(), $form_buttons = array();
    protected $entity;
    protected $props = array();

    protected $status, $status_text, $error_fields, $reload;

    public function __construct($input)
    {
        $this->submenu = new Submenu(isset($input['menu_id']) ? $input['menu_id'] : null);
        $this->breadcrumb = new Breadcrumb();
        $this->dialog = new Dialog();
    }
    
    protected $buttons_action;

    public function toArray()
    {
        $result = array();

        $submenu = $this->submenu->toArray();


        if ($this->reload) {
            $result['reload'] = $this->reload;
        }

        if ($submenu) {
            $result['submenu'] = $submenu;
        }

        $result['main_menu']['active'] = $this->submenu->getActiveMainMenu();

        $breadcrumb = $this->breadcrumb->toArray();
        if ($breadcrumb) {
            $result['breadcrumb'] = $breadcrumb;
        }

        if ($this->buttons) {
            $result['buttons'] = $this->buttons;
        }
        if ($this->buttons_pulldown) {
            $result['buttons_pulldown'] = $this->buttons_pulldown;
        }

        if ($this->buttons_action) {
            $result['buttons_action'] = $this->buttons_action;
        }

        if ($this->form_buttons) {
            $result['form_button'] = $this->form_buttons;
        }

        if ($this->fields) {
            $result['fields'] = $this->fields;
        }

        if ($this->tabs) {
            $result['tabs'] = $this->tabs;
        }

        $dialog = $this->dialog->toArray();
        if ($dialog) {
            $result['dialog'] = $dialog;
        }

        if ($this->entity) {
            $result['entity'] = $this->entity;
        }

        if ($this->status) {
            $result['status'] = $this->status;
            if ($this->status_text) {
                $result['text'] = $this->status_text;
            }
            if ($this->error_fields) {
                $result['fields'] = $this->error_fields;
            }
        }

        if ($this->props) {
            $result['props'] = $this->props;
        }

        return $result;
    }

    public function addButtons($buttons)
    {
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

    public function addPulldownItem($button, $name, $options)
    {
        if (is_string($options)) {
            parse_str($options, $options);
        }

        $this->buttons_pulldown[$button][] = array('name' => $name, 'options' => $options);

    }

    public function addButtonOptions($button, $options)
    {
        if (is_string($options)) {
            parse_str($options, $options);
        }
        $this->buttons_action[$button]['options'] = $options;
    }

    public function addFormButton($button)
    {
        if (!is_array($button)) {
            $button = array('key' => trim($button));
        }
        $this->form_buttons[] = $button;
    }

    public function addField($field, $tab = null)
    {
        if ($tab) {
            $field['tab'] = $tab;
        }
        $this->fields[] = $field;
    }

    public function addFields($fields, $tab = null, $prefix = null)
    {
        if ($fields instanceof System\Collection) {
            $fields = $fields->getData();
        }
        if (!is_array($fields)) {
            return;
        }
        if ($tab) {
            foreach ($fields as &$field) {
                if (!isset($field['tab'])) {
                    $field['tab'] = $tab;
                }
            }
        }
        foreach ($fields as $field_key => &$field) {
            if (!isset($field['name'])) {
                $field['name'] = $field_key;
            }
        }
        if ($prefix) {
            foreach ($fields as &$field) {
                $field['name'] = $prefix . '[' . $field['name'] . ']';
                if (isset($field['parent']) && is_array($field['parent'])) {
                    $np = array();
                    foreach ($field['parent'] as $pkey => $pval) {
                        if (preg_match("~\[~", $pkey)) {
                            $np[$pkey] = $pval;
                        } else {
                            $np[$prefix . '[' . $pkey . ']'] = $pval;
                        }
                    }
                    $field['parent'] = $np;
                }
                if (isset($field['join_with']) && !preg_match("~\[~", $field['join_with'])) {
                    $field['join_with'] = $prefix . '[' . $field['join_with'] . ']';
                }
            }
        }
        $this->fields = array_merge($this->fields, $fields);
    }


    public function addTab($key, $tab, $active = false)
    {
        if (!is_array($tab)){
            $tab = array(
                'label' => $tab
            );
        }
        $tab['key'] = $key;
        if ($active) {
            $tab['active'] = true;
        }
        $this->tabs[$key] = $tab;
    }
    
    public function addTabs($tabs) {
        foreach ($tabs as $key => $tab) {
            $this->addTab($key, $tab);
        }
    }

    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    public function setStatusError($text = '', $fields = array())
    {
        $this->status = 'error';
        $this->status_text = $text;
        if (!is_array($fields)) {
            $fields = array($fields);
        }
        if ($fields) {
            $this->error_fields = $fields;
        }
    }

    public function setStatusOk($text = '')
    {
        $this->status = 'ok';
        $this->status_text = $text;
    }

    public function setReload($reload = true)
    {
        $this->reload = $reload;
    }

    public function setProp($prop, $value)
    {
        $this->props[$prop] = $value;
    }

}