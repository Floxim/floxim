<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Template;

/**
 * Base class for all controllers
 * The constructor accepts parameters and action
 * Development - through the process()method
 */
class Controller
{

    protected $input = array();
    protected $action = null;

    public function getAction()
    {
        return $this->action;
    }

    /**
     * Designer controllers. It is better to use fx::controller('controller.action', $params).
     * @param array $input = 'array ('controller options
     * @param string $action = 'null', the name of action
     */
    public function __construct($input = array(), $action = null)
    {
        $this->setInput($input);
        $this->setAction($action);
    }

    /**
     * Get one of the parameters by name
     * @param string $name
     * @param mixed $default
     */
    public function getParam($name, $default = null)
    {
        if (!$this->forced_params_loaded) {
            $this->applyForcedParams();
        }
        return isset($this->input[$name]) ? $this->input[$name] : $default;
    }

    public function setParam($name, $value)
    {
        $this->input[$name] = $value;
    }

    public function setInput($input)
    {
        if (!$input) {
            $input = array();
        }
        $this->input = array_merge($this->input, $input);
        return $this;
    }

    public function defaultAction()
    {
        return array();
    }

    public function setAction($action)
    {
        if (is_null($action)) {
            return $this;
        }

        $this->action = fx::util()->underscoreToCamel($action, false);
        return $this;
    }

    public function afterSave()
    {

    }

    /**
     * Returns the action controller
     * @return array|string array with the results of a controller
     * $input = null, $action = null, $do_return = false
     */
    public function process()
    {
        $this->applyForcedParams();
        $this->trigger('before_action_run');
        $action = $this->getActionMethod();
        return $this->$action($this->input);
    }

    protected static $cfg_time = 0;
    protected $forced_params_loaded = false;
    protected function applyForcedParams()
    {
        if ($this->forced_params_loaded) {
            return;
        }
        $this->forced_params_loaded = true;
        
        $sig = str_replace(":", '__', $this->getSignature());
        $cache_file = fx::path('@files/cache/ctr_defaults_' . $sig . '.php');

        if (!fx::path()->exists($cache_file)) {
            $action = fx::util()->camelToUnderscore($this->action);
            $forced = array();
            $cfg = $this->getConfig();
            if (isset($cfg['actions'][$action]['force'])) {
                $forced = $cfg['actions'][$action]['force'];
            }
            fx::files()->writefile($cache_file, "<?php return " . var_export($forced, true) . ";");
        } else {
            $forced = include $cache_file;
        }
        foreach ($forced as $param => $value) {
            $this->setParam($param, $value);
        }
    }

    protected $_action_prefix = '';


    static protected function getAbbr($name)
    {
        $vowels = array('a', 'e', 'i', 'o', 'u', 'y');
        $head = mb_substr($name, 0, 1);
        $words = explode(" ", $name);
        if (count($words) > 1) {
            $tail = mb_substr($name, 1, 1) . '.' . mb_substr($words[1], 0, 1);
        } else {
            $tail = mb_substr(str_replace($vowels, '', mb_strtolower(mb_substr($name, 1))), 0, 2);
            if (mb_strlen($name) > 2 && mb_strlen($tail) < 2) {
                $tail = mb_substr($name, 1, 2);
            }
        }
        return $head . $tail;
    }

    public function getActionMethod()
    {
        $actions = explode('_', $this->action);
        while ($actions) {
            $action = $this->_action_prefix . implode('_', $actions);
            $action = fx::util()->underscoreToCamel($action, false);
            array_pop($actions);
            if (is_callable(array($this, $action))) {
                return $action;
            }
        }
        return 'default_action';
    }

    // controller_name.action_name
    public function getSignature()
    {
        $com = fx::getComponentNameByClass(get_class($this));
        return $com . ':' . fx::util()->camelToUnderscore($this->action);
    }


    public function findTemplate()
    {
        return fx::template($this->getSignature());
    }

    /*
     * Returns an array with options controller that you can use to find the template
     * Default - only controller itself,
     * For components overridden by adding inheritance chain
     */
    protected function getControllerVariants()
    {
        // todo: psr0 need verify
        // \Floxim\User\User\Controller
        // \Vendor\Module\Component\Controller
        return array(fx::getComponentNameByClass(get_class($this)));
    }

