<?php
class fx_controller_component_person extends fx_controller_component_page {
    public function do_list_infoblock() {
        $this->_with_contacts();
        return parent::do_list_infoblock();
    }
    public function do_record() {
        $this->_with_contacts();
        return parent::do_record();
    }
   
    protected function _with_contacts () {
        $this->listen('query_ready', function (fx_data $query) {
            $query->with('contacts');
        });
    }
    
} 
?>