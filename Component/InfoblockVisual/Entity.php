<?php

namespace Floxim\Floxim\Component\InfoblockVisual;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    protected function beforeSave()
    {
        parent::beforeSave();
        unset($this['is_stub']);
        if (!$this['priority'] && $this['layout_id']) {
            $last_vis = fx::data('infoblock_visual')->where('layout_id', $this['layout_id'])->where('area',
                $this['area'])->order(null)->order('priority', 'desc')->one();
            $this['priority'] = $last_vis['priority'] + 1;
        }
        $files = $this->getModifiedFileParams();
        foreach ($files as $f) {
            fx::files()->rm($f);
        }
    }

    protected function beforeDelete()
    {
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
        $files_path = fx::path('files');
        $res = array();
        $path = fx::path();
        foreach ($params as $p) {
            if (empty($p)) {
                continue;
            }
            if ($path->isInside($p, $files_path) && $path->isFile($p)) {
                $res [] = $p;
            }
        }
        return $res;
    }

    public function getModifiedFileParams()
    {
        $params = fx::collection();
        foreach (array('template_visual', 'wrapper_visual') as $field) {
            if (!$this->isModified($field)) {
                continue;
            }
            $new = $this[$field];
            $old = $this->getOld($field);
            if (!$old || !is_array($old)) {
                continue;
            }
            foreach ($old as $opk => $opv) {
                if (!isset($new[$opk]) || $new[$opk] != $opv) {
                    $params [] = $opv;
                }
            }
        }
        return $this->getFileParams($params);
    }
}