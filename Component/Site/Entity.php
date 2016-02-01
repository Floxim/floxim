<?php

namespace Floxim\Floxim\Component\Site;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{

    public function validate()
    {
        $res = true;
        if (!$this['name']) {
            $this->validate_errors[] = array(
                'field' => 'name',
                'text'  => fx::alang('Enter the name of the site', 'system')
            );
            $res = false;
        }
        return $res;
    }

    protected function beforeDelete()
    {
        $this->deleteInfoblocks();
        $this->deleteContent();
    }

    protected function deleteContent()
    {
        $content = fx::data('floxim.main.content')->where('site_id', $this['id'])->all();
        foreach ($content as $content_item) {
            $content_item->delete();
        }
    }

    protected function deleteInfoblocks()
    {
        $infoblocks = fx::data('infoblock')->where('site_id', $this['id'])->all();
        foreach ($infoblocks as $infoblock) {
            $infoblock->delete();
        }
    }

    /**
     * Get all host names bound to the site
     * @return array
     */
    public function getAllHosts()
    {
        $hosts = array();
        $hosts[] = trim($this['domain']);
        if (empty($this['mirrors'])) {
            return $hosts;
        }
        $mirrors = preg_split("~\s~", $this['mirrors']);
        foreach ($mirrors as $m) {
            $m = trim($m);
            if (!empty($m)) {
                $hosts[] = trim($m);
            }
        }
        return $hosts;
    }
    
    /**
     * If current host ends with .loc, returns site's .loc mirror, otherwise - main domain
     */
    public function getLocalDomain()
    {
        $is_loc = preg_match("~\.loc$~", $_SERVER['HTTP_HOST']);
        if (!$is_loc) {
            return $this['domain'];
        }
        $hosts = $this->getAllHosts();
        foreach ($hosts as $host) {
            if (preg_match("~\.loc$~", $host)) {
                return $host;
            }
        }
        return $this['domain'];
    }
}