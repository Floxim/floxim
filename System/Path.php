<?php

namespace Floxim\Floxim\System;

class Path
{

    protected $root = '';

    public function __construct()
    {
        $this->root = DOCUMENT_ROOT;
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
        $parts = preg_split("~^(@[^\\\\/]+)~", $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        // copy for php 5.3 support
        $registry = $this->registry;
        $parts = array_map( function($value) use ($registry) {
                preg_match("~@([^\\\\/]+)~", $value, $matches);
                return (
                    isset($matches[1])
                        && $matches[1]
                        && isset($registry[$matches[1]])
                    ? $registry[$matches[1]]
                    : $value
                );
            },
            $parts
        );

        $res = preg_replace("~/{2,}~", '/', join("", $parts));
        return $res;
    }

    public function abs($path)
    {
        $root = $this->root;
        $do = function ($value) use ($root) {
            $value = str_replace("/", DIRECTORY_SEPARATOR, trim($value));
            $value = preg_replace("~^" . preg_quote($root) . "~", '', $value);
            $value = trim($value, DIRECTORY_SEPARATOR);
            $value = $root . DIRECTORY_SEPARATOR . $value;
            $value = preg_replace("~" . preg_quote(DIRECTORY_SEPARATOR) . "+~", DIRECTORY_SEPARATOR, $value);
            return $value;
        };
        
        if (is_array($path)) {
            $path = array_map(array($this, "resolve"), $path);
            $path = array_map($do, $path);
        } else {
            $path = $do( $this->resolve($path) );
        }
        
        return $path;
    }

    public function http($path)
    {
        $root = $this->root;
        $do = function ($value) use ($root) {
            if (preg_match("~^https?://~", $value)) {
                return $value;
            }
            $ds = "[" . preg_quote('\/') . "]";
            $value = preg_replace("~" . $ds . "~", DIRECTORY_SEPARATOR, $value);
            $value = preg_replace("~^" . preg_quote($root) . "~", '', $value);
            $value = preg_replace("~" . $ds . "~", '/', $value);
            if (!preg_match("~^/~", $value)) {
                $value = '/' . $value;
            }
            $value = preg_replace("~/+~", '/', $value);
            return $value;
        };
        
        if (is_array($path)) {
            $path = array_map(array($this, "resolve"), $path);
            $path = array_map($do, $path);
        } else {
            $path = $do( $this->resolve($path) );
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