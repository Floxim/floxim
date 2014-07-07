<?php
class fx_patch extends fx_essence {
    public function install() {
        if ($this['status'] != 'ready') {
            return false;
        }
        if (!$this['url']) {
            return false;
        }
        
        $dir = fx::path('files', 'patches/'.$this['from'].'-'.$this['to']);
        
        if (!file_exists($dir)) {
            $saved = fx::files()->save_file(
                $this['url'],
                'patches/',
                $this['from'].'-'.$this['to'].'.zip'
            );
            fx::files()->unzip($saved['fullpath'], 'patches/'.$this['from'].'-'.$this['to']);
            unlink($saved['fullpath']);
        }
        
        if (!file_exists($dir) || !is_dir($dir)) {
            return false;
        }

        /**
         * Load patch info
         */
        $info_file=@json_decode(file_get_contents($dir.'/_patch_generator/patch.json'),true);
        if (!$info_file) {
            return false;
        }

        /**
         * Load hooks list
         */
        $hook_objects=array();
        if (isset($info_file['hooks'])) {
            foreach($info_file['hooks'] as $hook_file) {
                require_once($dir.'/'.$hook_file);
                $hook_info=pathinfo($hook_file);
                if (class_exists($hook_info['filename'])) {
                    $hook_objects[]=new $hook_info['filename'];
                }
            }
        }
        /**
         * Run before hooks
         */
        foreach($hook_objects as $hook) {
            if (method_exists($hook,'before')) {
                call_user_func(array($hook,'before'));
            }
        }
        /**
         * Remove files
         */
        if (isset($info_file['files']['del'])) {
            $this->_remove_files($info_file['files']['del']);
        }
        /**
         * Copy files
         */
        if (file_exists($dir)) {
            $this->_update_files($dir, $dir);
        }
        /**
         * Run migrations
         */
        $migration_objects=array();
        if (isset($info_file['migrations'])) {
            foreach($info_file['migrations'] as $migration_file) {
                require_once($dir.'/'.$migration_file);
                $migration_info=pathinfo($migration_file);
                if (class_exists($migration_info['filename'])) {
                    $migration_objects[]=new $migration_info['filename'];
                }
            }
        }
        foreach($migration_objects as $migration) {
            if (method_exists($migration,'exec_up')) {
                call_user_func(array($migration,'exec_up'));
            }
        }
        /**
         * Run after hooks
         */
        foreach($hook_objects as $hook) {
            if (method_exists($hook,'after')) {
                call_user_func(array($hook,'after'));
            }
        }

        $this->_update_version_number($this['to']);

        $this['status'] = 'installed';
        $this->save();
        $next_patch = fx::data('patch')->where('from', $this['to'])->one();
        if ($next_patch) {
            $next_patch->set('status', 'ready')->save();
        }
        return true;
    }

    protected function _remove_files($files) {
        foreach($files as $file) {
            $path=fx::path('root').$file;
            fx::files()->rm($path);
        }
    }

    protected function _update_files($dir, $base) {
        $items = glob($dir."/*");
        if (!$items) {
            return;
        }
        
        foreach ($items as $item) {
            $item_target = fx::path('root').str_replace($base, '', $item);
            if (is_dir($item)) {
                $item_info=pathinfo($item);
                // skip specific dirs
                if (in_array($item_info['basename'],array('_patch_generator'))) {
                    continue;
                }
                fx::files()->mkdir($item_target);
                $this->_update_files($item, $base);
            } else {
                fx::files()->writefile($item_target, file_get_contents($item));
            }
        }
    }
    
    protected function _update_version_number($new_version) {
        $config_file = fx::path('floxim', '/system/config.php');
        $config_content = file_get_contents($config_file);
        $config_content = preg_replace_callback(
            "~['\"]fx.version['\"]\s*=>\s*['\"]([\d\.]+)['\"]~is",
            function($matches) use ($new_version) {
                return str_replace($matches[1], $new_version, $matches[0]);
            }, 
            $config_content
        );
        fx::files()->writefile($config_file, $config_content);
    }
}