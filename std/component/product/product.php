<?php
class fx_controller_component_product extends fx_controller_component_page {
    public function do_listing_by_category() {
        $this->set_param('skip_parent_filter', true);
        $this->listen('query_ready', function($query) {
            $ids = fx::data('content_classifier_linker')->
                    where('classifier_id', fx::env('page')->get('id'))->
                    select('content_id')->
                    get_data()->get_values('content_id');
            $query->where('id', $ids);
        });
        $this->set_param('skip_infoblock_filter',true);
        return $this->do_list_infoblock();
    }    
    
}
?>