<?php
class fx_controller_widget_blockset extends fx_controller_widget {
    
    protected function _get_fake_ib() {
        static $fake_counter = 0;
        $fake_ib = fx::data('infoblock')->create();
        $fake_ib['id'] = 'fake-'.$fake_counter++;
        $fake_ib['name'] = fx::alang('New infoblock');
        return $fake_ib;
    }
    
    public function do_show() {
        $area_name = 'blockset_'.$this->input['infoblock_id'];
        $blocks = fx::page()->get_area_infoblocks($area_name);
        if ($this->get_param('is_fake')) {
            foreach (range(1,3)as $n) {
                $blocks[]= $this->_get_fake_ib();
            }
        } else {
            if ($this->get_param('add_new_infoblock')) {
                $blocks[]= $this->_get_fake_ib();
            }
        }
        $res = parent::do_show();
        $res += array('items' => $blocks, 'area' => $area_name);
        
        if (count($blocks) == 0 && !fx::is_admin()) {
            $this->_meta['disabled'] = true;
        }
        return $res;
    }
}