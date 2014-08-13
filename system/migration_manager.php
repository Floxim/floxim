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

        $dir=fx::path('floxim', '/update/migration');
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

    public function up() {
        $dir=fx::path('floxim', '/update/migration');
        // get migrations
        $migration_files = glob($dir.'/*.php');
        if (!$migration_files) {
            return;
        }
        $migration_names=array();
        foreach ($migration_files as $migration_file) {
            $info=pathinfo($migration_file);
            $paths=explode('_',$info['filename']);
            $paths=array_slice($paths,0,2);
            $migration_names[join('_',$paths)]=$info['filename'];
        }

        // get completed migration
        $migrations_completed=fx::data('patch_migration')->all();
        if ($migrations_completed_name=$migrations_completed->get_values('name')) {
            $migration_names=array_diff_key($migration_names,array_combine($migrations_completed_name,array_fill(0,count($migrations_completed_name),1)));
        }

        // run migrations
        $count=0;
        foreach($migration_names as $name_full) {
            $file=$dir.DIRECTORY_SEPARATOR.$name_full.'.php';
            if (file_exists($file)) {
                require_once($file);
                $migration=new $name_full;
                $migration->exec_up();
                $count++;
            }
        }
        // todo: need fix
        echo('Count run new migrations: '.$count);
    }
}