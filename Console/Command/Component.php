<?php

namespace Floxim\Floxim\Console\Command;

use Floxim\Floxim\System\Console;
use Floxim\Floxim\System\Fx as fx;

class Component extends Console\Command
{

    /**
     * Create / load component entity from passed args
     *
     * @param string|int $keyword Keyword or id for existing component
     * @param string|bool $name
     * @param string|bool $itemName
     * @param string $parent Parent component keyword
     * @param bool $overwrite Overwrite existing component
     *
     * @return \Floxim\Floxim\Component\Component\Entity
     */
    public function loadComponent($keyword, $name = false, $itemName = false, $parent = 'content')
    {
        if (is_numeric($keyword)) {
            $com = fx::data('component', $keyword);
        } else {
            $keyword_parts = explode('.', $keyword);
            if (count($keyword_parts) != 3) {
                $this->usageError('Name need format "vendor.module.name"');
            }
            $name = $name ? $name : preg_replace("~^.+\..+\.~", '', $keyword);
            $itemName = $itemName ? $itemName : $name;
            $parent_id = fx::data('component', $parent)->get('id');
            $com = fx::data('component')->create(array(
                'keyword'   => $keyword,
                'name'      => $name,
                'item_name' => $itemName,
                'parent_id' => $parent_id
            ));
        }
        return $com;
    }

    public function doNew($keyword, $name = false, $itemName = false, $parent = 'content', $overwrite = false)
    {
        $com = call_user_func_array(array($this, 'loadComponent'), func_get_args());
        $this->scaffold($com);
        $com->save();
        echo "\nYour component has been created successfully under " . $com->getPath() . ".\n";
    }

    public function doScaffold($keyword, $name = false, $itemName = false, $parent = 'content', $overwrite = false)
    {
        $com = call_user_func_array(array($this, 'loadComponent'), func_get_args());
        $this->scaffold($com);
    }

    protected function scaffold(\Floxim\Floxim\Component\Component\Entity $com)
    {
        $source_path = fx::path('floxim', '/Console/protected/component');
        $component_path = $com->getPath();
        $file_list = $this->buildFileList($source_path, $component_path);
        foreach ($file_list as &$file_data) {
            $file_data['callback_content'] = function ($content) use ($com) {
                return Component::replacePlaceholder($content, $com);
            };
        }
        $this->copyFiles($file_list);
    }

    protected static function replacePlaceholder($content, $com)
    {
        $parent = $com['parent'];
        $par_ns = $parent->getNamespace();
        $map = array(
            'Vendor'                => $com->getVendorName(),
            'Module'                => $com->getModuleName(),
            'Component'             => $com->getOwnName(),
            'ParentClassEntity'     => $par_ns . '\\Entity',
            'ParentClassFinder'     => $par_ns . '\\Finder',
            'ParentClassController' => $par_ns . '\\Controller'
        );
        foreach ($map as $prop => $val) {
            $content = str_replace('{' . $prop . '}', $val, $content);
        }
        return $content;
    }
}