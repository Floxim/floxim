<?php
class m20151026_031618_trim_url_slash extends \Floxim\Floxim\System\Migration {

    // Run for up migration
    protected function up() {
        \fx::data('floxim.main.page')->all()->apply(function($c) {
            $c->set('url', preg_replace("~^/~", '', $c['url']))->save(); 
        });
    }

    // Run for down migration
    protected function down() {

    }

}