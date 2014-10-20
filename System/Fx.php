<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Router;
use Floxim\Floxim\Template;
use Floxim\Floxim\Controller;

/**
"Static" class, just a helpers collection
*/ 

class Fx {
    protected function __construct() {

    }

    /**
     * Force complete run script
     */
    static public function complete($data=null) {
        fx::env('complete_ok',true);
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
    static public function config($k = null, $v = null) {
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
    public static function db() {
        static $db = false;
    	if ($db === false) {
            $db = new Db();
            $db->query("SET NAMES '".fx::config('db.charset')."'");
    	}
    	return $db;
    }
    
    protected static $data_cache = array();
    /* Get data finder for the specified type content_id data or the object(s) by id
     * @param string $datatype name of a data type 'component', 'content_news'
     * @param mixed [$id] IDs or ids array
     */
    public static $data_stat = array();
    
    public static function getComponentFullName($name) {
        $action=null;
        $c_parts = explode(':',$name);
        if (count($c_parts) == 2) {
            list($name, $action) = $c_parts;
        }
        $path = explode(".", $name);
        if (count($path) === 1) {
            $lib_components = array(
                'award', 
                'classifier', 
                'classifier_linker', 
                'comment', 
                'company', 
                'contact', 
                'content',
                'faq', 
                'grid',
                'map',
                'custom_code',
                'blockset',
                'news',
                'page', 
                'person', 
                'photo',
                'product', 
                'project', 
                'publication', 
                'section', 
                'social_icon',
                'linker',
                'tag', 
                'text', 
                'user', 
                'vacancy', 
                'video',
                'message_template',
                'mail_template',
                'wrapper'
            );
            if (in_array(strtolower($path[0]), $lib_components)) {
                array_unshift($path, 'main');
            } else {
                array_unshift($path, 'component');
            }
        }
        if (count($path) === 2) {
            array_unshift($path, 'floxim');
        }
        return join(".", $path) . ($action ? ':' . $action : '');
    }

    public static function getComponentParts($name) {
        $parts = array(
            'vendor' => '',
            'module' => '',
            'component' => '',
            'type' => '',
            'action' => '',
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
    public static function getComponentNamespace($name) {
        
        $name = self::getComponentFullName($name);
        
        $path = explode(".", $name);
        if ($path[0] === 'floxim' && $path[1] === 'component') {
            array_unshift($path, "floxim");
        }
        foreach ($path as &$part) {
            $chunks = explode("_", $part);
            foreach ($chunks as &$chunk) {
                $chunk = ucfirst($chunk);
            }
            $part = join('', $chunks);
        }
        return '\\'.join('\\', $path);
    }

    public static function getComponentPath($name) {
        return str_replace('\\', DIRECTORY_SEPARATOR, fx::getComponentNamespace($name));
    }

    public static function getClassNameFromNamespaceFull($namespace) {
        $path = explode('\\',$namespace);
        return array_pop($path);
    }

    public static function getComponentNameByClass($class) {
        // Floxim\Main\User\Controller
        // Vendor\Module\Component\[Controller|Finder|Entity]
        // Floxim\Floxim\Component\Component\[Entity|Finder]
        $path = explode('\\',$class);
        array_pop($path);
        $path=array_map(function($a){
            return fx::util()->camelToUnderscore($a);
        },$path);
        $name = join('.',$path);
        if (strpos($name,'floxim.floxim.component') === 0) {
            return $path[3];
        }
        return $name;
    }
    // todo: psr0 need verify - recursive request finder class
    /**
     * vendor.module.component - component finder
     * component - system component finder
     */
    public static function  data($datatype, $id = null) {
        
        // fx::data($page) instead of $page_id
        if (is_object($id) && $id instanceof Entity) {
            return $id;
        }

        $namespace = self::getComponentNamespace($datatype);

        $class_name = $namespace.'\\Finder';
        if (!class_exists($class_name)) {
            throw new \Exception('Class not found: '.$class_name. ' for '.$datatype);
        }
        
        $num_args = func_num_args();
        
        if ($num_args > 1 && $class_name::isStaticCacheUsed()) {
            if (is_scalar($id)) {
                $static_res = $class_name::getFromStaticCache($id);
                if ($static_res) {
                    return $static_res;
                }
            }
        }
        
        $finder = new $class_name;
        
        if ($num_args === 1) {
            return $finder;
        }
        if (is_array($id) || $id instanceof \Traversable ) {
            return $finder->getByIds($id);
        }
        return $finder->getById($id);
    }
    
    public static function content($type = null, $id = null) {
        if (is_numeric($type)) {
            if (func_num_args() === 1) {
                return fx::data('content', $type);
            }
            $type = fx::data('component', $type)->get('keyword');
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
    public static function router($router_name = null) {
    	if (self::$router === null) {
            self::$router = new Router\Manager();
    	}
        if (func_num_args() == 1) {
            return self::$router->getRouter($router_name);
        }
    	return self::$router;
    }
    
    public static function isAdmin() {
        static $is_admin = null;
        if (is_null($is_admin)) {
            $is_admin = (bool) self::env()->isAdmin();
        }
        return $is_admin;
    }
    
    /**
     * Call without parameters to return the object with the parameters - get/set property
     * @param string $property prop_name
     * @param mixed $value set value
     */
    public static function env() {
        static $env = false;
        if ($env === false) {
            $env = new Env();
        }
    	
        $args = func_get_args();
    	if (count($args) == 0) {
            return $env;
    	}
    	if (count($args) == 1) {
            if ($args[0] == 'is_admin') {
                $method = array($env, 'is_admin');
            } else {
                $method = array($env, 'get_'.$args[0]);
            }
            if (is_callable($method)) {
                return call_user_func($method);
            }
            return $env->get($args[0]);
    	}
    	if (count($args) == 2) {
            $method = array($env, 'set_'.$args[0]);
            if (is_callable($method)) {
                return call_user_func($method, $args[1]);
            }
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
    public static function controller($controller, $input = null, $action = null) {
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

        if ($controller=='layout') {
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
            $c_class = 'Admin\\Controller\\'.ucfirst($c_name);
            if (class_exists($c_class)) {
                $controller_instance = new $c_class($input, $action);
                return $controller_instance;
            }
            die("Failed loading controller class ".$c_class);
        }
        die("Failed loading class controller ".$controller);
    }

    // todo: psr0 need fix
    public static function template($template_name = null, $data = array()) {
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
        /*
        $template = Template\Loader::autoload($template_name);
        
        fx::debug($class_name);
        if (!class_exists($class_name)) {
            $class_name = '\\Floxim\\Floxim\\Template\\Template';
        }
        return new $class_name($action, $data);
         * 
         */
    }
    
    protected static $page = null;
    
    /**
     * @return \Floxim\Floxim\System\Page page instance
     */
    public static function page() {
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
    public static function dig($collection, $var_path) {
        if (func_num_args() > 2) {
            $var_path = func_get_args();
            array_shift($var_path);
        } else {
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
    
    public static function digSet(&$collection, $var_path, $var_value, $merge = false) {
        $var_path = explode('.', $var_path);
        
        $arr =& $collection;
        $total = count($var_path);
        foreach ($var_path as $num => $pp) {
            $is_arr = is_array($arr);
            $is_aa = $arr instanceof \ArrayAccess;
            if (!$is_arr && !$is_aa) {
                return null;
            }
            if (empty($pp)) {
                $arr[]= $var_value;
                return;
            }
            if (($is_arr && !array_key_exists($pp, $arr)) || ($is_aa && !isset($arr[$pp])) ) {
                if ($num + 1 === $total && !$merge) {
                    $arr[$pp] = $var_value;
                    return;
                }
                $arr[$pp]= array(); //fx::collection();
            }
            $arr =&  $arr[$pp];
        }
        
        if ($merge && is_array($arr) && is_array($var_value)) {
            $arr = array_merge_recursive($arr, $var_value);
        } else {
            $arr = $var_value;
        }
    }
    
    /**
     * 
     * @param \Floxim\Floxim\System\Collection $data
     * @return \Floxim\Floxim\System\Collection
     */
    public static function collection($data = array()) {
        return $data instanceof Collection ? $data : new Collection($data);
    }
    
    /*
     * @return \Floxim\Floxim\System\Input
     */
    public static function input() {
        static $input = false;
        if ($input === false) {
            $input = new Input();
        }
        if (func_num_args() === 0) {
            return $input;
        }
        $superglobal = strtolower(func_get_arg(0));
        if (!in_array($superglobal, array('get', 'post', 'cookie','session'))) {
            return $input;
        }
        $callback = array($input, 'fetch'.fx::util()->underscoreToCamel($superglobal));
        if (func_num_args() === 1) {
            return call_user_func($callback);
        }
        return call_user_func($callback, func_get_arg(1));
    }
    
    /*
     * @return fx_core
     */
    public static function load($config = null) {
        if ($config !== null) {
            self::config()->load($config);
        }

        // load options from DB
        self::config()->loadFromDb();
    }
    
    public static function lang ( $string = null, $dict = null) {
        static $lang = null;
        if (!$lang) {
            $lang = fx::data('lang_string');
            $lang->setLang(fx::env()->getSite()->get('language'));
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

    public static function alang ( $string = null, $dict = null) {
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
    public static function http() {
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
    public static function migrations($params=array()) {
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
    public static function hooks() {
        if (!self::$hook_manager) {
            self::$hook_manager = new HookManager();
        }
        return self::$hook_manager;
    }
    
    /**
     * Get current user or new empty entity (with no id) if not logged in
     * @return \Floxim\User\Component\User\Entity
     */
    public static function user() {
        return self::env()->getUser();
    }
    
    protected static function getEventManager() {
        static $event_manager = null;
        if (is_null($event_manager)) {
            $event_manager = new Eventmanager();
        }
        return $event_manager;
    }
    public static function listen($event_name, $callback) {
        self::getEventManager()->listen($event_name, $callback);
    }
    
    public static function unlisten($event_name) {
        self::getEventManager()->unlisten($event_name);
    }
    
    public static function trigger($event, $params = null) {
        self::getEventManager()->trigger($event, $params);
    }
    
    
    protected static $_cache = null;
    /*
     * until very blunt local cache,
     * not to get from the database is the same for single execution
     */
    public static function cache($key = null, $value = null) {
        if (!self::$_cache) {
            self::$_cache = new Cache();
        }
        $count_args = func_num_args();
        switch ($count_args) {
            case 0:
                return self::$_cache;
                break;
            case 1:
                return self::$_cache->get($key);
                break;
            case 2:
                self::$_cache->set($key, $value);
                break;
        }
    }
    
    public static function files() {
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
                $path = fx::path()->toAbs($args[0]);
                return $files->readableSize($path);
            case 'name':
                return fx::path()->fileName($args[0]);
            case 'type':
                return trim(fx::path()->fileExtension($args[0]), '.');
        }
    }
    
    public static function util() {
        static $util = false;
        if ($util === false) {
            $util = new Util();
        }
        return $util;
    }
    
    public static function date($value, $format) {
        if (empty($value)) {
            return $value;
        }
        if (!is_numeric($value)) {
            $value = strtotime($value);
        }
        if (empty($value)) {
            return $value;
        }
        return date($format, $value);
    }
    
    public static function image($value, $format) {
        try {
            $thumber = new Thumb($value, $format);
            $res = $thumber->getResultPath();
        } catch (\Exception $e) {
            $res = '';
        }
        return $res;
    }
    
    public static function version() {
        return fx::config('fx.version');
    }

    public static function changelog($version=null) {
        $file = fx::config('ROOT_FOLDER').'changelog.json';
        if (file_exists($file)) {
            if ($changelog = @json_decode(file_get_contents($file),true)) {
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
    
    public static function debug($what = null) {
        if (!fx::config('dev.on') && func_num_args() > 0) {
            return;
        }
        if (is_null(self::$debugger)) {
            self::$debugger = new Debug();
        }
        if (func_num_args() == 0) {
            return self::$debugger;
        }
        call_user_func_array(array(self::$debugger, 'debug'), func_get_args());
    }
    
    public static function log($what) {
        if (is_null(self::$debugger)) {
            self::$debugger = new Debug();
        }
        call_user_func_array(array(self::$debugger, 'log'), func_get_args());
    }
    
    public static function profiler() {
        static $profiler = null;
        if (is_null($profiler)) {
            $profiler = new Profiler();
        }
        return $profiler;
    }
    
    public static function path($key = null, $tale = null) {
        static $path = null;
        if (!$path) {
            $path = new Path();
        }
        switch(func_num_args()) {
            case 0: default:
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
    public static function mail($params = null, $data = null) {
        $mailer = new Mail($params, $data);
        return $mailer;
    }
}