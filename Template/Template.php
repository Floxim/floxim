<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;
use \Symfony\Component\Yaml;

class Template
{

    public $action = null;
    protected $parent = null;
    protected $level = 0;
    protected $admin_disabled = false;
    protected $current_template_dir = null;
    
    public function getCurrentDir()
    {
        return $this->current_template_dir;
        //return fx::path()->http('@home/'.$this->current_template_dir);
    }
    
    public function getAllDirs()
    {
        $dirs = $this->parent ? $this->parent->getAllDirs() : array();
        array_unshift($dirs, $this->getCurrentDir());
        return $dirs;
    }
    
    public $context;

    public function __construct($action = null, $data = array())
    {
        
        //$context_class = "Floxim\\Floxim\\Template\\".fx::config('templates.context_class');
        if ($data instanceof Context) {
            $context = $data;
        } else {
            $context = new ContextFlex();
            if (count($data) > 0) {
                $context->push($data);
            }
        }
        $this->context = $context;
        $this->action = $action;
        //fx::trigger('initTemplate', array('template' => $this));
    }
    
    public function getContext()
    {
        return $this->context;
    }
    
    public static function bemParseStr($str) 
    {
        $str = trim($str);
        $str = preg_split("~\s+~", $str);
        $res = array(
            'name' => array_shift($str),
            'modifiers' => array(),
            'plain' => array()
        );
        foreach ($str as $c) {
            if ($c[0] === '.') {
                $res['plain'][] = trim($c, '.');
            } else {
                if (substr($c, -1, 1) !== '_') {
                    $res['modifiers'][]= $c;
                }
            }
        }
        return $res;
    }
    
    protected $bem_stack = array();
    
    protected $bem_blocks_with_container_props = array();
    
    protected function bemStartBlock($name, $container_props = false)
    {
        $this->bem_stack[]= $name;
        if ($container_props !== false) {
            $this->context->pushContainerProps($container_props);
            $this->bem_blocks_with_container_props [count($this->bem_stack) - 1]= true;
        }
        $classes = $this->context->getContainerClasses($container_props);
        echo $classes;
    }
    
    protected function bemStopBlock()
    {
        array_pop($this->bem_stack);
        
        $index = count($this->bem_stack);
        if (isset($this->bem_blocks_with_container_props [ $index ])) {
            $this->context->popContainerProps();
            unset($this->bem_blocks_with_container_props [ $index ]);
        }
    }
    
    public function bemGetBlock()
    {
        if (count($this->bem_stack) > 0) {
            return end($this->bem_stack);
        }
        if ($this->parent) {
            return $this->parent->bemGetBlock();
        }
        return '';
    }
    
    protected $template_param_handlers = array();
    
    public function pushTemplateParamHandler($handler) 
    {
        $this->template_param_handlers[]= $handler;
    }
    
    public function popTemplateParamHandler() 
    {
        array_pop($this->template_param_handlers);
    }
    
    protected $template_params = array();
    
    public function registerParam($name, $data)
    {
        if (count($this->template_param_handlers) > 0) {
            end($this->template_param_handlers)->registerParam($name, $data, $this->context);
            return;
        }
        if ($this->parent) {
            $this->parent->registerParam($name, $data);
            return;
        }
        if ($data['type'] === 'image' && isset($data['value']) && is_string($data['value']) && !empty($data['value'])) {
            $data['value'] = \Floxim\Floxim\Field\Image::prepareValue($data['value']);
        }
        if (isset($this->template_params[$name])) {
            $this->template_params[$name] = array_merge(
                $data,
                $this->template_params[$name]
            );
        } else {
            $this->template_params[$name] = $data;
        }
    }
    
    public function getRegisteredParams()
    {
        $res = array();
        foreach ($this->template_params as $pk => $pp) {
            if (!$pp['is_forced']) {
                //$res[$pk] = $pp;
                $res []= array($pk, $pp);
            }
        }
        return $res;
    }
    
