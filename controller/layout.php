<?php
class fx_controller_layout extends fx_controller {
    
    public function show() {
        $page_id = $this->get_param('page_id', fx::env('page_id'));
        $layout_id = $this->get_param('layout_id', fx::env('layout'));
        
        // add admin files bundle BEFORE site scripts/styles
        if (! $this->get_param('ajax_mode') && fx::is_admin()) {
            fx_controller_admin::add_admin_files();
        }
        $page_infoblocks = fx::router('front')->get_page_infoblocks(
            $page_id, 
            $layout_id
        );
        fx::page()->set_infoblocks($page_infoblocks);
        $path = fx::data('content_page', $page_id)->get_path();
        $current_page = $path->last();
        $res = array(
            'page_id' => $page_id,
            'path' => $path,
            'current_page' => $current_page
        );
        return $res;
    }
    
    public function postprocess($html) {
        if ($this->get_param('ajax_mode')) {
            $html = preg_replace("~^.+?<body[^>]*?>~is", '', $html);
            $html = preg_replace("~</body>.+?$~is", '', $html);
        } else {
            $page = fx::env('page');
            $meta_title = empty($page['title']) ? $page['name'] : $page['title'];
            $this->_show_admin_panel();
            $html = fx::page()->set_metatags('title',$meta_title)
                                ->set_metatags('description',$page['description'])
                                ->set_metatags('keywords',$page['keywords'])
                                ->post_process($html);
        }
        return $html;
    }
    
    protected $_layout = null;


    protected function _get_layout() {
        if ($this->_layout) {
            return $this->_layout;
        }
        $page = fx::data('content_page', $this->get_param('page_id'));
        if ($page['layout_id']) {
            $layout_id = $page['layout_id'];
        } else {
            $site = fx::data('site', $page['site_id']);
            $layout_id = $site['layout_id'];
        }
        $this->_layout = fx::data('layout', $layout_id);
        return $this->_layout;
    }
    
    public function find_template() {
        $layout = $this->_get_layout();
        $tpl_name = 'layout_'.$layout['keyword'];
        return fx::template($tpl_name);
    }
    
    protected function _show_admin_panel() {
        if (!fx::is_admin()) {
            return;
        }
        // initialize the admin panel
        
        $p = fx::page();
        $js_config = new fx_admin_configjs();
        $p->add_js_text("\$fx.init(".$js_config->get_config().");");
        $p->set_after_body(fx_controller_admin_adminpanel::panel_html());        
    }
}