<?php

namespace Floxim\Floxim\Component\Site;

use Floxim\Floxim\System;

class Essence extends System\Essence {

    public function validate() {
        $res = true;
        if (!$this['name']) {
            $this->validate_errors[] = array('field' => 'name', 'text' => fx::alang('Enter the name of the site','system'));
            $res = false;
        }
        return $res;
    }
    
    protected function _before_delete() {
        $this->delete_infoblocks();
        $this->delete_content();
    }
    
    protected function delete_content() {
        $content = fx::data('content')->where('site_id', $this['id'])->all();
        foreach ( $content as $content_item ) {
            $content_item->delete();
        }
    }
    
    protected function delete_infoblocks () {
        $infoblocks = fx::data('infoblock')->where('site_id', $this['id'])->all();
        foreach ($infoblocks as $infoblock) {
            $infoblock->delete();
        }
    }
    
    /**
     * Get all host names bound to the site
     * @return array
     */
    public function get_all_hosts() {
        $hosts = array();
        $hosts[]= trim($this['domain']);
        if (empty($this['mirrors'])) {
            return $hosts;
        }
        $mirrors = preg_split("~\s~", $this['mirrors']);
        foreach ($mirrors as $m) {
            $m = trim($m);
            if (!empty($m)) {
                $hosts[]= trim($m);
            }
        }
        return $hosts;
    }
}