<?php

namespace Floxim\Floxim\System;

class MigrationManager
{

    protected $params = array();

    function __construct($params = array())
    {
        $this->params = array_merge($this->params, $params);
    }

    protected function getParam($name, $default = null)
    {
        return isset($this->params[$name]) ? $this->params[$name] : $default;
    }
    
    public function create($name = null, $save_as_done = false)
    {
        $name = 'm' . date('Ymd_His') . (!is_null($name) ? "_$name" : '');
        $content = "<?php
        class {$name} extends \\Floxim\\Floxim\\System\\Migration {

            // Run for up migration
            protected function up() {

            }

            // Run for down migration
            protected function down() {

            }

        }";

        $dir = fx::path('@floxim/update/migration');
        try {
            if (file_exists($dir)) {
                fx::files()->mkdir($dir);
            }
            $target_file = $dir . '/' . $name . '.php';
            fx::files()->writefile($target_file, $content);
            if ($this->getParam('console')) {
                echo('Successful!');
            }
            if ($save_as_done) {
                require_once($target_file);
                $migration = new $name;
                $migration->saveAsDone();
            }
            return true;
        } catch (Exception $e) {
            if ($this->getParam('console')) {
                echo('Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    public function up()
    {
        $dir = fx::path('@floxim/update/migration');
        // get migrations
        $migration_files = glob($dir . '/*.php');
        if (!$migration_files) {
            return;
        }
        $migration_names = array();
        foreach ($migration_files as $migration_file) {
            $info = pathinfo($migration_file);
            $paths = explode('_', $info['filename']);
            $paths = array_slice($paths, 0, 2);
            $migration_names[join('_', $paths)] = $info['filename'];
        }
        asort($migration_names);

        // get completed migration
        $migrations_completed = fx::data('patch_migration')->all();
        if ($migrations_completed_name = $migrations_completed->getValues('name')) {
            $migration_names = array_diff_key($migration_names,
                array_combine($migrations_completed_name, array_fill(0, count($migrations_completed_name), 1)));
        }

        // run migrations
        $count = 0;
        foreach ($migration_names as $name_full) {
            $file = $dir . DIRECTORY_SEPARATOR . $name_full . '.php';
            if (file_exists($file)) {
                require_once($file);
                $migration = new $name_full;
                $migration->execUp();
                $count++;
            }
        }
        // todo: need fix
        echo('Count run new migrations: ' . $count);
    }
    
    public function find($name)
    {
        $dir = fx::path('@floxim/update/migration');
        // get migrations
        $migration_files = glob($dir . '/m*_'.$name.'.php');
        if (!$migration_files) {
            return null;
        }
        foreach ($migration_files as $migration_file) {
            $info = pathinfo($migration_file);
            require_once($migration_file);
            $class_name = $info['filename'];
            $m = new $class_name;
            return $m;
        }
    }
}