<?php

class fx_field_string extends fx_field_baze {

    public function format_settings() {
        $fields[] = array('type' => 'checkbox', 'name' => 'html', 'label' => fx::alang('allow HTML tags','system'));
        return $fields;
    }

    public function get_sql_type() {
        return "VARCHAR(255)";
    }
}