    public function getIcon($what_for)
    {
        $theme = fx::env('theme_template');
        if ( !$theme) {
            return;
        }
        $dirs = $theme->getTemplateSourceDirs();
        if (!$dirs) {
            return;
        }
        $file = $what_for.'.png';
        foreach ($dirs as $dir) {
            $c_file = $dir.'/icons/'.$file;
            if (file_exists(fx::path($c_file))) {
                return $c_file;
            }
        }
    }
    
    public function getTemplateSourceDirs()
    {
        if (isset(static::$template_source_dirs)) {
            return static::$template_source_dirs;
        }
    }

    public function setParent($parent_template)
    {
        $this->parent = $parent_template;
        $this->level = $parent_template->getLevel() + 1;
        return $this;
    }

    public function isAdmin($set = null)
    {
        if ($set === null) {
            return !$this->admin_disabled && fx::isAdmin();
        }
        $this->admin_disabled = !$set;
        return $this;
    }

    public function getLevel()
    {
        return $this->level;
    }
    
    protected $mode_stack = array();

    public function pushMode($mode, $value)
    {
        if (!isset($this->mode_stack[$mode])) {
            $this->mode_stack[$mode] = array();
        }
        $this->mode_stack[$mode] [] = $value;
    }

    public function popMode($mode)
    {
        if (isset($this->mode_stack[$mode])) {
            array_pop($this->mode_stack[$mode]);
        }
    }

    public function getMode($mode)
    {
        if (isset($this->mode_stack[$mode])) {
            return end($this->mode_stack[$mode]);
        }
        if ($this->parent) {
            return $this->parent->getMode($mode);
        }
    }

    protected function printVar($val, $meta = null)
    {
        $tf = null;
        if ($meta && isset($meta['var_type'])) {
            $tf = new Field($val, $meta);
        }
        $res = $tf ? $tf : $val;
        return (string)$res;
    }

    public function getHelp()
    {
        //ini_set('memory_limit', '1G');
        return $this->context->getHelp();
    }
    
    protected function getVarMeta($var_name = null, $source = null)
    {
        return $this->context->getVarMeta($var_name, $source);
    }
    
    public function v($v = null, $l = null) {
        return $this->context->get($v, $l);
    }

    protected $is_wrapper = false;

    public function isWrapper($set = null)
    {
        if (func_num_args() === 0) {
            return $this->is_wrapper ? true : ($this->parent ? $this->parent->isWrapper() : false);
        }
        $this->is_wrapper = (bool)$set;
    }

    protected $context_stack = array();

    public static $v_count = 0;

    public static function beautifyHtml($html)
    {
        $level = 0;
        $html = preg_replace_callback(
            '~\s*?<(/?)([a-z0-9]+)[^>]*?(/?)>\s*?~',
            function ($matches) use (&$level) {
                $is_closing = $matches[1] == '/';
                $is_single = in_array(strtolower($matches[2]), array('img', 'br', 'link')) || $matches[3] == '/';

                if ($is_closing) {
                    $level = $level == 0 ? $level : $level - 1;
                }
                $tag = trim($matches[0]);
                $tag = "\n" . str_repeat(" ", $level * 4) . $tag;

                if (!$is_closing && !$is_single) {
                    $level++;
                }
                return $tag;
            },
            $html
        );
        return $html;
    }

    public function getTemplateSign()
    {
        $template_name = get_class($this);
        return $template_name . ':' . $this->action;
    }

    public static $area_replacements = array();

    /*
     * @param $mode - marker | data | both
     */
    public static function renderArea($area, $context, $mode = 'both')
    {
        if (isset($area['size'])) {
            $area['size'] = preg_replace("~[^a-z0-9]+~i", ',', $area['size']);
        }
        $is_admin = fx::isAdmin();
        if ($mode != 'marker') {
            fx::trigger('render_area', array('area' => $area));
            if ($is_admin && $context->isIdle()) {
                return;
            }
        }


        if ($is_admin) {
            ob_start();
        }
        if (
            $mode != 'marker' &&
            (!isset($area['render']) || $area['render'] != 'manual')
        ) {
            $area_blocks = fx::page()->getAreaInfoblocks($area['id']);
            $pos = 1;
            foreach ($area_blocks as $ib) {
                $ib->addParams(array('infoblock_area_position' => $pos));
                $result = $ib->render();
                echo $result;
                $pos++;
            }
        }
        if ($is_admin) {
            $area_result = ob_get_clean();
            self::$area_replacements [] = array($area, $area_result);
            $marker = '###fxa' . (count(self::$area_replacements) - 1);
            if ($mode != 'both') {
                $marker .= '|' . $mode;
            }
            $marker .= '###';
            echo $marker;
        }
    }

