<?php

namespace Floxim\Floxim\System;

class Event {
    public $name = 'event';
    public function __construct($name, $params) {
        $this->name = $name;
        foreach ($params as $p => $v) {
            $this->$p = $v;
        }
    }
}