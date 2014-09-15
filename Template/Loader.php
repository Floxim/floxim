<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

/*
 * Class collects the sources from different sources
 * Able to find sources on the template name
 * Stores the result will tell me where
 */
class Loader {
    protected $_source_files = array();
    protected $template_name = null;
    protected $_target_hash = null;

    public function __construct() {
        
    }
    
    public function add_source_file($source_file) {
        if (!file_exists($source_file)) {
            throw new \Exception('Source file '.$source_file.' does not exist');
        }
        if (is_dir($source_file)) {
            $this->add_source_dir($source_file);
            return;
        }
        $this->_source_files[]= realpath($source_file);
    }
    
    public function add_source_dir($source_dir) {
        if (!file_exists($source_dir) || !is_dir($source_dir)) {
            throw new \Exception('Source dir '.$source_dir.' does not exist');
        }
        
        $tpl_files = glob($source_dir.'/*.tpl');
        if (!$tpl_files) {
            return;
        }
        foreach ($tpl_files as $tpl_file) {
            // Do not include the template files that begin with "_"
            if (preg_match("~/_[^/]+$~", $tpl_file)) {
                continue;
            }
            $this->add_source_file($tpl_file);
        }
    }
    
    public function add_source($file_or_dir) {
        if (!file_exists($file_or_dir)) {
            throw new \Exception('Source '.$file_or_dir.' does not exist');
        }
        is_dir($file_or_dir) 
            ? $this->add_source_dir($file_or_dir) 
            : $this->add_source_file($file_or_dir);
    }
    
    protected $_controller_type = null;
    protected $_controller_name = null;
    
    public function setTemplateName($tpl_name) {
        $tpl_name_parts = null;
        if (preg_match("~^(layout|component|widget|helper|virtual)_([a-z0-9_]+)$~", $tpl_name, $tpl_name_parts)) {
            $this->_controller_type = $tpl_name_parts[1];
            $this->_controller_name = $tpl_name_parts[2];
        } else {
            $this->_controller_type = 'other';
            $this->_controller_name = $tpl_name;
        }
        $this->template_name = $tpl_name;
    }
    
    public function getTemplateName() {
        return $this->template_name;
    }

    public function add_default_source_dirs() {
        
        $ns = fx::getComponentNamespace($this->getTemplateName());
        
        $ns = explode("\\", trim($ns, "\\"));
        
        if ($ns[0] === 'Theme') {
            $ns[0] = 'theme';
        } else {
            array_unshift($ns, 'module');
        }
        
        $dirs = array( fx::path()->to_abs('/'.join("/", $ns)) );

        // todo: psr0 fix this mega-hack
        if (strpos($this->getTemplateName(),'helper_admin')===0) {
            $dirs[]=fx::path('floxim').'/std/helper/admin';
        }
        
        foreach ($dirs as $dir) {
            try {
                $this->add_source_dir($dir);
            } catch (\Exception $e) {
                
            }
        }
    }
    
    protected $_target_dir = null;
    protected $_target_file = null;
    
    public function set_target_dir($dir) {
        $this->_target_dir = $dir;
    }
    
    public function set_target_file($filename) {
        $this->_target_file = $filename;
    }
    
    public function get_target_path() {
        if (!$this->_target_dir) {
            $this->_target_dir = fx::config('templates.cache_dir');
        }
        if (!$this->_target_file) {
            $this->_target_file = $this->getTemplateName().'.php';
        }
        /**
         * Calc prefix hash by sources files
         */
        if (!$this->_target_hash) {
            $this->recalc_target_hash();
        }
        return $this->_target_dir.'/'.preg_replace("~\.php$~", '.'.$this->_target_hash.'.php', $this->_target_file);
    }

    public function recalc_target_hash() {
        $this->_target_hash='';
        $files=(array)$this->_source_files;
        foreach($files as $sFile) {
            $this->_target_hash.=filemtime($sFile);
        }
        $this->_target_hash=md5($this->_target_hash);
    }

    public function get_target_mask() {
        $path=$this->get_target_path();
        return str_replace($this->_target_hash, '*', $path);
    }
    
    /*
     * Automatically load the template by name
     * Standard scheme
     */
    public static function load($tpl_name, $action = null, $data = null) {
        $processor = new self();
        $processor->setTemplateName($tpl_name);
        $classname = $processor->getCompiledClassName();
        if (!class_exists($classname)) {
            $processor->add_default_source_dirs();
            $processor->process();
        }
        $tpl = new $classname($action, $data);
        return $tpl;
    }
    
    protected function getCompiledClassName() {
        $tpl_name = $this->getTemplateName();
        $tpl_name = preg_replace("~[^a-z0-9]+~i", '_', $tpl_name);
        return 'fx_template_'.$tpl_name;
    }


