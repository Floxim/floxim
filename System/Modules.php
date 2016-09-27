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
                if (!is_dir($vendor_dir)) {
                    continue;
                }
                $vendor_name = fx::path()->fileName($vendor_dir);
                $vendor_modules = glob($vendor_dir.'/*');
                foreach ($vendor_modules as $mod_dir) {
                    if (!is_dir($mod_dir)) {
                        continue;
                    }
                    $module_name = fx::path()->fileName($mod_dir);
                    
                    $module_keyword = fx::util()->camelToUnderscore($vendor_name)
                                        .'.'
                                        .fx::util()->camelToUnderscore($module_name);
                    
                    $module = array(
                        'vendor' => $vendor_name,
                        'name' => $module_name,
                        'keyword' => $module_keyword
                    );
                    $module_class = $vendor_name."\\".$module_name."\\Module";
                    if (class_exists($module_class)) {
                        $obj = new $module_class;
                        $obj->setPayload('module_data', $module);
                        $module['object'] = $obj;
                    }
                    $all_modules_data[]= $module;
                }
            }
        }
        return $all_modules_data;
    }
}