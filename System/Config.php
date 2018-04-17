<?php

namespace Floxim\Floxim\System;

class Config
{
    private $config = array(
        'db.driver'                  => 'mysql',
        'db.prefix'                  => 'fx',
        'db.charset'                 => 'utf8',
        'session.start_auto'         => true,
        'dev.on'                     => false,
        'dev.css.minify'             => true,
        'dev.css.source_map'         => false,
        'dev.mysqldump_path'         => 'mysqldump',
        'CHARSET'                    => 'utf-8',
        'lang.admin'                 => 'en',
        'auth.login_field'           => 'email',
        'auth.remember_ttl'          => 604800, // 86400 * 7
        'FILECHMOD'                  => 0644,
        'DIRCHMOD'                   => 0755,
        'HTTP_ROOT_PATH'             => '/floxim/',
        'HTTP_FILES_PATH'            => '/floxim_files/',
        'SESSION_KEY'                => '_fx_cms_',
        'HTTP_MODULE_PATH'           => '',
        'DOCUMENT_ROOT'              => '',
        'HTTP_HOST'                  => '',
        'FLOXIM_FOLDER'              => '',
        'ADMIN_PATH'                 => '',
        'ADMIN_TEMPLATE'             => '',
        'SYSTEM_FOLDER'              => '',
        'ROOT_FOLDER'                => '',
        'FILES_FOLDER'               => '',
        'DUMP_FOLDER'                => '',
        'INCLUDE_FOLDER'             => '',
        'TMP_FOLDER'                 => '',
        'MODULE_FOLDER'              => '',
        'ADMIN_FOLDER'               => '',
        'COMPONENT_FOLDER'           => '',
        'WIDGET_FOLDER'              => '',
        'fx.update_url'              => 'http://floxim.org/getfloxim/update/',
        'FLOXIM_SITE_PROTOCOL'       => 'http',
        'FLOXIM_SITE_HOST'           => 'floxim.org',
        'templates.ttl'              => 0,
        'templates.cache'            => true,
        'templates.check_php_syntax' => 1,
        'templates.context_class'    => 'ContextFlex',
        'cache.gzip_bundles'         => true,
        'cache.meta'                 => true,
        'date.timezone'              => 'Europe/Moscow',
        'image.max_filesize'         => 10485760, // ~10m,
        'path.admin_dir_name'        => 'floxim'
    );

    public function __construct()
    {
        // error_reporting(E_ALL & ~(E_NOTICE | E_STRICT));

        ini_set("session.auto_start", "0");
        ini_set("session.use_trans_sid", "0");
        ini_set("session.use_cookies", "1");
        ini_set("session.use_only_cookies", "1");
        ini_set("url_rewriter.tags", "");
        ini_set("session.gc_probability", "1");
        ini_set("session.gc_maxlifetime", "1800");
        ini_set("session.hash_bits_per_character", "5");
        //ini_set("mbstring.internal_encoding", "UTF-8");
        mb_internal_encoding('UTF-8');
        ini_set('default_charset', 'UTF-8');
        ini_set("session.name", ini_get("session.hash_bits_per_character") >= 5 ? "sid" : "ced");
        
        if (!defined('CMS_ROOT')) {
            define('CMS_ROOT', DOCUMENT_ROOT);
        }

        fx::path()->register('root', CMS_ROOT);
        fx::path()->register('home', CMS_ROOT);
        
        define('FX_BASE_URL', preg_replace("~/$~", '', fx::path()->http('@home')));
        
        $floxim_http_path = null;
        //  try to get correct case of floxim path
        if (preg_match("~vendor/floxim/floxim/~i", __FILE__, $floxim_http_path)) {
            $floxim_http_path = $floxim_http_path[0];
        } else {
            // lower case by default
            $floxim_http_path = 'vendor/floxim/floxim/';
        }
        
        fx::path()->register('floxim', '@home/'.$floxim_http_path);
        fx::path()->register('floxim_js', '@floxim/lib/js');
        fx::path()->register('module', '@home/module/');
        fx::path()->register('files', '@home/floxim_files/');
        fx::path()->register('theme', '@home/theme/');
        fx::path()->register('log', fx::path('@files/log'));
        fx::path()->register('thumbs', fx::path('@files/fx_thumbs'));
        fx::path()->register('content_files', fx::path('@files/content'));
        fx::path()->register('upload', '@files/upload');
        
        
        $this->config['lang.cache_dir'] = '@files/php_dictionaries';
        $this->config['less.cache_dir'] = '@files/less_cache';
        
        $this->config['asset.cache_dir'] = '@files/asset_cache';
        
        $this->config['path.admin'] = fx::path()->http('@home/'.$this->config['path.admin_dir_name'].'/');
        
        

        $this->config['path.jquery'] = fx::path('@floxim/lib/js/jquery-1.11.3.min.js');
        $this->config['path.jquery.http'] = fx::path()->http($this->config['path.jquery']);
        
        $this->config['path.jquery-ui'] = fx::path('@floxim/lib/js/jquery-ui.min.js');
        
        $this->config['templates.cache_dir'] = fx::path('@files/compiled_templates');
        
        $this->config['controller.cache_dir'] = fx::path('@files/cache');

        $this->config['console.commands'] = array(
            'module'    => '\\Floxim\\Floxim\\Console\\Command\\Module',
            'component' => '\\Floxim\\Floxim\\Console\\Command\\Component',
            'widget'    => '\\Floxim\\Floxim\\Console\\Command\\Widget'
        );

        $this->config['cache.data.default_storage'] = 'array';
        $this->config['cache.data.default_prefix'] = 'floxim_';
        $this->config['cache.data.storages'] = array(
            'array' => array(
                'class' => '\\Floxim\\Cache\\Storage\\ArrayKeys',
            ),
            'file'  => array(
                'class'     => '\\Floxim\\Cache\\Storage\\File',
                'cachePath' => fx::path('@files/cache/data'),
            ),
            'meta'  => array(
                'class'     => '\\Floxim\\Cache\\Storage\\File',
                'cachePath' => fx::path('@files/cache/meta'),
            ),
        );
    }

