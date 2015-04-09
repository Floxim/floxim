<?php

namespace Floxim\Floxim\Console\Command;

use Floxim\Floxim\System\Console;
use Floxim\Floxim\System\Fx as fx;

class Module extends Console\Command
{

    protected $module_vendor;
    protected $module_name;

    /**
     * Create new module
     *
     * @param string $name
     * @param bool $overwrite Overwrite exists module
     */
    public function doNew($name)
    {
        $module = fx::data('module')->create(array('keyword' => $name));
        $module->save();
        $this->doScaffold($name);
    }
    
    public function doScaffold($name, $overwrite = false)
    {
        $name_parts = explode('.', $name);
        if (count($name_parts) != 2) {
            $this->usageError('Name need format "vendor.name"');
        }
        $this->module_vendor = fx::util()->underscoreToCamel($name_parts[0]);
        $this->module_name = fx::util()->underscoreToCamel($name_parts[1]);
        /**
         * Check for exists
         */
        $module_path = fx::path('@root') . "module/{$this->module_vendor}/{$this->module_name}/";
        if (file_exists($module_path)) {
            if (!$overwrite) {
                $this->usageError('Module already exists');
            }
        } else {
            /**
             * Create dir
             */
            if (@mkdir($module_path, 0777, true)) {
                echo "Create dir {$module_path}" . "\n";
            } else {
                $this->usageError('Can\'t create module dir - ' . $module_path);
            }
        }
        $source_path = fx::path('@floxim') . '/Console/protected/module/';
        /**
         * Build file list
         */
        $file_list = $this->buildFileList($source_path, $module_path);
        foreach ($file_list as $file_name => $file_data) {
            $file_list[$file_name]['callback_content'] = array($this, 'replacePlaceholder');
        }
        /**
         * Copy files
         */
        $this->copyFiles($file_list);
        echo "\nYour module has been created successfully under {$module_path}.\n";
    }

    public function replacePlaceholder($content)
    {
        $content = str_replace('{Vendor}', $this->module_vendor, $content);
        $content = str_replace('{Module}', $this->module_name, $content);
        return $content;
    }
}