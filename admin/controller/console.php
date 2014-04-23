<?php
class fx_controller_admin_console extends fx_controller_admin {
    public function show($input) {
        $this->response->breadcrumb->add_item(fx::alang('Console'), '#admin.console.show');
        $this->response->submenu->set_menu('console');
        $fields = array(
            'console_text' => array(
                'name' => 'console_text',
                'type' => 'text',
                'code' => 'htmlmixed',
                'value' => isset($input['console_text']) ? $input['console_text'] : '<?php'."\n"
            )
        );
        if ($input['console_text']) {
            ob_start();
            $code = $input['console_text'];
            $code = preg_replace("~^<\?(?:php)?~", '', $code);
            eval($code);
            $res = ob_get_clean();
            $fields ['result']= array(
                'name' => 'result',
                'type' => 'html',
                'html' => $res
            );
        }
        $fields []= $this->ui->hidden('essence', 'console');
        $fields []= $this->ui->hidden('action', 'show');
        $this->response->add_form_button(array('key' => 'save', 'label' => fx::alang('Execute')));
        $this->response->add_fields($fields);
        //$this->response->set_status_ok();
        return array('show_result' => 1);
    }
}
