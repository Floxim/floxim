<?php

class fx_admin_store extends fx_admin_floximsite {

    public function get_items($type, $filter = array(), $reason = 'first', $position = 0) {
        $post = $this->get_base_post();
        $post['action'] = 'get_items';
        $post['type'] = $type;
        $post['filter'] = $filter;
        $post['reason'] = $reason;
        $post['position'] = $position;
        
        $result = $this->send($post);
        
        if ($result !== false) {
            $result = json_decode($result, 1);
        }
        
        
        return $result;
    }

    public function get_file($store_id) {
        $post = $this->get_base_post();
        $post['action'] = 'get_file';
        $post['store_id'] = $store_id;

        $result = $this->send($post);
        return $result;
    }

    public function get_info($store_id) {
        $post = $this->get_base_post();
        $post['action'] = 'get_info';
        $post['store_id'] = $store_id;

        $result = json_decode($this->send($post), 1);
        return $result;
    }

    protected function get_base_post() {
        $post = parent::get_base_post();
        $post['essence'] = 'module_store';
        return $post;
    }

}
