<?php
class fx_data_infoblock_visual extends fx_data {
    public function __construct() {
        parent::__construct();
        $this->classname = 'fx_infoblock_visual';
        $this->serialized = array('wrapper_visual', 'template_visual');
        $this->order('priority');
    }
    
    public function get_for_infoblocks(fx_collection $infoblocks, $layout_id) {
        $ib_ids = $infoblocks->get_values('id');
        $this->where('infoblock_id', $ib_ids);
        if ($layout_id) {
            $this->where('layout_id', $layout_id);
        }
        return $this->all();
    }
}