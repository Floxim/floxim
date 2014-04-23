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
        $replace = array('http:', 'https:', '/');

        if (!$host) {
            $host = fx::config()->HTTP_HOST;
        }
        $host = str_replace($replace, '', $host);

        $res = false;
        $expected = false;
        $first = false;
        $sites = $this->all();
        // search for the domain and the mirrors
        foreach ($sites as $site) {
            $domain = str_replace($replace, '', $site['domain']);
            $mirrors = str_replace($replace, '', $site['mirrors']);
            $all_domains = preg_quote($domain)."|".preg_replace("/\r\n/", "|", preg_quote($mirrors));

            if (preg_match("/^(?:".$all_domains.")$/", $host)) {
                $res = $site;
                break;
            }

            if (!$first) {
                $first = $site;
            }

            if ($site['checked'] && !$expected) {
                $expected = $site;
            }
        }

        return $res ? $res : ( $expected ? $expected : $first);
    }

    public function create($data = array()) {
        $obj = parent::create($data);
        $obj['created'] = date("Y-m-d H:i:s");
        $obj['priority'] = $this->next_priority();
        return $obj;
    }
}

