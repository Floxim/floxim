<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

/*
 * Class collects the sources from different sources
 * Able to find sources on the template name
 * Stores the result will tell me where
 */

class Loader
{
    protected $source_files = array();
    protected $template_name = null;
    protected $target_hash = null;
    protected $imported_paths = array();

    public function __construct()
    {

    }

    public function addSourceFile($source_file)
    {
        if (!file_exists($source_file)) {
            throw new \Exception('Source file ' . $source_file . ' does not exist');
        }
        if (is_dir($source_file)) {
            $this->addSourceDir($source_file);
            return;
        }
        $this->source_files[] = $source_file;
    }
    
    protected $config_entries = array();
    
    protected $import_level = 0;
    protected $max_import_level = 0;
    
    public function handleSourceDirConfig($source_dir)
    {
        $config_file = $source_dir.'/template.ini';
        if (!file_exists($config_file)) {
            return;
        }
        $config_data = parse_ini_file($config_file, true);
        
        $this->config_entries []= $config_data;
        
        if (!$config_data || !isset($config_data['import']) || !is_array($config_data['import'])) {
            return;
        }
        foreach ($config_data['import'] as $template => $import) {
            $template_dir = self::nameToPath($template);
            if ($import === '*') {
                $this->import_level++;
                if ($this->import_level > $this->max_import_level) {
                    $this->max_import_level = $this->import_level;
                }
                $this->imported_paths[$template_dir] = $this->import_level;
                $this->addSourceDir($template_dir);
                $this->import_level--;
            }
        }
    }

    public function addSourceDir($source_dir)
    {
        if (!file_exists($source_dir) || !is_dir($source_dir)) {
            throw new \Exception('Source dir ' . $source_dir . ' does not exist');
        }
        
        $this->handleSourceDirConfig($source_dir);

        $tpl_files = glob($source_dir . '/*.tpl');
        if (!$tpl_files) {
            return;
        }
        foreach ($tpl_files as $tpl_file) {
            // Do not include the template files that begin with "_"
            if (preg_match("~/_[^/]+$~", $tpl_file)) {
                continue;
            }
            $this->addSourceFile($tpl_file);
        }
    }

    public function addSource($file_or_dir)
    {
        if (!file_exists($file_or_dir)) {
            throw new \Exception('Source ' . $file_or_dir . ' does not exist');
        }
        is_dir($file_or_dir)
            ? $this->addSourceDir($file_or_dir)
            : $this->addSourceFile($file_or_dir);
    }

    public function setTemplateName($tpl_name)
    {
        $this->template_name = $tpl_name;
    }

    public function getTemplateName()
    {
        return $this->template_name;
    }
    
    // set for special template groups like @admin
    // with source dirs bound directly
    protected $is_aliased = false;
    
    public function isAliased($set = null)
    {
        if (func_num_args() === 1) {
            $this->is_aliased = (bool) $set;
        }
        return $this->is_aliased;
    }
    
    protected static $name_path_cache = [];
    
    public static function nameToPath($name)
    {
        if (isset(self::$name_path_cache[$name])) {
            return self::$name_path_cache[$name];
        }
        if (strstr($name, '.')) {
            $ns = fx::getComponentNamespace($name);
            $ns = explode("\\", trim($ns, "\\"));
            if ($ns[0] === 'Theme') {
                $ns[0] = 'theme';
            } else {
                array_unshift($ns, 'module');
            }
            $res = fx::path()->abs('@home/' . join("/", $ns));
        } else {
            $res = fx::path()->abs('@floxim/Component/'.fx::util()->underscoreToCamel($name));
        }
        self::$name_path_cache[$name] = $res;
        return $res;
    }

    public function addDefaultSourceDirs()
    {
        $template_name = $this->getTemplateName();

        if (!$this->isAliased()) {
            
            $dir = self::nameToPath($this->getTemplateName());
            
            $dirs = array($dir);

            foreach ($dirs as $dir) {
                try {
                    $this->addSourceDir($dir);
                } catch (\Exception $e) {
                    fx::log('Error while adding template source dir', $e->getMessage(), debug_backtrace());
                }
            }
        }

        if (isset(self::$source_paths[$template_name])) {
            foreach (self::$source_paths[$template_name] as $sp) {
                try {
                    $this->addSource($sp);
                } catch (\Exception $ex) {

                }
            }
        }

    }

