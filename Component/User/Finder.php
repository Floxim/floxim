<?php
namespace Floxim\Floxim\Component\User;

use fx;

class Finder extends \Floxim\Floxim\Component\Content\Finder {
    public function get_by_id($id) {
        if (!is_numeric($id)) {
            return $this->get_by_login($id);
        }
        return parent::get_by_id($id);
    }
    
    public function get_by_login($login) {
        $this->where(fx::config('auth.login_field'), $login);
        return $this->one();
    }
}