    public function getAreas()
    {
        $areas = array();
        ob_start();
        fx::listen('render_area.get_areas', function ($e) use (&$areas) {
            $areas[$e['area']['id']] = $e['area'];
        });
        $this->render(array('_idle' => true));
        fx::unlisten('render_area.get_areas');
        // hm, since IB render res is cached, we can not just remove added files
        // because they won't be added again
        // may be we should switch off caching for idle mode
        //fx::page()->clear_files();
        ob_get_clean();
        return $areas;
    }
    
    protected $forced_method = null;
    
    public function forceMethod($method) {
        $this->forced_method = $method;
    }

    public static function getActionMethod($action, $context, $tags = null, $with_priority = false)
    {
        if (!isset(static::$action_map[$action])) {
            return false;
        }
        $method = static::$action_map[$action];
        if (is_string($method)) {
            return !$with_priority ? $method : array($method, 0.5);
        }
        $res = call_user_func(get_called_class().'::solve_'.$action, $context, $tags);
        return !$with_priority ? $res[0] : $res;
    }

    protected function getProfiler()
    {
        $profile = fx::config('dev.profile_templates');
        if ($profile) {
            return fx::profiler();
        }
    }
    
    public function render($data = array())
    {
        if (isset($data['_idle'])) {
            $this->context->isIdle(true);
            unset($data['_idle']);
        }
        if (count($data) > 0) {
            $this->context->push($data);
        }
        ob_start();
        if (!is_null($this->forced_method)){
            $method = $this->forced_method;
        } else {
            $method = self::getActionMethod($this->action, $this->context);
            if (!$method) {
                fx::log('unknown template method', get_class($this) . '.' . $this->action, $this);
                throw new \Exception('No template: ' . get_class($this) . '.' . $this->action);
            }
        }
        
        if ($this->level > 50) {
            fx::log('recursion in', get_class($this), $method);
            return '<div class="fx_template_error">bad recursion?</div>';
        }
        
        
        $profiler = $this->getProfiler();
        if ($profiler) {
            $sign = preg_replace("~^fx_template_~", '', get_class($this)).'::'.preg_replace("~^tpl_~", '', $method);
            $profiler->block('<b style="color:#009;">tpl:</b> '. $sign );
        }
        
        
        try {
            $this->$method($this->context);
        } catch (\Exception $e) {
            if (preg_match("~Less_Exception~", get_class($e))) {
                fx::log(
                    'Less compiler exception', 
                    get_class($this).':'.$method,
                    $e->getMessage(), 
                    $e->currentFile,
                    $e->getTrace()
                );
            } else {
                fx::log(
                    'template exception', 
                    $e->getMessage()
                );
            }
        }
        $result = ob_get_clean();
        
        if (fx::isAdmin()) {
            if ($this->context->isIdle()) {
                if ($profiler) {
                    $profiler->stop();
                }
                return $result;
            }
            if (!$this->parent) {
                self::$count_replaces++;
                $result = Template::replaceAreas($result);
                $result = Field::replaceFields($result);
            }
        }
        if ($profiler) {
            $profiler->stop();
        }
        return $result;
    }

    public static $count_replaces = 0;

    // is populated when compiling
    protected static $templates = array();


    public static function getTemplateVariants()
    {
        return static::$templates;
    }

    public function getInfo()
    {
        if (!$this->action) {
            fx::log('no tpl action', $this);
            throw new \Exception('Specify template action/variant before getting info');
        }
        foreach (static::$templates as $tpl) {
            if ($tpl['id'] == $this->action) {
                return $tpl;
            }
        }
    }

    public static function replaceAreas($html)
    {
        if (!strpos($html, '###fxa')) {
            return $html;
        }
        $html = self::replaceAreasWrappedByTag($html);
        $html = self::replaceAreasInText($html);
        return $html;
    }

