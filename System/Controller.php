<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Template;

/**
 * Base class for all controllers
 * The constructor accepts parameters and action
 * Development - through the process()method
 */
class Controller {
    
    protected $input = array();
    protected $action = null;
    
    public function get_action() {
        return $this->action;
    }
    
    /**
     * Designer controllers. It is better to use fx::controller('controller.action', $params).
     * @param array $input = 'array ('controller options
     * @param string $action = 'null', the name of action
     */
    public function __construct($input = array(), $action = null) {
    	$this->set_input($input);
    	$this->set_action($action);
    }
    
    /**
     * Get one of the parameters by name
     * @param string $name
     * @param mixed $default
     */
    public function get_param($name, $default = null) {
        return isset($this->input[$name]) ? $this->input[$name] : $default;
    }
    
    public function set_param($name, $value) {
        $this->input[$name] = $value;
    }

    public function set_input($input) {
        if (!$input) {
            $input = array();
        }
        $this->input = array_merge($this->input, $input);
        return $this;
    }
    
    public function default_action() {
        return array();
    }
    
    public function set_action($action) {
        if (is_null($action)) {
            return $this;
        }
    	
    	$this->action = $action;
    	return $this;
    }

    public function after_save () {
        
    }

    /**
     * Returns the action controller
     * @return array|string array with the results of a controller
     * $input = null, $action = null, $do_return = false
     */
    public function process() {
        $this->_apply_forced_params();
        $this->trigger('before_action_run');
        $action = $this->get_action_method();
        return $this->$action($this->input);
    }
    
    protected static $cfg_time = 0;
    
    protected function _apply_forced_params() {
        /*
        static $time = 0;
        $start = microtime(true);
        */
        $cache_file = fx::path('files', 'cache/ctr_defaults_'.$this->get_signature().'.php');
        if (!fx::path()->exists($cache_file)) {
            $forced = array();
            $cfg = $this->get_config();
            if (isset($cfg['actions'][$this->action]['force'])) {
                $forced = $cfg['actions'][$this->action]['force'];
            }
            fx::files()->writefile($cache_file, "<?php return ".var_export($forced, true).";");
        } else {
            $forced = include $cache_file;
        }
        foreach ($forced as $param => $value) {
            $this->set_param($param, $value);
        }
    }
    
    protected $_action_prefix = '';


    static protected function _get_abbr($name) {
        $vowels = array('a', 'e', 'i', 'o', 'u', 'y');
        $head = mb_substr($name,0,1);
        $words = explode(" ", $name);
        if (count($words) > 1) {
            $tail = mb_substr($name, 1, 1).'.'.mb_substr($words[1], 0, 1);
        } else {
            $tail = mb_substr(str_replace($vowels, '', mb_strtolower(mb_substr($name,1))), 0, 2);
            if (mb_strlen($name) > 2 && mb_strlen($tail) < 2) {
                $tail = mb_substr($name, 1, 2);
            }
        }
        return $head.$tail;
    }

    public function get_action_method() {
        $actions = explode('_', $this->action);
        while($actions){
            $action = $this->_action_prefix.implode('_', $actions);
            array_pop($actions);
            if (is_callable(array($this, $action))) {
                return $action;
            }
        }
        return  'default_action';
    }
    
    // controller_name.action_name
    public function get_signature() {
        // todo: psr0 need fix
        return str_replace('fx_controller_', '', get_class($this)).'.'.$this->action;
    }


    public function find_template() {
        return fx::template($this->get_signature());
    }
    
    /*
     * Returns an array with options controller that you can use to find the template
     * Default - only controller itself,
     * For components overridden by adding inheritance chain
     */
    protected function _get_controller_variants() {
        // todo: psr0 need verify
        // \Floxim\Main\User\Controller
        // \Vendor\Module\Component\Controller
        return array(fx::getComponentNameByClass(get_class($this)));
    }
    
