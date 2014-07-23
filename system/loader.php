<?php
class fx_loader {

    public function __construct() {
        spl_autoload_register(array($this, 'load_class'));
    }
    
    protected static $classes_with_no_file = array();
    
    /**
     * @todo lead in nomrally view
     */
    static public function load_class($classname) {
        if (in_array($classname, self::$classes_with_no_file)) {
            throw new fx_exception_classload('AGAIN: Unable to load class '.$classname);
        }
        $file = self::get_class_file($classname);
        if (!$file) {
            return false;
        }
        $file = fx::path()->to_abs($file.'.php');
        if (!file_exists($file)) {
            $e = new fx_exception_classload('Unable to load class '.$classname." - ".$file);
            $e->class_file = $file;
            self::$classes_with_no_file[]= $classname;
            throw $e;
        }
        require_once $file;
    }

    protected static $essences = array(
        'component', 
        'field', 
        'group', 
        'history', 
        'history_item', 
        'infoblock', 
        'infoblock_visual',
        'layout',
        'content', 
        'redirect', 
        'simplerow', 
        'site', 
        'widget',
        'filetable',
        'patch',
        'patch_migration',
        'lang_string',
        'lang',
        'session'
    );
    
    protected static $tpl_classes = array(
        'processor',
        'field',
        'html',
        'suitable',
        'html_token',
        'token',
        'html_tokenizer',
        'fsm',
        'compiler',
        'loader',
        'parser',
        'expression_parser',
        'loop',
        'attr_parser',
        'attrtype_parser',
        'modifier_parser',
        'essence'
    );

    protected static $system_classes = array(
        'collection',
        'debug',
        'profiler',
        'http', 
        'event', 
        'cache', 
        'thumb',
        'db',
        'controller',
        'migration',
        'migration_manager',
        'hook_manager',
    );
    
