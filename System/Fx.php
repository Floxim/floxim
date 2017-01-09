<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Router;
use Floxim\Floxim\Template;
use Floxim\Floxim\Controller;

/**
 * "Static" class, provides access to main system services
 */
class Fx
{
    protected function __construct()
    {

    }
    
    public static $counters = array();
    public static function count($key) {
        if (!isset(self::$counters[$key])) {
            self::$counters[$key] = 0;
        }
        self::$counters[$key]++;
    }

    /**
     * Force complete run script
     */
    static public function complete($data = null)
    {
        fx::env('complete_ok', true);
        if (!is_null($data)) {
            for ($i = 0; $i < ob_get_level(); $i++) {
                ob_end_clean();
            }
            if (is_scalar($data)) {
                echo($data);
            } else {
                echo(json_encode($data));
            }
            die();
        }
    }

    /* Get config data */
    static public function config($k = null, $v = null)
    {
        static $config = false;
        if ($config === false) {
            $config = new Config();
        }
        $argc = func_num_args();
        if ($argc == 0) {
            return $config;
        }
        if ($argc == 1) {
            return $config->get($k);
        }
        $config->set($k, $v);
        return $config;
    }

    /**
     * Access a database object
     * @return \Floxim\Floxim\System\Db
     */
    public static function db()
    {
        static $db = null;
        if ($db === null) {
            $db = new Db();
            if (!$db) {
                $db = false;
            }
        }
        if ($db === false) {
            throw new \Exception("Database is not available");
        }
        return $db;
    }

    public static $floxim_components = array(
        'main' => array(
            'content',
            'linker',
            'page',
            'text',
            'mail_template',
            'message_template'
        ),
        'user' => array(
            'user'
        ),
        'nav' => array(
            'section',
            'tag',
            'classifier'
        ),
        'layout' => array(
            'grid',
            'block_set',
            'custom_code'
        ),
        'shop' => array(
            'product'
        ),
        'corporate' => array(
            'person',
            'vacancy',
            'project',
            'contact',
            'map'
        ),
        'media' => array(
            'photo',
            'video'
        ),
        'blog' => array(
            'publication',
            'news',
            'comment'
        )
    );

    public static function getComponentFullNameByPath($path)
    {
        $match = preg_match("~/([^/]+?)/([^/]+?)/([^/]+?)/[^/]+$~", $path, $parts);
        if (!$match) {
            return null;
        }
        array_shift($parts);
        foreach ($parts as &$p) {
            $p = fx::util()->camelToUnderscore($p);
        }
        $res = join(".", $parts);
        return $res;
    }
    
    public static function getComponentFullName($com_name)
    {
        static $cache = array();
        if (!is_string($com_name)) {
            //fx::log(debug_backtrace());
        }
        if (isset($cache[$com_name])) {
            return $cache[$com_name];
        }
        
        static $coms_by_module = null;
        
        $name = $com_name;
        $action = null;
        $c_parts = explode(':', $name);
        if (count($c_parts) == 2) {
            list($name, $action) = $c_parts;
        }
        $path = explode(".", $name);
        if (count($path) === 1) {
            /*
            if (is_null($coms_by_module)) {
                $coms_by_module = array();
                foreach (Fx::$floxim_components as $module => $coms) {
                    foreach ($coms as $com) {
                        $coms_by_module[$com] = $module;
                    }
                }
            }

            $short_com_name = fx::util()->camelToUnderscore($path[0]);

            // one of floxim default modules
            if (isset($coms_by_module[$short_com_name])) {
                array_unshift($path, $coms_by_module[$short_com_name]);
            } else 
            // system component such as 'site', 'session' etc.
            {
                array_unshift($path, 'component');
            }
             * 
             */
            
        }
        /*
        if (count($path) === 2) {
            array_unshift($path, 'floxim');
        }
         * 
         */
        $res = join(".", $path) . ($action ? ':' . $action : '');
        $cache[$com_name] = $res;
        return $res;
    }