    /*
     * Returns an array of templates that can be used for controller-action games
     * Call after the controller is initialized (action)
     */
    public function get_available_templates( $layout_name = null , $area_meta = null) {
        $area_size = Template\Suitable::get_size($area_meta['size']);
        $layout_defined = !is_null($layout_name);
        if (is_numeric($layout_name)) {
            $layout_names = array(fx::data('layout', $layout_name)->get('keyword'));
        } elseif (is_null($layout_name)) {
            $layout_names = fx::data('layout')->all()->get_values('keyword');
        } elseif (is_string($layout_name)) {
            $layout_names = array($layout_name);
        } elseif (is_array($layout_name)) {
            $layout_names = $layout_name;
        }
        // get acceptable controller
        $controller_variants = $this->_get_controller_variants();
        $template_variants = array();
        // first we take out all the variants of layout templates
        foreach ($layout_names as $layout_name) {
            if (($layout_tpl = fx::template('layout_'.$layout_name)) ) {
                $template_variants = array_merge(
                    $template_variants, 
                    $layout_tpl->get_template_variants()
                );
            }
        }
        
        // now - all the variants of templates from template from the controller
        foreach ($controller_variants as $controller_variant) {
            if (($controller_template = fx::template($controller_variant))) {
                $template_variants = array_merge(
                    $template_variants, 
                    $controller_template->get_template_variants()
                );
            }
        }
        // now - filtered
        $result = array();
        
        foreach ($template_variants as $k => $tplv) {
            foreach (explode(",", $tplv['of']) as $tpl_of) {
                
                $of_parts = explode(".", $tpl_of);
                if (count($of_parts) != 2) {
                    continue;
                }
                list($tpl_of_controller, $tpl_of_action) = $of_parts;
                
                $controller_match_rate = array_keys($controller_variants, $tpl_of_controller);
                if (!isset($controller_match_rate[0])) {
                    continue;
                }
                
                // the first controller variant is the most precious
                $tplv['controller_match_rate'] = $controller_match_rate[0]*-1;
                if (strpos($this->action, $tpl_of_action) !== 0) {
                    continue;
                }
                
                // if template action exactly matches current controller action
                $tplv['action_match_rate'] = $this->action == $tpl_of_action ? 1 : 0;
                
                if ($tplv['suit'] && $tplv['suit'] == 'local') {
                    if ($tplv['area'] != $area_meta['id']) {
                        continue;
                    }
                }
                // if current layout is defined, we should rate layout templates greater than standard ones
                $tplv['layout_match_rate'] = $layout_defined && preg_match("~^layout_~", $tplv['full_id']) ? 1 : 0;
                
                if ($area_size && isset($tplv['size'])) {
                    $size = Template\Suitable::get_size($tplv['size']);
                    $size_rate = Template\Suitable::check_sizes($size, $area_size);
                    if (!$size_rate) {
                        continue;
                    }
                    $tplv['size_rate'] = $size_rate;
                }
                $result []= $tplv;
            }
        }
        usort($result, function($a, $b) {
            $controller_diff = $b['controller_match_rate'] - $a['controller_match_rate'];
            if ($controller_diff != 0) {
                return $controller_diff;
            }
            $action_diff = $b['action_match_rate'] - $a['action_match_rate'];
            if ($action_diff != 0) {
                return $action_diff;
            }
            $layout_diff = $b['layout_match_rate'] - $a['layout_match_rate'];
            if ($layout_diff != 0) {
                return $layout_diff;
            }
            return 0;
        });
        return $result;
    }

    public function postprocess($html) {
        return $html;
    }
    
    public function render($template) {
        if (is_string($template)) {
            $template = fx::template($template);
        }
        $res = $this->process();
        $output = $template->render($res);
        $output = $this->postprocess($output);
        return $output;
    }
    
    public function get_action_settings($action) {
        $cfg = $this->get_config();
        if (!isset($cfg['actions'][$action])) {
            return;
        }
        $params = $cfg['actions'][$action];
        // We definitely want to return Null?
        if (!isset($params['settings'])) {
            return;
        }
        $settings = $params['settings'];
        foreach ($settings as $prop => $value) {
            if ($value instanceof Closure) {
                $settings[$prop] = call_user_func($value, $this);
            }
        }
        
        if (isset($params['defaults']) && is_array($params['defaults'])) {
            foreach ($params['defaults'] as $param => $val) {
                $settings[$param]['value'] = $val;
            }
        }
        if (!isset($params['force'])) {
            return $settings;
        }
        foreach (array_keys($params['force']) as $forced_key) {
            unset($settings[$forced_key]);
        }
        return $settings;
    }
    
