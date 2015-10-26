<?php
class m20151026_031618_trim_url_slash extends \Floxim\Floxim\System\Migration {

    // Run for up migration
    protected function up() {
        
        $pages = \fx::data('floxim.main.page')->all();
        $pages->apply(function($c) {
            $new_url = preg_replace("~^/~", '', $c['url']);
            $c->set('url', $new_url);
            $c->save();
        });
    }

    // Run for down migration
    protected function down() {

    }

}