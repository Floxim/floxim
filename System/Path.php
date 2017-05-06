<?php

namespace Floxim\Floxim\System;

class Path
{

    protected $root = '';
    protected $ds_rex = null;
    
    public function __construct()
    {
        //$this->root = defined("APP_ROOT") ? APP_ROOT : DOCUMENT_ROOT;
        $this->ds_rex = "[" . preg_quote('\/') . "]";
        $this->registerHttpResolver(
            DOCUMENT_ROOT, 
            function($path, $prefix, $tail) {
                return $tail;
            }
        );
        $this->registerAbsResolver(
            '/', 
            function($path, $prefix, $tail) {
                return DOCUMENT_ROOT.'/'.$tail;
            }
        );
        if (defined("APP_ROOT")) {
            $this->registerAbsResolver(
                APP_ROOT, 
                function($path, $prefix, $tail) {
                    return $path;
                }    
            );
        } else {
            $this->registerAbsResolver(
                DOCUMENT_ROOT, 
                function($path, $prefix, $tail) {
                    return $path;
                }    
            );
        }
    }
    
    public function removeBase($url) {
        return $url;
        $url = preg_replace('~^https?://[^/]+~', '', $url);
        return mb_substr($url, mb_strlen(FX_BASE_URL));
    }

    protected $registry = array();
    
    public function aliasExists($alias) 
    {
        $alias = preg_replace("~^@~", '', $alias);
        return isset($this->registry[$alias]);
    }

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
        
        //$this->registry = array_map(array($this, "http"), $this->registry);
        $this->registry = array_map(array($this, "resolve"), $this->registry);
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
        
        $alias = $parts[1];
        
        if (!isset($this->registry[$alias])) {
            throw new \Exception('Alias @'.$alias.' is not registered');
        }
        
        $alias_value = $this->registry[$alias];
        if (is_array($alias_value)) {
            $alias_value  = fx::util()->circle($this->registry[$alias]);
        }
        
        $res = $alias_value. (isset($parts[2]) ? $parts[2] : '');
        //$res = preg_replace("~/{2,}~", '/', $res);
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
    
    protected function resolveParents($value, $sep) 
    {
        if (!strstr($value, '.'.$sep)) {
            return $value;
        }
        
        $parts = explode($sep, $value);
        
        $path = array();
        
        foreach($parts as $dir) {
            switch( $dir) {
                case '.':
                    break;
                case '..':
                    array_pop( $path);
                    break;
                default:
                    $path[] = $dir;
                    break;
            }
        }
        
        return join($sep, $path);
    }
    
    protected function processAbs($value)
    {
        $value = preg_replace("~^(http://|https://|//)[^/]+~", '', $value);
        
        foreach ($this->abs_resolvers as $resolver) {
            if (mb_substr($value, 0, $resolver['length']) === $resolver['prefix']) {
                $tail = mb_substr($value, $resolver['length']);
                $value = call_user_func(
                    $resolver['resolver'],
                    $value,
                    $resolver['prefix'],
                    $tail
                );
                break;
            }
        }
        
        $value = preg_replace("~" . preg_quote(DIRECTORY_SEPARATOR) . "+~", DIRECTORY_SEPARATOR, $value);
        
        $value = $this->resolveParents($value, DIRECTORY_SEPARATOR);
        return $value;
    }
    
    protected $abs_resolvers = [];
    
    public function registerAbsResolver($prefix, $resolver)
    {
        $this->abs_resolvers[]= [
            'prefix' => $prefix,
            'length' => mb_strlen($prefix),
            'resolver' => $resolver
        ];
        if (count($this->abs_resolvers) > 1) {
            uasort(
                $this->abs_resolvers, 
                function($a, $b) {
                    return $b['length'] - $a['length'];
                }
            );
        }
    }
    
    protected $http_resolvers = [];
    
    public function registerHttpResolver($prefix, $resolver)
    {
        $this->http_resolvers[]= [
            'prefix' => $prefix,
            'length' => mb_strlen($prefix),
            'resolver' => $resolver
        ];
        if (count($this->http_resolvers) > 1) {
            uasort(
                $this->http_resolvers, 
                function($a, $b) {
                    return $b['length'] - $a['length'];
                }
            );
        }
    }
    
    protected function processHttp($value)
    {
        $value = preg_replace("~^https?://[^/]+~", '', $value);
        
        foreach ($this->http_resolvers as $resolver) {
            if (mb_substr($value, 0, $resolver['length']) === $resolver['prefix']) {
                $tail = mb_substr($value, $resolver['length']);
                $value = call_user_func(
                    $resolver['resolver'],
                    $value,
                    $resolver['prefix'],
                    $tail
                );
                break;
            }
        }
        
        if (DIRECTORY_SEPARATOR !== '/') {
            $value = str_replace("\\", '/', $value);
        }
        
        if (!preg_match("~^/~", $value)) {
            $value = '/' . $value;
        }
        //$value = preg_replace("~/+~", '/', $value);
        return $value;
    }
    
    public function trimHost($path)
    {
        return preg_replace("~^(http://|https://|//)[^/]+~", '', $path);
    }

    public function http($path, $trim_host = false)
    {
        if (is_array($path)) {
            $path = array_map(array($this, "resolve"), $path);
            $path = array_map(array($this, 'processHttp'), $path);
            if ($trim_host) {
                $path = array_map(array($this, 'trimHost'), $path);
            }
        } else {
            $path = $this->processHttp( $this->resolve($path) );
            if ($trim_host) {
                $path = $this->trimHost($path); 
            }
        }
        return $path;
    }
    
    public function storable($abs) 
    {
        if ( ($closure = fx::config('content_files.store_path_closure'))) {
            return $closure($abs);
        }
        return $this->http($abs, true);
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