<?php

class fx_auth {
    /** @var fx_auth_user_mail */
    public $mail;

    protected function __construct() {
        
    }

    /**
     * Instance self object method
     *
     * @return fx_auth object
     */
    public static function get_object() {
        static $storage;
        if (!isset($storage)) {
            $storage = new self();
        }
        return is_object($storage) ? $storage : false;
    }

    public static function get_logout_url() {
        return fx::config()->SUB_FOLDER.fx::config()->HTTP_ROOT_PATH."index.php?essence=module_auth&action=logout";
    }
}