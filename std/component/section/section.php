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
                        'title' => fx::alang('Add subsection','component_section'),
                        'parent_id' => $item['id']
                    ), $item);
                }
            });
        }
        $res = parent::do_list_infoblock();
        return $res;
    }
    
    public function do_list() {
        $this->on_items_ready(function($items, $ctr) {
            $extra_ibs =  $ctr->get_param('extra_infoblocks', array());
            if (is_array($extra_ibs) && count($extra_ibs) > 0) {
                foreach ($extra_ibs as $extra_ib_id) {
                    $extra_ib = fx::data('infoblock', $extra_ib_id);
                    if ($extra_ib) {
                        $extra_q = $extra_ib
                                    ->init_controller()
                                    ->get_finder()
                                    ->where('infoblock_id', $extra_ib_id);
                        $extra_items = $extra_q->all();
                        $items->concat($extra_items);
                    }
                }
            }
            $items->unique();
            $extra_roots = $ctr->get_param('extra_root_ids');
            if (!$extra_roots) {
                $extra_roots = array();
            }
            fx::data('content_page')
                ->make_tree($items, 'submenu', $extra_roots)
                ->add_filter('parent_id', $this->_get_parent_id());
        });
        return parent::do_list();
    }
    
    protected function _add_submenu_items($items) {
        $submenu_type = $this->get_param('submenu');
        if ($submenu_type === 'none') {
            return;
        }
        $finder = fx::content($this->get_component()->get('keyword'));
        switch ($submenu_type) {
            case 'all':
                $finder->descendants_of($items);
                break;
            case 'active':
                $path = fx::env('page')->get_path();
                $finder->where('parent_id', $path->get_values('id'));
                break;
        }
        $items->concat($finder->all());
    }

    public function do_list_selected () {
        $this->on_items_ready(function($items, $ctr) {
            $ctr->set_param('extra_root_ids', $items->get_values('id'));
        });
        $this->on_items_ready(array($this, '_add_submenu_items'));
        return parent::do_list_selected();
    }
    
    public function do_list_filtered() {
        $this->on_items_ready(array($this, '_add_submenu_items'));
        return parent::do_list_filtered();
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
        $essence_page['active'] = true;
        if ($this->get_param('header_only')) {
            $pages = new fx_collection(array($essence_page));
        } else {
            $pages = $essence_page->get_path();
        }
        return array('items' => $pages);
    }

    /**
     * Return allow parent pages for current component
     *
     * @return fx_collection
     */
    protected function _get_allow_parent_pages() {
        /**
         * Retrieve pages object
         */
        $pages=fx::data('content_section')->where('site_id',fx::env('site_id'))->all();
        $additional_parent_ids=array_diff($pages->get_values('parent_id'),$pages->get_values('id'));
        $additional_parent_ids=array_unique($additional_parent_ids);
        $pages_add=fx::data('content')->where('id',$additional_parent_ids)->all();

        return $pages_add->concat($pages);
    }
}