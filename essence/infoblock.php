<?php
class fx_infoblock extends fx_essence {
    
    protected $_visual = array();
    
    public function set_visual(fx_infoblock_visual $visual) {
        $this->_visual[$visual['layout_id']] = $visual;
    }
    
    public function get_visual($layout_id = null) {
        if (!$layout_id) {
            $layout_id = fx::env('layout');
        }
        if (!isset($this->_visual[$layout_id])) {
            $stored = fx::data('infoblock_visual')->
                    where('infoblock_id', $this['id'])-> 
                    where('layout_id', $layout_id)->
                    one();
            if ($stored) {
                $this->_visual[$layout_id] = $stored;
            } else {
                $i2l_params = array(
                    'layout_id' => $layout_id,
                    'is_stub' => true
                );
                if (($ib_id = $this->get('id'))) {
                    $i2l_params['infoblock_id'] = $ib_id;
                }
                $this->_visual[$layout_id] = fx::data('infoblock_visual')->create($i2l_params);
            }
        }
        return $this->_visual[$layout_id];
    }
    
    public function get_type() {
        return 'infoblock';
    }
    
    public function is_layout() {
        return $this->get_prop_inherited('controller') == 'layout' && $this->get_prop_inherited('action') == 'show';
    }
    
    protected function _after_delete() {
        $killer = function($cv) {
            $cv->delete();
        };
        fx::data('infoblock_visual')->where('infoblock_id', $this['id'])->all()->apply($killer);
        fx::data('infoblock')->where('parent_infoblock_id', $this['id'])->all()->apply($killer);
    }
    
    public function get_owned_content() {
        if ($this['action'] != 'list_infoblock') {
            return false;
        }
        $content_type = fx::controller($this['controller'])->get_content_type();
        $content = fx::data('content_'.$content_type)->
                    where('infoblock_id',$this['id'])->
                    all();
        return $content;
    }
    
    public function get_parent_infoblock() {
        if ( !( $parent_ib_id = $this->get('parent_infoblock_id'))) {
            return;
        }
        return fx::data('infoblock', $parent_ib_id);
    }
    
    public function get_prop_inherited($path_str, $layout_id = null) {
        $own_result = null;
        $parent_result = null;
        $path = explode(".", $path_str);
        if ($path[0] == 'visual') {
            $c_i2l = $this->get_visual($layout_id);
            $vis_path_str = join(".", array_slice($path, 1));
            $own_result = fx::dig($c_i2l, $vis_path_str);
        } else {
            $own_result = fx::dig($this, $path_str);
        }
        if ($own_result && !is_array($own_result)) {
            return $own_result;
        }
        if ( ($parent_ib = $this->get_parent_infoblock()) ) {
            $parent_result = $parent_ib->get_prop_inherited($path_str);
        }
        if (is_array($own_result) && is_array($parent_result)) {
            return array_merge($parent_result, $own_result);
        }
        return $own_result ? $own_result : $parent_result;
    }
    
    public function get_root_infoblock() {
        $cib = $this;
        while ($cib['parent_infoblock_id']) {
            $cib = $cib->get_parent_infoblock();
        }
        return $cib;
    }
    
    public function init_controller() {
        $controller = $this->get_prop_inherited('controller');
        $action = $this->get_prop_inherited('action');
        if (!$controller || !$action) {
            return null;
        }
        $ctr = fx::controller($controller.'.'.$action, $this->get_prop_inherited('params'));
        $ctr->set_param('infoblock_id', $this['id']);
        return $ctr;
    }
    
    protected $controller_cache = null;
    protected function _get_ib_controller() {
        if (!$this->controller_cache) {
            $this->controller_cache = $this->init_controller();
        }
        return $this->controller_cache;
    }
    
    public function add_params($params) {
        $c_params = $this['params'];
        if (!is_array($c_params)) {
            $c_params = array();
        }
        $this->data['params'] = array_merge($c_params, $params);
        return $this;
    }
    
    public function is_available_on_page($page) {
        if ($this['site_id'] != $page['site_id']) {
            return;
        }
        
        $ids = $page->get_parent_ids();
        $ids []= $page['id'];
        $ids []= 0; // root
        
        if (!in_array($this['page_id'], $ids)) {
            return false;
        }
        
        // if page_id=0 blunt - all pages, ignored by the filter scope.pages
        if ($this['page_id'] != 0) {
            // scope - "this page only"
            if (fx::dig($this, 'scope.pages') == 'this' && $this['page_id'] != $page['id']) {
                return false;
            }
            // scope - "this level, and we look parent
            if (fx::dig($this, 'scope.pages') == 'children' && $this['page_id'] == $page['id']) {
                return false;
            }
        }
        // check for compliance with the filter type page
        $scope_page_type = fx::dig($this, 'scope.page_type');
        if ( $scope_page_type && $scope_page_type != $page['type'] ) {
            return false;
        }
        return true;
    }
    