    public static function getComponentParts($name)
    {
        $parts = array(
            'vendor'    => '',
            'module'    => '',
            'component' => '',
            'type'      => '',
            'action'    => '',
        );
        $name = self::getComponentFullName($name);
        $act_path = explode(':', $name);
        $path = explode(".", $act_path[0]);

        $parts['vendor'] = $path[0];
        $parts['module'] = $path[1];
        $parts['component'] = $path[2];

        if (isset($act_path[1])) {
            $parts['action'] = $act_path[1];
        }
        return $parts;
    }

    /**
     * Transform dot-separated component name to full namespace
     * @param type $name
     * @return type
     */
    public static function getComponentNamespace($name)
    {
        static $ns_cache = array();
        if (isset($ns_cache[$name])) {
            return $ns_cache[$name];
        }
        
        $full_name = fx::getComponentFullName($name);
        $path = explode(".", $full_name);
        /*
        if ($path[0] === 'floxim' && $path[1] === 'component') {
            array_unshift($path, "floxim");
        }
         * 
         */
        if (count($path) === 1) {
            if (in_array($name, array('user', 'page','content'))) {
                fx::log('oldstyle com', $name, fx::debug()->backtrace());
            }
            $res = '\\Floxim\\Floxim\\Component\\' . fx::util()->underscoreToCamel($full_name);
        } else {
            foreach ($path as &$part) {
                $part = fx::util()->underscoreToCamel($part);
                /*
                $chunks = explode("_", $part);
                foreach ($chunks as &$chunk) {
                    $chunk = fx::util()->underscoreToCamel($chunk);
                }
                $part = join('', $chunks);
                 * 
                 */
            }
            $res = '\\' . join('\\', $path);
        }
        $ns_cache[$name] = $res;
        return $res;
    }

    public static function getComponentPath($name)
    {
        return str_replace('\\', DIRECTORY_SEPARATOR, fx::getComponentNamespace($name));
    }

    public static function getClassNameFromNamespaceFull($namespace)
    {
        $path = explode('\\', $namespace);
        return array_pop($path);
    }

    public static function getComponentNameByClass($class)
    {
        // Floxim\User\User\Controller
        // Vendor\Module\Component\[Controller|Finder|Entity]
        // Floxim\Floxim\Component\Component\[Entity|Finder]
        
        static $class_cache = array();
        
        if (isset($class_cache[$class])) {
            return $class_cache[$class];
        }
        
        $path = explode('\\', $class);
        array_pop($path);
        
        if ($path[0] === 'Floxim' && $path[1] === 'Floxim' && $path[2] === 'Component') {
            $name = fx::util()->camelToUnderscore($path[3]);
        } else {
            $path = array_map(function ($a) {
                return fx::util()->camelToUnderscore($a);
            }, $path);
            $name = join('.', $path);
        }
        $class_cache[$class] = $name;
        return $name;
    }
    
    public static function  data($datatype, $id = null)
    {
        if (is_object($id) && $id instanceof Entity) {
            return $id;
        }
        
        $namespace = self::getComponentNamespace($datatype);

        $class_name = $namespace . '\\Finder';
        
        if (!class_exists($class_name)) {
            fx::log('no data class', $datatype, $class_name, fx::debug()->backtrace());
            throw new \Exception('Class not found: ' . $class_name . ' for ' . $datatype);
        }
        
        $num_args = func_num_args();
        
        if ($num_args  === 2 && is_int($id) && ($entity = self::registry()->getEntity($datatype, $id))) {
            return $entity;
        }

        /*
        if ($num_args > 1 && $class_name::isStaticCacheUsed()) {
            if (is_scalar($id)) {
                $static_res = $class_name::getFromStaticCache($id);
                if ($static_res) {
                    return $static_res;
                }
            }
        }
        */
        $finder = new $class_name;
        
        if ($num_args === 1) {
            return $finder;
        }
        if (is_array($id) || $id instanceof \Traversable) {
            return $finder->getByIds($id);
        }
        return $finder->getById($id);
    }

