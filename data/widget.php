<?php

class fx_data_widget extends fx_data {

    
    public function get_by_id($id) {
        return $this->where(is_numeric($id) ? 'id' : 'keyword', $id)->one();
    }
    
    public function get_multi_lang_fields() {
        return array(
            'name',
            'description'
        );
    }
}