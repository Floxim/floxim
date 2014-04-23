<?php
class fx_infoblock_visual extends fx_essence {
    protected  function _before_save() {
        parent::_before_save();
        unset($this['is_stub']);
        if (!$this['priority'] && $this['layout_id']) {
            $last_vis = fx::data('infoblock_visual')
                            ->where('layout_id', $this['layout_id'])
                            ->where('area', $this['area'])
                            ->order(null)
                            ->order('priority', 'desc')
                            ->one();
            $this['priority'] = $last_vis['priority']+1;
        }
        $files = $this->get_modified_file_params();
        foreach ($files as $f) {
            fx::files()->rm($f);
        }
    }
    
    protected  function _before_delete() {
        parent::_before_delete();
        $files = $this->get_file_params();
        foreach ($files as $f) {
            fx::files()->rm($f);
        }
    }
    
    /**
     * find file paths inside params collection and drop them
     */
    public function get_file_params(fx_collection $params = null) {
        if (!$params) {
            $params = fx::collection($this['template_visual'])->concat($this['wrapper_visual']);
        }
        $files_path = fx::path('files');
        $res = array();
        $path = fx::path();
        foreach ($params as $p) {
            if (empty($p)) {
                continue;
            }
            if ($path->is_inside($p, $files_path) && $path->is_file($p)) {
                $res []= $p;
            }
        }
        return $res;
    }
    
    public function get_modified_file_params() {
        $params = fx::collection();
        foreach (array('template_visual', 'wrapper_visual') as $field) {
            if (!$this->is_modified($field)) {
                continue;
            }
            $new = $this[$field];
            $old = $this->get_old($field);
            if (!$old || !is_array($old)) {
                continue;
            }
            foreach ($old as $opk => $opv) {
                if (!isset($new[$opk]) || $new[$opk] != $opv) {
                    $params []= $opv;
                }
            }
        }
        return $this->get_file_params($params);
    }
}