<?php

namespace Floxim\Floxim\Component\InfoblockVisual;

use Floxim\Floxim\System;

class Finder extends System\Data {
    public function __construct() {
        parent::__construct();
        // todo: psr0 need verify
        $this->classname = 'fx_infoblock_visual';
        $this->serialized = array('wrapper_visual', 'template_visual');
        $this->order('priority');
    }
    
    public function get_for_infoblocks(System\Collection $infoblocks, $layout_id) {
        $ib_ids = $infoblocks->get_values('id');
        $this->where('infoblock_id', $ib_ids);
        if ($layout_id) {
            $this->where('layout_id', $layout_id);
        }
        return $this->all();
    }
}