    public function is_fresh($target_path) {
        
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
        if (time() - filemtime($target_path) > $ttl ) {
            return false;
        }
        return true;
    }
    
    public function process() {
        $target_path = $this->get_target_path();
        if ($this->is_fresh($target_path)) {
            require_once ($target_path);
            return;
        }
        $source = $this->compile();
        if ($this->save($source)) {
            require_once ($target_path);
        } else {
            $this->run_eval($source);
        }
    }
    
    public function virtual($source = null, $action = null)  {
        static $count_virtual = 0;
        if ($source === false) {
            $source = ob_get_clean();
        } elseif (preg_match('~\.tpl$~', $source)) {
            $source = fx::files()->readfile($source);
        }
        $count_virtual++;
        $this->setTemplateName('virtual_'.$count_virtual);
        $src = $this->build_source(array('/dev/null/virtual.tpl' => $source));
        $php = $this->compile($src);
        $this->run_eval($php);
        // todo: psr0 need verify
        //$classname = 'fx_template_virtual_'.$count_virtual;
        $classname = $this->getCompiledClassName();
        $tpl = new $classname(is_null($action) ? 'virtual' : $action);
        $tpl->source = $src;
        $tpl->compiled = $php;
        return $tpl;
    }
    
    public function run_eval(&$source) {
        try {
            return eval(preg_replace("~^<\?(php)?~", '', $source));
        } catch (\Exception $e) {
            // ignore
        }
    }


    public function compile($source = null) {
        if (is_null($source)) {
            $source = $this->build_source();
        }
        $parser = new Parser();
        $tree = $parser->parse($source);
        
        unset($parser);
        $compiler = new Compiler();
        $res = $compiler->compile($tree, $this->getCompiledClassName());
        return $res;
    }
    
    public function save($source) {
        try {
            // Remove old file
            $this->remove_old_files();
            fx::files()->writefile($this->get_target_path(), $source);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function remove_old_files() {
        $mask=$this->get_target_mask();
        $files = glob($mask);
        if (is_array($files)) {
            foreach($files as $file) {
                fx::files()->rm($file);
            }
        }
    }
    
    protected function _load_sources() {
        $sources = array();
        foreach ($this->_source_files as $sf) {
            $sources[$sf] = file_get_contents($sf);
        }
        return $sources;
    }
    
    /**
     * Convert files-source code in one big
     * @return string
     */
    public function build_source($sources = null) {
        if (is_null($sources)) {
            $sources = $this->_load_sources();
        }
        $res = '{templates name="'.$this->getTemplateName().'"';
        if (!empty($this->_controller_type)) {
            $res .= ' controller_type="'.$this->_controller_type.'"';
        }
        if (!empty($this->_controller_name)) {
            $res .= ' controller_name="'.$this->_controller_name.'"';
        }
        $res .= "}";
        //foreach ($this->_source_files as $file) {
        foreach ($sources as $file => $source) {
            $res .= '{templates source="'.$file.'"}';
            $res .= $this->_prepare_file_data($source, $file);
            $res .= '{/templates}';
        }
        $res .= '{/templates}';
        return $res;
    }
    
    public function read_file($file) {
        $file_data = file_get_contents($file);
        return $this->_prepare_file_data($file_data, $file);
    }
    
    protected function _prepare_file_data($file_data, $file) {
        // convert fx::attributes to the canonical Smarty-syntax
        $T = new Html($file_data);
        try {
            $file_data = $T->transform_to_floxim();
        } catch (\Exception $e) {
            fx::debug('Floxim html parser error', $e->getMessage(), $file);
        }

        // remove fx-comments
        $file_data = preg_replace("~\{\*.*?\*\}~s", '', $file_data);
        $file_data = trim($file_data);
        if (!preg_match("~^{template~", $file_data)) {
            $file_data = $this->wrap_file($file, $file_data);
        }
        return $file_data;
    }


    public function wrap_file($file, $file_data) {
        //$is_layout = $this->_controller_type == 'layout';
        $is_theme = preg_match("~^theme\.~", $this->getTemplateName());
        $tpl_of = 'false';
        if ($is_theme) {
            $tpl_id = '_layout_body';
        } else {
            $file_tpl_name = null;
            preg_match('~([a-z0-9_]+)\.tpl$~', $file, $file_tpl_name);
            $tpl_id = $file_tpl_name[1];
            if ($this->_controller_type == 'component' && $this->_controller_name) {
                $tpl_of = 'component_'.$this->_controller_name.'.'.$tpl_id;
            }
        }
        $file_data = 
            '{template id="'.$tpl_id.'" of="'.$tpl_of.'"}'.
               $file_data.
            '{/template}';
        return $file_data;
    }
}
