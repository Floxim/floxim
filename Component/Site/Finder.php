<?php

namespace Floxim\Floxim\Component\Site;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Finder
{

    public function __construct()
    {
        parent::__construct();
        $this->order[] = 'priority asc';
    }
    
    public function relations()
    {
        return array(
            'theme' => array(
                self::BELONGS_TO,
                'layout',
                'layout_id'
            )
        );
    }

    public function getById($id)
    {
        if (is_numeric($id)) {
            return parent::getById($id);
        }
        return $this->getByHostName($id);
    }

    public function getByHostName($host = '')
    {
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
            if (in_array($host, $site->getAllHosts())) {
                return $site;
            }
        }
        return $sites->first();
    }

    public function create($data = array())
    {
        $obj = parent::create($data);
        $obj['created'] = date("Y-m-d H:i:s");
        $obj['priority'] = $this->nextPriority();
        return $obj;
    }
}

