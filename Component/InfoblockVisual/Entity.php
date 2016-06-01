<?php

namespace Floxim\Floxim\Component\InfoblockVisual;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    protected $needRecountFiles = true;

    protected function beforeSave()
    {
        parent::beforeSave();
        unset($this['is_stub']);
        if ($this->needRecountFiles) {
            $this->recountFiles();
        }
    }
    
    public function setNeedRecountFiles($need)
    {
        $this->needRecountFiles = $need;
    }
    
    public function recountFiles()
    {
        $modified_params = $this->getModifiedParams();
        $fxPath = fx::path();
        foreach ($modified_params as $field => $params) {
            $all_params = $this[$field];
            $is_moved = false;
            foreach ($params as $pk => $pv) {
                if (self::checkValueIsFile($pv['new'])) {
                    $ib = $this['infoblock'];
                    $site_id = $ib ? $ib['site_id'] : fx::env('site_id');

                    $file_name = $fxPath->fileName($pv['new']);
                    $new_path = $fxPath->abs('@content_files/' . $site_id . '/visual/' . $file_name);
                    
                    $move_from = $fxPath->abs($pv['new']);
                    
                    if (file_exists($move_from)) {
                        fx::files()->move($move_from, $new_path);
                        $all_params[$pk] = $fxPath->removeBase($fxPath->http($new_path));
                        $is_moved = true;
                    }
                }
                if ($pv['old'] && (!$is_moved  || $pv['old'] !== $all_params[$pk])) {
                    //$old_path = $fxPath->abs(FX_BASE_URL.$pv['old']);
                    $old_path = $pv['old'];
                    if (self::checkValueIsFile($old_path)) {
                        fx::files()->rm($old_path);
                    }
                }
            }
            $this[$field] = $all_params;
        }
    }
    
    /**
     * Copy (json) params and duplicate files
     * @param type $source
     */
    public function copyParams($source)
    {
        if (!is_array($source)) {
            return $source;
        }
        $res = array();
        foreach ($source as $k => $v) {
            if (self::checkValueIsFile($v)) {
                try {
                    $v = fx::files()->duplicate($v);
                } catch (\Exception $e) {
                    fx::log('failed copying file', $e, $source, $k);
                    continue;
                }
            }
            $res[$k] = $v;
        }
        return $res;
    }

    protected function beforeDelete()
    {
        if (!fx::env('console')) {
            fx::log(debug_backtrace());
        }
        parent::beforeDelete();
        $files = $this->getFileParams();
        foreach ($files as $f) {
            fx::files()->rm($f);
        }
    }

    /**
     * find file paths inside params collection and drop them
     */
    public function getFileParams(System\Collection $params = null)
    {
        if (!$params) {
            $params = fx::collection($this['template_visual'])->concat($this['wrapper_visual']);
        }
        $res = array();
        foreach ($params as $p) {
            if (self::checkValueIsFile($p)) {
                $res [] = $p;
            }
        }
        return $res;
    }

    public static function checkValueIsFile($v)
    {
        if (empty($v) || !is_string($v) || substr($v, 0, 1) !== '/') {
            return false;
        }
        $files_path = fx::path('@files');
        $path = fx::path();
        return $path->isInside($v, $files_path) && $path->isFile($v);
    }

    public function getModifiedParams()
    {
        $res = array();
        foreach (array('template_visual', 'wrapper_visual') as $field) {
            $res[$field] = array();
            if (!$this->isModified($field)) {
                continue;
            }
            $new = $this[$field];
            if (!$new) {
                $new = array();
            }
            $old = $this->getOld($field);
            if (!$old || !is_array($old)) {
                $old = array();
            }
            $keys = array_unique(
                array_merge(
                    array_keys($old),
                    array_keys($new)
                )
            );
            foreach ($keys as $key) {
                if (isset($old[$key]) && isset($new[$key]) && $old[$key] == $new[$key]) {
                    continue;
                }
                $res[$field][$key] = array(
                    'old' => isset($old[$key]) ? $old[$key] : null,
                    'new' => isset($new[$key]) ? $new[$key] : null
                );
            }
        }
        return $res;
    }
}