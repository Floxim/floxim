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
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_clean();
        }
        if (!is_null($data)) {
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
            $db->query("SET NAMES '".fx::config()->DB_CHARSET."'");
    	}
    	return $db;
    }
    
    protected static $data_cache = array();
    /* Get data finder for the specified type content_id data or the object(s) by id
     * @param string $datatype name of a data type 'component', 'content_news'
     * @param mixed [$id] IDs or ids array
     */
    public static $data_stat = array();
    
    /**
     * Transform dot-separated component name to full namespace
     * @param type $name
     * @return type
     */
    public static function getComponentNamespace($name) {
        $path = explode(".", $name);
        foreach ($path as &$part) {
            $chunks = explode("_", $part);
            foreach ($chunks as &$chunk) {
                $chunk = ucfirst($chunk);
            }
            $part = join('', $chunks);
        }
        if (count($path) === 1) {
            // list of components inside standard library
            // todo: psr0 need verify component 'content'
            $lib_components = array('award', 'classifier', 'classifier_linker', 'comment', 'company', 'contact', 'faq', 'news', 'page', 'person', 'photo',
                                    'product', 'project', 'publication', 'section', 'social_icon', 'tag', 'text', 'user', 'vacancy', 'video');
            if (in_array(strtolower($path[0]), $lib_components)) {
                array_unshift($path, 'Component');
                array_unshift($path, 'Floxim\Main');
            } else {
                array_unshift($path, 'Floxim\Component');
            }
        }
        if (count($path) === 2) {
            array_unshift($path, 'Floxim');
        }
        return '\\'.join('\\', $path);
    }

    // todo: psr0 need verify - recursive request finder class
    /**
     * vendor.module.component - component finder
     * component - system component finder
     */
    public static function  data($datatype, $id = null) {
        
        // fx::data($page) instead of $page_id
        if (is_object($id) && $id instanceof Essence) {
            return $id;
        }
        
        $namespace = self::getComponentNamespace($datatype);
        
        $class_name = $namespace.'\\Finder';
        if (!class_exists($class_name)) {
            say(debug_backtrace());
            throw new \Exception('Class not found: '.$class_name. ' for '.$datatype);
        }
        
        $finder = new $class_name;
        
        if (func_num_args() === 1) {
            return $finder;
        }
        
        return $finder->get_by_id($id);
        // look for data-* class in cache
        if (isset($data_classes_cache[$datatype])) {
            $finder_class = $data_classes_cache[$datatype];
            if ($finder_class == 'Floxim\\Floxim\\System\\Data') {
                $data_finder = new Data($datatype);
            } else {
                $data_finder = new $finder_class();
            }
        } else {
            try {
                /**
                 * 1. module - [vendor].[module].[component]
                 * 2. system - [component]
                 */
                if (count($parts) == 3) {
                    list($d_vendor, $d_module, $d_component) = $parts;
                    $classname = '\\'.ucfirst($d_vendor).'\\'.ucfirst($d_module).'\\Component\\'.ucfirst($d_component).'\\Finder';
                } else {
                    $classname = '\\Floxim\\Floxim\\Component\\'.ucfirst($datatype).'\\Finder';
                }
                if (!class_exists($classname)) {
                    throw new \Exception();
                }
                $data_finder = new $classname();
                $data_classes_cache[$datatype] = $classname;
            } catch (\Exception $e) {
                // Finder for the content that the class is not defined
                if ($component) {
                    $not_existing = array($datatype);
                    foreach ($component->get_ancestors() as $parent_com) {
                        try {
                            $keyword = $parent_com['keyword']; // vendor.module.component
                            $c_datatype = $keyword;
                            if ($keyword == 'floxim.content.content') {
                                $classname = '\\Floxim\\Floxim\\Component\\Content\\Finder';
                            } else {
                                list($d_vendor, $d_module, $d_component) = $parts;
                                $classname = '\\'.ucfirst($d_vendor).'\\'.ucfirst($d_module).'\\Component\\'.ucfirst($d_component).'\\Finder';
                            }

                            if (!class_exists($classname)) {
                                throw new \Exception();
                            }
                            $data_finder = new $classname;
                            foreach ($not_existing as $ne) {
                                $data_classes_cache[$ne] = $classname;
                            }
                            break;
                        } catch (\Exception $ex) {
                            $not_existing []= $c_datatype;
                        }
                    }
                } elseif (preg_match("~^field_~", $datatype)) {
                    $data_finder = new \Floxim\Floxim\Component\Field\Finder();
                    $data_classes_cache[$datatype] = 'Floxim\\Floxim\\Component\\Field\\Finder';
                }
            }
            if (is_null($data_finder)) {
                say('no df', $datatype);
                $data_finder = new \Floxim\Floxim\System\Data($datatype);
                $data_classes_cache[$datatype] = 'Floxim\\Floxim\\System\\Data';
            }
        }


        if ($component) {
            $data_finder->set_component($component['id']);
        }

        if (func_num_args() == 2) {
            if (is_numeric($id) || is_string($id)) {
                $res = $data_finder->get_by_id($id);
                self::$data_cache[$datatype][$id] = $res;
                return $res;
            }
            if (is_array($id)) {
                return $data_finder->get_by_ids($id);
            }
            return null;
        }
        return $data_finder;
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
            return self::$router->get_router($router_name);
        }
    	return self::$router;
    }
    
    public static function is_admin() {
        static $is_admin = null;
        if (is_null($is_admin)) {
            $is_admin = (bool) self::env('is_admin');
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
         * vendor.module.component.admin - component admin controller
         * vendor.module.admin - module admin
         * vendor.module.widget - widget controller
         * com -> \Floxim\Floxim\Component\com
         *     or \Floxim\Main\com
         * layout - layout controller
         * content - base controller component
         * admin.controller - admin controller site
         */

        $c_parts = explode(":", $controller);
        if (count($c_parts) == 2) {
            $controller = $c_parts[0];
            $action = $c_parts[1];
        }

        if ($controller=='layout') {
            return new Controller\Layout($input, $action);
        } elseif($controller=='content') {
            $controller_instance = new Controller\Component($input, $action);
            $controller_instance->set_content_type('content');
            return $controller_instance;
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
        /**
         * Sytem components
         */
        if (count($c_parts) === 1) {
            $c_class = fx::getComponentNamespace($c_parts[0]) . '\\Controller';
            $controller_instance = new $c_class($input, $action);
            return $controller_instance;
        }
        /**
         * Component controllers
         */
        
        if (count($c_parts) >= 3) {
            $c_vendor = $c_parts[0];
            $c_module = $c_parts[1];
            $c_component = $c_parts[2];
            if (in_array($c_component,array('admin','widget'))) {
                // todo: admin module controllers
                // .....
            } else {
                if (isset($c_parts[3])) {
                    // todo: check type - admin/widget
                    // .....
                } else {
                    // Component essence
                    $c_keyword = "{$c_vendor}.{$c_module}.{$c_component}";
                    $component = fx::data('component',$c_keyword);

                    if ($component) {
                        foreach ($component->get_ancestors() as $parent_com) {
                            try {
                                $c_class = fx::getComponentNamespace($parent_com['keyword']) . '\\Controller';
                                if (!class_exists($c_class)) {
                                    throw new \Exception();
                                }
                                $controller_instance = new $c_class($input, $action);
                                $controller_instance->set_content_type($c_keyword); // todo: psr0 need verify
                                return $controller_instance;
                            } catch (\Exception $ex) {

                            }
                        }
                    } else {
                        fx::log("no com", $c_keyword, debug_backtrace());
                    }
                }
            }
        }
        say(debug_backtrace());
        die("Failed loading class controller ".$controller);
    }

    // todo: psr0 need fix
    public static function template($template = null, $data = array()) {
        if (func_num_args() == 0) {
            return new Template\Loader();
        }
        $parts= explode(".", $template);
        if (count($parts) == 2) {
            $template = $parts[0];
            $action = $parts[1];
        } else {
            $action = null;
        }
        $class_name = 'fx_template_'.$template;
        if (!class_exists($class_name)) {
            $class_name = '\\Floxim\\Floxim\\Template\\Template';
        }
        return new $class_name($action, $data);
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
    
    public static function dig_set(&$collection, $var_path, $var_value, $merge = false) {
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
        $callback = array($input, 'fetch_'.$superglobal);
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
        self::config()->load_from_db();

        if (fx::config('cache.meta')) {
            self::_load_meta_cache();
        }
    }
    
    protected static function _load_meta_cache() {
        // preload meta info
        $cache_file = fx::path('files', 'cache/meta_cache.php');
        
        if (!file_exists($cache_file)) {
            $coms = fx::data('component')->with('fields')->all();
            $com_cache = array();
            $field_cache = array();
            foreach ($coms as $com) {
                $com_cache[$com['keyword']] = $com;
                $com_cache[$com['id']] = $com;
                foreach ($com->fields() as $com_field) {
                    $field_cache[$com_field['id']] = $com_field;
                }
            }
            fx::files()->writefile(
                $cache_file, serialize(array(
                    'component' => $com_cache,
                    'field' => $field_cache
                ))
            );
        } else {
            $cache = unserialize(fx::files()->readfile($cache_file));
            $com_cache = $cache['component'];
            $field_cache = $cache['field'];
        }
        self::$data_cache['component'] = $com_cache;
    }

    public static function lang ( $string = null, $dict = null) {
        static $lang = null;
        if (!$lang) {
            $lang = fx::data('lang_string');
            $lang->set_lang(fx::env()->get_site()->get('language'));
        }
        if ($string === null) {
            return $lang;
        }
        
        if (!($res = $lang->get_string($string, $dict))) {
            try {
                $lang->add_string($string, $dict);
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
            $lang->set_lang();
        }
        if ($string === null) {
            return $lang;
        }
        
        if (!($res = $lang->get_string($string, $dict))) {
            try {
                $lang->add_string($string, $dict);
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
     * Get current user or new empty essence (with no id) if not logged in
     * @return \Floxim\User\Component\User\Essence
     */
    public static function user() {
        return self::env()->get_user();
    }
    
    protected static function _get_event_manager() {
        static $event_manager = null;
        if (is_null($event_manager)) {
            $event_manager = new Eventmanager();
        }
        return $event_manager;
    }
    public static function listen($event_name, $callback) {
        self::_get_event_manager()->listen($event_name, $callback);
    }
    
    public static function unlisten($event_name) {
        self::_get_event_manager()->unlisten($event_name);
    }
    
    public static function trigger($event, $params = null) {
        self::_get_event_manager()->trigger($event, $params);
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
                $path = fx::path()->to_abs($args[0]);
                return $files->readable_size($path);
            case 'name':
                return fx::path()->file_name($args[0]);
            case 'type':
                return trim(fx::path()->file_extension($args[0]), '.');
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
            $res = $thumber->get_result_path();
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