    protected static function replaceAreasWrappedByTag($html)
    {
        //$html = preg_replace("~<!--.*?-->~s", '', $html);
        $html = preg_replace_callback(
        /*"~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)###\s*(</[a-z0-9_-]+>)~s",*/
            "~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)\|?(.*?)###~s",
            function ($matches) use ($html) {
                $replacement = Template::$area_replacements[$matches[2]];
                $mode = $matches[3];
                if ($mode == 'data') {
                    Template::$area_replacements[$matches[2]] = null;
                    $res = $matches[1] . $replacement[1];
                    if (!$replacement[1]) {
                        $res .= '<span class="fx_area_marker"></span>';
                    }
                    return $res;
                }

                $tag = HtmlToken::createStandalone($matches[1]);
                $tag->addMeta(array(
                    'class'        => 'fx_area',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();

                if ($mode == 'marker') {
                    return $tag;
                }

                Template::$area_replacements[$matches[2]] = null;
                return $tag . $replacement[1] . $matches[3];
            },
            $html
        );
        return $html;
    }

    protected static function replaceAreasInText($html)
    {
        $html = preg_replace_callback(
            "~###fxa(\d+)\|?(.*?)###~",
            function ($matches) {
                $mode = $matches[2];
                $replacement = Template::$area_replacements[$matches[1]];
                if ($mode == 'data') {
                    if (!$replacement[1]) {
                        return '<span class="fx_area_marker"></span>';
                    }
                    return $replacement[1];
                }
                $tag_name = 'div';
                $tag = HtmlToken::createStandalone('<' . $tag_name . '>');
                $tag->addMeta(array(
                    'class'        => 'fx_area fx_wrapper',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();
                Template::$area_replacements[$matches[1]] = null;
                return $tag . $replacement[1] . '</' . $tag_name . '>';
            },
            $html
        );
        return $html;
    }
    
    public function isTemplateAllowed($template_name)
    {
        if (!isset(static::$template_config_entries) || !is_array(static::$template_config_entries)) {
            return true;
        }
        $res = true;
        foreach (static::$template_config_entries as $e) {
            if (isset($e['ignore'])) {
                foreach ($e['ignore'] as $key => $rule) {
                    $rex = '~^'.str_replace("\\*", '.*', preg_quote($key)).'$~';
                    if (preg_match($rex, $template_name)) {
                        $res = false;
                    }
                }
            }
            // implement "allow" directive here
        }
        return $res;
    }
    
    public function addStyleLess($block, $value, $params = array(), $is_temp = false)
    {
        $bundle_keyword = $block.'_'.$value;
        $bundle = fx::assets('style', $bundle_keyword, $params);
        $main_bundle = $is_temp ? 
                                fx::page()->getTempCssBundle() 
                                : 
                                fx::page()->getDefaultCssBundle();
        
        $main_bundle->push(array($bundle));
        $export_file = $bundle->getExportFile();
        if ($export_file) {
            //fx::log($block, $value, $params, file_get_contents($export_file));
        }
        return $export_file;
    }
    
    public function addTempStyleLess($block, $value, $params = array())
    {
        $bundle_keyword = $block.'_'.$value;
        $bundle = fx::assets('style', $bundle_keyword, $params);
        $default_bundle = fx::page()->getTempCssBundle();
        $default_bundle->push(array($bundle));
        $export_file = $bundle->getExportFile();
        return $export_file;
    }
    
    public static function processYaml($s)
    {
        $space = '';
        $s = preg_replace("~^[\r\n]+~", '', $s);
        
        $s = preg_replace("~^[ ]+[\r\n]+~s", '', $s);
        
        $s = str_replace("\t", '    ', $s);

        if (preg_match("~^[ ]+~", $s, $space) ) {
            $space = $space[0];
        }

        $s = trim($s);
        
        $s = preg_replace("~([\r\n]+)".$space."~", "\$1", $s);
        try {
            $res = Yaml\Yaml::parse($s);
            return $res;
        } catch (\Exception $e) {
            fx::log('incorrect yaml', $e);
        }
    }
}