    protected $target_dir = null;
    protected $target_file = null;

    public function setTargetDir($dir)
    {
        $this->target_dir = $dir;
    }

    public function setTargetFile($filename)
    {
        $this->target_file = $filename;
    }

    public function getTargetPath()
    {
        if (!$this->target_dir) {
            $this->target_dir = fx::config('templates.cache_dir');
        }
        if (!$this->target_file) {
            $this->target_file = $this->getTemplateName() . '.php';
        }
        /**
         * Calc prefix hash by sources files
         */
        if (!$this->target_hash) {
            $this->recalcTargetHash();
        }
        $res = $this->target_dir . '/';
        $res .= preg_replace(
                    "~\.php$~", 
                    '.' . $this->target_hash . '.php',
                    $this->target_file
                );
        $res = fx::path($res);
        return $res;
    }

    public function recalcTargetHash()
    {
        $this->target_hash = '';
        $files = (array)$this->source_files;
        foreach ($files as $sFile) {
            $this->target_hash .= filemtime($sFile);
        }
        $this->target_hash = md5($this->target_hash);
    }

    public function getTargetMask()
    {
        $path = $this->getTargetPath();
        return str_replace($this->target_hash, '*', $path);
    }


    protected static $source_paths = array();

    public function registerSource($tpl_name, $path)
    {
        if (!isset(self::$source_paths[$tpl_name])) {
            self::$source_paths[$tpl_name] = array();
        }
        self::$source_paths[$tpl_name][] = $path;
    }
    
    protected static $imported_classes = array();
    
    protected static $registered_names = array();
    
    /*
     * Make template availble for search
     */
    public static function register($tpl_name)
    {
        if (is_array($tpl_name)) {
            foreach ($tpl_name as $real_name) {
                self::register($real_name);
            }
            return;
        }
        self::$registered_names []= $tpl_name;
    }
    
    protected static function importRegistered()
    {
        foreach (self::$registered_names as $name) {
            self::import($name);
        }
        self::$registered_names = array();
    }
    
    public static function import($tpl_name)
    {
        $is_aliased = preg_match("~^\@(.+)~", $tpl_name, $real_name);
        if ($is_aliased) {
            $tpl_name = $real_name[1];
        } else {
            $tpl_name = fx::getComponentFullName($tpl_name);
        }
        
        if (isset (self::$imported_classes[$tpl_name])) {
            return self::$imported_classes[$tpl_name];
        }
        $processor = new self();
        
        $processor->setTemplateName($tpl_name);
        if ($is_aliased) {
            $processor->isAliased(true);
        }
        $classname = $processor->getCompiledClassName();
        $processor->addDefaultSourceDirs();
        try {
            $processor->process();
        } catch (\Exception $e) {
            self::$imported_classes[$tpl_name] = false;
            return false;
        }
        self::$imported_classes[$tpl_name] = $classname;
        $classname::init();
        return $classname;
    }
    
    public function getImportedClasses()
    {
        self::importRegistered();
        return self::$imported_classes;
    }
    
