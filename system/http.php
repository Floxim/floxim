<?php
class fx_http {
    
    protected $status_values = array(
        200 => 'OK',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily',
        403 => 'Forbidden',
        404 => 'Not Found'
    );
    
    public function status($code) {
        header("HTTP/1.1 ".$code." ".$this->status_values[$code]);
    }
    
    public function redirect($target_url, $status = 302) {
        $this->status($status);
        header("Location: ".$target_url);
        fx::env()->set('complete_ok', true);
        die();
    }
    
    public function refresh() {
        $this->redirect($_SERVER['REQUEST_URI'], 200);
    }
    
    public function header($name, $value = null) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
                if (!$value) {
            // send header only if the first arg contains full header text, e.g.
            // My-Header: something
            if (!preg_match("~\:[^\s+]~", $name)) {
                return;
            }
            header($name);
        }
        header($name. ": ".$value);
    }
}