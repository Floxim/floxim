<?php
class fx_controller_admin extends fx_controller {

    /** @var string the default action for the controller to return the html markup */
    protected $action = 'admin_office';
    
    /** @var bool the process() method should return the result? */
    protected $process_do_return = false;
    
    protected $essence_type;
    protected $save_history = true;

    /** @var fx_admin_response */
    protected $response;

    /** @var fx_admin_ui */
    protected $ui;

    public function __construct($input = array(), $action = null, $do_return = false) {
        parent::__construct($input, $action);
        
        $this->essence_type = str_replace('fx_controller_admin_', '', get_class($this));
        $this->ui = new fx_admin_ui();
        
        $this->process_do_return = isset($input['do_return']) ? $input['do_return'] : $do_return;
    }

    public function process() {

        $input = $this->input;
        $action = $this->action;
        
        if (!fx::is_admin()) {
            $result = $this->admin_office($input);
            if (is_string($result)) {
                return $result;
            }    
        }
        
        if (!$action || !is_callable(array($this, $action))) {
            die("Error! Class:".get_class($this).", action:".htmlspecialchars($action));
        }
        
        $this->response = new fx_admin_response($input);
        $result = $this->$action($input);
        if (is_string($result)) {
            return $result;
        }

        if ($input['posting']) {
            if (!$result['text']) {
                $result['text'] = $this->get_status_text();
            }
        }

        if ($this->response) {
            $result = array_merge(
                $result ? $result : array(), 
                $this->response->to_array()
            );
        }
        // force numeric indexes for fields to preserve order
        if (isset($result['fields']) && is_array($result['fields'])) {
            $result['fields'] = array_values($result['fields']);
        }
        
        if ($this->process_do_return) {
            return $result;
        }
        return json_encode($result);
    }

    protected function get_status_text() {
        return fx::alang('Saved','system');
    }

    protected function get_active_tab() {
        return $this->input['params'][1];
    }
    
    public static function add_admin_files() {
        $js_files = array(
            FX_JQUERY_PATH,
            '/floxim/admin/js/fxj.js',
            '/floxim/admin/js/fx.js',
            '/floxim/admin/js/js-dictionary-'.fx::config()->ADMIN_LANG.'.js',
            FX_JQUERY_UI_PATH,
            '/floxim/lib/js/jquery.nestedSortable.js',
            '/floxim/lib/js/jquery.ba-hashchange.min.js',
            '/floxim/lib/js/jquery.json-2.3.js',
            '/floxim/lib/js/ajaxfileupload.js',                                            
            '/floxim/admin/js-templates/jstx.js',
            'http://'.getenv("HTTP_HOST").'/floxim/admin/js-templates/compile.php',
            '/floxim/admin/js/lib.js',
            '/floxim/admin/js/front.js',
            '/floxim/admin/js/buttons.js',                                     
            '/floxim/admin/js/form.js',
            '/floxim/admin/js/debug.js',
            '/floxim/admin/js/livesearch.js',
            '/floxim/admin/js/fields.js',
            '/floxim/admin/js/edit-in-place.js',
            '/floxim/admin/js/panel.js',
            '/floxim/admin/js/popup.js',
            '/floxim/admin/js/admin.js',
            '/floxim/admin/js/nav.js',
            '/floxim/lib/editors/redactor/redactor.js',
            '/floxim/lib/editors/redactor/fontcolor.js',
            '/floxim/lib/js/jquery.form.js',
            '/floxim/lib/js/jquery.cookie.js',
            '/floxim/lib/js/jquery.ba-resize.min.js',
            '/floxim/lib/js/jquery.scrollTo.js',
            'floxim/admin/js/infoblock.js' // infoblock form overrides
        );
        $page = fx::page();
        
        
        
        $page->add_js_bundle($js_files, array('name' => 'fx_admin'));
        
        /*
        $update_checker_url = fx::config()->FLOXIM_SITE_PROTOCOL.'://'.
                  fx::config()->FLOXIM_SITE_HOST.
                  '/getfloxim/check_updates.js?v='.
                  fx::config()->FX_VERSION;
        
        $page->add_js_text("
           (function(){
            var fxupdate = document.createElement('script');
               fxupdate.type = 'text/javascript';
               fxupdate.async = true;
               fxupdate.src = '".$update_checker_url."';
            (document.getElementsByTagName('head')[0]||document.getElementsByTagName('body')[0]).appendChild(fxupdate);
          })(); 
        ");
         * 
         */
        $page->add_css_bundle(array(
            '/floxim/lib/editors/redactor/redactor.css',
        ));
        $page->add_css_bundle(array(
            //'/floxim/admin/style/jqueryui.less',
            '/floxim/admin/style/main.less',
            '/floxim/admin/style/forms.less',
            '/floxim/admin/style/front.less',
            '/floxim/admin/style/debug.less',
        ), array('name' => 'admin_less'));
    }
    
    /**
     * @return string
     */
    public function admin_office()
    {   
        self::add_admin_files();
        
        if (fx::is_admin()) {
            $res = fx::template('helper_admin.back_office')->render();
            $js_config = new fx_admin_configjs();
            fx::page()->add_js_text("\$fx.init(".$js_config->get_config().");");
        } else {
            $auth_form = fx::controller('component_user.auth_form')
                            ->render('component_user.auth_form');
            
            $recover_form = fx::controller('component_user.recover_form', array('email' => $_POST['email']))
                            ->render('component_user.recover_form');
            
            $res = fx::template('helper_admin.authorize')->render(array(
                    'auth_form' => $auth_form,
                    'recover_form' => $recover_form
            ));
        }
        return fx::page()->post_process($res);
    }
    
    
    public function move_save($input) {
        
        $essence = $this->essence_type;

        $positions = $input['positions'] ? $input['positions'] : $input['pos'];
        if ($positions) {
            $priority = 0;
            foreach ($positions as $id) {
                $item = fx::data($essence)->get_by_id($id);
                if ($item) {
                    $item->set('priority', $priority++)->save();
                }
            }
        }

        return array('status' => 'ok');
    }

    public function on_save($input) {

        $es = $this->essence_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                fx::data($es)->get_by_id($id)->checked();
            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }

        return $result;
    }

    public function off_save($input) {
        
        $es = $this->essence_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                fx::data($es)->get_by_id($id)->unchecked();
            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }

        return $result;
    }

    public function delete_save($input) {
        
        $es = $this->essence_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                fx::data($es, $id)->delete();
            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }
}

class fx_controller_admin_module extends fx_controller_admin {

    protected $menu_items = array();

    public function basesettings($input) {
        $module_keyword = str_replace('fx_controller_admin_module_', '', get_class($this));
        $this->response->submenu->set_menu('settings')->set_subactive('settings-'.$module_keyword);
        $this->response->breadcrumb->add_item( fx::alang('Configuring the','system') . ' ' . $module_keyword);
        $this->settings();
    }

    public function settings() {
        $this->response->add_field($this->ui->label( fx::alang('Override the settings in the class','system') ));
    }

    public function basesettings_save($input) {
        $this->settings_save($input);
    }

    public function settings_save($input) {
        ;
    }

    public function add_node($id, $name, $href = '') {
        $this->menu_items[] = array('id' => $id, 'name' => $name, 'href' => $href);
    }

    public function get_menu_items() {
        $this->init_menu();
        return $this->menu_items;
    }

    public function init_menu() {
        
    }

}

