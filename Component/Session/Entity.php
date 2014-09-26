<?php

namespace Floxim\Floxim\Component\Session;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity {
    public function set_cookie() {
        fx::data('session')->set_cookie(
            $this['session_key'],
            $this['remember'] ? time() + fx::config('auth.remember_ttl') : 0
        );
    }
}