<?php
class fx_data_content_user extends fx_data_content {
    public function get_by_id($id) {
        if (!is_numeric($id)) {
            return $this->get_by_login($id);
        }
        return parent::get_by_id($id);
    }
    
    public function get_by_login($login) {
        $this->where(fx::config()->AUTHORIZE_BY, $login);
        return $this->one();
    }
}