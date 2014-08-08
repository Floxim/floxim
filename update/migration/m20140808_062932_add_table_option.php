<?php

class m20140808_062932_add_table_option extends fx_migration {

    // Run for up migration
    protected function up() {
        // create table
        fx::db()->query("
            CREATE TABLE IF NOT EXISTS {{option}} (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `keyword` varchar(255) NOT NULL,
              `name` varchar(255) NOT NULL,
              `value` text NOT NULL,
              `autoload` tinyint(1) NOT NULL DEFAULT '1',
              PRIMARY KEY (`id`),
              KEY `autoload` (`autoload`),
              KEY `keyword` (`keyword`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
        ");
    }

    // Run for down migration
    protected function down() {

    }

}