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
    
    protected $meta = array();
    
    public function getType()
    {
        return $this->type;
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
            $this->meta = $meta;
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
    
    public function getDirPath()
    {
        $hash = $this->getHash();
        
        return fx::path('@files/asset_cache/'. ( $hash ? $hash.'/' : '').$this->keyword);
    }
    
    public function getMetaPath()
    {
        return fx::path($this->getDirPath().'/'.$this->type.'.meta.php');
    }
    
    protected function getSubBundle($file)
    {
        if (substr($file, 0, 7) !== 'bundle:') {
            return;
        }
        $parts = explode(":", substr($file, 7));
        //$bundle = fx::page()->getBundleManager()->getBundle($this->type, $keyword);
        $bundle = fx::assets($parts[0], $parts[1]);
        return $bundle;
    }
    
    public function isNew()
    {
        return $this->is_new;
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
        
        foreach ($this->files as $f) {
            
            $sub_bundle = $this->getSubBundle($f);
            if ($sub_bundle) {
                $sub_is_fresh = $sub_bundle->isFresh();
                if (!$sub_is_fresh) {
                    return false;
                }
                continue;
            }
            $file_time = file_exists($f) ? filemtime($f) : 0;
            
            if ($file_time > $saved_time) {
                return false;
            }
        }
        return true;
    }
    
    public function getFilePath()
    {
        return fx::path($this->getDirPath().'/'.$this->version.'.'.$this->extension);
    }
    
    public function push($files)
    {
        foreach ($files as $file) {
            if ($file instanceof Bundle) {
                $file = 'bundle:'.$file->getType().':'.$file->keyword;
            }
            if (!$this->has_new_files && !in_array($file, $this->files)) {
                $this->has_new_files = true;
            }
            $this->files[] = $file;
        }
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
    }
    
    public abstract function getBundleContent();
    
    protected function getUniqueFiles()
    {
        $res = array();
        foreach ($this->files as $f) {
            $res []= fx::path($f);
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
            $this->version = time();
        }
        fx::files()->mkdir($this->getDirPath());
        $content = $this->getBundleContent();
        
        //$files = array_unique($this->files);
        $files = $this->getUniqueFiles();
        
        $meta = array(
            'version' => $this->version,
            'files' => $files
        );
        
        foreach ($files as $f) {
            $sub = $this->getSubBundle($f);
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
    }
    
}