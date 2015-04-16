<?php

namespace Floxim\Floxim\System;

class Eventmanager
{
    protected $_listeners = array();

    public function listen($event_name, $callback)
    {
        if (preg_match("~,~", $event_name)) {
            $event_name = explode(",", $event_name);
        }
        if (is_array($event_name)) {
            foreach ($event_name as $event_name_var) {
                $this->listen($event_name_var, $callback);
            }
            return;
        }
        $event = $this->parseEventName($event_name);
        if (!isset($this->_listeners[$event['name']])) {
            $this->_listeners[$event['name']] = array();
        }
        $this->_listeners[$event['name']][] = array(
            'event_scope' => $event['scope'],
            'callback'    => $callback
        );
    }

    protected function parseEventName($event_name)
    {
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

    public function unlisten($event_name)
    {
        $event = $this->parseEventName($event_name);
        if (!$event['scope']) {
            unset($this->_listeners[$event['name']]);
            return;
        }
        if (!isset($this->_listeners[$event['name']])) {
            return;
        }
        foreach ($this->_listeners[$event['name']] as $lst_num => $lst) {
            if ($event['scope'] == $lst['event_scope']) {
                unset($this->_listeners[$event['name']][$lst_num]);
            }
        }
    }

    public function trigger($e, $params = null)
    {
        if (is_string($e)) {
            if (!isset($this->_listeners[$e])) {
                return;
            }
            $e = new Event($e, $params);
        } elseif (!isset($this->_listeners[$e->name])) {
            return;
        }
        $callback_res = null;
        foreach ($this->_listeners[$e->name] as $lst) {
            $callback_res = $lst['callback']($e);
            if ($e->isStopped()) {
                $event_res = $e->getResult();
                return $event_res ? $event_res : $callback_res;
            }
        }
        $event_res = $e->getResult();
        return $event_res ? $event_res : $callback_res;
    }
}