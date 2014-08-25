<?php
class fx_data_option extends fx_data {

    public function __construct() {
        parent::__construct();
        $this->serialized = array('value');
    }

}