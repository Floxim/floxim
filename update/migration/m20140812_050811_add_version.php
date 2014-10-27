<?php
class m20140812_050811_add_version extends fx_migration {

    // Run for up migration
    protected function up() {
        fx::db()->query("
            INSERT INTO {{option}} (`id`, `keyword`, `name`, `value`, `autoload`) VALUES (NULL, 'fx.version', 'Current floxim version', '0.1.1', '1');
        ");
    }

    // Run for down migration
    protected function down() {

    }

}