    public function get_scope_string() {
        list($cib_page_id, $cib_pages, $cib_page_type) = array(
            $this['page_id'], 
            $this['scope']['pages'],
            $this['scope']['page_type']
        );
        
        if ($cib_page_id == 0) {
            $cib_page_id = fx::data('site', $this['site_id'])->get('index_page_id'); //$path[0]['id'];
        }
        if ($cib_pages == 'this') {
            $cib_page_type = '';
        } 
        if ($cib_pages == 'all' || empty($cib_pages)) {
            $cib_pages = 'descendants';
        }
        
        return $cib_page_id.'-'.$cib_pages.'-'.$cib_page_type;
    }
    
    public function read_scope_string($str) {
        list($scope_page_id, $scope_pages, $scope_page_type) = explode("-", $str);
        return array(
            'pages' => $scope_pages,
            'page_type' => $scope_page_type,
            'page_id' => $scope_page_id
        );
    }


    public function set_scope_string($str) {
        $ss = $this->read_scope_string($str);
        $new_scope = array(
            'pages' => $ss['pages'],
            'page_type' => $ss['page_type']
        );
        $this['scope'] = $new_scope;
        $this['page_id'] = $ss['page_id'];
    }
    
    /**
     * Returns number meaning "strength" (exactness) of infoblock's scope
     */
    public function get_scope_weight() {
        $s = $this['scope'];
        $pages = isset($s['pages']) ? $s['pages'] : 'all';
        $page_type = isset($s['page_type']) ? $s['page_type'] : '';
        if ($pages == 'this') {
            return 4;
        }
        if ($page_type) {
            if ($pages == 'children') {
                return 3;
            } else {
                return 2;
            }
        }
        if ($pages == 'children') {
            return 1;
        }
        return 0;
    }
    
    public function is_fake() {
        return preg_match("~^fake~", $this['id']);
    }
    
    public function override_param($param, $value) {
        $params = $this->get_prop_inherited('params');
        $params[$param] = $value;
        $this['params'] = $params;
    }
    
    public function override($data) {
        if (isset($data['params'])) {
            $data['params']['is_overriden'] = true;
            $this['params'] = $data['params'];
        }
        if (isset($data['visual'])) {
            $vis = $this->get_visual();
            //$vis['']
            foreach ($data['visual'] as $k => $v) {
                $vis[$k] = $v;
            }
        }
        if (isset($data['controller']) && $data['controller'] && $data['controller'] !== 'null') {
            $ctr = explode(".", $data['controller']);
            $this['controller'] = $ctr[0];
            $this['action'] = $ctr[1];
        }
    }


    public function render() {
        
        $output = '';
        if (fx::is_admin() || (!$this->is_disabled() && !$this->is_hidden() )) {   
            $output = $this->get_output();
            $output = $this->_wrap_output($output);
        }
        $output = $this->_add_infoblock_meta($output);
        if ( ($controller = $this->_get_ib_controller())) {
            $output = $controller->postprocess($output);
        }
        return $output;
    }
    
    protected $result_is_cached = false;
    protected $result_cache = null;
    /**
     * get result (plain data) from infoblock's controller
     */
    public function get_result() {
        if ($this->result_is_cached) {
            return $this->result_cache;
        }
        if ($this->is_fake()) {
            $this->data['params']['is_fake'] = true;
        }
        $controller = $this->_get_ib_controller();
        if (!$controller) {
            $res = false;
        } else {
            try {
                $res = $controller->process();
            } catch (Exception $e) {
                fx::log('controller exception', $controller, $e);
                $res = '';
            }
        }
        $this->result_is_cached = true;
        $this->result_cache = $res;
        return $res;
    }
    
    /**
     * get controller_meta from the result 
     */
    protected function _get_result_meta() {
        $res = $this->get_result();
        if ( ! (is_array($res) || $res instanceof ArrayAccess)) {
            return array();
        }
        return isset($res['_meta']) ? $res['_meta'] : array();
    }
    
    
    public function is_disabled() {
        $res = $this->get_result();
        if ($res === false) {
            return true;
        }
        $meta = $this->_get_result_meta();
        return isset($meta['disabled']) && $meta['disabled'];
    }
    
