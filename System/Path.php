<?php

namespace Floxim\Floxim\System;

class Path
{

    protected $root = '';
    protected $ds_rex = null;
    protected $root_rex = null;

    public function __construct()
    {
        $this->root = DOCUMENT_ROOT;
        $this->ds_rex = "[" . preg_quote('\/') . "]";
        $this->root_rex = preg_quote($this->root);
        $this->root_len = mb_strlen($this->root);
    }
    
    public function removeBase($url) {
        $url = preg_replace('~^https?://[^/]+~', '', $url);
        return mb_substr($url, mb_strlen(FX_BASE_URL));
    }

    protected $registry = array();

    public function register($key, $path)
    {
        if (isset($this->registry[$key]) && is_array($this->registry[$key])) {
            if (is_array($path)) {
                $this->registry[$key] = array_merge($this->registry[$key], $path);
            } else {
                $this->registry[$key][] = $path;
            }
        } else {
            $this->registry[$key] = $path;
        }
        
        $this->registry = array_map(array($this, "http"), $this->registry);
    }

    /**
     * Resolve path aliases,
     * e.g. @floxim/js/olo.js => /vendor/Floxim/Floxim/js/olo.js
     */
    public function resolve($path)
    {
        if ($path[0] !== '@') {
            return $path;
        }
        
        $parts = preg_split("~^(@)([^\\\\/]+)~", $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        if (!isset($this->registry[$parts[1]])) {
            throw new \Exception('Alias @'.$parts[1].' is not registered');
        }
        $res = $this->registry[$parts[1]].$parts[2];
        $res = preg_replace("~/{2,}~", '/', $res);
        return $res;
    }

    public function abs($path)
    {
        if (is_array($path)) {
            $path = array_map(array($this, "resolve"), $path);
            $path = array_map(array($this, 'processAbs'), $path);
        } else {
            $path = $this->processAbs( $this->resolve($path) );
        }
        
        return $path;
    }
    
    protected function processAbs($value)
    {
        $value = str_replace("/", DIRECTORY_SEPARATOR, trim($value));
        if (mb_substr($value, 0, $this->root_len) !== $this->root) {
            $value = $this->root . DIRECTORY_SEPARATOR . trim($value, DIRECTORY_SEPARATOR);
        }
        //$value = preg_replace("~^" . preg_quote($root) . "~", '', $value);
        //$value = trim($value, DIRECTORY_SEPARATOR);
        //$value = $root . DIRECTORY_SEPARATOR . $value;
        $value = preg_replace("~" . preg_quote(DIRECTORY_SEPARATOR) . "+~", DIRECTORY_SEPARATOR, $value);
        return $value;
    }
    
    protected function processHttp($value)
    {
        if (mb_substr($value, 0, $this->root_len) === $this->root) {
            $value = mb_substr($value, $this->root_len);
        }
        
        if (DIRECTORY_SEPARATOR !== '/') {
            $value = str_replace("\\", '/', $value);
        }
        
        if (!preg_match("~^/~", $value)) {
            $value = '/' . $value;
        }
        $value = preg_replace("~/+~", '/', $value);
        return $value;
    }

    public function http($path)
    {
        if (is_array($path)) {
            $path = array_map(array($this, "resolve"), $path);
            $path = array_map(array($this, 'processHttp'), $path);
        } else {
            $path = $this->processHttp( $this->resolve($path) );
        }
        return $path;
    }

    public function exists($path)
    {
        return file_exists($this->abs($path));
    }

    public function isFile($path)
    {
        $path = $this->abs($path);
        return file_exists($path) && is_file($path);
    }

    public function isInside($child, $parent)
    {
        $child = $this->abs($child);
        $parent = $this->abs($parent);
        return preg_match("~^" . preg_quote($parent) . "~", $child);
    }
    
    public function parse($path) {
        $res = array();
        $path = preg_replace_callback(
            "~\.([^\.]+)$~", 
            function($match) use (&$res) {
                $res['extension'] = $match[1];
                return '';
            },
            $path
        );
        $path = preg_replace_callback(
            "~([^/\\\]+)$~", 
            function($match) use (&$res) {
                $res['name'] = $match[1];
                return '';
            },
            $path
        );
        $res['path'] = $path;
        return $res;
    }
    
    public function build($parts)
    {
        $res = '';
        if (isset($parts['path'])) {
            $res .= $parts['path'];
        }
        if (isset($parts['name'])) {
            $res .= $parts['name'];
        }
        if (isset($parts['extension'])) {
            $res .= '.'.$parts['extension'];
        }
        return $res;
    }

    public function fileName($path)
    {
        $path = $this->http($path);
        preg_match("~[^/]+$~", $path, $file_name);
        if (!$file_name) {
            return '';
        }
        $file_name = preg_replace("~\?.+$~", '', $file_name[0]);
        return $file_name;
    }

    public function fileExtension($path)
    {
        $file_name = $this->fileName($path);
        if (!$file_name) {
            return '';
        }
        preg_match("~\.([^\.]+)$~", $file_name, $ext);
        return $ext ? $ext[1] : '';
    }
}