    public static function content($type = null, $id = null)
    {
        if (is_numeric($type)) {
            if (func_num_args() === 1) {
                return fx::data('floxim.main.content', $type);
            }
            $type = fx::component($type)->get('keyword');
        }
        $args = func_get_args();
        $args[0] = $type;
        return call_user_func_array('fx::data', $args);
    }

    protected static $router = null;

    /**
     * Get a basic routing Manager or router $router_name
     * @param $router_name = null
     * @return \Floxim\Floxim\Router\Manager
     */
    public static function router($router_name = null)
    {
        if (self::$router === null) {
            self::$router = new Router\Manager();
        }
        if (func_num_args() == 1) {
            return self::$router->getRouter($router_name);
        }
        return self::$router;
    }

    public static function isAdmin($set = null)
    {
        static $is_admin = null;
        static $was_admin = null;
        if (is_null($is_admin)) {
            $is_admin = (bool)self::env()->getIsAdmin();
        }
        if (func_num_args() === 1) {
            if (is_null($was_admin)) {
                $was_admin = $is_admin;
            }
            $is_admin = is_null($set) ? $was_admin : (bool) $set;
        }
        return $is_admin;
    }

    /**
     * Call without parameters to return the object with the parameters - get/set property
     * @param string $property prop_name
     * @param mixed $value set value
     */
    public static function env()
    {
        static $env = false;
        if ($env === false) {
            $env = new Env();
        }
        $num_args = func_num_args();
        
        if ($num_args === 0) {
            return $env;
        }
        $args = func_get_args();
        if ($num_args === 1) {
            return $env->get($args[0]);
        }
        if (count($args) == 2) {
            /*
            $method = array($env, 'set_' . $args[0]);
            if (is_callable($method)) {
                return call_user_func($method, $args[1]);
            }
             * 
             */
            return call_user_func(array($env, 'set'), $args[0], $args[1]);
        }
        return null;
    }

    /**
     * todo: psr0 need fix
     *
     * @param string $controller 'controller_name' or 'controller_name:action_name'
     * @param array $input
     * @param string $action
     * @return \Floxim\Floxim\System\Controller initialized controller
     */
    public static function controller($controller, $input = null, $action = null)
    {
        /**
         * vendor.module.component - front component controller
         * vendor.module.component:action - front component controller with action
         * todo: vendor.module.component.admin - component admin controller
         * todo: vendor.module.admin - module admin
         * todo: vendor.module.widget - widget controller
         * layout - layout controller
         * admin.controller - admin controller site
         */

        $c_parts = explode(":", $controller);
        if (count($c_parts) == 2) {
            $controller = $c_parts[0];
            $action = $c_parts[1];
        }

        if ($controller == 'layout') {
            return new Controller\Layout($input, $action);
        }
        /**
         * Vendor component
         */
        $c_class = fx::getComponentNamespace($controller) . '\\Controller';
        if (class_exists($c_class)) {
            return new $c_class($input, $action);
        }

        $c_parts = explode(".", $controller);
        /**
         * Admin controllers
         */
        if ($c_parts[0] == 'admin') {
            $c_name = isset($c_parts[1]) ? $c_parts[1] : 'Admin';
            $c_class = '\\Floxim\\Floxim\\Admin\\Controller\\' . ucfirst($c_name);
            if (class_exists($c_class)) {
                $controller_instance = new $c_class($input, $action);
                return $controller_instance;
            }
            fx::debug(func_get_args(), fx::debug()->backtrace());
            die("Failed loading controller class " . $c_class);
        }
        fx::debug(func_get_args(), fx::debug()->backtrace());
        die("Failed loading class controller " . $controller);
    }

