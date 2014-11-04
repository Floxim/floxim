<?php

namespace Floxim\Floxim\System;

class Modules
{

    public function getAll()
    {
        static $all_modules_data = false;
        if ($all_modules_data === false) {
            $all_modules_data = fx::collection();
            $vendor_dirs = glob(fx::path('@module/*'));
            foreach ($vendor_dirs as $vendor_dir) {
                $vendor_name = fx::path()->fileName($vendor_dir);
                $vendor_modules = glob($vendor_dir.'/*');
                foreach ($vendor_modules as $mod_dir) {
                    $module_name = fx::path()->fileName($mod_dir);
                    //fx::debug($vendor_name.'/'.$module_name);
                    $module = array(
                        'vendor' => $vendor_name,
                        'name' => $module_name
                    );
                    $module_class = $vendor_name."\\".$module_name."\\Module";
                    if (class_exists($module_class)) {
                        $module['object'] = new $module_class;
                    }
                    $all_modules_data[]= $module;
                }
            }
        }
        return $all_modules_data;
    }

    /**
     * Check installed module by keyword
     *
     * @param string module keyword
     * @param bool `Installed` column
     *
     * @return array module data or false
     */
    public function getByKeyword($keyword)
    {
        $all_modules_data = $this->getData();

        foreach ($all_modules_data AS $module_data) {
            if ($module_data['keyword'] == $keyword) {
                return $module_data;
            }
        }

        return false;
    }

    public function isInstalled($keyword)
    {
        return (bool)$this->getByKeyword($keyword);
    }
}