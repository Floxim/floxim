<?php

namespace Floxim\Floxim\System;

class Path {
    
    protected $root = '';
    public function __construct() {
        $this->root = DOCUMENT_ROOT;
    }
    
    protected $registry = array();
    
    public function register($key, $path) {
        if (is_array($path)) {
            foreach ($path as &$p) {
                $p = $this->to_http($p);
            }
        } else {
            $path = $this->to_http($path);
        }
        if (isset($this->registry[$key]) && is_array($this->registry[$key])) {
            if (is_array($path)) {
                $this->registry[$key] = array_merge($this->registry[$key], $path);
            } else {
                $this->registry[$key][]= $path;
            }
        } else {
            $this->registry[$key] = $path;
        }
    }
    
    /**
     * Resolve path aliases, e.g. @floxim/js/olo.js => /vendor/Floxim/Floxim/js/olo.js
     * Not ready yet =(
     */
    public function resolve($path) {
        
        $parts = preg_split("~^(@[^\\\\/]+)~", $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (count($parts) === 1) {
            array_unshift($parts, '@root');
        }
        $parts[0] = preg_replace("~^@~", '', $parts[0]);
        
        
        if (!isset($this->registry[$parts[0]])) {
            return null;
        }
        
        $res = $this->to_abs(join("/", $parts));
        return $res;
    }
    
    public function abs($key, $tale = null) {
        if (!isset($this->registry[$key])) {
            return null;
        }
        $path = $this->registry[$key];
        if (!is_null($tale)) {
            $tale = '/'.$tale;
        }
        if (is_array($path)) {
            foreach ($path as &$p) {
                $p = $this->to_abs($p.$tale);
            }
        } else {
            $path = $this->to_abs($path.$tale);
        }
        return $path;
    }
    
    public function http($key, $tale = null) {
        if (!isset($this->registry[$key])) {
            return null;
        }
        $path = $this->registry[$key];
        if (!is_null($tale)) {
            $path .= '/'.$tale;
        }
        $path = $this->to_http($path);
        return $path;
    }
    
    public function to_http($path) {
        if (preg_match("~^https?://~", $path)){
            return $path;
        }
        $ds = "[".preg_quote('\/')."]";
        $path = preg_replace("~".$ds."~", DIRECTORY_SEPARATOR, $path);
        $path = preg_replace("~^".preg_quote($this->root)."~", '', $path);
        $path = preg_replace("~".$ds."~", '/', $path);
        if (!preg_match("~^/~", $path)) {
            $path = '/'.$path;
        }
        $path = preg_replace("~/+~", '/', $path);
        return $path;
    }
    
    public function to_abs($path) {
        $path = str_replace("/", DIRECTORY_SEPARATOR, trim($path));
        $path = preg_replace("~^".preg_quote($this->root)."~", '', $path);
        $path = trim($path, DIRECTORY_SEPARATOR);
        $path = $this->root.DIRECTORY_SEPARATOR.$path;
        $path = preg_replace("~".preg_quote(DIRECTORY_SEPARATOR)."+~", DIRECTORY_SEPARATOR, $path);
        return $path;
    }
    
    public function exists($path) {
        return file_exists($this->to_abs($path));
    }
    
    public function is_file($path) {
        $path = $this->to_abs($path);
        return file_exists($path) && is_file($path);
    }
    
    public function is_inside($child, $parent) {
        $child = $this->to_abs($child);
        $parent = $this->to_abs($parent);
        return preg_match("~^".preg_quote($parent)."~", $child);
    }
    
    public function file_name($path){
        $path = $this->to_http($path);
        preg_match("~[^/]+$~", $path, $file_name);
        if (!$file_name) {
            return '';
        }
        $file_name = preg_replace("~\?.+$~", '', $file_name[0]);
        return $file_name;
    }
    
    public function file_extension($path) {
        $file_name = $this->file_name($path);
        if (!$file_name) {
            return '';
        }
        preg_match("~\.([^\.]+)$~", $file_name, $ext);
        return $ext ? $ext[0] : '';
    }
}