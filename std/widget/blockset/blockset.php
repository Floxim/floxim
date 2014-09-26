<?php
class fx_controller_widget_blockset extends fx_controller_widget {
    
    protected function getFakeIb() {
        static $fake_counter = 0;
        $fake_ib = fx::data('infoblock')->create();
        $fake_ib['id'] = 'fake-'.$fake_counter++;
        $fake_ib['name'] = fx::alang('New infoblock');
        return $fake_ib;
    }
    
    public function doShow() {
        $area_name = 'blockset_'.$this->input['infoblock_id'];
        $blocks = fx::page()->getAreaInfoblocks($area_name);
        if ($this->getParam('is_fake')) {
            foreach (range(1,3)as $n) {
                $blocks[]= $this->getFakeIb();
            }
        } else {
            if ($this->getParam('add_new_infoblock')) {
                $blocks[]= $this->getFakeIb();
            }
        }
        $res = parent::doShow();
        $res += array('items' => $blocks, 'area' => $area_name);
        
        if (count($blocks) == 0 && !fx::isAdmin()) {
            $this->_meta['disabled'] = true;
        }
        return $res;
    }
}