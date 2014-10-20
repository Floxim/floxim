<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;


class Manager {

    public function __construct() {
        $namespace = 'Floxim\Floxim\Router';
        foreach (array('Admin', 'Infoblock', 'Ajax', 'Front', 'Error') as $r_name) {
            $classname = $namespace.'\\'.$r_name;
            if (class_exists($classname)) {
                $router = new $classname;
                $this->register($router);
            }
        }
    }

    protected $routers = array();

    public function register(Base $router, $name = null, $priority = null) {
        if (is_null($name)) {
            $name = get_class($router);
        }
        $reorder_needed = false;
        if (is_null($priority)) {
            $priority = count($this->routers) + 1;
        } else {
            $reorder_needed = true;
        }
        $this->routers[$name] = array('router' => $router, 'priority' => $priority);
        if ($reorder_needed) {
            $this->reorderRouters();
        }
    }

    protected function reorderRouters() {
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
    public function getRouter($router_name) {
        $class = 'Floxim\\Floxim\\Router\\' . ucfirst($router_name);
        return fx::dig($this->routers, $class.'.router');
    }
}