    public function get_template() {
        $tpl_name = $this->get_prop_inherited('visual.template');
        $tpl = fx::template($tpl_name);
        return $tpl;
    }
    
    protected $output_is_cached = false;
    protected $output_cache = null;
    protected $output_is_subroot = false;
    /**
     * get result rendered by ib's template (with no wrapper and meta)
     */
    public function get_output(){
        if ($this->output_is_cached) {
            return $this->output_cache;
        }
        $result = $this->get_result();
        if ($result === false) {
            return false;
        }
        $meta = $this->_get_result_meta();
        
        if ($meta['disabled']) {
            return false;
        }
        $tpl = $this->get_template();
        $tpl_params = $this->get_prop_inherited('visual.template_visual');
        if (!is_array($tpl_params)) {
            $tpl_params = array();
        }
        
        // @todo: what if the result is object?
        if (is_array($result)) {
            $tpl_params = array_merge($tpl_params, $result);
        }
        $tpl_params['infoblock'] = $this;
        $this->output_cache = $tpl->render($tpl_params);
        $this->output_is_subroot = $tpl->is_subroot;
        $this->output_is_cached = true;
        return $this->output_cache;
    }
    
    /**
     * wrap ib's output
     */
    protected function _wrap_output($output) {
        $wrapper = $this->get_prop_inherited('visual.wrapper');
        if (!$wrapper) {
            return $output;
        }
        $tpl_wrap = fx::template($wrapper);
        if (!$tpl_wrap->has_action()) {
            return $output;
        }
        $tpl_wrap->is_wrapper(true);
        $wrap_params = $this->get_prop_inherited('visual.wrapper_visual');
        if (!is_array($wrap_params)) {
            $wrap_params = array();
        }
        $wrap_params['content'] = $output;
        $wrap_params['infoblock'] = $this;
        $result = $tpl_wrap->render($wrap_params);
        $this->output_is_subroot = $tpl_wrap->is_subroot;
        return $result;
    }
    
    public function is_hidden() {
        $controller_meta = $this->_get_result_meta();
        return isset($controller_meta['hidden']) && $controller_meta['hidden'];
    }
    
    protected function _add_infoblock_meta($html_result) {
        $controller_meta = $this->_get_result_meta();
        if (!fx::is_admin() && !$controller_meta['ajax_access']) {
            return $html_result;
        }
        $ib_info = array('id' => $this['id']);
        if (($vis = $this->get_visual()) && $vis['id']) {
            $ib_info['visual_id'] = $vis['id'];
        }
        
        $ib_info['controller'] = $this->get_prop_inherited('controller')
                                    .'.'.$this->get_prop_inherited('action');
        
        $meta = array(
            'data-fx_infoblock' => $ib_info,
            'class' => 'fx_infoblock fx_infoblock_'.$this['id']
        );
        
        if ($this->is_fake()) {
            $meta['class'] .= ' fx_infoblock_fake';
            if (!$this->_get_ib_controller()) {
                $controller_meta['hidden_placeholder'] = fx::alang('Fake infoblock data', 'system');
            }
        }
         
        if ($controller_meta['hidden']) {
            $meta['class'] .= ' fx_infoblock_hidden';
        }
        if (count($controller_meta) > 0 && fx::is_admin()) {
            $meta['data-fx_controller_meta'] = $controller_meta;
        }
        if ($this->is_layout()) {
            $meta['class'] .= ' fx_unselectable';
            $html_result = preg_replace_callback(
                '~<body[^>]*?>~is', 
                function($matches) use ($meta) {
                    $body_tag = fx_template_html_token::create_standalone($matches[0]);
                    $body_tag->add_meta($meta);
                    return $body_tag->serialize();
                }, 
                $html_result
            );
        } elseif ($this->output_is_subroot && preg_match("~^(\s*?)(<[^>]+?>)~", $html_result)) {
            $html_result = preg_replace_callback(
                "~^(\s*?)(<[^>]+?>)~", 
                function($matches) use ($meta) {
                    $tag = fx_template_html_token::create_standalone($matches[2]);
                    $tag->add_meta($meta);
                    return $matches[1].$tag->serialize();
                }, 
                $html_result
            );
        } else {
            $html_proc = new fx_template_html($html_result);
            $html_result = $html_proc->add_meta(
                $meta, 
                mb_strlen($html_result) > 1000 // auto wrap long html blocks without parsing
            );
        }
        return $html_result;
    }
}