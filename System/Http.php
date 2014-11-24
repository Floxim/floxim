<?php

namespace Floxim\Floxim\System;

class Http
{

    protected $status_values = array(
        200 => 'OK',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily',
        403 => 'Forbidden',
        404 => 'Not Found'
    );

    public function status($code)
    {
        if (headers_sent()) {
            return false;
        }
        header("HTTP/1.1 " . $code . " " . $this->status_values[$code]);
    }

    public function redirect($target_url, $status = 301)
    {
        $this->status($status);
        header("Location: " . $target_url);
        fx::complete();
        die();
    }

    public function refresh()
    {
        $this->redirect($_SERVER['REQUEST_URI'], 200);
    }

    public function header($name, $value = null)
    {
        if (headers_sent()) {
            return false;
        }
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
        header($name . ": " . $value);
    }
    
    public function post($url, $data)
    {
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data, null, '&')
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }
}