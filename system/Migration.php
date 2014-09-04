<?php

namespace Floxim\Floxim\System;

abstract class Migration {

    abstract protected function up();

    abstract protected function down();

    public function exec_up() {
        $name=$this->_get_name();
        // check already exec
        if (fx::data('patch_migration')->where('name',$name)->one()) {
            return;
        }
        // exec
        $this->up();
        // mark for exec
        $migration=fx::data('patch_migration')->create(
            array(
                'name'=>$name
            )
        );
        $migration->save();
    }

    public function exec_down() {
        $name=$this->_get_name();
        // check not exec run
        if (!$migration=fx::data('patch_migration')->where('name',$name)->one()) {
            return;
        }
        // exec
        $this->down();
        // remove mark
        $migration->delete();
    }

    protected function _get_name() {
        $name=get_class($this);
        /**
         * Variants: m20140619_111111, m20140619_111111_any_skip_name
         */
        $paths=explode('_',$name);
        $paths=array_slice($paths,0,2);
        return join('_',$paths);
    }
}