    public static function get_class_file($classname) {
        $root = fx::config()->ROOT_FOLDER;
        $doc_root = fx::config()->DOCUMENT_ROOT.'/';
        
        $libs = array(
            'lessc' => 'lessphp/lessc.inc',
            'FB' => 'firephp/fb',
            'tmhOAuth' => 'tmhoAuth/tmhoauth',
            'tmhUtilities' => 'tmhoAuth/tmhutilities',
            'Facebook' => 'facebook/facebook'
        );
        
        if (isset($libs[$classname])) {
            return fx::config()->INCLUDE_FOLDER.$libs[$classname];
        }
        
        if (substr($classname, 0, 3) != 'fx_') {
            return false;
        }
        
        $classname = preg_replace('~^fx_~', '', $classname);
        

        if ($classname == 'template') {
            return $root.'template/template';
        }
        if (preg_match("~^template_(.+)$~", $classname, $tpl_name)) {
            $tpl_name = $tpl_name[1];
            if (in_array($tpl_name, self::$tpl_classes)) {
                return $root.'template/'.$classname;
            }
            fx_template_loader::autoload($tpl_name);
            return;
        }
        
        if (in_array($classname, self::$system_classes)) {
            return $root.'system/'.$classname;
        }
        
        if (preg_match("~^system_(.+)$~", $classname, $sys_name)){
            return $root.'system/'.$sys_name[1];
        }
            
        if (in_array($classname, self::$essences)) {
            return $root."essence/".$classname;
        }
            
        if (preg_match('~controller_(component|widget|layout)$~', $classname, $ctr_type)) {
            return $root.'controller/'.$ctr_type[1];
        }
            
        if (preg_match("~^router~", $classname)) {
            return $root.'routing/'.$classname;
        }
            
        if (preg_match('~^content_~', $classname)) {
            $com_name = preg_replace("~^content_~", '', $classname);
            $file = fx::path()->to_abs('/component/'.$com_name.'/'.$com_name.'.essence');
            if (file_exists($file.'.php')) {
                return $file;
            } 
            $std_file = fx::path()->to_abs('/floxim/std/component/'.$com_name.'/'.$com_name.'.essence');
            if(file_exists($std_file.'.php')) {
                return $std_file;
            }
        }
            
        if (preg_match("~^controller_(.+)~", $classname, $controller_name)) {
            $controller_name = $controller_name[1];
            if (preg_match("~^(layout|component|widget)_(.+)$~", $controller_name, $name_parts)) {
                $ctr_type = $name_parts[1];
                $ctr_name = $name_parts[2];
            } else {
                $ctr_type = 'other';
                $ctr_name = $controller_name;
            }
            $test_file = $doc_root.$ctr_type.'/'.$ctr_name.'/'.$ctr_name;
            if (file_exists($test_file.'.php')) {
                return $test_file;
            } 
            $std_file = fx::path('std', $ctr_type.'/'.$ctr_name.'/'.$ctr_name);
            if (file_exists($std_file.'.php')) {
                return $std_file;
            }
        }
        // Some old classes
        if ($classname == 'controller_layout' || $classname == 'controller_admin_layout') {
            return $root.'admin/controller/layout';
        }

        if ($classname == 'controller_admin' || $classname == 'controller_admin_module') {
            return $root."admin/admin";
        }

        if (preg_match("/^controller_admin_module_([a-z]+)/", $classname, $match)) {
            return $root."modules/".$match[1]."/admin";
        }

        if (preg_match("/^controller_admin_([a-z_]+)/", $classname, $match)) {
            return $root.'admin/controller/'.str_replace('_', '/', $match[1]);
        }

        if (preg_match("/^controller_(site|template_files|template_colors|template|component|field|settings|widget)$/", $classname, $match)) {
            return $root.'/admin/controller/'.str_replace('_', '/', $match[1]);
        }

        if (preg_match("/^controller_module_([a-z]+)/", $classname, $match)) {
            return $root."modules/".$match[1]."/controller";
        }
        
        if ($classname == 'form') {
            return $root."std/helper/form/form";
        }

        if (preg_match("/^controller_admin_module_([a-z]+)/", $classname, $match)) {
            return $root."modules/".$match[1]."/admin";
        }

        if (preg_match("~^data_(.+)$~", $classname, $match)) {
            $data_name = $match[1];
            if (preg_match("~^content_~", $data_name)) {
                $com_name = preg_replace("~^content_~", '', $data_name);
                $file = $doc_root.'component/'.$com_name.'/'.$com_name.'.data';
                if (file_exists($file.'.php')){
                    return $file;
                } 
                $std_file = $doc_root.'floxim/std/component/'.$com_name.'/'.$com_name.'.data';
                if(file_exists($std_file.'.php')) {
                    return $std_file;
                }
            } 
            return $root.'data/'.$match[1];
        }
        if ($classname === 'data') {
            return $root.'/data/data';
        }
        if ($classname === 'essence') {
            return $root.'/essence/essence';
        }
        if (preg_match("/^controller_admin_module_([a-z]+)/", $classname, $match)) {
            return $root."modules/".$match[1]."/admin";
        }

        if (preg_match("/^controller_admin_([a-z_]+)/", $classname, $match)) {
            return $root.'admin/controller/'.str_replace('_', '/', $match[1]);
        }

        if (preg_match("/^controller_module_([a-z]+)/", $classname, $match)) {
            return $root."modules/".$match[1]."/controller";
        }

        if (preg_match("/^controller_admin_module_([a-z]+)/", $classname, $match)) {
            return $root."modules/".$match[1]."/admin";
        }

        if (preg_match("~^data_(.+)$~", $classname, $match)) {
            $data_name = $match[1];
            if (preg_match("~^content_~", $data_name)) {
                $com_name = preg_replace("~^content_~", '', $data_name);
                $file = $doc_root.'component/'.$com_name.'/'.$com_name.'.data';
                if (file_exists($file.'.php')) {
                    return $file;
                } 
                $std_file = $doc_root.'floxim/std/component/'.$com_name.'/'.$com_name.'.data';
                if (file_exists($std_file.'.php')) {
                    return $std_file;
                }
            }
            return $root.'data/'.$match[1];
        }
            
        if (preg_match("/^(admin|controller|event|field|infoblock|layout|system)_([a-z0-9_]+)/", $classname, $match)) {
            return $root.$match[1]."/".str_replace('_', '/', $match[2]);
        }
        
        return $root.$classname;
    }
}

class fx_exception extends Exception {

}

class fx_exception_classload extends fx_exception {
	public $class_file = false;
	public function get_class_file() {
		return $this->class_file;
	}
}