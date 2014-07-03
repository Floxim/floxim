<?php

class fx_hook_manager {

    public function create($name=null,$type=null,$params=array()) {
        if (is_null($name) and !is_null($type)) {
            $name=$type;
        }
        $name='h'.date('Ymd_His').(!is_null($name) ? "_$name" : '');
        // get code for before
        if (!is_null($type) and method_exists($this,"create_before_for_{$type}")) {
            $code_before=call_user_func(array($this,"create_before_for_{$type}"),$params);
        } else {
            $code_before='';
        }
        // get code for after
        if (!is_null($type) and method_exists($this,"create_after_for_{$type}")) {
            $code_after=call_user_func(array($this,"create_after_for_{$type}"),$params);
        } else {
            $code_after='';
        }
        $content="<?php
        class {$name} {

            // Run before patch
            public function before() {
                {$code_before}
            }

            // Run after patch
            public function after() {
                {$code_after}
            }

        }";

        $dir=fx::path('root', '/update/hook');
        try {
            if (file_exists($dir)) {
                fx::files()->mkdir($dir);
            }
            fx::files()->writefile($dir.'/'.$name.'.php', $content);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate code for creating component
     *
     * @param $params
     *
     * @return string
     */
    protected function create_after_for_component_create($params) {
        $data_export=var_export($params['data'],true);
        $code='$data='.$data_export.";\n";
        $data=$params['data'];

        // replace parent_id by search keyword
        if ($data['parent_id']) {
            if ($component_parent=fx::data("component",$data['parent_id'])) {
                $code.='if ($component_parent=fx::data("component")->where("keyword", "'.$component_parent['keyword'].'")->one()) {
                    $data["parent_id"]=$component_parent["id"];
                }'."\n";
            }
        }

        $code.='
        $component=fx::data("component")->create($data);
        $component->save();
        $component->create_content_table();
        ';
        return $code;
    }
}