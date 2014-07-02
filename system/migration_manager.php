<?php

class fx_migration_manager {

    protected $params=array();

    function __construct($params=array()) {
        $this->params=array_merge($this->params,$params);
    }

    protected function get_param($name, $default=null) {
        return isset($this->params[$name]) ? $this->params[$name] : $default;
    }


    public function create($name=null) {
        $name='m'.date('Ymd_His').(!is_null($name) ? "_$name" : '');
        $content="<?php
        class {$name} extends fx_migration {

            // Run for up migration
            protected function up() {

            }

            // Run for down migration
            protected function down() {

            }

        }";

        $dir=fx::path('root', '/update/migration');
        try {
            if (file_exists($dir)) {
                fx::files()->mkdir($dir);
            }
            fx::files()->writefile($dir.'/'.$name.'.php', $content);
            if ($this->get_param('console')) {
                echo('Successful!');
            }
            return true;
        } catch (Exception $e) {
            if ($this->get_param('console')) {
                echo('Error: '.$e->getMessage());
            }
            return false;
        }
    }

}