<?php
namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

abstract class Bundle {
    
    protected $keyword =  null;
    protected $files = array();
    protected $params = array();
    protected $type;
    protected $version = null;
    protected $dir;
    protected $is_new = false;
    protected $has_new_files = false;

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
        }
    }
    
    protected function getHash()
    {
        $parts = array();
        foreach ($this->params as $k => $v) {
            $parts[] = $k.'-'.$v;
        }
        return join(".", $parts);
    }
    
    protected function getDirPath()
    {
        return fx::path('@files/asset_cache');
    }
    
    public function getMetaPath()
    {
        return fx::path($this->getDirPath().'/'.$this->keyword.'.'.$this->getHash().'.'.$this->type.'.meta.php');
    }
    
    public function isFresh()
    {
        if ($this->is_new || $this->has_new_files) {
            return false;
        }
        $saved_time = (int) $this->version;
        foreach ($this->files as $f) {
            $file_time = file_exists($f) ? filemtime($f) : 0;
            if ($file_time > $saved_time) {
                return false;
            }
        }
        return true;
    }
    
    public function getFilePath()
    {
        
        return fx::path($this->getDirPath().'/'.$this->keyword.'.'.$this->getHash().'.'.$this->version.'.'.$this->type);
    }


    public function push($files)
    {
        foreach ($files as $file) {
            $this->processFile($file);
            if (!$this->has_new_files && !in_array($file, $this->files)) {
                $this->has_new_files = true;
            }
            $this->files[] = $file;
        }
    }
    
    public function processFile($file)
    {
        
    }
    
    
    public function delete()
    {
        unlink($this->getFilePath());
        unlink($this->getMetaPath());
    }
    
    protected abstract function getBundleContent();
    
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
        $meta = array(
            'version' => $this->version,
            'files' => $this->files
        );
        $meta_content = '<?'."php\nreturn ".var_export($meta, true).';';
        $meta_path = $this->getMetaPath();
        file_put_contents($meta_path, $meta_content);
        $target_path = $this->getFilePath();
        file_put_contents($target_path, $content);
        $this->is_new = false;
        $this->has_new_files = false;
    }
    
}
