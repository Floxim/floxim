<?php

namespace Floxim\Floxim\Component\Session;

use Floxim\Floxim\System;

class Finder extends System\Data {
    
    protected $cookie_name = 'fx_sid';
    
    public function start($data = array()) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $now = time();
        $data = array_merge(
            array(
                'ip' => sprintf("%u", ip2long($ip)),
                'session_key' => md5(time().rand(0, 1000).$ip),
                'start_time' => $now,
                'last_activity_time' => $now
            ),
            $data
        );
        $data['remember'] = $data['remember'] ? 1 : 0;
        $session = $this->create($data);
        $session->save();
        $session->set_cookie();
        return $session;
    }
    
    /*
     * @todo should we do something with www/nowww problem?
     */
    public function set_cookie($sid, $time) {
        $host = null;
        setcookie(
            $this->cookie_name, 
            $sid, 
            $time, 
            "/", 
            $host
        );
    }
    
    public function load() {
        static $session = null;
        if (is_null($session)) {
            $this->drop_old_sessions();
            $session_key = fx::input()->fetch_cookie($this->cookie_name);
            if (!$session_key) {
                return null;
            }
            $session = $this->get_by_key($session_key);
            if ($session) {
                $session->set('last_activity_time', time())->save();
            }
        }
        return $session;
    }
    
    public function drop_old_sessions() {
        $ttl = (int) fx::config('auth.remember_ttl');
        fx::db()->query(
                'delete from {{session}} '
                . 'where '
                . 'user_id is not null '
                . 'and last_activity_time + '. $ttl . ' < '.time()
        );
    }
    
    public function get_by_key($session_key) {
        return $this
                ->where('session_key', $session_key)
                ->where('site_id', array(fx::env('site_id'), 0))
                ->one();
    }
    
    public function stop() {
        $session_key = fx::input()->fetch_cookie($this->cookie_name);
        if (!$session_key) {
            return;
        }
        $this->set_cookie(null, null);
        $session = $this->get_by_key($session_key);
        if (!$session) {
            return;
        }
        $session->delete();
    }
}