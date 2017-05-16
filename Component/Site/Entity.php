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
        $mirrors = preg_split("~\s~", $this['mirrors']);
        foreach ($mirrors as $m) {
            $m = trim($m);
            if (!empty($m)) {
                $hosts[] = trim($m);
            }
        }
        $base = fx::config('base_host');
        if ( $base ) {
            foreach ($hosts as &$host) {
                if (preg_match("~\.$~", $host)) {
                    $host=  $host.$base;
                }
            }
        }
        return $hosts;
    }
    
    protected static function getHostZone($host)
    {
        preg_match("~\.([^\.]+?)$~", $host, $c_zone);
        $c_zone = isset($c_zone[1]) ? $c_zone[1] : null;
        return $c_zone;
    }
            


    public function getMainHost()
    {
        $c_zone = self::getHostZone($_SERVER['HTTP_HOST']);
        $first_host = null;
        $zone_host = null;
        foreach ($this->getAllHosts() as $host) {
            if (!$first_host) {
                $first_host = $host;
            }
            $c_host_zone = self::getHostZone($host);
            if ($c_zone && !$zone_host && $c_host_zone === $c_zone) {
                $zone_host = $host;
            }
        }
        return $zone_host ? $zone_host : $first_host;
    }
    
    /**
     * Return host for the current environment
     */
    public function getLocalDomain()
    {
        if ( ($getter = fx::config('get_site_host')) ) {
            $res = $getter($this);
            if ($res) {
                return $res;
            }
        }
        return $this->getMainHost();
    }
}