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
        if (is_array($path)) {
            foreach ($path as &$p) {
                $p = $this->toHttp($p);
            }
        } else {
            $path = $this->toHttp($path);
        }
        if (isset($this->registry[$key]) && is_array($this->registry[$key])) {
            if (is_array($path)) {
                $this->registry[$key] = array_merge($this->registry[$key], $path);
            } else {
                $this->registry[$key][] = $path;
            }
        } else {
            $this->registry[$key] = $path;
        }
    }

    /**
     * Resolve path aliases, e.g. @floxim/js/olo.js => /vendor/Floxim/Floxim/js/olo.js
     * Not ready yet =(
     */
    public function resolve($path)
    {

        $parts = preg_split("~^(@[^\\\\/]+)~", $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (count($parts) === 1) {
            array_unshift($parts, '@root');
        }
        $parts[0] = preg_replace("~^@~", '', $parts[0]);


        if (!isset($this->registry[$parts[0]])) {
            return null;
        }

        $res = $this->toAbs(join("/", $parts));
        return $res;
    }

    public function abs($key, $tale = null)
    {
        if (!isset($this->registry[$key])) {
            return null;
        }
        $path = $this->registry[$key];
        if (!is_null($tale)) {
            $tale = '/' . $tale;
        }
        if (is_array($path)) {
            foreach ($path as &$p) {
                $p = $this->toAbs($p . $tale);
            }
        } else {
            $path = $this->toAbs($path . $tale);
        }
        return $path;
    }

    public function http($key, $tale = null)
    {
        if (!isset($this->registry[$key])) {
            return null;
        }
        $path = $this->registry[$key];
        if (!is_null($tale)) {
            $path .= '/' . $tale;
        }
        $path = $this->toHttp($path);
        return $path;
    }

    public function toHttp($path)
    {
        if (preg_match("~^https?://~", $path)) {
            return $path;
        }
        $ds = "[" . preg_quote('\/') . "]";
        $path = preg_replace("~" . $ds . "~", DIRECTORY_SEPARATOR, $path);
        $path = preg_replace("~^" . preg_quote($this->root) . "~", '', $path);
        $path = preg_replace("~" . $ds . "~", '/', $path);
        if (!preg_match("~^/~", $path)) {
            $path = '/' . $path;
        }
        $path = preg_replace("~/+~", '/', $path);
        return $path;
    }

    public function toAbs($path)
    {
        $path = str_replace("/", DIRECTORY_SEPARATOR, trim($path));
        $path = preg_replace("~^" . preg_quote($this->root) . "~", '', $path);
        $path = trim($path, DIRECTORY_SEPARATOR);
        $path = $this->root . DIRECTORY_SEPARATOR . $path;
        $path = preg_replace("~" . preg_quote(DIRECTORY_SEPARATOR) . "+~", DIRECTORY_SEPARATOR, $path);
        return $path;
    }

    public function exists($path)
    {
        return file_exists($this->toAbs($path));
    }

    public function isFile($path)
    {
        $path = $this->toAbs($path);
        return file_exists($path) && is_file($path);
    }

    public function isInside($child, $parent)
    {
        $child = $this->toAbs($child);
        $parent = $this->toAbs($parent);
        return preg_match("~^" . preg_quote($parent) . "~", $child);
    }

    public function fileName($path)
    {
        $path = $this->toHttp($path);
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
        return $ext ? $ext[0] : '';
    }
}