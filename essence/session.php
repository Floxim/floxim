<?php
class fx_session extends fx_essence {       
    public function set_cookie() {
        fx::data('session')->set_cookie(
            $this['session_key'],
            $this['remember'] ? time() + fx::config('auth.remember_ttl') : 0
        );
    }
}