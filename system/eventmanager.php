<?php
class fx_system_eventmanager {
    protected $_listeners = array();

    public function listen($event_name, $callback) {
        if (preg_match("~,~", $event_name)) {
            $event_name = explode(",", $event_name);
        }
        if (is_array($event_name)) {
            foreach ($event_name as $event_name_var) {
                $this->listen($event_name_var, $callback);
            }
        }
        $event = $this->_parse_event_name($event_name);
        if ($event['name'] == '*') {
            return;
        }
        $this->_listeners[]= array(
            'event_name' => $event['name'],
            'event_scope' => $event['scope'],
            'callback' => $callback
        );
    }
    
    protected function _parse_event_name($event_name) {
        $parts = explode(".", $event_name);
        if (!isset($parts[1])) {
            $parts[1] = 'global';
        }
        list($event_name, $event_scope) = $parts;
        if (empty($event_name)) {
            $event_name = '*';
        }
        return array('name' => $event_name, 'scope' => $event_scope);
    }
    
    public function unlisten($event_name) {
        $event = $this->_parse_event_name($event_name);
        foreach ($this->_listeners as $lst_num => $lst) {
            if ($event['name'] == '*' || $event['name'] == $lst['event_name']) {
                if ($event['scope'] == $lst['event_scope']) {
                    unset($this->_listeners[$lst_num]);
                }
            }
        }
    }
    
    public function trigger($e, $params = null) {
        if (is_string($e)) {
            $e = new fx_event($e, $params);
        }
        foreach ($this->_listeners as $lst) {
            if ($lst['event_name'] == $e->name) {
                $callback_res = $lst['callback']($e);
                if ($callback_res === false){
                    return;
                }
            }
        }
    }
}