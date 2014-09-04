<?php

namespace Floxim\Floxim\Component\Session;

use Floxim\Floxim\System;

class Essence extends System\Essence {
    public function set_cookie() {
        fx::data('session')->set_cookie(
            $this['session_key'],
            $this['remember'] ? time() + fx::config('auth.remember_ttl') : 0
        );
    }
}