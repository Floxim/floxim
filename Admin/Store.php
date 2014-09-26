<?php

namespace Floxim\Floxim\Admin;

class Store extends Floximsite {

    public function getItems($type, $filter = array(), $reason = 'first', $position = 0) {
        $post = $this->getBasePost();
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

    public function getFile($store_id) {
        $post = $this->getBasePost();
        $post['action'] = 'get_file';
        $post['store_id'] = $store_id;

        $result = $this->send($post);
        return $result;
    }

    public function getInfo($store_id) {
        $post = $this->getBasePost();
        $post['action'] = 'get_info';
        $post['store_id'] = $store_id;

        $result = json_decode($this->send($post), 1);
        return $result;
    }

    protected function getBasePost() {
        $post = parent::getBasePost();
        $post['entity'] = 'module_store';
        return $post;
    }

}