    /*
     * Returns an array of templates that can be used for controller-action games
     * Call after the controller is initialized (action)
     * @todo move it to Template\Suitable
     */
    public function getAvailableTemplates($theme_id = null, $area_meta = null)
    {
        $area_size = Template\Suitable::getSize($area_meta['size']);
        /*
        $layout_defined = !is_null($layout_name);
        if (is_numeric($layout_name)) {
            fx::log($layout_name, debug_backtrace());
            $layout_names = array(fx::data('theme', $layout_name)->get('layout'));
        } elseif (is_null($layout_name)) {
            $layout_names = fx::data('theme')->all()->getValues('layout');
        } elseif (is_string($layout_name)) {
            $layout_names = array($layout_name);
        } elseif (is_array($layout_name)) {
            $layout_names = $layout_name;
        }
        */
        
        $theme = is_null($theme_id) ? fx::env('theme') : fx::data('theme', $theme_id);
        $layout_names = array($theme['layout']);
        
        // get acceptable controller
        $controller_variants = $this->getControllerVariants();
        foreach ($controller_variants as $controller_variant) {
            fx::template()->import($controller_variant);
        }
        
        // this will be used to restrict allowed templates by config 'ignore' directive
        $theme_template = null;
        $layout_classes = array();
        foreach ($layout_names as $layout_name) {
            $layout_classes []= fx::template()->import('theme.'.$layout_name);
        }
        if (count($layout_names) === 1) {
            $theme_template = fx::template('theme.'.end($layout_names));
        }
        $imported_classes = fx::template()->getImportedClasses();
        $template_variants = array();
        foreach ($imported_classes as $class) {
            if (!$class) {
                continue;
            }
            // ignore templates from layouts not listed in arg
            if (preg_match("~^fx_template_theme~", $class) && !in_array($class, $layout_classes)) {
                continue;
            }
            $template_variants = array_merge(
                $template_variants,
                call_user_func(array($class, 'getTemplateVariants'))
            );
        }
        
        // now - filtered
        $result = array();
        $replace = array();
        foreach ($template_variants as $k => $tplv) {
            if (isset($tplv['is_abstract'])) {
                continue;
            }
            if (!isset($tplv['of']) || !is_array($tplv['of'])) {
                continue;
            }
            foreach ($tplv['of'] as $tpl_of => $tpl_of_priority) {
                $of_parts = explode(":", $tpl_of);
                if (count($of_parts) != 2) {
                    continue;
                }
                list($tpl_of_controller, $tpl_of_action) = $of_parts;

                $tpl_of_action = fx::util()->underscoreToCamel($tpl_of_action, false);
                if (!in_array($tpl_of_controller, $controller_variants)) {
                    continue;
                }

                // the first controller variant is the most precious
                if (strpos($this->action, $tpl_of_action) !== 0) {
                    continue;
                }

                // if template action exactly matches current controller action
                $tplv['action_match_rate'] = $this->action == $tpl_of_action ? 1 : 0;

                if (isset($tplv['suit']) && $area_meta) {
                    $tplv_areas = explode(",", preg_replace("~\s+~", '', $tplv['suit']));
                    if (in_array('local', $tplv_areas)) {
                        $tplv_areas [] = $tplv['area'];
                    }
                    if (!in_array($area_meta['id'], $tplv_areas)) {
                        continue;
                    }
                }
                // if current layout is defined, we should rate layout templates greater than standard ones
                $tplv['layout_match_rate'] = $layout_defined && preg_match("~^theme\.~", $tplv['full_id']) ? 1 : 0;

                if ($area_size && isset($tplv['size'])) {
                    $size = Template\Suitable::getSize($tplv['size']);
                    $size_rate = Template\Suitable::checkSizes($size, $area_size);
                    if (!$size_rate) {
                        continue;
                    }
                    $tplv['size_rate'] = $size_rate;
                }
                if ($theme_template && !$theme_template->isTemplateAllowed($tplv['full_id'])) {
                    continue;
                }
                if (!isset($tplv['of_priority']) || $tplv['of_priority'] < $tpl_of_priority) {
                    $tplv['of_priority'] = $tpl_of_priority;
                }
                $result [$tplv['full_id']] = $tplv;
                if ($tplv['is_preset_of'] && $tplv['replace_original']) {
                    $replace []= $tplv['is_preset_of'];
                }
            }
        }
        foreach ($replace as $rep_id ) {
            unset($result[$rep_id]);
        }
        usort($result, function ($a, $b) {
            $action_diff = $b['action_match_rate'] - $a['action_match_rate'];
            if ($action_diff != 0) {
                return $action_diff;
            }
            $prior_diff = $b['of_priority'] - $a['of_priority'];
            if ($prior_diff !== 0) {
                return $prior_diff;
            }
            $layout_diff = $b['layout_match_rate'] - $a['layout_match_rate'];
            if ($layout_diff != 0) {
                return $layout_diff;
            }
            return 0;
        });
        return $result;
    }

    public function postprocess($html)
    {
        return $html;
    }

