<?php

namespace Floxim\Floxim\Console\Command;

use Floxim\Floxim\System\Console;
use Floxim\Floxim\System\Fx as fx;

class Component extends Console\Command {

    protected $module_vendor;
    protected $module_name;
    protected $component_name;
    protected $parent_entity;
    protected $parent_finder;
    protected $parent_controller;

    /**
     * Create new component
     *
     * @param string $keyword
     * @param string|bool $name
     * @param string|bool $itemName
     * @param bool   $overwrite Overwrite exists component
     * @param string $parent Parent component
     */
    public function doNew($keyword, $name=false, $itemName=false, $parent = 'content', $overwrite = false) {
        $keyword_parts = explode('.', $keyword);
        if (count($keyword_parts) != 3) {
            $this->usageError('Name need format "vendor.module.name"');
        }
        $this->module_vendor = ucfirst($keyword_parts[0]);
        $this->module_name = ucfirst($keyword_parts[1]);
        $this->component_name = ucfirst($keyword_parts[2]);
        if (!$name) {
            $name=$this->component_name;
        }
        if (!$itemName) {
            $itemName=$this->component_name;
        }
        if (!$parentComponent=fx::data('component',$parent)) {
            $this->usageError('Not found parent component');
        }
        $parentNamespace=fx::getComponentNamespace($parent);
        $this->parent_entity=$parentNamespace.'\\Entity';
        $this->parent_finder=$parentNamespace.'\\Finder';
        $this->parent_controller=$parentNamespace.'\\Controller';
        /**
         * Check for exists module
         */
        $module_path = fx::path('root') . "module/{$this->module_vendor}/{$this->module_name}/";
        if (!file_exists($module_path)) {
            /**
             * Create module
             */
            $command_module = $this->getManager()->createCommand('module');
            if ($command_module) {
                $command_module->doNew(strtolower("{$this->module_vendor}.{$this->module_name}"));
            } else {
                $this->usageError('Command "module" not found');
            }
        }
        /**
         * Check for exists component
         */
        $component_path = $module_path . $this->component_name . '/';
        if (file_exists($component_path)) {
            if (!$overwrite) {
                $this->usageError('Component already exists');
            }
        } else {
            /**
             * Create dir
             */
            if (@mkdir($component_path, 0777, true)) {
                echo "Create dir {$component_path}" . "\n";
            } else {
                $this->usageError('Can\'t create component dir - ' . $component_path);
            }
        }
        $source_path = fx::path('floxim') . '/Console/protected/component/';
        /**
         * Build file list
         */
        $file_list = $this->buildFileList($source_path, $component_path);
        foreach ($file_list as $file_name => $file_data) {
            $file_list[$file_name]['callback_content'] = array($this, 'replacePlaceholder');
        }
        /**
         * Copy files
         */
        $this->copyFiles($file_list);
        /**
         * Create in database
         */
        $data=array(
            'name' => $name,
            'keyword' => $keyword,
            'vendor' => strtolower($this->module_vendor),
            'parent_id' => $parentComponent['id'],
            'item_name' => $itemName,
        );
        $component=fx::data('component')->create($data);
        $component->save();

        echo "\nYour component has been created successfully under {$component_path}.\n";
    }

    public function replacePlaceholder($content) {
        $content = str_replace('{Vendor}', $this->module_vendor, $content);
        $content = str_replace('{Module}', $this->module_name, $content);
        $content = str_replace('{Component}', $this->component_name, $content);
        $content = str_replace('{ParentClassEntity}', $this->parent_entity, $content);
        $content = str_replace('{ParentClassFinder}', $this->parent_finder, $content);
        $content = str_replace('{ParentClassController}', $this->parent_controller, $content);
        return $content;
    }
}