<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;


class Manager
{

    public function __construct()
    {
        $namespace = 'Floxim\Floxim\Router';
        foreach (array('Admin', 'Infoblock', 'Ajax', 'Thumb', 'Front', 'Error') as $r_name) {
            $classname = $namespace . '\\' . $r_name;
            if (class_exists($classname)) {
                $router = new $classname;
                $this->register($router);
            }
        }
    }

    protected $routers = array();

    public function register($router, $name = null, $priority = null)
    {
        if ($router instanceof \Closure) {
            $router = Closure::create($router);
        }
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

    protected function reorderRouters()
    {
        uasort($this->routers, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    /**
     * Perform all registered routers, to return most suitable controller
     * @param string $url
     * @param array $context
     * @return fx_controller
     */
    public function route($url = null, $context = array())
    {
        if (is_null($url)) {
            $url = getenv('REQUEST_URI');
        }

        if (!isset($context['site_id'])) {
            $env_site = fx::env('site');
            $context['site_id'] = $env_site ? $env_site['id'] : null;
        }
        foreach ($this->routers as $router_key => $r) {
            $result = $r['router']->route($url, $context);
            if ($result !== null && $result !== false) {
                $log_option = fx::config('dev.log_routes');
                if (
                    (is_bool($log_option) && $log_option) ||
                    (is_array($log_option)) && in_array($router_key, $log_option) ||
                    (is_string($log_option) && $log_option === $router_key)
                ) {
                    fx::log('routed', $router_key, $url);
                }
                if ($result instanceof \Floxim\Floxim\System\Controller) {
                    $result = $result->process();
                }
                return $result;
            }
        }
    }
    
    public function getPath($url, $site_id = null) {
        $url = preg_replace("~\#.*$~", '', $url);
        if (is_null($site_id)) {
            $site_id = fx::env('site_id');
        }
        // @todo check if url contains another host name
        $url = preg_replace("~^https?://.+?/~", '/', $url);
        foreach ($this->routers as $r) {
            $result = $r['router']->getPath($url, $site_id);
            if ($result) {
                return $result;
            }
        }
    }

    /**
     * Get the option router by name
     * fx::router('front')
     */
    public function getRouter($router_name)
    {
        $class = 'Floxim\\Floxim\\Router\\' . ucfirst($router_name);
        return fx::dig($this->routers, $class . '.router');
    }
}
