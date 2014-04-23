<?php
class fx_data_session extends fx_data {
    
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
        $session = $this->create($data);
        $session->save();
        $this->_set_cookie(
            $data['session_key'], 
            $data['remember'] ? time() + fx::config('AUTHTIME') : 0
        );
        return $session;
    }
    
    protected function _set_cookie($sid, $time) {
        setcookie(
            $this->cookie_name, 
            $sid, 
            $time, 
            "/", 
            str_replace("www.", "", $_SERVER['HTTP_HOST'])
        );
    }
    
    public function load() {
        $session_key = fx::input()->fetch_cookie($this->cookie_name);
        if (!$session_key) {
            return null;
        }
        $session = $this->get_by_key($session_key);
        if ($session) {
            $session->set('last_activity_time', time())->save();
        }
        return $session;
    }
    
    public function get_by_key($session_key) {
        return $this
                ->where('session_key', $session_key)
                ->where('site_id', fx::env('site_id'))
                ->one();
    }
    
    public function stop() {
        $session_key = fx::input()->fetch_cookie($this->cookie_name);
        if (!$session_key) {
            return;
        }
        $this->_set_cookie(null, null);
        $session = $this->get_by_key($session_key);
        if (!$session) {
            return;
        }
        $session->delete();
    }
}