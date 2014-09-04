<?php

namespace Floxim\Floxim\Router;

/**
$controller = fx::routers()->route('/news/news_10.html');
fx::routers()->register($callback, 1);
*/

class Manager {

    public function __construct() {
        foreach (array('admin', 'front', 'infoblock', 'ajax', 'error') as $r_name) {
            try {
                // todo: psr0 need fix
                $classname = 'fx_router_'.$r_name;
                if (class_exists($classname)) {
                    $router = new $classname;
                    $this->register($router);
                }
            } catch (Exception $e) {
                // no file
            }
        }
    }
    
    protected $routers = array();

    public function register(Base $router, $name = null, $priority = null) {
        if (is_null($name)) {
            // todo: psr0 need fix
            $name = preg_replace("~^fx_router_~", '', get_class($router)); 
        }
        $reorder_needed = false;
        if (is_null($priority)) {
            $priority = count($this->routers) + 1;
        } else {
            $reorder_needed = true;
        }
        $this->routers[$name] = array('router' => $router, 'priority' => $priority);
        if ($reorder_needed) {
            $this->_reorder_routers();
        }
    }

    protected function _reorder_routers() {
        uasort($this->routers, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    /**
     * Perform all registered routers, to return most suitable controller
     * @param string $url
     * @param array $context
     * @return fx_controller
     */
    public function route($url = null, $context = array()) {
        if (is_null($url)) {
            $url = getenv('REQUEST_URI');
        }
        
        if (!isset($context['site_id'])) {
            $context['site_id'] = fx::env('site')->get('id');
        }
        foreach ($this->routers as $r) {
            $result = $r['router']->route($url, $context);
            if ($result !== null && $result !== false) {
                return $result;
            }
        }
    }
    
    /**
     * Get the option router by name
     * fx::router('front')
     */
    public function get_router($router_name) {
        return fx::dig($this->routers, $router_name.'.router');
    }
}
