<?php
class m20150901_161253_content_type_field extends \Floxim\Floxim\System\Migration {

    // Run for up migration
    protected function up() {
        $content_com = fx::component('floxim.main.content');
        
        fx::db()->query(
            array(
                'insert into {{field}} (
                    `component_id` ,
                    `keyword` ,
                    `name_en` ,
                    `name_ru` ,
                    `type`)
                  VALUES (%d, "type", "Type", "Тип", 1)',
                $content_com['id']
            )
        );
        
        fx::cache('meta')->flush();
    }

    // Run for down migration
    protected function down() {

    }

}