    // todo: psr0 need fix
    public static function template($template_name = null, $data = array())
    {
        if (func_num_args() == 0) {
            return new Template\Loader();
        }
        $parts = explode(":", $template_name);
        if (count($parts) == 2) {
            $template_name = $parts[0];
            $action = $parts[1];
        } else {
            $action = null;
        }
        if (!preg_match("~^@~", $template_name)) {
            $template_name = self::getComponentFullName($template_name);
        }
        $template = Template\Loader::loadByName($template_name, $action, $data);
        return $template;
    }

    protected static $page = null;

    /**
     * @return \Floxim\Floxim\System\Page page instance
     */
    public static function page()
    {
        if (!self::$page) {
            self::$page = new Page();
        }
        return self::$page;
    }

    /*
     * Utility for accessing deep array indexes
     * @param ArrayAccess $collection
     * @param $var_path
     * @param [$index_2] etc.
     * @example $x = fx::dig(array('y' => array('x' => 2)), 'y.x');
     * @example $x = fx::dig(array('y' => array('x' => 2)), 'y', 'x');
     */
    public static function dig($collection, $var_path)
    {
        if (func_num_args() > 2) {
            $var_path = func_get_args();
            array_shift($var_path);
        } elseif (is_string($var_path)) {
            $var_path = explode(".", $var_path);
        }
        $arr = $collection;
        foreach ($var_path as $pp) {
            if (is_array($arr) || $arr instanceof \ArrayAccess) {
                if (!isset($arr[$pp])) {
                    return null;
                }
                $arr = $arr[$pp];
            } elseif (is_object($arr) && isset($arr->$pp)) {
                if (!isset($arr->$pp)) {
                    return null;
                }
                $arr = $arr->$pp;
            } else {
                return null;
            }
        }
        return $arr;
    }

    public static function digSet(&$collection, $var_path, $var_value, $merge = false)
    {
        if (!is_array($var_path)) {
            $var_path = explode('.', $var_path);
        }

        $arr =& $collection;
        $total = count($var_path);
        foreach ($var_path as $num => $pp) {
            $is_arr = is_array($arr);
            $is_aa = $arr instanceof \ArrayAccess;
            if (!$is_arr && !$is_aa) {
                //return null;
                $arr = array();
            }
            if ($pp === '') {
                $arr[] = $var_value;
                break;
            }
            $is_last = $num + 1 === $total;
            
            if ($is_last) {
                $arr[$pp] = $var_value;
                break;
            }
            
            @ $arr =& $arr[$pp];
        }
        return $collection;
    }
    
    public static function digUnset(&$collection, $var_path)
    {
        if (!is_array($var_path)) {
            $var_path = explode('.', $var_path);
        }

        $arr =& $collection;
        $total = count($var_path);
        foreach ($var_path as $num => $pp) {
            $is_arr = is_array($arr);
            $is_aa = $arr instanceof \ArrayAccess;
            if (!$is_arr && !$is_aa) {
                return $collection;
            }
            $is_last = $num + 1 === $total;
            
            if ($is_last) {
                unset($arr[$pp]);
                break;
            }
            
            @ $arr =& $arr[$pp];
        }
        return $collection;
    }

    /**
     *
     * @param \Floxim\Floxim\System\Collection $data
     * @return \Floxim\Floxim\System\Collection
     */
    public static function collection($data = array())
    {
        return $data instanceof Collection ? $data : new Collection($data);
    }
    
    public static function tree($data, $children_key = 'children', $extra_root_ids = array()) 
    {
        return new Tree($data, $children_key, $extra_root_ids);
    }

    /*
     * @return \Floxim\Floxim\System\Input
     */
    public static function input()
    {
        static $input = false;
        if ($input === false) {
            $input = new Input();
        }
        if (func_num_args() === 0) {
            return $input;
        }
        $superglobal = strtolower(func_get_arg(0));
        if (!in_array($superglobal, array('get', 'post', 'cookie', 'session'))) {
            return $input;
        }
        $callback = array($input, 'fetch' . fx::util()->underscoreToCamel($superglobal));
        if (func_num_args() === 1) {
            return call_user_func($callback);
        }
        return call_user_func($callback, func_get_arg(1));
    }
    
