<?php
namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

abstract class Bundle {
    
    protected $keyword =  null;
    protected $files = array();
    protected $params = array();
    protected $type = null;
    protected $version = null;
    protected $dir;
    protected $is_new = false;
    protected $has_new_files = false;
    
    protected $is_fresh = null;
    
    protected $meta = array();
    
    public function getType()
    {
        return $this->type;
    }
    
    public function getVersion()
    {
        return (int) $this->version;
    }

    public function __construct($keyword, $params = array()) 
    {
        $this->keyword = $keyword;
        $this->params = $params;
        $meta_path = $this->getMetaPath();
        
        if (!file_exists($meta_path)) {
            $this->is_new = true;
            $this->version = time();
        } else {
            $meta = include($meta_path);
            $this->files = $meta['files'];
            $this->version = $meta['version'];
            $this->meta = array_merge($this->meta, $meta);
        }
    }
    
    public function getFiles()
    {
        return $this->files;
    }
    
    public function getMeta()
    {
        return $this->meta;
    }
    
    protected function getHash()
    {
        $parts = array();
        foreach ($this->params as $k => $v) {
            $parts[] = $k.'-'.$v;
        }
        if (count($parts) === 0) {
            return '';
        }
        return join(".", $parts);
    }
    
    public static function getCacheDir()
    {
        static $dir = null;
        if (is_null($dir)) {
            $dir = fx::path('@files/asset_cache');
        }
        return $dir;
    }
    
    public function getDirPath()
    {
        $hash = $this->getHash();
        return self::getCacheDir().'/'. ( $hash ? $hash.'/' : '').$this->keyword;
    }
    
    public function getMetaPath()
    {
        return fx::path($this->getDirPath().'/'.$this->type.'.meta.php');
    }
    
    protected static function isSubBundle($file) 
    {
        return substr($file, 0, 7) === 'bundle:';
    }
    
    protected static function getSubBundle($file)
    {
        if (!self::isSubBundle($file)) {
            return;
        }
        $parts = explode(":", substr($file, 7), 3);
        $params = isset($parts[2]) && $parts[2] ? json_decode($parts[2], true) : array();
        $bundle = fx::assets($parts[0], $parts[1], $params);
        return $bundle;
    }
    
    public function isNew()
    {
        return $this->is_new;
    }
   
    public function isTemp()
    {
        return isset($this->meta['is_temp']) && $this->meta['is_temp'];
    }
    
    public function isFresh($file = null)
    {
        $saved_time = (int) $this->version;
        if ($file !== null) {
            return file_exists($file) && filemtime($file) < $saved_time;
        }
        if ($this->is_new || $this->has_new_files) {
            return false;
        }
        if ($this->is_fresh !== null) {
            return $this->is_fresh;
        }
        
        if (!file_exists($this->getFilePath())) {
            $this->is_fresh = false;
            return false;
        }
        
        $this->is_fresh = true;
        
        foreach ($this->files as $f) {
            
            $sub_bundle = self::getSubBundle($f);
            if ($sub_bundle) {
                $sub_is_fresh = $sub_bundle->isFresh() && 
                                $sub_bundle->getVersion() <= $saved_time;
                if (!$sub_is_fresh && !$sub_bundle->isTemp()) {
                    $this->is_fresh = false;
                    break;
                }
                continue;
            }
            $file_time = file_exists($f) ? filemtime($f) : 0;
            
            if ($file_time > $saved_time) {
                $this->is_fresh = false;
                break;
            }
        }
        return $this->is_fresh;
    }
    
    public function getFilePath()
    {
        return fx::path($this->getDirPath().'/'.$this->version.'.'.$this->extension);
    }
    
    public $collect_pushed_files = false;
    protected $pushed_files = array();

    public function push($files)
    {
        foreach ($files as $file) {
            if ($file instanceof Bundle) {
                $file = $file->getSignature();
            }
            $is_new_file = !in_array($file, $this->files);
            if ($this->collect_pushed_files && !in_array($file, $this->pushed_files)) {
                $this->pushed_files [] = $file;
            }
            if (!$is_new_file) {
                continue;
            }
            if (!$this->has_new_files) {
                $this->has_new_files = true;
                $this->is_fresh = false;
                fx::log('pushd new', $file);
            }
            $this->files[] = $file;
        }
    }
    
    protected $signature = null;
    
    protected function getSignature()
    {
        if (is_null($this->signature)) {
            $this->signature = 'bundle:'.$this->getType().':'.$this->keyword.":".$this->exportParams();
        }
        return $this->signature;
    }
    
    public function exportParams()
    {
        return '';
    }
    
    public function delete()
    {
        if ($this->is_new) {
            return;
        }
        $files = array(
            $this->getFilePath(),
            $this->getMetaPath()
        );
        foreach ($files as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
        $this->is_fresh = false;
    }
    
    public abstract function getBundleContent();
    
    protected function getUniqueFiles()
    {
        $res = array();
        foreach ($this->files as $f) {
            $res []= self::isSubBundle($f) ? $f : fx::path($f);
        }
        $res = array_unique($res);
        return $res;
    }
    
    public function save()
    {
        if ($this->isFresh()) {
            return;
        }
        if (!$this->is_new) {
            $this->delete();
        }
        fx::files()->mkdir($this->getDirPath());
        $content = $this->getBundleContent();
        
        $files = $this->getUniqueFiles();
        
        $this->version = time();
        
        $meta = array(
            'version' => $this->version,
            'files' => $files
        );
        
        foreach ($files as $f) {
            $sub = self::getSubBundle($f);
            if ($sub) {
                $sub->save();
            }
        }
        $meta = array_merge($this->meta, $meta);
        
        $meta_content = '<?'."php\nreturn ".var_export($meta, true).';';
        $meta_path = $this->getMetaPath();
        file_put_contents($meta_path, $meta_content);
        $target_path = $this->getFilePath();
        file_put_contents($target_path, $content);
        $this->is_new = false;
        $this->has_new_files = false;
        $this->is_fresh = true;
    }
    
}