<?php
class fx_controller_admin_patch extends fx_controller_admin {
    
    public function all() {
        if (!fx::data('patch')->check_updates()) {
            $this->response->add_field(array(
                'type' => 'label',
                'value' => '<p style="color:#F00;">'.
                    fx::alang('Update check failed','system').
                '</p>'
            ));
        }
        
        $this->response->add_field(array(
            'type' => 'label',
            'value' => '<p>'.
                    fx::alang('Current Floxim version:', 'system').
                    ' '.fx::version().
                '</p>'
        ));
        
        $patches = fx::data('patch')->all();
        
        $list = array('type' => 'list', 'filter' => true, 'sortable' => false);
        $list['labels'] = array(
            'name' => fx::alang('Version'),
            'description' => fx::alang('Description'),
            'buttons' => array('type' => 'buttons'),
            'from' => fx::alang('Previous'),
            'status' => fx::alang('Status')
        );

        $list['values'] = array();
        foreach ($patches as $patch) {
            $r = array(
                'name' => $patch['to'],
                'description' => $patch['description'],
                'from' => $patch['from'],
                'status' => $patch['status'],
                'buttons' => array()
            );
            if ($patch['status'] == 'ready') {
                $r['buttons'] []= array(
                    'url' => 'patch.install('.$patch['id'].')', 
                    'label' => fx::alang('Install')
                );
            };
            $list['values'][] = $r;
        }
        $this->response->add_field($list);
        $this->_set_layout();
    }
    
    public function install($input) {
        $patch_id = $input['params'][0];
        if (!$patch_id) {
            return;
        }
        $patch = fx::data('patch', $patch_id);
        if (!$patch) {
            return;
        }
        $this->response->add_field(array(
            'type' => 'label',
            'value' => 
                '<p>'.
                    sprintf(fx::alang('Installing patch %s...', 'system'), $patch['to']).
                '</p>'
        ));
        
        $res = $patch->install();
        
        if (!$res) {
            $this->response->add_field(array(
                'type' => 'label',
                'value' => '<p style="color:#F00;">Install failed!</p>'
            ));
        } else {
            $this->response->add_field(array(
                'type' => 'label',
                'value' => '<p>Patch installed sucessfully!</p>'
            ));
        }
        $this->response->add_field(array(
            'type' => 'button',
            'url' => 'patch.all',
            'label' => 'Back'
        ));
        $this->_set_layout($patch);
    }
    
    protected function _set_layout($c_patch = null) {
    	$this->response->breadcrumb->add_item( fx::alang('Patches','system'), '#admin.patch.all');
        if ($c_patch) {
            $this->response->breadcrumb->add_item( 
                $c_patch['to'], 
                '#admin.patch.view('.$c_patch['id'].')'
            );
        }
        $this->response->submenu->set_menu('patch');
    }
}