    protected static $module_manager = null;
    
    public static function load($config = null)
    {
        if (!class_exists('fx')) {
            class_alias('\\Floxim\\Floxim\\System\\Fx', 'fx');
        }
        
        ClassLoader::register();
        ClassLoader::addDirectories(array(DOCUMENT_ROOT . '/module'));
        
        if ($config !== null) {
            self::config()->load($config);
        }
        
        // load options from DB
        //self::config()->loadFromDb();
        
        if (self::config('session.start_auto')) {
            session_start();
        }
        
        self::loadComponents();
        
        // init modules
        self::$module_manager = new Modules();
        $modules = self::$module_manager->getAll();
        foreach ($modules as $m) {
            if (isset($m['object'])) {
                $m['object']->init();
            }
        }
    }
    
    public static function modules()
    {
        $res =
            self::$module_manager
                ->getAll()
                ->find('object')
                ->column(
                    'object'
                );
        return $res;
    }
    
    public static function module($keyword)
    {
        $modules = self::$module_manager->getAll();
        $parts = explode(".", $keyword);
        foreach ($parts as &$p) {
            $p = fx::util()->underscoreToCamel($p);
        }
        $parts []= 'Module';
        $class = join("\\", $parts);
        foreach ($modules as $m) {
            if (isset($m['object']) && get_class($m['object']) === $class) {
                return $m['object'];
            }
        }
    }
    
    protected static $components = null;
    protected static $components_collection = null;
    protected static $components_by_keyword = array();
    protected static function loadComponents() 
    {
        $finder = new \Floxim\Floxim\Component\Component\Finder();
        $components = $finder->all();
        if (!self::$components) {
            self::registerComponents($components);
        }
    }
    
    public static function registerComponents($components)
    {
        self::$components_collection = $components;
        self::$components = self::$components_collection->getData();
        foreach (self::$components as $com) {
            self::$components_by_keyword[$com['keyword']] = $com;
        }
    }
    
    /**
     * @param type $id_or_keyword
     * @return \Floxim\Floxim\Component\Component\Entity;
     */
    public static function component($id_or_keyword = null) {
        if (func_num_args() === 0) {
            return self::$components_collection;
        }
        if (is_numeric($id_or_keyword)) {
            return isset(self::$components[$id_or_keyword]) ? self::$components[$id_or_keyword] : null;
        }
        $keyword = self::getComponentFullName($id_or_keyword);
        return isset(self::$components_by_keyword[$keyword]) ? self::$components_by_keyword[$keyword] : null;
    }
    
    public static function getComponentById($id)
    {
        return isset(self::$components[$id]) ? self::$components[$id] : null;
    }
    
    public static function getComponentByKeyword($keyword)
    {
        return isset(self::$components_by_keyword[$keyword]) ? self::$components_by_keyword[$keyword] : null;
    }

    public static function lang($string = null, $dict = null)
    {
        static $lang = null;
        if (!$lang) {
            $lang = fx::data('lang_string');
            $site = fx::env()->getSite();
            if ($site) {
                $lang_keyword = $site['language'];
            } else {
                $lang_keyword = 'en';
            }
            $lang->setLang($lang_keyword);
        }
        if ($string === null) {
            return $lang;
        }

        if (!($res = $lang->getString($string, $dict))) {
            try {
                $lang->addString($string, $dict);
            } catch (\Exception $e) {
                fx::log('exc', $e);
            }
            $res = $string;
        }
        if (func_num_args() > 2) {
            $replacements = array_slice(func_get_args(), 2);
            array_unshift($replacements, $res);
            $res = call_user_func_array('sprintf', $replacements);
        }
        return $res;
    }

