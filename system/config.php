<?php
class fx_config {
    private $config = array(
        'SUB_FOLDER' => '',
        'DB_DSN' => '',
        'DB_USER' => '',
        'DB_PASSWORD' => '',
        'DB_PREFIX' => 'fx',
        'DB_CHARSET' => 'utf8',
        'CHARSET' => 'utf-8',
        
        'ADMIN_LANG' => 'en',
        
        'AUTHORIZE_BY' => 'email',
        'AUTHTIME' => 86400,
        'FILECHMOD' => 0644,
        'DIRCHMOD' => 0755,
        
        'HTTP_ROOT_PATH' => '/floxim/',
        'HTTP_FILES_PATH' => '/floxim_files/',
        'HTTP_LAYOUT_PATH' => '/layout/',

        'SESSION_KEY' => '_fx_cms_',

        'HTTP_MODULE_PATH' => '',
        'HTTP_ACTION_LINK' => '',

        'DOCUMENT_ROOT' => '',
        'HTTP_HOST' => '',
        'FLOXIM_FOLDER' => '',
        'ADMIN_PATH' => '',
        'ADMIN_TEMPLATE' => '',
        'SYSTEM_FOLDER' => '',
        'ROOT_FOLDER' => '',
        'FILES_FOLDER' => '',
        'DUMP_FOLDER' => '',
        'INCLUDE_FOLDER' => '',
        'TMP_FOLDER' => '',
        'MODULE_FOLDER' => '',
        'ADMIN_FOLDER' => '',
        'COMPONENT_FOLDER' => '',
        'WIDGET_FOLDER' => '',
        'FX_VERSION' => '0.1.1.1',
        'FLOXIM_SITE_PROTOCOL' => 'http',
        'FLOXIM_SITE_HOST' => 'floxim.org',
        'COMPILED_TEMPLATES_TTL' => 0,
        'IS_DEV_MODE' => false
    );

    public function __construct() {
        error_reporting(E_ALL & ~(E_NOTICE | E_STRICT));
        @date_default_timezone_set(@date_default_timezone_get());

        @ini_set("session.auto_start", "0");
        @ini_set("session.use_trans_sid", "0");
        @ini_set("session.use_cookies", "1");
        @ini_set("session.use_only_cookies", "1");
        @ini_set("url_rewriter.tags", "");
        @ini_set("session.gc_probability", "1");
        @ini_set("session.gc_maxlifetime", "1800");
        @ini_set("session.hash_bits_per_character", "5");
        @ini_set("mbstring.internal_encoding", "UTF-8");
        @ini_set("session.name", ini_get("session.hash_bits_per_character") >= 5 ? "sid" : "ced");
        
        
        fx::path()->register('root', DOCUMENT_ROOT);
        fx::path()->register('home', DOCUMENT_ROOT);
        
        fx::path()->register('floxim', '/floxim/');
        fx::path()->register('std', fx::path('floxim', '/std'));
        fx::path()->register('layouts', array('/layout', fx::path('std', '/layout')));
        fx::path()->register('files', '/floxim_files/');
        fx::path()->register('log', fx::path('files', '/log'));
        fx::path()->register('thumbs', fx::path('files', '/fx_thumbs'));
        fx::path()->register('content_files', fx::path('files', '/content'));
                
        
        $this->config['DOCUMENT_ROOT'] = DOCUMENT_ROOT;//rtrim(getenv("DOCUMENT_ROOT"), "/\\");
        $this->config['HTTP_HOST'] = getenv("HTTP_HOST");
        $this->config['FLOXIM_FOLDER'] = $this->config['DOCUMENT_ROOT'] . $this->config['SUB_FOLDER'];

        $this->config['HTTP_MODULE_PATH'] = $this->config['HTTP_ROOT_PATH'] . 'modules/';
        $this->config['HTTP_ACTION_LINK'] = $this->config['HTTP_ROOT_PATH'] . 'index.php';

        $this->config['ADMIN_PATH'] = $this->config['SUB_FOLDER'].$this->config['HTTP_ROOT_PATH'].'admin/';
        $this->config['ADMIN_TEMPLATE'] = $this->config['ADMIN_PATH'].'skins/default/';

        $this->config['ROOT_FOLDER'] = $this->config['FLOXIM_FOLDER'].$this->config['HTTP_ROOT_PATH'];
        $this->config['SYSTEM_FOLDER'] = $this->config['ROOT_FOLDER'].'system/';
        $this->config['FILES_FOLDER'] = $this->config['FLOXIM_FOLDER'].$this->config['HTTP_FILES_PATH'];
        $this->config['WIDGET_FOLDER'] = $this->config['FLOXIM_FOLDER'].$this->config['HTTP_WIDGET_PATH'];
        $this->config['INCLUDE_FOLDER'] = $this->config['ROOT_FOLDER'].'lib/';
        $this->config['TMP_FOLDER'] = $this->config['ROOT_FOLDER'].'tmp/';
        $this->config['MODULE_FOLDER'] = $this->config['FLOXIM_FOLDER'].$this->config['HTTP_MODULE_PATH'];
        $this->config['ADMIN_FOLDER'] = $this->config['ROOT_FOLDER'].'admin/';
        $this->config['COMPILED_TEMPLATES_FOLDER'] = $this->config['FILES_FOLDER'].'compiled_templates';

    }

    public function load(array $config = array()) {
        $this->config = array_merge($this->config, $config);
        if (!$this->config['IS_DEV_MODE'] && !defined("FX_ALLOW_DEBUG")) {
            define("FX_ALLOW_DEBUG", false);
        }
        return $this;
    }

    public function to_array() {
        return $this->config;
    }
    
    public function set($k, $v) {
        $this->config[$k] = $v;
    }
    
    public function get($k) {
        return isset($this->config[$k]) ? $this->config[$k] : null;
    }

    public function __get($name) {
        return isset($this->config[$name]) ? $this->config[$name] : null;
    }

    public function __isset($name) {
        return isset($this->config[$name]);
    }
}