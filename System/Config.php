<?php

namespace Floxim\Floxim\System;

class Config
{
    private $config = array(
        'db.prefix'                  => 'fx',
        'db.charset'                 => 'utf8',
        'dev.on'                     => false,
        'CHARSET'                    => 'utf-8',
        'lang.admin'                 => 'en',
        'auth.login_field'           => 'email',
        'auth.remember_ttl'          => 604800, // 86400 * 7
        'FILECHMOD'                  => 0644,
        'DIRCHMOD'                   => 0755,
        'HTTP_ROOT_PATH'             => '/floxim/',
        'HTTP_FILES_PATH'            => '/floxim_files/',
        'HTTP_LAYOUT_PATH'           => '/layout/',
        'SESSION_KEY'                => '_fx_cms_',
        'HTTP_MODULE_PATH'           => '',
        'HTTP_ACTION_LINK'           => '',
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
        'cache.gzip_bundles'         => true,
        'cache.meta'                 => true,
        'date.timezone'              => 'America/New_York'
    );

    public function __construct()
    {
        error_reporting(E_ALL & ~(E_NOTICE | E_STRICT));

        ini_set("session.auto_start", "0");
        ini_set("session.use_trans_sid", "0");
        ini_set("session.use_cookies", "1");
        ini_set("session.use_only_cookies", "1");
        ini_set("url_rewriter.tags", "");
        ini_set("session.gc_probability", "1");
        ini_set("session.gc_maxlifetime", "1800");
        ini_set("session.hash_bits_per_character", "5");
        ini_set("mbstring.internal_encoding", "UTF-8");
        ini_set("session.name", ini_get("session.hash_bits_per_character") >= 5 ? "sid" : "ced");


        fx::path()->register('root', DOCUMENT_ROOT);
        fx::path()->register('home', DOCUMENT_ROOT);
        
        $floxim_http_path = preg_replace("~System/Config.php$~", '', fx::path()->http(__FILE__));
        fx::path()->register('floxim', $floxim_http_path);
        fx::path()->register('module', '/module/');
        fx::path()->register('files', '/floxim_files/');
        fx::path()->register('log', fx::path('@files/log'));
        fx::path()->register('thumbs', fx::path('@files/fx_thumbs'));
        fx::path()->register('content_files', fx::path('@files/content'));

        $this->config['path.jquery'] = fx::path('@floxim/lib/js/jquery-1.9.1.min.js');
        $this->config['path.jquery.http'] = fx::path()->http('@floxim/lib/js/jquery-1.9.1.min.js');
        $this->config['path.jquery-ui'] = fx::path('@floxim/lib/js/jquery-ui-1.10.3.custom.min.js');

        $this->config['templates.cache_dir'] = fx::path('@files/compiled_templates');
        $this->config['HTTP_ACTION_LINK'] = '/floxim/'; //fx::path()->http('@floxim/index.php');

        $this->config['console.commands'] = array(
            'module'    => '\\Floxim\\Floxim\\Console\\Command\\Module',
            'component' => '\\Floxim\\Floxim\\Console\\Command\\Component',
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
        define("FX_ALLOW_DEBUG", true);
        if (isset($config['disable'])) {
            $config['disable'] = $this->prepareDisableConfig($config['disable']);
        }
        $this->config = array_merge($this->config, $config);
        if (!$loaded) {
            if (!$this->config['dev.on'] && !defined("FX_ALLOW_DEBUG")) {
                define("FX_ALLOW_DEBUG", false);
            }
            if (!isset($this->config['db.dsn'])) {
                $this->config['db.dsn'] = 'mysql:dbname=' . $this->config['db.name'] . ';host=' . $this->config['db.host'];
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