    public static function alang($string = null, $dict = null)
    {
        static $lang = null;
        if (!$lang) {
            $lang = fx::data('lang_string');
            $lang->setLang();
        }
        if ($string === null) {
            return $lang;
        }

        if (!($res = $lang->getString($string, $dict))) {
            try {
                $lang->addString($string, $dict);
            } catch (\Exception $e) {
                fx::log('exc', $e);
            }
            $res = $string;
        }
        if (func_num_args() > 2) {
            $replacements = array_slice(func_get_args(), 2);
            array_unshift($replacements, $res);
            $res = call_user_func_array('sprintf', $replacements);
        }
        return $res;
    }


    protected static $http = null;

    /**
     * http helper
     * @return \Floxim\Floxim\System\Http
     */
    public static function http()
    {
        if (!self::$http) {
            self::$http = new Http();
        }
        return self::$http;
    }

    protected static $migration_manager = null;

    /**
     * migration manager
     * @param array $params
     *
     * @return \Floxim\Floxim\System\MigrationManager
     */
    public static function migrations($params = array())
    {
        if (!self::$migration_manager) {
            self::$migration_manager = new MigrationManager($params);
        }
        return self::$migration_manager;
    }

    protected static $hook_manager = null;

    /**
     * hook manager
     *
     * @return \Floxim\Floxim\System\HookManager
     */
    public static function hooks()
    {
        if (!self::$hook_manager) {
            self::$hook_manager = new HookManager();
        }
        return self::$hook_manager;
    }

    /**
     * Get current user or new empty entity (with no id) if not logged in
     * @return \Floxim\User\User\Entity
     */
    public static function user()
    {
        return self::env()->getUser();
    }

    protected static function getEventManager()
    {
        static $event_manager = null;
        if (is_null($event_manager)) {
            $event_manager = new Eventmanager();
        }
        return $event_manager;
    }

    public static function listen($event_name, $callback)
    {
        self::getEventManager()->listen($event_name, $callback);
    }

    public static function unlisten($event_name)
    {
        self::getEventManager()->unlisten($event_name);
    }

    public static function trigger($event, $params = null)
    {
        return self::getEventManager()->trigger($event, $params);
    }
    
    public static function event($name, $params = null)
    {
        return new Event($name, $params);
    }


    protected static $cache = null;

    /**
     * 
     * @staticvar null $cacheSettings
     * @staticvar null $defaultStorageName
     * @param type $storageName
     * @return \Floxim\Cache\Storage\AbstractStorage;
     */
    public static function cache($storageName = null)
    {
        static $cacheSettings = null;
        static $defaultStorageName = null;
        
        if (is_null(self::$cache)) {
            $cacheSettings = fx::config('cache.data.storages');
            $defaultStorageName = fx::config('cache.data.default_storage');
            
            self::$cache = new \Floxim\Cache\Manager();
            self::$cache->setKeyPrefix(fx::config('cache.data.default_prefix'));
            
            // setup default storage
            $defaultStorage = self::$cache->getStorage($defaultStorageName, $cacheSettings[$defaultStorageName]);
            self::$cache->setDefaultStorage($defaultStorage);
        }
        
        if (is_null($storageName)) {
            $storageName = $defaultStorageName;
        }
        
        $params = isset($cacheSettings[$storageName]) ? $cacheSettings[$storageName] : array();

        return self::$cache->getStorage($storageName, $params);
    }
    
    protected static $registry = null;
    
    public static function registry()
    {
        if (is_null(self::$registry)) {
            self::$registry = new RegistryManager();
        }
        return self::$registry;
    }
    
    /**
     * Get database schema
     * @param type $table
     */
    public static function schema($table = null, $add_prefix = true)
    {
        static $schema = null;
        if (is_null($schema)) {
            $schema = fx::db()->getSchema();
        }
        if (func_num_args() === 0) {
            return $schema;
        }
        if ($table && $add_prefix) {
            $table = fx::db()->getPrefix().$table;
        }
        
        if (isset($schema[$table])) {
            return $schema[$table];
        }
    }

