<?php

namespace Floxim\Floxim\System;

class Input
{


    public function __construct()
    {
        $this->prepareExtract();
    }


    public function prepareExtract()
    {
        $request_uri = isset($_GET['REQUEST_URI']) ? $_GET['REQUEST_URI'] : (
        isset($_POST['REQUEST_URI']) ? $_POST['REQUEST_URI'] : (
        isset($_ENV['REQUEST_URI']) ? $_ENV['REQUEST_URI'] :
            getenv("REQUEST_URI")));
        if (substr($request_uri, 0, 1) != "/") {
            $request_uri = "/" . $request_uri;
        }
        $request_uri = trim($request_uri);
        $url = "http"
            . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "s" : "")
            . "://" . getenv("HTTP_HOST")
            . $request_uri;
        // parse entire url
        $parsed_url = @parse_url($url);

        // validate query parameter
        if (is_array($parsed_url) && array_key_exists('query', $parsed_url) && $parsed_url['query']) {
            $parsed_query_arr = null;
            parse_str($parsed_url['query'], $parsed_query_arr);
            $_GET = $parsed_query_arr ? $parsed_query_arr : array();
        }


        // superglobal arrays
        $superglobals = array(
            "_COOKIE" => $_COOKIE,
            "_GET"    => $_GET,
            "_POST"   => $_POST,
            "_FILES"  => $_FILES,
            "_ENV"    => $_ENV,
            "_SERVER" => $_SERVER,
            "_SESSION" => $_SESSION
        );
        // set default

        // merge superglobals arrays
        foreach ($superglobals as $key => $super_array) {
            // set internal data from superglobal arrays
            $this->$key = self::prepareSuperglobal($super_array);
        }

        return false;
    }

    public static function recursiveAddSlashes($input)
    {
        if (!is_array($input)) {
            return addslashes($input);
        }
        $output = array();

        foreach ($input as $k => $v) {
            $output[$k] = self::recursiveAddSlashes($v);
        }

        return $output;
    }
    
    public function set($target, $key, $value)
    {
        $target = '_'.strtoupper($target);
        $this->{$target}[$key] = $value;
        switch ($target) {
            case '_SESSION':
                $_SESSION[$key] = $value;
                break;
        }
    }


    public static function prepareSuperglobal($array)
    {
        if (!get_magic_quotes_gpc()) {
            return $array;
        }
        return self::recursiveStripslashes($array);
    }

    public static function recursiveStripslashes($input)
    {
        $output = array();
        foreach ($input as $k => $v) {
            $output[$k] = is_array($v) ? self::recursiveStripslashes($v) : stripslashes($v);
        }
        return $output;
    }

    public function fetchGet($item = "")
    {

        if (empty($this->_GET)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item, $this->_GET) ? $this->_GET[$item] : null;
        } else {
            return $this->_GET;
        }

    }

    public function fetchPost($item = "")
    {
        if (empty($this->_POST)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item, $this->_POST) ? $this->_POST[$item] : null;
        } else {
            return $this->_POST;
        }

    }

    public function fetchPostJson() {
        return json_decode(file_get_contents('php://input'), true);
    }

    public function fetchCookie($item = "")
    {

        if (empty($this->_COOKIE)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item, $this->_COOKIE) ? $this->_COOKIE[$item] : null;
        } else {
            return $this->_COOKIE;
        }

    }

    public function setCookie($name, $value, $expire = 0, $path = '/')
    {
        setcookie($name, $value, $expire, $path);
    }

    public function fetchSession($item = "")
    {

        if (empty($this->_SESSION)) {
            return $item ? null : array();
        }

        if ($item) {
            return array_key_exists($item, $this->_SESSION) ? $this->_SESSION[$item] : null;
        } else {
            return $this->_SESSION;
        }

    }

    public function fetchFiles($item = "")
    {
        if (empty($this->_FILES)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item, $this->_FILES) ? $this->_FILES[$item] : null;
        } else {
            return $this->_FILES;
        }

    }

    public function fetchGetPost($item = "")
    {

        if (empty($this->_GET) && empty($this->_POST)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item, $this->_GET) ? $this->_GET[$item] : (array_key_exists($item,
                $this->_POST) ? $this->_POST[$item] : null);
        } else {
            return array_merge($this->_POST, $this->_GET);
        }

    }

    public function getServiceSession($item)
    {
        $key = fx::config()->SESSION_KEY;
        $data = $_SESSION[$key];
        return $data[$item];
    }

    public function setServiceSession($item, $value)
    {
        $key = fx::config()->SESSION_KEY;
        $data = isset($_SESSION[$key]) ? $_SESSION[$key] : array();
        $data[$item] = $value;
        $_SESSION[$key] = $data;
    }

    public function unsetServiceSession($item)
    {
        $key = fx::config()->SESSION_KEY;
        $data = $_SESSION[$key];
        unset($data[$item]);
        $_SESSION[$key] = $data;
    }

    public function GET($item = "")
    {
        if (empty($this->_GET)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item, $this->_GET) ? fx::db()->escape($this->_GET[$item]) : null;
        }

        $get = $this->_GET;
        foreach ($get as $k => &$v) {
            $v = fx::db()->escape($v);
        }
        return $get;
    }

    public function POST($item = "")
    {

        if (empty($this->_POST)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item, $this->_POST) ? fx::db()->escape($this->_POST[$item]) : null;
        }
        $post = $this->_POST;
        foreach ($post as $k => &$v) {
            $v = fx::db()->escape($v);
        }
        return $post;
    }

    public function GET_POST($item = "")
    {
        if (empty($this->_GET) && empty($this->_POST)) {
            return array();
        }

        if ($item) {
            return array_key_exists($item,
                $this->_GET) ? fx::db()->escape($this->_GET[$item]) : (array_key_exists($item,
                $this->_POST) ? fx::db()->escape($this->_POST[$item]) : null);
        }
        $data = array_merge($this->_POST, $this->_GET);
        foreach ($data as $k => &$v) {
            $v = fx::db()->escape($v);
        }
        return $data;
    }


    public function makeInput()
    {
        $files = $this->fetchFiles();
        $post = $this->fetchGetPost();

        // arrays should unite, but nothing to lose, not suitable array_merge
        // ex, POST['foto']['link'] = 'x', FILES['foto']['name'] = 'y' => input['foto']['link']='x',input['foto']['name']='y'
        /*@todo rewritten by-normal*/
        $result = $post;
        if ($files) {
            foreach ($files as $k => $v) {
                if (isset($result[$k])) {
                    if (is_array($v)) {
                        foreach ($v as $key => $value) {
                            $result[$k][$key] = $value;
                        }
                    } else {
                        $result[$k] = $v;
                    }
                } else {
                    $result[$k] = $v;
                }
            }
        }

        return $result;
    }

}