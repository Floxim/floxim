<?php
class fx_system_modules {

    public function get_data() {
        static $all_modules_data = false;
        if ($all_modules_data === false) {
            $all_modules_data = fx::db()->get_results("SELECT * FROM `{{module}}`");
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
    public function get_by_keyword($keyword) {
        $all_modules_data = $this->get_data();

        foreach ($all_modules_data AS $module_data) {
            if ($module_data['keyword'] == $keyword) {
                return $module_data;
            }
        }

        return false;
    }

    public function is_installed($keyword) {
        return (bool) $this->get_by_keyword($keyword);
    }
}