    public static function files()
    {
        static $files = false;
        if ($files === false) {
            $files = new Files();
        }

        if (func_num_args() == 0) {
            return $files;
        }
        $args = func_get_args();
        switch ($args[1]) {
            case 'size':
                $path = fx::path()->abs($args[0]);
                return $files->readableSize($path);
            case 'name':
                return fx::path()->fileName($args[0]);
            case 'type':
                return trim(fx::path()->fileExtension($args[0]), '.');
        }
    }

    public static function util()
    {
        static $util = false;
        if ($util === false) {
            $util = new Util();
        }
        return $util;
    }
    
    protected static function smartDateFormat($value, $format) {
        $ru_month = function($date, $placeholder) {
            $months = array (
              1 => 'январь',
              2 => 'февраль',
              3 => 'март',
              4 => 'апрель',
              5 => 'май',
              6 => 'июнь',
              7 => 'июль',
              8 => 'август',
              9 => 'сентябрь',
              10 => 'октябрь',
              11 => 'ноябрь',
              12 => 'декабрь'
            );
            $months_gen = array (
              1 => 'января',
              2 => 'февраля',
              3 => 'марта',
              4 => 'апреля',
              5 => 'мая',
              6 => 'июня',
              7 => 'июля',
              8 => 'августа',
              9 => 'сентября',
              10 => 'октября',
              11 => 'ноября',
              12 => 'декабря'
            );
            $parts = explode(":", $placeholder);
            $arr = isset($parts[1]) && $parts[1] === 'gen' ? $months_gen : $months;
            $month_num = (int) date('m', $date);
            $month_name = $arr[$month_num];
            if ( ucfirst($parts[0]) === $parts[0]) {
                $month_name = fx::util()->ucfirst($month_name);
            }
            return $month_name;
        };
        $parts = preg_split("~(\%.+?\%)~", $format, -1, PREG_SPLIT_DELIM_CAPTURE);
        $res = array();
        foreach ($parts as $part) {
            $is_special = preg_match("~\%(.+)\%~", $part, $placeholder);
            if (!$is_special) {
                $res []= date($part, $value);
                continue;
            }
            $placeholder = $placeholder[1];
            $chunk = '';
            switch ($placeholder) {
                case 'month:gen':
                case 'Month:gen':
                case 'month':
                case 'Month':
                    $chunk = $ru_month($value, $placeholder);
                    break;
            }
            $res []= $chunk;
        }
        return join('', $res);
    }
    
    public static function cb($arg) {
        fx::log('unknown callback', $arg, fx::debug()->backtrace());
        return $arg;
    }
    
    public static function date($value = null, $format = 'Y-m-d H:i:s') {
        if ($value === null) {
          return null;
        }
        if (empty($value)) {
            return $value;
        }
        if (!is_numeric($value)) {
            $value = strtotime($value);
        }
        if (empty($value)) {
            return $value;
        }
        if (!strstr($format, '%')) {
            return date($format, $value);
        }
        return self::smartDateFormat($value, $format);
    }
    
    public static function timestamp($value = null)
    {
        if (is_null($value)) {
            return time();
        }
        $res = self::date($value, 'U') * 1;
        return $res;
    }
    
    public static function icon($icon)
    {
        return \Floxim\Floxim\Asset\Icons::getClass( $icon );
    }
    
    public static function image($value, $format)
    {
        try {
            $thumber = new Thumb($value, $format);
            $res = $thumber->getResultPath();
        } catch (\Exception $e) {
            fx::log('img exception', $e->getMessage(), fx::debug()->backtrace(false));
            $res = '';
        }
        return $res;
    }
    
    public static function imageSize($value, $format = 'css') {
        $f = fx::path($value);
        if (!file_exists($f) || !is_file($f)) {
            return '';
        }
        $size = getimagesize($f);
        if (!$size) {
            return '';
        }
        if (preg_match("~\%[wh]~", $format)) {
            return str_replace("%w", $size[0], str_replace("%h", $size[1], $format));
        }
        switch ($format) {
            case 'css':
            default:
                return 'width:'.$size[0].'px; height:'.$size[1].'px;';
            case 'width':
            case 'w':
                return $size[0];
            case 'height':
            case 'h':
                return $size[1];
        }
    }

