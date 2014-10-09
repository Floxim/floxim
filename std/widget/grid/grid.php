<?php
class fx_controller_widget_grid extends fx_controller_widget {
    
    public function process() {
        $res = parent::process();
        if (isset($res['areas'])) {
            foreach ($res['areas'] as $i => &$area) {
                if (!isset($area['id'])) {
                    $area['id'] = 'grid_'.(isset($area['keyword']) ? $area['keyword'] : $i).'_'.$this->get_param('infoblock_id');
                }
            }
        }
        return $res;
    }
    
    public function do_two_columns() {
        return array(
            'areas' => array(
                array('name' => 'Sidebar', 'keyword' => 'sidebar'),
                array('name' => 'Data', 'keyword' => 'content')
            )
        );
    }
}