    public static function loadTemplateVariant(
        $name,
        $action = null,
        $context = null,
        $force_group = false,
        $tags = null
    ) 
    {
        // just return new template instance
        if (!$action) {
            $tpl_class = self::import($name);
            if (!$tpl_class) {
                return false;
            }
            return new $tpl_class(null, $context);
        }
        
        // forced templates declared by {use}
        if ($context && ($forced_parts = $context->getForcedTemplate($name.':'.$action)) ) {
            $force_group = $forced_parts[0];
            $name = $force_group;
            $action = $forced_parts[1];
        }
        
        // if group is forced
        if ($force_group) {
            $tpl_class = self::import($force_group);
            if (!$tpl_class) {
                return false;
            }
            // recount action name for external group
            if ($force_group !== $name) {
                $action = str_replace(".", "_", $name)."__".$action;
            }
            $method = $tpl_class::getActionMethod($action, $context, $tags);
            if (!$method) {
                return false;
            }
            $tpl = new $tpl_class(null, $context);
            $tpl->forceMethod($method);
            return $tpl;
        }
        // run full process - trigger event and collect external implementations
        $base_class = self::import($name);
        
        $found_variants = fx::trigger('loadTemplate', array(
            'name' => $name,
            'action' => $action,
            'context' => $context,
            'full_name' => $name.':'.$action,
            'tags' => $tags
        ));
        // no external implementation, quickly return base one
        if (!$found_variants) {
            if (!$base_class) {
                return false;
            }
            $method = $base_class::getActionMethod($action, $context, $tags);
            if (!$method) {
                return false;
            }
            $tpl = new $base_class(null, $context);
            $tpl->forceMethod($method);
            return $tpl;
        }
        if ( ($local_method = $base_class::getActionMethod($action, $context, $tags, true) ) ) {
            $local_method[2] = $base_class;
            $found_variants []= $local_method;
        }
        usort($found_variants, function($a, $b) use ($base_class) {
            $ap = $a[1];
            $bp = $b[1];
            if ($ap > $bp) {
                return -1;
            }
            if ($ap < $bp) {
                return 1;
            }
            $a_loc = $a[2] === $base_class;
            $b_loc = $b[2] === $base_class;
            if ($a_loc) {
                return 1;
            }
            if ($b_loc) {
                return -1;
            }
            return 0;
        });
        $winner = $found_variants[0];
        $winner_class = $winner[2];
        $tpl = new $winner_class(null, $context);
        $tpl->forceMethod($winner[0]);
        return $tpl;
    }

    /*
     * Automatically load the template by name
     * Standard scheme
     */
    public static function loadByName($tpl_name, $action = null, $data = null)
    {
        $classname = self::import($tpl_name);
        if ($classname) {
            $tpl = new $classname($action, $data);
            return $tpl;
        }
        //fx::log('template not found', $tpl_name);
        return false;
    }

    protected function getCompiledClassName()
    {
        $tpl_name = $this->getTemplateName();
        $tpl_name = preg_replace("~[^a-z0-9]+~i", '_', $tpl_name);
        return 'fx_template_' . $tpl_name;
    }


    public function isFresh($target_path)
    {
        
        $cache = fx::config('templates.cache');
        
        // template caching is disabled
        if (!$cache) {
            return false;
        }

        $ttl = fx::config('templates.ttl');
        
        // file is not created yet
        if (!file_exists($target_path)) {
            return false;
        }
        // cache forever
        if ($ttl === 0) {
            return true;
        }
        // file is older than ttl
        if (time() - filemtime($target_path) > $ttl) {
            return false;
        }
        return true;
    }

    public function process()
    {
        $target_path = $this->getTargetPath();
        if ($this->isFresh($target_path)) {
            require_once($target_path);
            return;
        }
        $source = $this->compile();
        if ($this->save($source)) {
            require_once($target_path);
        } else {
            $this->runEval($source);
        }
    }

    public function virtual($source = null, $action = null)
    {
        static $count_virtual = 0;
        if ($source === false) {
            $source = ob_get_clean();
        } elseif (preg_match('~\.tpl$~', $source)) {
            $source = fx::files()->readfile($source);
        }
        $count_virtual++;
        $tpl_name = 'virtual_' . $count_virtual;
        $this->setTemplateName($tpl_name);
        $src = $this->buildSource(array('/dev/null/virtual.tpl' => $source));
        $php = $this->compile($src);

        $this->runEval($php);
        $classname = $this->getCompiledClassName();
        self::$imported_classes[$tpl_name] = $classname;
        $tpl = new $classname(is_null($action) ? 'virtual' : $action);
        $tpl->source = $src;
        $tpl->compiled = $php;
        return $tpl;
    }

    public function runEval(&$source)
    {
        try {
            return eval(preg_replace("~^<\?(php)?~", '', $source));
        } catch (\Exception $e) {
            // ignore
        }
    }


    public function compile($source = null)
    {
        if (is_null($source)) {
            $source = $this->buildSource();
        }
        
        $parser = new Parser();
        $tree = $parser->parse($source);
        
        unset($parser);
        
        $compiler = new Compiler();
        $compiler->addTemplateConfigEntries($this->config_entries);
        $res = $compiler->compile($tree, $this->getCompiledClassName());
        return $res;
    }

