<?php

class fx_data_site extends fx_data {

    public function __construct() {
        parent::__construct();
        $this->order = 'priority';
    }
    
    public function get_by_id($id) {
        if (is_numeric($id)) {
            return parent::get_by_id($id);
        }
        return $this->get_by_host_name($id);
    }

    public function get_by_host_name($host = '') {
        if (!$host) {
            $host = fx::config()->HTTP_HOST;
        }
        $host = preg_replace("~^https?://~i", '', $host);
        $host = preg_replace("~/$~", '', $host);
        
        $sites = $this->all();
        if (count($sites) === 1) {
            return $sites->first();
        }
        // search for the domain and the mirrors
        foreach ($sites as $site) {
            if (in_array($host, $site->get_all_hosts())) {
                return $site;
            }
        }
        return $sites->first();
    }

    public function create($data = array()) {
        $obj = parent::create($data);
        $obj['created'] = date("Y-m-d H:i:s");
        $obj['priority'] = $this->next_priority();
        return $obj;
    }
}

