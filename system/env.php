<?php
class fx_system_env {
    protected $current = array();

  
    public function set($var, $val) {
        $setter = 'set_'.$var;
        if (method_exists($this, $setter)) {
            call_user_func(array($this, $setter), $val);
        } else {
            $this->current[$var] = $val;
        }
    }
  
    public function get($var) {
        $getter = 'get_'.$var;
        if (method_exists($this, $getter)) {
            return call_user_func(array($this, $getter));
        }
        return isset($this->current[$var]) ? $this->current[$var] : null;
    }

    public function set_site ( $env ) {
        $this->current['site'] = $env;
    }


    /**
     * @return fx_site
     */
    public function get_site () {
        if (!isset($this->current['site'])) {
            $this->current['site'] = fx::data('site')->get_by_host_name($_SERVER['HTTP_HOST'], 1);
        }
        return $this->current['site'];
    }
    
    public function get_host() {
        if (!isset($this->current['host'])) {
            $this->current['host'] = $_SERVER['HTTP_HOST'];
        }
        return $this->current['host'];
    }

    public function set_action ( $action ) {
        $this->current['action'] = $action;
    }

    public function get_action ( ) {
        return $this->current['action'];
    }

    public function set_page ( $page ) {
        if (is_numeric($page)) {
          $page = fx::data('content_page', $page);
        }
        $this->current['page'] = $page;
    }

    public function get_page ( ) {
        return $this->current['page'];
    }

    public function get_page_id () {
        if (isset($this->current['page']) && is_object($this->current['page'])) {
           return $this->current['page']->get('id');
        }
        if (isset($this->current['page_id'])) {
            $this->current['page'] = fx::data('content_page', $this->current['page_id']);
            return $this->current['page_id'];
        }
        return NULL;
    }

    public function get_site_id() {
        return $this->get_site()->get('id');
    }

    public function set_user ( $user ) {
        $this->current['user'] = $user;
    }

    public function get_user () {
        if (!isset($this->current['user'])) {
            $this->current['user'] = fx_content_user::load();
        }
        return $this->current['user'];
    }

    public function set_main_content ( $str ) {
        $this->current['main_content'] = $str;
    }

    public function get_main_content () {
        return $this->current['main_content'];
    }
  
    public function get_home_id() {
        if (!isset($this->current['home_id'])) {
            $site = $this->get_site();
            $home_page = fx::data('content_page')
                ->where('parent_id', 0)
                ->where('site_id', $site['id'])
                ->one();
            $this->current['home_id'] = $home_page['id'];
        }
        return $this->current['home_id'];
    }
  
    public function is_admin() {
        return ($user = $this->get_user()) ? $user->is_admin() : false;
    }
  
    public function get_layout() {
        if (!$this->current['layout']) {
            $page_id = $this->get_page_id();
            if ($page_id) {
                $page = fx::data('content_page', $page_id);
                if ($page['layout_id']) {
                    $this->current['layout'] = $page['layout_id'];
                }
            }
            if (!$this->current['layout']) {
                $this->current['layout'] = $this->get_site()->get('layout_id');
            }
            if (!$this->current['layout']) {
                $this->current['layout'] = fx::data('layout')->one()->get('id');
            }
        }
        return $this->current['layout'];
    }
    
    public function get_layout_id() {
        return $this->get_layout();
    }
}