    public static function version()
    {
        return fx::config('fx.version');
    }

    public static function changelog($version = null)
    {
        $file = fx::config('ROOT_FOLDER') . 'changelog.json';
        if (file_exists($file)) {
            if ($changelog = @json_decode(file_get_contents($file), true)) {
                if (is_null($version)) {
                    return $changelog;
                } else {
                    if (isset($changelog[$version])) {
                        return $changelog[$version];
                    }
                }
            }
        }
        return null;
    }

    protected static $debugger = null;
    
    public static function getDebugger()
    {
        if (is_null(self::$debugger)) {
            self::$debugger = new Debug();
            self::$debugger->setDir(fx::path('@log'));
        }
        return self::$debugger;
    }

    public static function debug($what = null)
    {
        if (!fx::config('dev.on') && func_num_args() > 0) {
            return;
        }
        $debugger = self::getDebugger();
        if (func_num_args() == 0) {
            return $debugger;
        }
        call_user_func_array(array($debugger, 'debug'), func_get_args());
    }
    
    public static function cdebug()
    {
        if (fx::env('console') !== true) {
            return;
        }
        
        if (ob_get_level() <= 1) {
            call_user_func_array('fx::debug', func_get_args());
        } else {
            ob_start();
            call_user_func_array('fx::debug', func_get_args());
            $res = ob_get_clean();
            fx::env('console_buffer', fx::env('console_buffer').$res);
        }
    }

    public static function log($what)
    {
        $debugger = self::getDebugger();
        call_user_func_array(array($debugger, 'log'), func_get_args());
    }
    
    public static function logIf($cond, $what) {
        $args = func_get_args();
        $cond = array_shift($args);
        if (!$cond) {
            return;
        }
        $debugger = self::getDebugger();
        call_user_func_array(array($debugger, 'log'), $args);
    }

    public static function profiler()
    {
        static $profiler = null;
        if (is_null($profiler)) {
            $profiler = new Profiler();
            self::debug()->start();
            self::debug()->onStop(function() use ($profiler) {
                if ($profiler->hasData()) {
                    fx::log(
                        '%raw%'.$profiler->show(), 
                        $profiler->getSortedTags(), 
                        $profiler
                    );
                }
            });
        }
        return $profiler;
    }

    public static function path($key = null, $tale = null)
    {
        static $path = null;
        if (!$path) {
            $path = new Path();
        }
        switch (func_num_args()) {
            case 0:
            default:
                return $path;
            case 1:
                return $path->abs($key);
            case 2:
                return $path->abs($key, $tale);
        }
    }

    /**
     * Get mailer service
     * @param array $params
     * @param array $data
     * @return \Floxim\Floxim\System\Mail
     */
    public static function mail($params = null, $data = array())
    {
        $mailer = new Mail($params, $data);
        return $mailer;
    }

    public static function console($command)
    {
        ob_start();
        $manager = new \Floxim\Floxim\System\Console\Manager();
        $manager->addCommands(fx::config('console.commands'));
        $manager->addPath(fx::path()->abs('/vendor/Floxim/Floxim/System/Console/Command'));
        $manager->run($command);
        return ob_get_clean();
    }
    
    
    public static function decl($word, $number)
    {
        return fx::util()->getDeclensionByNumber($word, $number);
    }
    
    public static function assets($type = null, $keyword = null, $params = array())
    {
        static $bundleManager = null;
        if (is_null($bundleManager)) {
            $bundleManager = new \Floxim\Floxim\Asset\Manager();
        }
        switch (func_num_args()) {
            case 0:
                return $bundleManager;
            case 1:
                return $bundleManager->getBundle($type, 'default');
            case 2:
                return $bundleManager->getBundle($type, $keyword);
            case 3:
                return $bundleManager->getBundle($type, $keyword, $params);
        }
    }
}