    public function save($source)
    {
        try {
            // Remove old file
            $this->removeOldFiles();
            fx::files()->writefile($this->getTargetPath(), $source);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function removeOldFiles()
    {
        $mask = $this->getTargetMask();
        $files = glob($mask);
        if (is_array($files)) {
            foreach ($files as $file) {
                fx::files()->rm($file);
            }
        }
    }

    protected function loadSources()
    {
        $sources = array();
        foreach ($this->source_files as $sf) {
            $file_data = file_get_contents($sf);
            if (!preg_match("~^\s*$~", $file_data)) {
                $sources[$sf] = $file_data;
            }
        }
        return $sources;
    }

    /**
     * Convert files-source code in one big
     * @return string
     */
    public function buildSource($sources = null)
    {
        if (is_null($sources)) {
            $sources = $this->loadSources();
        }
        if (count($sources) === 0) {
            throw new \Exception('No template sources found');
        }
        $res = '{templates name="' . $this->getTemplateName() . '" is_aliased="'.($this->isAliased() ? 'true': 'false').'"}';
        foreach ($sources as $file => $source) {
            $is_imported = false;
            foreach ($this->imported_paths as $imported_path => $import_level) {
                if (strpos($file, $imported_path) === 0) {
                    $is_imported = true;
                    break;
                }
            }
            $res .= '{templates source="' . $file . '" is_imported="'.($is_imported ? ($this->max_import_level - $import_level) : 'false').'" }';
            $res .= $this->prepareFileData($source, $file);
            $res .= '{/templates}';
        }
        $res .= '{/templates}';
        return $res;
    }

    protected function prepareFileData($file_data, $file)
    {
        // convert fx::attributes to the canonical Smarty-syntax
        $T = new Html($file_data);
        try {
            $file_data = $T->transformToFloxim();
        } catch (\Exception $e) {
            fx::debug('Floxim html parser error', $e->getMessage(), $file);
        }

        // remove fx-comments
        $file_data = preg_replace("~\{\*.*?\*\}~s", '', $file_data);
        $file_data = trim($file_data);
        if (!preg_match("~^{(template|preset)~", $file_data)) {
            $file_data = $this->wrapFile($file, $file_data);
        }
        return $file_data;
    }


    public function wrapFile($file, $file_data)
    {
        $is_theme = preg_match("~^theme\.~", $this->getTemplateName());
        $tpl_of = 'false';
        if ($is_theme) {
            $tpl_id = '_layout_body';
            $tpl_of = 'layout:show';
        } else {
            $file_tpl_name = null;
            preg_match('~([a-z0-9_]+)\.tpl$~', $file, $file_tpl_name);
            $tpl_id = $file_tpl_name[1];
        }
        $file_data =
            '{template id="' . $tpl_id . '" of="' . $tpl_of . '"}' .
            $file_data .
            '{/template}';
        return $file_data;
    }
    
    public static function parseTemplateName($name, $group_name, $is_aliased = false) {
        if (!preg_match("~[\:\@]~", $name)) {
           $name = $group_name . ":" . $name;
        }
        
        $tpl_at_parts = explode("@", $name);
        if (count($tpl_at_parts) === 1) {
            $forced_group = null;
            list($set_name, $action_name) = explode(":", $name);
            // save @ for named ("aliased") template groups (like "@admin")
            if ($set_name === $group_name && $is_aliased) {
                $set_name = '@'.$set_name;
            }
        } else {
            $forced_group = !empty($tpl_at_parts[0]) ? $tpl_at_parts[0] : $group_name;
            $action_parts = explode(":", $tpl_at_parts[1]);
            if (count($action_parts) === 1) {
                array_unshift($action_parts, $forced_group);
            }
            list($set_name, $action_name) = $action_parts;
        }
        $tag_parts = explode("#", $action_name);
        if (count($tag_parts) > 1) {
            $action_name = $tag_parts[0];
            $tags = explode(",", $tag_parts[1]);
        } else {
            $tags = null;
        }
        return array(
            'group' => $set_name,
            'action' => $action_name,
            'forced_group' => $forced_group,
            'tags' => $tags
        );
    }
}