    public function render($template)
    {
        if (is_string($template)) {
            $template = fx::template($template);
        }
        $res = $this->process();
        
        $output = $template->render($res);
        $output = $this->postprocess($output);
        return $output;
    }

    public function getActionSettings($action)
    {
        $cfg = $this->getConfig();
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
            if ($value instanceof \Closure) {
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

    public function getConfig($searched_action = null)
    {
        if ($searched_action === true) {
            $searched_action = fx::util()->camelToUnderscore($this->action);
        }
        if (!is_null($this->_config_cache)) {
            return $searched_action ? $this->_config_cache['actions'][$searched_action] : $this->_config_cache;
        }
        $sources = $this->getConfigSources();
        $actions = $this->getRealActions();
        $blocks = array();
        $meta = array();
        $my_name = $this->getControllerName();
        
        
        foreach ($sources as $src) {
            $src_hash = md5($src);
            $is_own = $my_name && fx::getComponentFullNameByPath(fx::path()->http($src)) === $my_name;
            $src = include $src;
            if (!isset($src['actions'])) {
                continue;
            }
            $src_actions = $this->prepareActionConfig($src['actions']);
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
                    $blocks [] = $props;
                    $meta [] = array($inherit_horizontal, $action_code);
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
        foreach ($actions as $action => &$action_props) {
            $action_name = fx::util()->underscoreToCamel($action);
            
            $settings_method = 'settings'.$action_name;
            if (method_exists($this, $settings_method)) {
                $action_props['settings'] = call_user_func(
                    array($this, $settings_method), 
                    isset($action_props['settings']) ? $action_props['settings'] : array()
                );
            }
            
            $config_method = 'config'.$action_name;
            if (method_exists($this, $config_method)) {
                $action_props = call_user_func(
                    array($this, $config_method),
                    $action_props
                );
            }
        }
        unset($actions['.']);
        $this->_config_cache = array('actions' => $actions);
        return $searched_action ? $actions[$searched_action] : $this->_config_cache;
    }

    public function getControllerName()
    {
        return fx::getComponentNameByClass(get_class($this));
    }

    protected function prepareActionConfig($actions)
    {
        foreach ($actions as &$params) {
            if (!isset($params['defaults'])) {
                continue;
            }
            foreach ($params['defaults'] as $key => $value) {
                if (preg_match('~^!~', $key) !== 0) {
                    $params['force'][substr($key, 1)] = $value;
                    $params['defaults'][substr($key, 1)] = $value;
                    unset($params['defaults'][$key]);
                }
            }
        }
        return $actions;
    }

    protected function getConfigSources()
    {
        return array();
    }

    protected static function mergeActions($actions)
    {
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


    public function getRealActions()
    {
        $class = new \ReflectionClass(get_class($this));
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        $props = $class->getDefaultProperties();
        $prefix = isset($props['_action_prefix']) ? $props['_action_prefix'] : '';
        $prefix = fx::util()->underscoreToCamel($prefix, false);
        $actions = array();
        foreach ($methods as $method) {
            $action_name = null;
            if (preg_match("~^" . $prefix . "(.+)$~", $method->name, $action_name)) {
                $action_name = $action_name[1];
                $action_name = fx::util()->camelToUnderscore($action_name);
                $actions[$action_name] = array();
            }
        }
        return $actions;
    }

    public function listen($event, $callback)
    {
        $ctr = $this;
        fx::listen($event, function($e) use ($ctr, $callback) {
            if ($e['controller'] === $ctr) {
                return call_user_func($callback, $e);
            }
        });
    }

    public function __call($name, $arguments)
    {
        if (!preg_match("~^on([A-Z].+$)~", $name, $e)) {
            return null;
        }
        $event_name = $e[1];
        $event_name = fx::util()->camelToUnderscore($event_name);
        $this->listen($event_name, $arguments[0]);
    }


    public function trigger($event, $params = array())
    {
        if (is_string($event)) {
            $event = new \Floxim\Floxim\System\Event($event, $params);
        }
        $event['controller'] = $this;
        $sig = explode(":", $this->getSignature());
        $event['controller_name'] = $sig[0];
        $event['action_name'] = $sig[1];
        $event_res = fx::trigger($event);
        return $event_res;
    }

    public function getActions()
    {
        $cfg = $this->getConfig();
        $res = array();
        foreach ($cfg['actions'] as $action => $info) {
            if (isset($info['disabled']) && $info['disabled']) {
                continue;
            }
            $res[$action] = $info;
        }
        return $res;
    }

    public function handleInfoblock($callback, $infoblock, $params = array())
    {

        $full_config = $this->getConfig();
        $action = fx::util()->camelToUnderscore($this->action);
        if (!isset($full_config['actions'][$action])) {
            return;
        }
        $config = $full_config['actions'][$action];
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