    public function load(array $config = array())
    {
        static $loaded = false;
        if (isset($config['disable'])) {
            $config['disable'] = $this->prepareDisableConfig($config['disable']);
        }
        
        if (isset($config['path.alias'])) {
            foreach ($config['path.alias'] as $alias => $value) {
                fx::path()->register($alias, $value);
            }
        }
        
        if (isset($config['path.http'])) {
            $resolvers = (array) $config['path.http'];
            foreach ($resolvers as $prefix => $resolver) {
                fx::path()->registerHttpResolver($prefix, $resolver);
            }
        }
        
        if (isset($config['path.abs'])) {
            $resolvers = (array) $config['path.abs'];
            foreach ($resolvers as $prefix => $resolver) {
                fx::path()->registerAbsResolver($prefix, $resolver);
            }
        }
        
        //$this->config = array_merge($this->config, $config);
        $this->config = fx::util()->fullMerge($this->config, $config);
        if (!$loaded) {
            if (!$this->config['dev.on'] && !defined("FX_ALLOW_DEBUG")) {
                define("FX_ALLOW_DEBUG", false);
            }
            if (!isset($this->config['db.dsn'])) {
                switch ($this->config['db.driver']) {
                    case 'mysql':
                        $this->config['db.dsn'] = 'mysql:dbname=' . $this->config['db.name'] . ';host=' . $this->config['db.host'];
                        break;
                    case 'sqlite':
                        $this->config['db.dsn'] = 'sqlite:' . $this->config['db.file'];
                        break;
                }
            }
            define('FX_JQUERY_PATH', $this->config['path.jquery']);
            define('FX_JQUERY_PATH_HTTP', $this->config['path.jquery.http']);
            define('FX_JQUERY_UI_PATH', $this->config['path.jquery-ui']);
        }
        
        ini_set('date.timezone', $this->config['date.timezone']);
        fx::template()->registerSource('admin', fx::path('@floxim/Admin/templates'));
        
        $loaded = true;
        return $this;
    }

    protected function prepareDisableConfig($cfg)
    {
        $linear = fx::util()->arrayLinear($cfg);
        $res = array();
        foreach ($linear as $com) {
            if (strstr($com, ':')) {
                list($com, $act) = explode(":", $com);
                if (!isset($res[$com])) {
                    $res[$com] = array();
                }
                $res[$com][]= $act;
            } else {
                $res[]= $com;
            }
        }
        return $res;
    }
    
    public function isBlockDisabled($component, $action = null)
    {
        $disabled = $this->config['disable'];
        if (!$disabled || !is_array($disabled)) {
            return false;
        }
        if (in_array($component, $disabled)) {
            return true;
        }
        if (!isset($disabled[$component])) {
            return false;
        }
        $actions = $disabled[$component];
        if (is_array($actions) && in_array($action, $actions)) {
            return true;
        }
        return false;
    }
    
    public function toArray()
    {
        return $this->config;
    }

    public function set($k, $v)
    {
        $this->config[$k] = $v;
    }

    public function get($k)
    {
        return isset($this->config[$k]) ? $this->config[$k] : null;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name)
    {
        return isset($this->config[$name]);
    }

    /**
     * Load options from DB
     */
    public function loadFromDb()
    {
        try {
            $options = fx::data('option')->all();
            foreach ($options as $option) {
                $this->set($option['keyword'], $option['value']);
            }
        } catch (\Exception $e) {
            fx::log('Error while loading options: ', $e);
        }
    }

    /**
     * Store params by keys in DB
     *
     * @param $keys
     */
    public function store($keys)
    {
        if (!is_array($keys)) {
            $keys = array($keys);
        }
        foreach ($keys as $key) {
            if (!($option = fx::data('option')->where('keyword', $key)->one())) {
                $option = fx::data('option')->create(array('keyword' => $key));
            }
            $option['value'] = $this->get($key);
            $option->save();
        }
    }
}