    protected $_config_cache = null;
    public function get_config($searched_action = null) {
        if ($searched_action === true) {
            $searched_action = $this->action;
        }
        if (!is_null($this->_config_cache)) {
            return $searched_action ? $this->_config_cache['actions'][$searched_action] : $this->_config_cache;
        }
        $sources = $this->_get_config_sources();
        $actions = $this->_get_real_actions();
        $blocks = array();
        $meta = array();
        $my_name = $this->get_controller_name();
        foreach ($sources as $src) {
            $src_name = null;
            $src_hash = md5($src);
            $src_abs = fx::path()->to_http($src);
            preg_match("~/([^/]+?)/[^/]+$~", $src_abs, $src_name);
            $is_own = $src_name && $my_name && $src_name[1] === $my_name;
            $src = include $src;
            if (!isset($src['actions'])) {
                continue;
            }
            $src_actions = $this->_prepare_action_config($src['actions']);
            foreach ($src_actions as $k => $props) {
                $action_codes = preg_split("~\s*,\s*~", $k);
                foreach ($action_codes as $ak) {
                    $inherit_vertical = preg_match("~^\*~", $ak);
                    // parent blocks without vertical inheritance does not use
                    if (!$is_own && !$inherit_vertical) {
                        continue;
                    }
                    $inherit_horizontal = preg_match("~\*$~", $ak);
                    $action_code = trim($ak, '*');
                    foreach (array('install', 'delete', 'save') as $cb_name) {
                        if (isset($props[$cb_name])) {
                            if (!is_array($props[$cb_name]) || is_callable($props[$cb_name])) {
                                $props[$cb_name] = array($src_hash => $props[$cb_name]);
                            }
                        }
                    }
                    $blocks []= $props;
                    $meta []= array($inherit_horizontal, $action_code);
                    if (!isset($actions[$action_code])) {
                        $actions[$action_code] = array();
                    }
                }
            }
        }
        foreach ($blocks as $bn => $block) {
            list($inherit, $bk) = $meta[$bn];
            foreach ($actions as $ak => &$action_props) {
                if (
                        $ak === $bk || 
                        (
                            $inherit && 
                            ($bk === '.' || substr($ak, 0, strlen($bk)) === $bk)
                        )
                ) {
                    $action_props = array_replace_recursive($action_props, $block);
                    if (isset($action_props['settings'])) {
                        foreach ($action_props['settings'] as $s_key => $s) {
                            if (is_array($s) && !isset($s['name'])) {
                                $action_props['settings'][$s_key]['name'] = $s_key;
                            }
                        }
                    }
                }
            }
        }
        unset($actions['.']);
        $this->_config_cache = array('actions' => $actions);
        return $searched_action ? $actions[$searched_action] : $this->_config_cache;
    }

    public function get_controller_name($with_type = false){
        $name = preg_replace('~^[^\W_]+_[^\W_]+_~', '', get_class($this));
        if (!$with_type) {
            $name = preg_replace('~^[^\W_]+_~', '', $name);
        }
        return $name;
    }

    protected function _prepare_action_config($actions) {
        foreach ($actions as &$params) {
            if(!isset($params['defaults'])) {
                continue;
            }
            foreach ($params['defaults'] as $key => $value) {
                if (preg_match('~^!~', $key) !== 0) {
                    $params['force'][substr($key, 1)] =$value;
                    $params['defaults'][substr($key, 1)] =$value;
                    unset($params['defaults'][$key]);
                }
            }
        }
        return $actions;
    }
    
    protected function _get_config_sources() {
        return array();
    }
    
    protected static function _merge_actions($actions) {
        ksort($actions);
        $key_stack = array();
        foreach ($actions as $key => $params) {
            // do not inherit flag horizontally disabled
            $no_disabled = !isset($params['disabled']);
            
            foreach ($key_stack as $prev_key_index => $prev_key) {
                if (substr($key, 0, strlen($prev_key)) === $prev_key) {
                    $actions[$key] = array_replace_recursive(
                        $actions[$prev_key], $params
                    );
                    break;
                }
                unset($key_stack[$prev_key_index]);
            }
            array_unshift($key_stack, $key);
            if ($no_disabled) {
                unset($actions[$key]['disabled']);
            }
        }
        return $actions;
    }
    

    public function _get_real_actions() {
        $class = new \ReflectionClass(get_class($this));
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        $props = $class->getDefaultProperties();
        $prefix = isset($props['_action_prefix']) ? $props['_action_prefix'] : '';
        $actions = array();
        foreach ($methods as $method) {
            $action_name = null;
            if (preg_match("~^".$prefix."(.+)$~", $method->name, $action_name)) {
                $action_name = $action_name[1];
                $actions[$action_name]= array();
            }
        }
        return $actions;
    }
    
    
    protected $_bound = array();
    public function listen($event, $callback) {
        if (!isset($this->_bound[$event])) {
            $this->_bound[$event] = array();
        }
        $this->_bound[$event][]= $callback;
    }
    
    public function __call($name, $arguments) {
        if (!preg_match("~^on_(.+$)~", $name, $e)) {
            return null;
        }
        $this->listen($e[1], $arguments[0]);
    }


    public function trigger($event, $data = null) {
        if (isset($this->_bound[$event]) && is_array($this->_bound[$event])) {
            foreach ( $this->_bound[$event] as $cb) {
                call_user_func($cb, $data, $this);
            }
        }
    }
    
    public function get_actions() {
        $cfg = $this->get_config();
        $res = array();
        foreach ($cfg['actions'] as $action => $info) {
            if (isset($info['disabled']) && $info['disabled']) {
                continue;
            }
            $res[$action] = $info;
        }
        return $res;
    }
    
    public function handle_infoblock($callback, $infoblock, $params = array()) {
        
        $full_config = $this->get_config();
        if (!isset($full_config['actions'][$this->action])) {
            return;
        }
        $config = $full_config['actions'][$this->action];
        if (!isset($config[$callback])) {
            return;
        }
        foreach ($config[$callback] as $c_callback) {
            if (is_callable($c_callback)) {
                call_user_func($c_callback, $infoblock, $this, $params);
            }
        }
    }

}