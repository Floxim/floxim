<?php
class fx_controller_component_section extends fx_controller_component_page {

   public function do_list_infoblock() {
        $c_page_id  = fx::env('page')->get('id');
        $path = fx::env('page')->get_parent_ids();
        $path []= $c_page_id;
        $submenu_type = $this->get_param('submenu');
        switch ($submenu_type) {
            case 'none':
                break;
            case 'active':
                $this->set_param('parent_id', $path);
                break;
            case 'all': default:
                $this->set_param('parent_id', false);
                break;
        }
        if ($submenu_type !== 'none') {
            $this->on_items_ready(function($items, $ctr) {
                foreach ($items as $item) {
                    $ctr->accept_content(array(
                        'title' => fx::alang('Add subsection to','component_section')
                                    . ' &quot;' . $item['name'].'&quot;',
                        'parent_id' => $item['id']
                    ), $item);
                }
            });
        }
        $res = parent::do_list_infoblock();
        return $res;
    }
    
    public function do_list() {
        $this->listen('items_ready', function($items, $ctr) {
            $extra_ibs =  $ctr->get_param('extra_infoblocks', array());
            if (is_array($extra_ibs) && count($extra_ibs) > 0) {
                $extra_ibs = fx::data('infoblock', $extra_ibs);
                foreach ($extra_ibs as $extra_ib) {
                    $extra_res = $extra_ib->get_result();
                    if (isset($extra_res['items'])) {
                        $items->concat($extra_res['items']);
                    }
                }
            }
            fx::data('content_page')->make_tree($items, 'submenu');
        });
        return parent::do_list();
    }

    public function do_list_selected () {
        $c_page_id  = fx::env('page')->get('id');
        $path = fx::env('page')->get_parent_ids();
        $path []= $c_page_id;
        $submenu_type = $this->get_param('submenu');
        $this->listen('query_ready', function($q) use ($path, $submenu_type) {
            switch ($submenu_type) {
                case 'all':
                    $q->clear_where('parent_id');
                    break;
                case 'active':
                    $q->clear_where('parent_id')->where('parent_id', $path);
                    break;
            }
        });
        $ctr = $this;
        $recurcive_children = function ($items) use (&$recurcive_children, $ctr) {
            $sub_items = $ctr
                    ->get_finder()
                    ->where('parent_id', $items->get_values('id'))
                    ->all();
            if (!count($sub_items)>0) {
                return;
            }
            $items->attache_many($sub_items, 'parent_id', 'children');
            $recurcive_children($sub_items);

        };
        $this->listen('items_ready', function ($items) use ($recurcive_children, $submenu_type) {
            $recurcive_children($items);
            if ($submenu_type == 'none') {
                $items->apply(function($item){
                    unset($item['children']);
                });
            }
        });
        return parent::do_list_selected();
    }

    public function do_list_submenu() {
        $source = $this->get_param('source_infoblock_id');
        $path = fx::env('page')->get_path();
        if (count($path) < 2) {
            return;
        }
        if (isset($path[1])) {
            $this->listen('query_ready', function($q) use ($path, $source){
                $q->where('parent_id', $path[1]->get('id'))->where('infoblock_id', $source);
            });
        }
        return $this->do_list();
    }
    
    public function do_breadcrumbs() {
        if ( !($page_id = $this->get_param('page_id'))) {
            $page_id = fx::env('page_id');
        }
        $essence_page = fx::data('content_page',$page_id);
        $parents = $essence_page->get_parent_ids();
        $essence_page['active'] = true;
        if ($this->get_param('header_only')) {
            $pages = new fx_collection(array($essence_page));
        } else {
            $pages = fx::data('content_page', $parents);
            $pages[]= $essence_page;
        }
        return array('items' => $pages);
    }
}