<?php

namespace Floxim\Floxim\System;

class Modules
{

    public function getData()
    {
        static $all_modules_data = false;
        if ($all_modules_data === false) {
            $all_modules_data = fx::db()->getResults("SELECT * FROM `{{module}}`");
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